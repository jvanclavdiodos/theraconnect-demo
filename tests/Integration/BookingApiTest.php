<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Models\User;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    private function makeClinician(string $email): Clinician
    {
        $user = User::create([
            'name' => 'Dr. '.ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);

        return Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-'.strtoupper(substr(md5($email), 0, 6)),
            'specialization' => 'CBT',
        ]);
    }

    private function patientHeaders(): array
    {
        $patient = $this->createPatient();

        return $this->apiHeaders($this->getApiToken($patient['user']));
    }

    public function test_clinicians_list_returns_safe_fields(): void
    {
        $clinician = $this->makeClinician('chen@test.com');

        $this->withHeaders($this->patientHeaders())
            ->getJson('/api/v1/clinicians')
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $clinician->id,
                'name' => 'Dr. Chen',
                'specialization' => 'CBT',
            ]);
    }

    public function test_schedules_can_filter_by_clinician(): void
    {
        $a = $this->makeClinician('a@test.com');
        $b = $this->makeClinician('b@test.com');

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson("/api/v1/schedules?date=2030-12-31&clinician_id={$a->id}")
            ->assertStatus(200);

        $clinicianIds = collect($response->json('data'))->pluck('clinician_id')->unique()->all();

        $this->assertSame([$a->id], $clinicianIds);
        $this->assertNotContains($b->id, $clinicianIds);
    }

    public function test_availability_lists_open_dates_excluding_blocks(): void
    {
        $clinician = $this->makeClinician('avail@test.com');
        $clinician->dateOverrides()->create([
            'date' => '2030-12-02',
            'is_available' => false,
        ]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson("/api/v1/schedules/availability?clinician_id={$clinician->id}&from=2030-12-01&to=2030-12-03")
            ->assertStatus(200);

        $dates = $response->json('data');

        $this->assertContains('2030-12-01', $dates);
        $this->assertNotContains('2030-12-02', $dates);
        $this->assertContains('2030-12-03', $dates);
        $response->assertJsonPath('availability.2030-12-01', 'open');
        $response->assertJsonPath('availability.2030-12-02', 'blocked');
    }

    public function test_availability_validates_params(): void
    {
        $this->withHeaders($this->patientHeaders())
            ->getJson('/api/v1/schedules/availability?clinician_id=999999&from=bad&to=2030-12-03')
            ->assertStatus(422);
    }
}
