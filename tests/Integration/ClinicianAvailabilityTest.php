<?php

namespace Tests\Integration;

use App\Models\Appointment;
use Tests\TestCase;

class ClinicianAvailabilityTest extends TestCase
{
    public function test_admin_cannot_access_calendar_endpoints(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->getJson('/availability/month?month=2030-12')
            ->assertStatus(403);
    }

    public function test_dashboard_shows_calendar_for_clinician_only(): void
    {
        $clinician = $this->createClinician();
        $this->actingAs($clinician['user'], 'web')
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('My Schedule');

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee('My Schedule');
    }

    public function test_month_returns_counts_and_blocks(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-10 09:00:00',
            'scheduled_at' => '2030-12-10 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);
        $clinician['clinician']->dateOverrides()->create([
            'date' => '2030-12-15',
            'is_available' => false,
        ]);

        $response = $this->actingAs($clinician['user'], 'web')
            ->getJson('/availability/month?month=2030-12')
            ->assertStatus(200);

        $this->assertSame(1, $response->json('days.2030-12-10.count'));
        $this->assertTrue($response->json('days.2030-12-15.blocked'));
        $this->assertFalse($response->json('days.2030-12-10.blocked'));
    }

    public function test_day_lists_appointments_and_hour_statuses(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($clinician['user'], 'web')
            ->getJson('/availability/day?date=2030-12-31')
            ->assertStatus(200);

        $this->assertFalse($response->json('day_blocked'));
        $this->assertCount(1, $response->json('appointments'));

        $hours = collect($response->json('hours'))->keyBy('time');
        $this->assertSame('booked', $hours['09:00']['status']);
        $this->assertSame('available', $hours['10:00']['status']);
    }

    public function test_toggle_day_blocks_then_unblocks(): void
    {
        $clinician = $this->createClinician();

        $wholeDayBlocks = fn () => $clinician['clinician']->dateOverrides()
            ->whereNull('start_time')->where('is_available', false)->count();

        // Block.
        $this->actingAs($clinician['user'], 'web')
            ->postJson('/availability/toggle-day', ['date' => '2030-12-31'])
            ->assertStatus(200)
            ->assertJsonPath('day_blocked', true);
        $this->assertSame(1, $wholeDayBlocks());

        // Unblock.
        $this->actingAs($clinician['user'], 'web')
            ->postJson('/availability/toggle-day', ['date' => '2030-12-31'])
            ->assertStatus(200)
            ->assertJsonPath('day_blocked', false);
        $this->assertSame(0, $wholeDayBlocks());
    }

    public function test_toggle_hour_blocks_then_unblocks(): void
    {
        $clinician = $this->createClinician();

        $response = $this->actingAs($clinician['user'], 'web')
            ->postJson('/availability/toggle-hour', ['date' => '2030-12-31', 'hour' => '10:00'])
            ->assertStatus(200);

        $hours = collect($response->json('hours'))->keyBy('time');
        $this->assertSame('blocked', $hours['10:00']['status']);
        $this->assertSame('available', $hours['11:00']['status']);

        // Unblock returns it to available.
        $response = $this->actingAs($clinician['user'], 'web')
            ->postJson('/availability/toggle-hour', ['date' => '2030-12-31', 'hour' => '10:00'])
            ->assertStatus(200);

        $hours = collect($response->json('hours'))->keyBy('time');
        $this->assertSame('available', $hours['10:00']['status']);
    }

    public function test_cannot_block_a_booked_hour(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->postJson('/availability/toggle-hour', ['date' => '2030-12-31', 'hour' => '09:00'])
            ->assertStatus(422);
    }

    public function test_booking_a_blocked_hour_is_rejected(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        // Block 09:00 on that date.
        $clinician['clinician']->dateOverrides()->create([
            'date' => '2030-12-31',
            'is_available' => false,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(422);

        // A different open hour still books fine.
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 10:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(201);
    }
}
