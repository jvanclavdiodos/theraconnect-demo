<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinician;
use Carbon\Carbon;

/**
 * Single source of truth for when a clinician is bookable.
 *
 * A weekday has a working window (weekly row, or the 08:00-16:00 default —
 * "available by default"). On top of that, date overrides for a specific date
 * subtract availability:
 *   - whole-day block  : is_available=false, start_time=null  → no slots that day
 *   - hourly block     : is_available=false, start_time=HH:00 → that hour removed
 *
 * Slots are whole-hour starts; the window end is the LAST bookable start
 * (inclusive), preserving the original 08:00..16:00 = 9-slot behaviour.
 */
class AvailabilityService
{
    public const DEFAULT_START = '08:00';

    public const DEFAULT_END = '16:00';

    /**
     * Bookable "HH:00" slot starts for $date (base hours minus any blocks),
     * ignoring existing appointments (conflict filtering is done elsewhere).
     *
     * @return array<int, string>
     */
    public function availableSlots(Clinician $clinician, Carbon $date): array
    {
        $overrides = $this->overridesFor($clinician, $date);

        if ($this->hasWholeDayBlock($overrides)) {
            return [];
        }

        $blockedHours = $this->blockedHours($overrides);

        return array_values(array_filter(
            $this->baseHours($clinician, $date),
            fn ($hour) => ! in_array($hour, $blockedHours, true)
        ));
    }

    /**
     * The weekday's working-hour slots, ignoring date overrides — the full grid
     * a clinician could offer on this date. Empty when the weekday is off.
     *
     * @return array<int, string>
     */
    public function baseHours(Clinician $clinician, Carbon $date): array
    {
        $weekly = $clinician->weeklyAvailabilities
            ->firstWhere('day_of_week', strtolower($date->format('l')));

        if ($weekly) {
            if (! $weekly->is_available) {
                return [];
            }

            return $this->hourlySlots(
                $weekly->start_time ?? self::DEFAULT_START,
                $weekly->end_time ?? self::DEFAULT_END
            );
        }

        // No configuration → available by default.
        return $this->hourlySlots(self::DEFAULT_START, self::DEFAULT_END);
    }

    /**
     * Does $dateTime fall on an open whole-hour slot for this clinician?
     * Also enforces slot alignment (e.g. 09:17 is never available).
     */
    public function isAvailable(int $clinicianId, Carbon $dateTime): bool
    {
        $clinician = Clinician::with(['weeklyAvailabilities', 'dateOverrides'])
            ->find($clinicianId);

        if (! $clinician) {
            return false;
        }

        return in_array(
            $dateTime->format('H:i'),
            $this->availableSlots($clinician, $dateTime),
            true
        );
    }

    /**
     * Dates in [$from, $to] that have at least one open slot (availability only,
     * not appointment conflicts) — used to enable days in the booking calendar.
     *
     * @return array<int, string> Y-m-d strings
     */
    public function openDates(int $clinicianId, Carbon $from, Carbon $to): array
    {
        return collect($this->dateStatuses($clinicianId, $from, $to))
            ->filter(fn (string $status) => $status === 'open')
            ->keys()
            ->all();
    }

    /**
     * Availability status for every date in a bounded range. Relationships and
     * appointments are loaded once so calendar rendering does not query per day.
     *
     * @return array<string, string> Y-m-d => open|blocked|full|off
     */
    public function dateStatuses(int $clinicianId, Carbon $from, Carbon $to): array
    {
        $clinician = Clinician::with(['weeklyAvailabilities', 'dateOverrides'])
            ->find($clinicianId);

        if (! $clinician) {
            return [];
        }

        $busy = Appointment::where('clinician_id', $clinicianId)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('scheduled_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->orWhere(function ($query) use ($from, $to) {
                        $query->whereNull('scheduled_at')
                            ->whereBetween('requested_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
                    });
            })
            ->get(['scheduled_at', 'requested_at'])
            ->map(fn (Appointment $appointment) => ($appointment->scheduled_at ?? $appointment->requested_at)->format('Y-m-d H:i'))
            ->flip();

        $statuses = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $date = $d->format('Y-m-d');
            $overrides = $this->overridesFor($clinician, $d);

            if ($this->hasWholeDayBlock($overrides)) {
                $statuses[$date] = 'blocked';

                continue;
            }

            $baseHours = $this->baseHours($clinician, $d);
            if (empty($baseHours)) {
                $statuses[$date] = 'off';

                continue;
            }

            $availableHours = $this->availableSlots($clinician, $d);
            $hasOpenSlot = collect($availableHours)
                ->contains(fn (string $hour) => ! $busy->has("{$date} {$hour}"));

            $statuses[$date] = $hasOpenSlot ? 'open' : 'full';
        }

        return $statuses;
    }

    /** Overrides (from the loaded relation) that fall on $date. */
    private function overridesFor(Clinician $clinician, Carbon $date)
    {
        return $clinician->dateOverrides->filter(fn ($o) => $o->date->isSameDay($date));
    }

    private function hasWholeDayBlock($overrides): bool
    {
        return $overrides->contains(
            fn ($o) => ! $o->is_available && $o->start_time === null
        );
    }

    /** @return array<int, string> blocked "HH:00" starts */
    private function blockedHours($overrides): array
    {
        return $overrides
            ->filter(fn ($o) => ! $o->is_available && $o->start_time !== null)
            ->map(fn ($o) => substr($o->start_time, 0, 5))
            ->values()
            ->all();
    }

    /**
     * Whole-hour slot starts from $start to $end inclusive (e.g. 08:00..16:00).
     *
     * @return array<int, string>
     */
    private function hourlySlots(string $start, string $end): array
    {
        $startHour = (int) Carbon::parse($start)->format('G');
        $endHour = (int) Carbon::parse($end)->format('G');

        $slots = [];
        for ($h = $startHour; $h <= $endHour; $h++) {
            $slots[] = sprintf('%02d:00', $h);
        }

        return $slots;
    }
}
