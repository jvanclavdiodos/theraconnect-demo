<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinician;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON backend for the clinician dashboard availability calendar. Every action
 * operates on the logged-in clinician's own records (ownership boundary).
 */
class ClinicianAvailabilityController extends Controller
{
    /** Statuses that occupy a slot (everything except terminal-negative ones). */
    private const ACTIVE_STATUSES = ['pending', 'approved', 'rescheduled', 'completed'];

    public function __construct(private AvailabilityService $availability) {}

    /** Per-day summary for a month: appointment count + whole-day block flag. */
    public function month(Request $request): JsonResponse
    {
        $clinician = $this->currentClinician($request);

        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $start = Carbon::parse($validated['month'].'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $counts = $this->appointmentsBetween($clinician, $start, $end)
            ->groupBy(fn ($a) => $this->apptDate($a)->toDateString())
            ->map->count();

        $blockedDays = $clinician->dateOverrides()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('is_available', false)
            ->whereNull('start_time')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->toDateString();
            $days[$ds] = [
                'count' => $counts[$ds] ?? 0,
                'blocked' => $blockedDays->contains($ds),
            ];
        }

        return response()->json(['days' => $days]);
    }

    /** Detail for one day: appointments + per-hour status (booked/blocked/available). */
    public function day(Request $request): JsonResponse
    {
        $clinician = $this->currentClinician($request);
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json($this->buildDay($clinician, Carbon::parse($validated['date'])));
    }

    /** Toggle a whole-day block on/off. */
    public function toggleDay(Request $request): JsonResponse
    {
        $clinician = $this->currentClinician($request);
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::parse($validated['date']);

        $existing = $clinician->dateOverrides()
            ->whereDate('date', $date->toDateString())
            ->where('is_available', false)
            ->whereNull('start_time')
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            $clinician->dateOverrides()->create([
                'date' => $date->toDateString(),
                'is_available' => false,
                'start_time' => null,
                'end_time' => null,
            ]);
        }

        return response()->json($this->buildDay($clinician, $date));
    }

    /** Toggle a single hour block on/off (refused if that hour is booked). */
    public function toggleHour(Request $request): JsonResponse
    {
        $clinician = $this->currentClinician($request);
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['required', 'date_format:H:i'],
        ]);
        $date = Carbon::parse($validated['date']);
        $hour = $validated['hour'];

        // Don't let a booked hour be blocked out from under a patient.
        $booked = $this->appointmentsBetween($clinician, $date->copy()->startOfDay(), $date->copy()->endOfDay())
            ->contains(fn ($a) => $this->apptDate($a)->format('H:i') === $hour);

        if ($booked) {
            return response()->json(['message' => 'That hour has an appointment and cannot be blocked.'], 422);
        }

        $existing = $clinician->dateOverrides()
            ->whereDate('date', $date->toDateString())
            ->where('is_available', false)
            ->where('start_time', $hour)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            $clinician->dateOverrides()->create([
                'date' => $date->toDateString(),
                'is_available' => false,
                'start_time' => $hour,
                'end_time' => sprintf('%02d:00', ((int) substr($hour, 0, 2) + 1) % 24),
            ]);
        }

        return response()->json($this->buildDay($clinician, $date));
    }

    /** Assemble the day-detail payload shared by day()/toggle*(). */
    private function buildDay(Clinician $clinician, Carbon $date): array
    {
        $clinician->load(['weeklyAvailabilities', 'dateOverrides']);

        $appts = $this->appointmentsBetween($clinician, $date->copy()->startOfDay(), $date->copy()->endOfDay())
            ->sortBy(fn ($a) => $this->apptDate($a)->format('H:i'))
            ->values();

        $byHour = [];
        foreach ($appts as $a) {
            $byHour[$this->apptDate($a)->format('H:i')] = $a;
        }

        $available = $this->availability->availableSlots($clinician, $date);

        $dayBlocked = $clinician->dateOverrides
            ->contains(fn ($o) => $o->date->isSameDay($date) && ! $o->is_available && $o->start_time === null);

        $hours = [];
        foreach ($this->availability->baseHours($clinician, $date) as $h) {
            if (isset($byHour[$h])) {
                $status = 'booked';
            } elseif (in_array($h, $available, true)) {
                $status = 'available';
            } else {
                $status = 'blocked';
            }
            $hours[] = [
                'time' => $h,
                'status' => $status,
                'patient' => isset($byHour[$h]) ? $byHour[$h]->patient->user->name : null,
            ];
        }

        return [
            'date' => $date->toDateString(),
            'day_blocked' => $dayBlocked,
            'hours' => $hours,
            'appointments' => $appts->map(fn ($a) => [
                'time' => $this->apptDate($a)->format('H:i'),
                'patient' => $a->patient->user->name,
                'status' => $a->status,
                'mode' => $a->mode,
            ])->all(),
        ];
    }

    /** Active appointments for the clinician whose effective date is in [from, to]. */
    private function appointmentsBetween(Clinician $clinician, Carbon $from, Carbon $to)
    {
        return Appointment::where('clinician_id', $clinician->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with('patient.user')
            ->get()
            ->filter(fn ($a) => $this->apptDate($a)->between($from, $to));
    }

    /** Effective datetime of an appointment (scheduled if set, else requested). */
    private function apptDate(Appointment $a): Carbon
    {
        return $a->scheduled_at ?? $a->requested_at;
    }

    private function currentClinician(Request $request): Clinician
    {
        $clinician = $request->user()->clinician;

        abort_unless($clinician !== null, 403, 'No clinician profile.');

        return $clinician;
    }
}
