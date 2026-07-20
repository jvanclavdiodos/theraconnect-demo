<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\User;
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

    public function test_only_the_assigned_clinician_sees_the_booking_reason(): void
    {
        $owner = $this->createClinician();
        $other = $this->createClinicianWithIdentity('Other Clinician', 'other-reason@test.com');
        $appointment = $this->appointmentFor(
            $owner['clinician']->id,
            'Reason Patient',
            'reason-patient@test.com',
            '2030-12-04 09:00:00'
        );
        $appointment->update(['reason' => 'Private reason <script>alert(1)</script>']);

        $this->actingAs($owner['user'])
            ->get('/appointments')
            ->assertOk()
            ->assertSee('View booking reason')
            ->assertSee('Private reason &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->actingAs($other['user'])
            ->get('/appointments')
            ->assertOk()
            ->assertDontSee('Private reason')
            ->assertDontSee('Reason Patient');

        $this->actingAs($this->createAdmin())
            ->get('/appointments')
            ->assertOk()
            ->assertSee('Reason Patient')
            ->assertDontSee('Private reason')
            ->assertDontSee('View booking reason');
    }

    public function test_missing_booking_reason_renders_a_graceful_empty_state(): void
    {
        $clinician = $this->createClinician();
        $this->appointmentFor(
            $clinician['clinician']->id,
            'No Reason Patient',
            'no-reason@test.com',
            '2030-12-05 09:00:00'
        );

        $this->actingAs($clinician['user'])
            ->get('/appointments')
            ->assertOk()
            ->assertSee('No booking reason provided');
    }

    private function createClinicianWithIdentity(string $name, string $email): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-REASON-002',
            'specialization' => 'Testing',
        ]);

        return compact('user', 'clinician');
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
