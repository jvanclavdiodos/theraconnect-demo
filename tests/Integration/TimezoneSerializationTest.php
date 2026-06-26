<?php

namespace Tests\Integration;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The clinic operates in one timezone (Asia/Manila) and stores/displays times
 * as naive wall-clock. After the UTC -> Asia/Manila switch, Carbon must still
 * serialize to JSON as the wall-clock with a trailing 'Z' (NOT converted to
 * true UTC), so the mobile app — which strips the 'Z' and shows the wall-clock
 * (see date_format.dart) — keeps displaying the exact time the clinic entered,
 * on any device and without a rebuild.
 */
class TimezoneSerializationTest extends TestCase
{
    public function test_carbon_serializes_as_wall_clock_not_converted_to_utc(): void
    {
        $manila = Carbon::parse('2026-06-24 09:00:00', 'Asia/Manila');

        // Default Carbon would convert to UTC ("...T01:00:00...Z"). Our global
        // serializer keeps the 09:00 wall-clock instead.
        $this->assertSame('2026-06-24T09:00:00.000000Z', $manila->jsonSerialize());

        // And inside an actual JSON payload (the API-resource path).
        $this->assertSame('{"at":"2026-06-24T09:00:00.000000Z"}', json_encode(['at' => $manila]));
    }

    public function test_immutable_carbon_serializes_as_wall_clock(): void
    {
        $manila = CarbonImmutable::parse('2026-06-24 17:30:00', 'Asia/Manila');

        $this->assertSame('2026-06-24T17:30:00.000000Z', $manila->jsonSerialize());
    }
}
