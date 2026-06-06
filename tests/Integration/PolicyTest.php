<?php

namespace Tests\Integration;

use App\Models\Appointment;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    public function test_appointment_policy_blocks_wrong_patient(): void
    {
        $clinician = $this->createClinician();
        $patientA = $this->createPatient('pol-a@test.com');
        $patientB = $this->createPatient('pol-b@test.com');

        // Patient A creates an appointment
        $appointment = Appointment::create([
            'patient_id' => $patientA['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2026-06-10 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        // Patient A should be allowed
        $this->assertTrue(
            Gate::forUser($patientA['user'])->allows('view', $appointment),
            'Patient A should be able to view their own appointment'
        );

        // Patient B should NOT be allowed
        $this->assertFalse(
            Gate::forUser($patientB['user'])->allows('view', $appointment),
            'Patient B should NOT be able to view Patient A appointment'
        );

        // Admin should be allowed
        $admin = $this->createAdmin();
        $this->assertTrue(
            Gate::forUser($admin)->allows('view', $appointment),
            'Admin should be able to view any appointment'
        );
    }
}
