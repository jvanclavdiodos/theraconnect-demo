<?php

namespace Tests\Integration;

use App\Models\Appointment;
use Tests\TestCase;

class AppointmentIndexTest extends TestCase
{
    public function test_staff_appointments_index_paginates_ten_per_page(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();

        for ($i = 1; $i <= 11; $i++) {
            $this->appointmentFor(
                $clinician['clinician']->id,
                sprintf('Patient %02d', $i),
                sprintf('patient-%02d@test.com', $i),
                sprintf('2030-12-%02d 09:00:00', $i)
            );
        }

        $response = $this->actingAs($admin)
            ->get('/appointments');

        $response->assertOk()
            ->assertSee('Patient 11')
            ->assertSee('Patient 02')
            ->assertDontSee('Patient 01');
    }

    public function test_staff_appointments_index_filters_by_meeting_mode(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();

        $this->appointmentFor($clinician['clinician']->id, 'Online Patient', 'online@test.com', '2030-12-01 09:00:00', 'online');
        $this->appointmentFor($clinician['clinician']->id, 'Offline Patient', 'offline@test.com', '2030-12-02 09:00:00', 'in_person');

        $this->actingAs($admin)
            ->get('/appointments?mode=online')
            ->assertOk()
            ->assertSee('Online Patient')
            ->assertDontSee('Offline Patient');

        $this->actingAs($admin)
            ->get('/appointments?mode=in_person')
            ->assertOk()
            ->assertSee('Offline Patient')
            ->assertDontSee('Online Patient');
    }

    public function test_staff_appointments_index_sorts_by_requested_date(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();

        $this->appointmentFor($clinician['clinician']->id, 'Older Patient', 'older@test.com', '2030-12-01 09:00:00');
        $this->appointmentFor($clinician['clinician']->id, 'Newer Patient', 'newer@test.com', '2030-12-03 09:00:00');

        $this->actingAs($admin)
            ->get('/appointments?sort=requested_at&direction=asc')
            ->assertOk()
            ->assertSeeInOrder(['Older Patient', 'Newer Patient']);

        $this->actingAs($admin)
            ->get('/appointments?sort=requested_at&direction=desc')
            ->assertOk()
            ->assertSeeInOrder(['Newer Patient', 'Older Patient']);
    }

    private function appointmentFor(
        int $clinicianId,
        string $patientName,
        string $patientEmail,
        string $requestedAt,
        string $mode = 'in_person'
    ): Appointment {
        $patient = $this->createPatient($patientEmail);
        $patient['user']->update(['name' => $patientName]);

        return Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinicianId,
            'requested_at' => $requestedAt,
            'mode' => $mode,
            'status' => 'pending',
        ]);
    }
}
