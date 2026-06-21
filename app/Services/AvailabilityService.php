<?php

namespace App\Services;

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
        $clinician = Clinician::with(['weeklyAvailabilities', 'dateOverrides'])
            ->find($clinicianId);

        if (! $clinician) {
            return [];
        }

        $dates = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            if (! empty($this->availableSlots($clinician, $d))) {
                $dates[] = $d->format('Y-m-d');
            }
        }

        return $dates;
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
