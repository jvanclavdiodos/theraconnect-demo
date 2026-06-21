<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Models\ClinicianDateOverride;
use App\Models\User;
use Tests\TestCase;

class ClinicianAvailabilityTest extends TestCase
{
    /** Create a clinician with a unique email/license (the shared helper hardcodes one). */
    private function makeClinician(string $email): array
    {
        $user = User::create([
            'name' => 'Dr. '.ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-'.strtoupper(substr(md5($email), 0, 6)),
            'specialization' => 'Testing',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    /** Full weekly payload (all 7 days available 08:00-16:00). */
    private function weeklyPayload(array $overrides = []): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $weekly = [];
        foreach ($days as $day) {
            $weekly[$day] = array_merge(
                ['is_available' => '1', 'start_time' => '08:00', 'end_time' => '16:00'],
                $overrides[$day] ?? []
            );
        }

        return ['weekly' => $weekly];
    }

    public function test_clinician_can_view_availability_page(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->get('/my-availability')
            ->assertStatus(200)
            ->assertSee('My Availability');
    }

    public function test_admin_cannot_access_availability_page(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->get('/my-availability')
            ->assertStatus(403);
    }

    public function test_clinician_can_save_weekly_schedule(): void
    {
        $clinician = $this->createClinician();

        // Sunday off; Monday 09:00-12:00.
        $payload = $this->weeklyPayload([
            'sunday' => ['is_available' => false, 'start_time' => '08:00', 'end_time' => '16:00'],
            'monday' => ['is_available' => '1', 'start_time' => '09:00', 'end_time' => '12:00'],
        ]);
        // Unchecked checkbox => key absent.
        unset($payload['weekly']['sunday']['is_available']);

        $this->actingAs($clinician['user'], 'web')
            ->put('/my-availability', $payload)
            ->assertRedirect(route('availability.edit'));

        $rows = $clinician['clinician']->weeklyAvailabilities()->get()->keyBy('day_of_week');

        $this->assertFalse($rows['sunday']->is_available);

        $this->assertTrue($rows['monday']->is_available);
        $this->assertSame('09:00', substr($rows['monday']->start_time, 0, 5));
        $this->assertSame('12:00', substr($rows['monday']->end_time, 0, 5));
    }

    public function test_end_before_start_is_rejected_for_available_day(): void
    {
        $clinician = $this->createClinician();

        $payload = $this->weeklyPayload([
            'monday' => ['is_available' => '1', 'start_time' => '14:00', 'end_time' => '10:00'],
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->put('/my-availability', $payload)
            ->assertSessionHasErrors('weekly.monday.end_time');
    }

    public function test_clinician_can_block_and_unblock_a_date(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->post('/my-availability/overrides', [
                'date' => '2030-12-25',
                'reason' => 'Holiday',
            ])
            ->assertRedirect(route('availability.edit'));

        $override = ClinicianDateOverride::where('clinician_id', $clinician['clinician']->id)->first();

        $this->assertNotNull($override);
        $this->assertSame('2030-12-25', $override->date->toDateString());
        $this->assertFalse($override->is_available);
        $this->assertSame('Holiday', $override->reason);

        $this->actingAs($clinician['user'], 'web')
            ->delete("/my-availability/overrides/{$override->id}")
            ->assertRedirect(route('availability.edit'));

        $this->assertDatabaseMissing('clinician_date_overrides', ['id' => $override->id]);
    }

    public function test_clinician_cannot_delete_another_clinicians_override(): void
    {
        $owner = $this->makeClinician('owner@test.com');
        $other = $this->makeClinician('other@test.com');

        $override = $owner['clinician']->dateOverrides()->create([
            'date' => '2030-12-25',
            'is_available' => false,
        ]);

        $this->actingAs($other['user'], 'web')
            ->delete("/my-availability/overrides/{$override->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('clinician_date_overrides', ['id' => $override->id]);
    }

    public function test_booking_on_a_blocked_date_is_rejected(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $clinician['clinician']->dateOverrides()->create([
            'date' => '2030-12-31',
            'is_available' => false,
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('appointments', [
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
        ]);
    }

    public function test_booking_in_an_open_slot_succeeds(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(201);
    }
}
