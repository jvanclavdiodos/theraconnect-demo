<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Clinician;
use App\Models\Patient;
use App\Models\Submission;
use App\Models\User;
use Tests\TestCase;

/**
 * Cross-clinician isolation on the web dashboard (P0/P1 scoping).
 *
 * A clinician may only see and act on their own caseload — their assigned
 * patients, their appointments, their assignments/submissions. An admin sees
 * and manages everything. These tests lock that boundary so a regression can't
 * silently re-open the cross-clinician data leak.
 */
class ClinicianScopingTest extends TestCase
{
    private function makeClinician(string $email, string $license): array
    {
        $user = User::create([
            'name' => "Dr. {$email}",
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => $license,
            'specialization' => 'Testing',
            'contact_no' => '555-0000',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    private function makePatient(string $email, string $name, int $clinicianId): Patient
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => 'patient',
        ]);

        return Patient::create([
            'user_id' => $user->id,
            'assigned_clinician_id' => $clinicianId,
            'date_of_birth' => '1990-01-01',
        ]);
    }

    private function pendingAppointment(int $patientId, int $clinicianId): Appointment
    {
        return Appointment::create([
            'patient_id' => $patientId,
            'clinician_id' => $clinicianId,
            'requested_at' => now()->addDays(3)->setTime(9, 0),
            'mode' => 'in_person',
            'status' => 'pending',
            'reason' => 'Test',
        ]);
    }

    private function submissionFor(int $clinicianId, int $patientId): Submission
    {
        $assignment = Assignment::create([
            'clinician_id' => $clinicianId,
            'patient_id' => $patientId,
            'title' => 'Test assignment',
            'description' => 'Do the thing',
            'due_date' => now()->addDays(5),
        ]);

        return Submission::create([
            'assignment_id' => $assignment->id,
            'patient_id' => $patientId,
            'content' => 'My work',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function test_clinician_cannot_approve_another_clinicians_appointment(): void
    {
        $a = $this->makeClinician('a@test.com', 'LIC-A');
        $b = $this->makeClinician('b@test.com', 'LIC-B');
        $patientA = $this->makePatient('pa@test.com', 'Patient A', $a['clinician']->id);
        $apptA = $this->pendingAppointment($patientA->id, $a['clinician']->id);

        // Clinician B may not approve clinician A's appointment.
        $this->actingAs($b['user'], 'web')
            ->patch("/appointments/{$apptA->id}/approve")
            ->assertForbidden();

        $this->assertDatabaseHas('appointments', ['id' => $apptA->id, 'status' => 'pending']);
    }

    public function test_clinician_can_approve_own_appointment(): void
    {
        $a = $this->makeClinician('a@test.com', 'LIC-A');
        $patientA = $this->makePatient('pa@test.com', 'Patient A', $a['clinician']->id);
        $apptA = $this->pendingAppointment($patientA->id, $a['clinician']->id);

        $this->actingAs($a['user'], 'web')
            ->patch("/appointments/{$apptA->id}/approve")
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', ['id' => $apptA->id, 'status' => 'approved']);
    }

    public function test_clinician_cannot_view_another_clinicians_submissions(): void
    {
        $a = $this->makeClinician('a@test.com', 'LIC-A');
        $b = $this->makeClinician('b@test.com', 'LIC-B');
        $patientA = $this->makePatient('pa@test.com', 'Patient A', $a['clinician']->id);
        $submission = $this->submissionFor($a['clinician']->id, $patientA->id);

        // View submissions list for A's assignment.
        $this->actingAs($b['user'], 'web')
            ->get("/assignments/{$submission->assignment_id}/submissions")
            ->assertForbidden();

        // Download A's submission file.
        $this->actingAs($b['user'], 'web')
            ->get("/submissions/{$submission->id}/file")
            ->assertForbidden();
    }

    public function test_clinician_can_review_own_submission(): void
    {
        $a = $this->makeClinician('a@test.com', 'LIC-A');
        $patientA = $this->makePatient('pa@test.com', 'Patient A', $a['clinician']->id);
        $submission = $this->submissionFor($a['clinician']->id, $patientA->id);

        $this->actingAs($a['user'], 'web')
            ->patch("/submissions/{$submission->id}/review")
            ->assertRedirect();

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'status' => 'reviewed',
        ]);
    }

    public function test_patient_index_is_scoped_to_clinician(): void
    {
        $a = $this->makeClinician('a@test.com', 'LIC-A');
        $b = $this->makeClinician('b@test.com', 'LIC-B');
        $this->makePatient('pa@test.com', 'Alice Assigned', $a['clinician']->id);
        $this->makePatient('pb@test.com', 'Bob Otherclinic', $b['clinician']->id);

        $response = $this->actingAs($a['user'], 'web')->get('/patients');
        $response->assertOk();
        $response->assertSee('Alice Assigned');
        $response->assertDontSee('Bob Otherclinic');
    }

    public function test_admin_sees_all_patients(): void
    {
        $admin = $this->createAdmin();
        $a = $this->makeClinician('a@test.com', 'LIC-A');
        $b = $this->makeClinician('b@test.com', 'LIC-B');
        $this->makePatient('pa@test.com', 'Alice Assigned', $a['clinician']->id);
        $this->makePatient('pb@test.com', 'Bob Otherclinic', $b['clinician']->id);

        $response = $this->actingAs($admin, 'web')->get('/patients');
        $response->assertOk();
        $response->assertSee('Alice Assigned');
        $response->assertSee('Bob Otherclinic');
    }

    public function test_clinician_cannot_reach_admin_only_routes(): void
    {
        $a = $this->makeClinician('a@test.com', 'LIC-A');
        $patientA = $this->makePatient('pa@test.com', 'Patient A', $a['clinician']->id);

        // Editing an existing patient record is admin-only.
        $this->actingAs($a['user'], 'web')->get("/patients/{$patientA->id}/edit")->assertForbidden();
        // Clinician management is admin-only.
        $this->actingAs($a['user'], 'web')->get('/clinicians')->assertForbidden();
        // Chatbot content + notification logs are admin-only.
        $this->actingAs($a['user'], 'web')->get('/chatbot-content')->assertForbidden();
        $this->actingAs($a['user'], 'web')->get('/notifications/logs')->assertForbidden();
    }

    public function test_clinician_can_add_patient_to_own_caseload(): void
    {
        $a = $this->makeClinician('a@test.com', 'LIC-A');

        // The create form is reachable.
        $this->actingAs($a['user'], 'web')->get('/patients/create')->assertOk();

        // Creating a patient auto-assigns them to the acting clinician, even if
        // a different clinician id is submitted (forced self-assignment).
        $b = $this->makeClinician('b@test.com', 'LIC-B');
        $this->actingAs($a['user'], 'web')->post('/patients', [
            'name' => 'New Patient',
            'email' => 'newp@test.com',
            'password' => 'Password123',
            'assigned_clinician_id' => $b['clinician']->id, // attempt to assign to B
        ])->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'assigned_clinician_id' => $a['clinician']->id, // forced to A, not B
        ]);

        // And the new patient shows up in clinician A's scoped index.
        $this->actingAs($a['user'], 'web')->get('/patients')->assertSee('New Patient');
    }
}
