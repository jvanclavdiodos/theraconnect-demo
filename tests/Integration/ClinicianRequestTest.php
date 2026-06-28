<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

/**
 * A self-registered patient picks a clinician at sign-up; that request is
 * pending until the clinician approves it. Approval is what places the patient
 * on the clinician's caseload (patients tab + messaging), which is exactly the
 * gap reported as "new patients not reflecting on the clinician side".
 */
class ClinicianRequestTest extends TestCase
{
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
            'specialization' => 'CBT',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    /** A patient with a pending request to the given clinician. */
    private function pendingPatient(Clinician $clinician, string $email = 'newpatient@test.com'): array
    {
        $patient = $this->createPatient($email);
        $patient['patient']->update([
            'requested_clinician_id' => $clinician->id,
            'clinician_request_status' => Patient::REQUEST_PENDING,
        ]);

        return $patient;
    }

    public function test_clinician_directory_is_public(): void
    {
        $clinician = $this->makeClinician('dir@test.com');

        $this->getJson('/api/v1/clinicians')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $clinician['clinician']->id]);
    }

    public function test_api_registration_with_requested_clinician_creates_pending_request(): void
    {
        $clinician = $this->makeClinician('apidoc@test.com');

        $this->postJson('/api/v1/register', [
            'name' => 'New Patient',
            'email' => 'apinew@test.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'requested_clinician_id' => $clinician['clinician']->id,
        ])->assertStatus(201);

        $patient = Patient::whereHas('user', fn ($q) => $q->where('email', 'apinew@test.com'))->firstOrFail();
        $this->assertSame($clinician['clinician']->id, $patient->requested_clinician_id);
        $this->assertSame(Patient::REQUEST_PENDING, $patient->clinician_request_status);
        $this->assertNull($patient->assigned_clinician_id);

        // The clinician was notified of the request.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $clinician['user']->id,
            'type' => 'patient_request',
        ]);
    }

    public function test_web_registration_with_requested_clinician_creates_pending_request(): void
    {
        $clinician = $this->makeClinician('webdoc@test.com');

        $this->post('/register', [
            'name' => 'Web Patient',
            'email' => 'webnew@test.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'requested_clinician_id' => $clinician['clinician']->id,
        ])->assertRedirect(route('portal.dashboard'));

        $patient = Patient::whereHas('user', fn ($q) => $q->where('email', 'webnew@test.com'))->firstOrFail();
        $this->assertSame(Patient::REQUEST_PENDING, $patient->clinician_request_status);
        $this->assertNull($patient->assigned_clinician_id);
    }

    public function test_pending_patient_is_not_yet_on_the_caseload(): void
    {
        $clinician = $this->makeClinician('owner@test.com');
        $patient = $this->pendingPatient($clinician['clinician']);

        // Shows up as a pending request, not as an active caseload patient.
        $this->actingAs($clinician['user'], 'web')
            ->get('/patients')
            ->assertStatus(200)
            ->assertSee('Pending clinician requests')
            ->assertSee('Jane Patient');

        // Messaging is still blocked because they are unassigned.
        $this->actingAs($clinician['user'], 'web')
            ->post('/messages/open', ['patient_id' => $patient['patient']->id])
            ->assertStatus(403);
    }

    public function test_clinician_approves_request_and_patient_joins_caseload(): void
    {
        $clinician = $this->makeClinician('owner@test.com');
        $patient = $this->pendingPatient($clinician['clinician']);

        $this->actingAs($clinician['user'], 'web')
            ->post("/patients/{$patient['patient']->id}/request/approve")
            ->assertRedirect(route('patients.index'));

        $patient['patient']->refresh();
        $this->assertSame($clinician['clinician']->id, $patient['patient']->assigned_clinician_id);
        $this->assertSame(Patient::REQUEST_APPROVED, $patient['patient']->clinician_request_status);

        // Patient was notified, and can now be messaged (on the caseload).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'patient_request_approved',
        ]);
        $this->actingAs($clinician['user'], 'web')
            ->post('/messages/open', ['patient_id' => $patient['patient']->id])
            ->assertRedirect();
    }

    public function test_clinician_denies_request(): void
    {
        $clinician = $this->makeClinician('owner@test.com');
        $patient = $this->pendingPatient($clinician['clinician']);

        $this->actingAs($clinician['user'], 'web')
            ->post("/patients/{$patient['patient']->id}/request/deny")
            ->assertRedirect(route('patients.index'));

        $patient['patient']->refresh();
        $this->assertSame(Patient::REQUEST_DENIED, $patient['patient']->clinician_request_status);
        $this->assertNull($patient['patient']->assigned_clinician_id);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'patient_request_denied',
        ]);
    }

    public function test_other_clinician_cannot_act_on_someone_elses_request(): void
    {
        $owner = $this->makeClinician('owner@test.com');
        $other = $this->makeClinician('other@test.com');
        $patient = $this->pendingPatient($owner['clinician']);

        $this->actingAs($other['user'], 'web')
            ->post("/patients/{$patient['patient']->id}/request/approve")
            ->assertStatus(403);

        $patient['patient']->refresh();
        $this->assertSame(Patient::REQUEST_PENDING, $patient['patient']->clinician_request_status);
        $this->assertNull($patient['patient']->assigned_clinician_id);
    }
}
