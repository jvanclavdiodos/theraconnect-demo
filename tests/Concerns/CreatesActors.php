<?php

namespace Tests\Concerns;

use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\Patient;
use App\Models\User;

/**
 * Adversarial-test scaffolding for the TheraConnect three-tier model.
 *
 * The existing tests\TestCase helpers cover the canonical single-admin /
 * single-clinician / single-patient scenarios; the adversarial suite
 * needs additional fixtures — a *second* clinician and patient for
 * cross-tenant boundary checks, plus helpers to seed assignments,
 * submissions, conversations, and complete appointments so individual
 * tests don't have to drive the full happy flow before probing an edge.
 *
 * Direct Model::create([...]) is used (consistent with the rest of the
 * suite) rather than factories — the project has no model factories
 * except the UserFactory stub.
 */
trait CreatesActors
{
    /**
     * A second clinician (distinct from TestCase::createClinician).
     * Used as the "off-caseload" / "other clinician" actor in IDOR tests.
     */
    protected function createSecondClinician(): array
    {
        $user = User::create([
            'name' => 'Dr. Other',
            'email' => 'other-clinician@test.com',
            'password' => 'password',
            'role' => 'clinician',
        ]);

        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-OTHER-002',
            'specialization' => 'Other',
            'contact_no' => '555-0110',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    /**
     * A second patient (distinct from the email passed to TestCase::createPatient).
     * Used as the "B actor" in cross-patient IDOR tests.
     */
    protected function createSecondPatient(): array
    {
        return $this->createPatientWithEmail('other-patient@test.com');
    }

    /**
     * Patient factory with a specific email, so multiple patients can coexist
     * in one test without colliding on the user.email unique index.
     */
    protected function createPatientWithEmail(string $email): array
    {
        $user = User::create([
            'name' => 'Patient ' . $email,
            'email' => $email,
            'password' => 'password',
            'role' => 'patient',
        ]);

        $patient = Patient::create([
            'user_id' => $user->id,
            'date_of_birth' => '1992-07-22',
            'contact_no' => '555-0299',
            'address' => '456 Other Ave',
            'emergency_contact' => 'Jane Other - 555-0310',
        ]);

        return ['user' => $user, 'patient' => $patient];
    }

    /**
     * Convenience: seed a fully-knit approved appointment that belongs to a
     * given patient+clinician pair, leaving the actor free to test the
     * subsequent state transition (complete/reschedule/etc.) directly.
     */
    protected function seedApprovedAppointment(int $patientId, int $clinicianId, string $at = '2030-12-31 09:00:00'): Appointment
    {
        return Appointment::create([
            'patient_id' => $patientId,
            'clinician_id' => $clinicianId,
            'requested_at' => $at,
            'scheduled_at' => $at,
            'mode' => 'in_person',
            'status' => 'approved',
        ]);
    }
}
