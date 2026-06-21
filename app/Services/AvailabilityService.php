<?php

namespace App\Services;

use App\Models\Clinician;
use Carbon\Carbon;

/**
 * Single source of truth for when a clinician is bookable.
 *
 * Resolution precedence for a given date:
 *   1. A date override for that date (whole-day block, or custom hours).
 *   2. The clinician's recurring weekly row for that weekday.
 *   3. Default: available 08:00-16:00 ("available by default").
 *
 * Slots are whole-hour starts; the window end is the LAST bookable start
 * (inclusive), preserving the original 08:00..16:00 = 9-slot behaviour.
 */
class AvailabilityService
{
    public const DEFAULT_START = '08:00';

    public const DEFAULT_END = '16:00';

    /**
     * The "HH:00" slot starts a clinician is open on $date, ignoring existing
     * appointments (conflict filtering happens in AppointmentService).
     *
     * @return array<int, string>
     */
    public function availableSlots(Clinician $clinician, Carbon $date): array
    {
        $override = $clinician->dateOverrides
            ->first(fn ($o) => $o->date->isSameDay($date));

        if ($override) {
            if (! $override->is_available) {
                return []; // whole day blocked (e.g. vacation)
            }

            return $this->hourlySlots(
                $override->start_time ?? self::DEFAULT_START,
                $override->end_time ?? self::DEFAULT_END
            );
        }

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
