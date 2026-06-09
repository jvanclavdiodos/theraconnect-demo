<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinician;
use Carbon\Carbon;

class AppointmentService
{
    public function getScheduleSlots(string $date): array
    {
        $slots = [];
        $clinicians = Clinician::with('user')->get();

        foreach ($this->generateTimeSlots() as $slotTime) {
            foreach ($clinicians as $clinician) {
                $slotDateTime = Carbon::parse($date)->setTimeFromTimeString($slotTime);
                $formatted = $slotDateTime->format('Y-m-d H:i:s');

                $conflict = Appointment::where('clinician_id', $clinician->id)
                    ->where(function ($q) use ($formatted) {
                        $q->where('scheduled_at', $formatted)
                          ->orWhere(function ($q2) use ($formatted) {
                              $q2->whereNull('scheduled_at')
                                 ->where('requested_at', $formatted);
                          });
                    })
                    ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
                    ->exists();

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
     */
    public function isSlotAvailable(int $clinicianId, string $requestedAt, ?int $ignoreAppointmentId = null): bool
    {
        $at = Carbon::parse($requestedAt)->format('Y-m-d H:i:s');

        $conflict = Appointment::where('clinician_id', $clinicianId)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->where(function ($q) use ($at) {
                $q->where('scheduled_at', $at)
                  ->orWhere(function ($q2) use ($at) {
                      $q2->whereNull('scheduled_at')
                         ->where('requested_at', $at);
                  });
            })
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->exists();

        return ! $conflict;
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

    public function approve(Appointment $appointment, ?string $scheduledAt = null): Appointment
    {
        $appointment->update([
            'status' => 'approved',
            'scheduled_at' => $scheduledAt ?? $appointment->requested_at,
        ]);

        return $appointment->fresh();
    }

    public function reject(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => 'rejected']);

        return $appointment->fresh();
    }

    public function reschedule(Appointment $appointment, string $scheduledAt): Appointment
    {
        $appointment->update([
            'status' => 'rescheduled',
            'scheduled_at' => Carbon::parse($scheduledAt),
        ]);

        return $appointment->fresh();
    }

    private function generateTimeSlots(): array
    {
        return ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
    }
}
