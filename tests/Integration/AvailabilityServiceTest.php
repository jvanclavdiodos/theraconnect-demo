<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    private AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AvailabilityService::class);
    }

    private function clinician(): Clinician
    {
        return $this->createClinician()['clinician'];
    }

    private function freshDate(): Carbon
    {
        // A fixed far-future weekday so tests are deterministic.
        return Carbon::parse('2030-01-07'); // Monday
    }

    public function test_no_config_is_available_by_default(): void
    {
        $clinician = $this->clinician()->fresh(['weeklyAvailabilities', 'dateOverrides']);

        $slots = $this->service->availableSlots($clinician, $this->freshDate());

        $this->assertSame(
            ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'],
            $slots
        );
    }

    public function test_weekly_off_day_yields_no_slots(): void
    {
        $clinician = $this->clinician();
        $date = $this->freshDate();
        $clinician->weeklyAvailabilities()->create([
            'day_of_week' => strtolower($date->format('l')),
            'is_available' => false,
        ]);

        $this->assertSame([], $this->service->availableSlots(
            $clinician->fresh(['weeklyAvailabilities', 'dateOverrides']),
            $date
        ));
    }

    public function test_weekly_window_restricts_hours_inclusive(): void
    {
        $clinician = $this->clinician();
        $date = $this->freshDate();
        $clinician->weeklyAvailabilities()->create([
            'day_of_week' => strtolower($date->format('l')),
            'is_available' => true,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->assertSame(
            ['09:00', '10:00', '11:00', '12:00'],
            $this->service->availableSlots(
                $clinician->fresh(['weeklyAvailabilities', 'dateOverrides']),
                $date
            )
        );
    }

    public function test_date_override_blocks_whole_day(): void
    {
        $clinician = $this->clinician();
        $date = $this->freshDate();
        // Weekly says available, but a date block overrides it.
        $clinician->dateOverrides()->create([
            'date' => $date->toDateString(),
            'is_available' => false,
            'reason' => 'Vacation',
        ]);

        $this->assertSame([], $this->service->availableSlots(
            $clinician->fresh(['weeklyAvailabilities', 'dateOverrides']),
            $date
        ));
    }

    public function test_hourly_block_removes_only_that_hour(): void
    {
        $clinician = $this->clinician();
        $date = $this->freshDate();
        $clinician->dateOverrides()->create([
            'date' => $date->toDateString(),
            'is_available' => false,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $slots = $this->service->availableSlots(
            $clinician->fresh(['weeklyAvailabilities', 'dateOverrides']),
            $date
        );

        $this->assertNotContains('10:00', $slots);
        $this->assertContains('09:00', $slots);
        $this->assertContains('11:00', $slots);
    }

    public function test_is_available_enforces_slot_alignment(): void
    {
        $id = $this->clinician()->id;
        $date = $this->freshDate();

        $this->assertTrue($this->service->isAvailable($id, $date->copy()->setTime(9, 0)));
        $this->assertFalse($this->service->isAvailable($id, $date->copy()->setTime(9, 30)));
        $this->assertFalse($this->service->isAvailable($id, $date->copy()->setTime(20, 0)));
    }

    public function test_open_dates_excludes_blocked_dates(): void
    {
        $clinician = $this->clinician();
        $from = $this->freshDate();
        $to = $from->copy()->addDays(2);
        $blocked = $from->copy()->addDay();

        $clinician->dateOverrides()->create([
            'date' => $blocked->toDateString(),
            'is_available' => false,
        ]);

        $open = $this->service->openDates($clinician->id, $from, $to);

        $this->assertContains($from->toDateString(), $open);
        $this->assertNotContains($blocked->toDateString(), $open);
        $this->assertContains($to->toDateString(), $open);
    }
}
