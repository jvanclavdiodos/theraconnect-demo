<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class MoodLogDates
{
    public const TIMEZONE = 'Asia/Manila';

    public const MANILA_CUTOVER = '2026-06-27 02:07:00';

    public static function today(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->toDateString();
    }

    public static function fromLegacyTimestamp(DateTimeInterface|string $createdAt): string
    {
        $stored = CarbonImmutable::parse($createdAt, 'UTC');
        $cutover = CarbonImmutable::parse(self::MANILA_CUTOVER, self::TIMEZONE);

        return $stored->lessThan($cutover)
            ? $stored->addHours(8)->toDateString()
            : $stored->toDateString();
    }
}
