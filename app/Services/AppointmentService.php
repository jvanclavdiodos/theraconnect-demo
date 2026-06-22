<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Clinician;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private JitsiService $jitsi,
        private AvailabilityService $availability,
    ) {}

    public function getScheduleSlots(string $date): array
    {
        $slots = [];
        $clinicians = Clinician::with(['user', 'weeklyAvailabilities', 'dateOverrides'])->get();
        $clinicianIds = $clinicians->pluck('id')->all();

        $dayStart = Carbon::parse($date)->startOfDay()->format('Y-m-d H:i:s');
        $dayEnd = Carbon::parse($date)->endOfDay()->format('Y-m-d H:i:s');

        $activeAppointments = Appointment::whereIn('clinician_id', $clinicianIds)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('scheduled_at', [$dayStart, $dayEnd])
                  ->orWhere(function ($q2) use ($dayStart, $dayEnd) {
                      $q2->whereNull('scheduled_at')
                         ->whereBetween('requested_at', [$dayStart, $dayEnd]);
                  });
            })
            ->get(['clinician_id', 'scheduled_at', 'requested_at']);

        $busy = [];
        foreach ($activeAppointments as $appt) {
            $at = $appt->scheduled_at?->format('Y-m-d H:i:s')
                ?? $appt->requested_at->format('Y-m-d H:i:s');
            $busy[$appt->clinician_id][$at] = true;
        }

        $day = Carbon::parse($date);

        foreach ($clinicians as $clinician) {
            // Only the clinician's open hours for this date are offered; closed
            // days/hours simply produce no slots for that clinician.
            foreach ($this->availability->availableSlots($clinician, $day) as $slotTime) {
                $formatted = Carbon::parse($date)
                    ->setTimeFromTimeString($slotTime)
                    ->format('Y-m-d H:i:s');

                $conflict = $busy[$clinician->id][$formatted] ?? false;

                $slots[] = [
                    'slot' => $slotTime,
                    'clinician_id' => $clinician->id,
                    'clinician_name' => $clinician->user->name,
                    'available' => ! $conflict,
                ];
            }
        }

        return $slots;
    }

    /**
     * Is the given clinician free at the requested datetime?
     *
     * Mirrors the conflict logic in getScheduleSlots(): an active appointment
     * (not cancelled/rejected/completed) for this clinician whose scheduled_at
     * matches, or — when not yet scheduled — whose requested_at matches.
     *
     * When $lock is true (and the caller is inside a transaction), the matching
     * rows are locked FOR UPDATE so concurrent bookings serialize at the DB
     * level, preventing a TOCTOU double-booking race.
     */
    public function isSlotAvailable(int $clinicianId, string $requestedAt, ?int $ignoreAppointmentId = null, bool $lock = false): bool
    {
        $at = Carbon::parse($requestedAt)->format('Y-m-d H:i:s');

        $query = Appointment::where('clinician_id', $clinicianId)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->where(function ($q) use ($at) {
                $q->where('scheduled_at', $at)
                  ->orWhere(function ($q2) use ($at) {
                      $q2->whereNull('scheduled_at')
                         ->where('requested_at', $at);
                  });
            })
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return ! $query->exists();
    }

    /**
     * Atomically book a new appointment: slot-availability check + insert run
     * inside a single DB transaction with the conflict row locked FOR UPDATE,
     * so two concurrent bookings for the same clinician+slot cannot both pass.
     *
     * @throws SlotUnavailableException
     */
    public function bookAppointment(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $clinicianId = $data['clinician_id'] ?? null;

            if ($clinicianId) {
                if (! $this->availability->isAvailable($clinicianId, Carbon::parse($data['requested_at']))) {
                    throw new SlotUnavailableException('The clinician is not available at that time.');
                }

                if (! $this->isSlotAvailable($clinicianId, $data['requested_at'], lock: true)) {
                    throw new SlotUnavailableException();
                }
            }

            return $this->create($data);
        });
    }

    public function create(array $data): Appointment
    {
        return Appointment::create([
            'patient_id' => $data['patient_id'],
            'clinician_id' => $data['clinician_id'] ?? null,
            'requested_at' => $data['requested_at'],
            'mode' => $data['mode'] ?? 'in_person',
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function cancel(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => 'cancelled']);

        return $appointment->fresh();
    }

    /** Close the case: mark a held appointment as completed. */
    public function complete(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => 'completed']);

        return $appointment->fresh();
    }

    public function approve(Appointment $appointment, ?string $scheduledAt = null): Appointment
    {
        $appointment->update([
            'status' => 'approved',
            'scheduled_at' => $scheduledAt ?? $appointment->requested_at,
            'meeting_link' => $this->resolveMeetingLink($appointment),
        ]);

        return $appointment->fresh();
    }

    public function reject(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => 'rejected']);

        return $appointment->fresh();
    }

    /**
     * Atomically reschedule: slot-availability check + update run inside a
     * single DB transaction with the conflict row locked FOR UPDATE, so two
     * concurrent reschedules (or a reschedule-vs-new-booking) cannot collide.
     *
     * @throws SlotUnavailableException
     */
    public function reschedule(Appointment $appointment, string $scheduledAt): Appointment
    {
        return DB::transaction(function () use ($appointment, $scheduledAt) {
            if ($appointment->clinician_id) {
                if (! $this->availability->isAvailable($appointment->clinician_id, Carbon::parse($scheduledAt))) {
                    throw new SlotUnavailableException('The clinician is not available at that time.');
                }

                if (! $this->isSlotAvailable($appointment->clinician_id, $scheduledAt, $appointment->id, lock: true)) {
                    throw new SlotUnavailableException('That time slot is already booked for this clinician.');
                }
            }

            $appointment->update([
                'status' => 'rescheduled',
                'scheduled_at' => Carbon::parse($scheduledAt),
                'meeting_link' => $this->resolveMeetingLink($appointment),
            ]);

            return $appointment->fresh();
        });
    }

    /**
     * Online appointments get a Jitsi room, generated once and kept stable
     * across reschedules. In-person appointments never get a link.
     */
    private function resolveMeetingLink(Appointment $appointment): ?string
    {
        if ($appointment->mode !== 'online') {
            return $appointment->meeting_link;
        }

        return $appointment->meeting_link
            ?: $this->jitsi->generateMeetingLink($appointment->id);
    }
}
