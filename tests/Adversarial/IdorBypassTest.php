<?php

namespace Tests\Adversarial;

use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Assessment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\PatientNote;
use App\Models\Submission;
use App\Models\TherapyGoal;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Phase 2.A — Authorization Bypasses (IDOR).
 *
 * Mission: for every route that takes a resource ID, prove that one
 * authenticated user cannot view, edit, or delete another user's data.
 * Coverage gaps surfaced in Phase 1 include:
 *   - POST/send/submit paths across patient boundaries (assignments/
 *     conversations/messages/portal assessments) — never IDOR-tested
 *   - DELETE cross-patient routes — never IDOR-tested
 *   - The entire portal {id} GET surface (assignments/assessments/
 *     messages/submissions) — only portal appointment show was tested
 *
 * Each test sets up two patients (A, B) with their own token/session,
 * A requests B's resource by ID, and we assert the rejection boundary
 * (403 / 404) plus DB integrity (no leakage, no mutation).
 */
class IdorBypassTest extends TestCase
{
    use CreatesActors;

    // ─────────────────────────────────────────────────────────────────────
    // API surface (Sanctum bearer, role:patient)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A1 — Patient A cancels Patient B's appointment via DELETE /api/v1/appointments/{id}.
     */
    public function test_patient_cannot_cancel_other_patients_appointment_api(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $appt = Appointment::create([
            'patient_id' => $patientB['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($patientA['user'])))
            ->deleteJson("/api/v1/appointments/{$appt->id}")
            ->assertForbidden();

        $this->assertEquals('approved', $appt->fresh()->status);
    }

    /**
     * A2 — Patient A reads Patient B's assignment via GET /api/v1/assignments/{id}.
     */
    public function test_patient_cannot_view_other_patients_assignment_api(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patientB['patient']->id,
            'title' => 'B-only worksheet',
            'description' => 'Private to B.',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($patientA['user'])))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertForbidden();

        // Information-leak finding (see InformationLeakageTest C7 for full
        // details): the bootstrap/app.php:76 renderable for AuthorizationException
        // is dead code — Laravel wraps it in AccessDeniedHttpException first,
        // so the message is 'This action is unauthorized.' not 'Forbidden.'.
        // We assert the rejection boundary without coupling to the message text.
        $this->assertStringNotContainsString(
            'B-only worksheet',
            $this->withHeaders($this->apiHeaders($this->getApiToken($patientA['user'])))
                ->getJson("/api/v1/assignments")->getContent()
        );
    }

    /**
     * A3 — Patient A submits into Patient B's assignment via POST /api/v1/assignments/{id}/submit.
     */
    public function test_patient_cannot_submit_into_other_patients_assignment_api(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patientB['patient']->id,
            'title' => 'B worksheet',
            'description' => 'private',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($patientA['user'])))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => 'A trying to inject into B',
            ])
            ->assertForbidden();

        $this->assertEquals(0, Submission::where('assignment_id', $assignment->id)->count());
    }

    /**
     * A4 — Patient A injects a message into Patient B's conversation thread via
     * POST /api/v1/conversations/{conversation}/messages.
     */
    public function test_patient_cannot_inject_message_into_other_patients_conversation_api(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $conv = Conversation::create([
            'patient_id' => $patientB['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);

        $this->actingAs($patientA['user'], 'sanctum')
            ->postJson("/api/v1/conversations/{$conv->id}/messages", [
                'body' => 'A injecting into B thread',
            ])
            ->assertForbidden();

        $this->assertEquals(0, Message::where('conversation_id', $conv->id)->count());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Portal surface (session auth, role:patient)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A5 — Portal Patient A reads Portal Patient B's assignment via GET /portal/assignments/{assignment}.
     */
    public function test_patient_cannot_view_other_patients_assignment_portal(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patientB['patient']->id,
            'title' => 'B-only portal worksheet',
            'description' => 'private',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $this->actingAs($patientA['user'])
            ->get("/portal/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    /**
     * A6 — Portal Patient A downloads Patient B's assignment worksheet.
     */
    public function test_patient_cannot_download_other_patients_worksheet_portal(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        // Create a real file on disk so the 404-existence check doesn't short-circuit
        // before the policy Gate runs.
        $path = 'assignments/'.uniqid('b_worksheet_', true).'.txt';
        \Illuminate\Support\Facades\Storage::disk()->put($path, 'B only content');

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patientB['patient']->id,
            'title' => 'B worksheet',
            'description' => 'private',
            'attachment_path' => $path,
            'attachment_name' => 'b_worksheet.txt',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $this->actingAs($patientA['user'])
            ->get("/portal/assignments/{$assignment->id}/worksheet")
            ->assertForbidden();
    }

    /**
     * A7 — Portal Patient A submits into Patient B's assignment.
     */
    public function test_patient_cannot_submit_into_other_patients_assignment_portal(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patientB['patient']->id,
            'title' => 'B portal worksheet',
            'description' => 'private',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $this->actingAs($patientA['user'])
            ->post("/portal/assignments/{$assignment->id}/submit", [
                'content' => 'A injecting via portal',
            ])
            ->assertForbidden();

        $this->assertEquals(0, Submission::where('assignment_id', $assignment->id)->count());
    }

    /**
     * A8 — Portal Patient A downloads Patient B's submission file.
     */
    public function test_patient_cannot_download_other_patients_submission_portal(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patientB['patient']->id,
            'title' => 'B worksheet',
            'description' => 'private',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $path = 'submissions/'.uniqid('b_sub_', true).'.pdf';
        \Illuminate\Support\Facades\Storage::disk()->put($path, 'B only submission');

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'patient_id' => $patientB['patient']->id,
            'file_path' => $path,
            'original_name' => 'b_sub.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($patientA['user'])
            ->get("/portal/submissions/{$submission->id}/file")
            ->assertForbidden();
    }

    /**
     * A9 — Portal Patient A reads Patient B's assessment.
     */
    public function test_patient_cannot_view_other_patients_assessment_portal(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $assessment = Assessment::create([
            'patient_id' => $patientB['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'pending',
        ]);

        $this->actingAs($patientA['user'])
            ->get("/portal/assessments/{$assessment->id}")
            ->assertForbidden();
    }

    /**
     * A10 — Portal Patient A submits Patient B's assessment.
     */
    public function test_patient_cannot_submit_other_patients_assessment_portal(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $assessment = Assessment::create([
            'patient_id' => $patientB['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'pending',
        ]);

        $this->actingAs($patientA['user'])
            ->post("/portal/assessments/{$assessment->id}/submit", [
                'responses' => array_fill(0, 9, 0),
            ])
            ->assertForbidden();

        $this->assertEquals('pending', $assessment->fresh()->status);
    }

    /**
     * A11 — Portal Patient A injects a message into Patient B's conversation thread.
     */
    public function test_patient_cannot_inject_message_into_other_patients_conversation_portal(): void
    {
        [$patientA, $patientB, $clinician] = $this->twoPatientsAndAClinician();

        $conv = Conversation::create([
            'patient_id' => $patientB['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);

        $this->actingAs($patientA['user'])
            ->post("/portal/messages/{$conv->id}", [
                'body' => 'A injecting via portal',
            ])
            ->assertForbidden();

        $this->assertEquals(0, Message::where('conversation_id', $conv->id)->count());
    }

    /**
     * A12 — Portal Patient A marks Patient B's notification read.
     * The controller scopes by `where('user_id', auth()->id())`, so this is
     * expected to 404 ("not found in my set") rather than 403.
     */
    public function test_patient_cannot_mark_other_patients_notification_read_portal(): void
    {
        [$patientA, $patientB] = $this->twoPatients();

        $notif = Notification::create([
            'user_id' => $patientB['user']->id,
            'type' => 'generic',
            'title' => 'B notif',
            'body' => 'private to B',
            'channel' => 'fcm',
        ]);

        $this->actingAs($patientA['user'])
            ->post("/portal/notifications/{$notif->id}/read")
            ->assertNotFound();

        $this->assertNull($notif->fresh()->read_at);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Web dashboard (session auth, role:admin,clinician) — cross-clinician edits
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A14 — Clinician X deletes a note authored by Clinician Y.
     */
    public function test_clinician_cannot_delete_other_clinicians_patient_note(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('pn-patient@test.com');

        $note = PatientNote::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinicianB['clinician']->id,
            'title' => 'B note',
            'body' => 'private to B',
            'is_shared' => false,
        ]);

        $this->actingAs($clinicianA['user'])
            ->delete("/patient-notes/{$note->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('patient_notes', ['id' => $note->id]);
    }

    /**
     * A15 — Clinician X changes the status of Clinician Y's therapy goal.
     */
    public function test_clinician_cannot_change_status_of_other_clinicians_goal(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('goal-status-p@test.com');

        $goal = TherapyGoal::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinicianB['clinician']->id,
            'description' => 'B goal',
            'status' => 'active',
            'target_date' => '2030-12-31',
        ]);

        $this->actingAs($clinicianA['user'])
            ->patch("/goals/{$goal->id}/status", ['status' => 'met'])
            ->assertForbidden();

        $this->assertEquals('active', $goal->fresh()->status);
    }

    /**
     * A16 — Clinician Y (not the requested clinician) denies Patient P's
     * clinician request — must be refused via PatientPolicy::respondToRequest.
     */
    public function test_wrong_clinician_cannot_deny_others_patient_request(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('req-deny-p@test.com');

        // Patient P requested clinician B.
        $patient['patient']->update([
            'requested_clinician_id' => $clinicianB['clinician']->id,
            'clinician_request_status' => 'pending',
            'assigned_clinician_id' => null,
        ]);

        // Clinician A tries to deny it.
        $this->actingAs($clinicianA['user'])
            ->post("/patients/{$patient['patient']->id}/request/deny")
            ->assertForbidden();

        $this->assertEquals('pending', $patient['patient']->fresh()->clinician_request_status);
    }

    /**
     * A17 — Cross-clinician worksheet download via web dashboard.
     * Clinician A should not download worksheet for an assignment authored by Clinician B.
     */
    public function test_clinician_cannot_download_other_clinicians_assignment_worksheet_web(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('web-ws-p@test.com');

        $path = 'assignments/'.uniqid('web_b_ws_', true).'.txt';
        \Illuminate\Support\Facades\Storage::disk()->put($path, 'B only');

        $assignment = Assignment::create([
            'clinician_id' => $clinicianB['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'B web worksheet',
            'description' => 'private',
            'attachment_path' => $path,
            'attachment_name' => 'b.txt',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $this->actingAs($clinicianA['user'])
            ->get("/assignments/{$assignment->id}/worksheet")
            ->assertForbidden();
    }

    /**
     * A18 — Cross-clinician submission review (web). Clinician A reviews a
     * submission belonging to Clinician B's assignment.
     */
    public function test_clinician_cannot_review_other_clinicians_submission_web(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('rev-p@test.com');

        $assignment = Assignment::create([
            'clinician_id' => $clinicianB['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'B assignment',
            'description' => 'private',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'patient_id' => $patient['patient']->id,
            'content' => 'submitted',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($clinicianA['user'])
            ->patch("/submissions/{$submission->id}/review")
            ->assertForbidden();

        $this->assertEquals('submitted', $submission->fresh()->status);
    }

    /**
     * A19 — Cross-clinician message injection (web). Clinician A posts into
     * a conversation that's between Patient P and Clinician B.
     */
    public function test_clinician_cannot_post_into_other_clinicians_conversation_web(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('msg-web-p@test.com');

        $conv = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinicianB['clinician']->id,
        ]);

        $this->actingAs($clinicianA['user'])
            ->post("/messages/{$conv->id}", ['body' => 'A injecting'])
            ->assertForbidden();

        $this->assertEquals(0, Message::where('conversation_id', $conv->id)->count());
    }

    /**
     * A20 — Clinician A reschedules an appointment that belongs to Clinician B.
     */
    public function test_clinician_cannot_reschedule_other_clinicians_appointment_web(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('res-web-p@test.com');

        $appt = $this->seedApprovedAppointment($patient['patient']->id, $clinicianB['clinician']->id);

        $this->actingAs($clinicianA['user'])
            ->patch("/appointments/{$appt->id}/reschedule", [
                'scheduled_at' => '2030-12-31 14:00:00',
            ])
            ->assertForbidden();

        $this->assertEquals('2030-12-31 09:00:00', $appt->fresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    /**
     * A21 — Cross-caseload avatar fetch: Clinician A requests Patient P's
     * avatar by user ID, where P is on Clinician B's caseload.
     */
    public function test_clinician_cannot_view_off_caseload_patient_avatar(): void
    {
        [$clinicianA, $clinicianB] = $this->twoClinicians();
        $patient = $this->createPatient('avatar-idor-p@test.com');

        // Put patient on B's caseload.
        $patient['patient']->update(['assigned_clinician_id' => $clinicianB['clinician']->id]);
        // Give the patient an avatar.
        $path = 'avatars/'.uniqid('av_', true).'.png';
        \Illuminate\Support\Facades\Storage::disk()->put($path, 'binary');
        $patient['user']->update(['avatar_path' => $path]);

        $this->actingAs($clinicianA['user'])
            ->get("/avatars/{$patient['user']->id}")
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Mass-assignment & RBAC boundary checks
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A22 — A crafted `role => 'admin'` in the POST /api/v1/register body
     * must NOT escalate the new account. The controller hard-codes
     * `role => 'patient'` (AuthController:29), so this is a defense test.
     */
    public function test_register_ignores_crafted_role_admin(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Attacker',
            'email' => 'attacker-escalate@test.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
            'accepted_terms' => true,
            'role' => 'admin',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'attacker-escalate@test.com',
            'role' => 'patient',
        ]);
    }

    /**
     * A23 — Variant of A22 with role => 'clinician'.
     */
    public function test_register_ignores_crafted_role_clinician(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Attacker2',
            'email' => 'attacker-clin@test.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
            'accepted_terms' => true,
            'role' => 'clinician',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'attacker-clin@test.com',
            'role' => 'patient',
        ]);
    }

    /**
     * A24 — Patient session hits /dashboard → 403 (role boundary).
     */
    public function test_patient_cannot_access_staff_dashboard(): void
    {
        $patient = $this->createPatient('p-to-dashboard@test.com');

        $this->actingAs($patient['user'])
            ->get('/dashboard')
            ->assertForbidden();
    }

    /**
     * A25 — Patient session hits /activity-logs → 403.
     */
    public function test_patient_cannot_access_activity_logs(): void
    {
        $patient = $this->createPatient('p-to-activity@test.com');

        $this->actingAs($patient['user'])
            ->get('/activity-logs')
            ->assertForbidden();
    }

    /**
     * A26 — Patient session hits /notifications/logs → 403.
     */
    public function test_patient_cannot_access_notification_logs(): void
    {
        $patient = $this->createPatient('p-to-notif-logs@test.com');

        $this->actingAs($patient['user'])
            ->get('/notifications/logs')
            ->assertForbidden();
    }

    /**
     * A27 — Guest hits a protected portal GET route → redirect to login (302).
     */
    public function test_guest_redirected_from_protected_portal_routes(): void
    {
        // A representative sample of portal routes; the same auth middleware
        // protects every portal route via the route group.
        $this->get('/portal')->assertRedirect('/login');
        $this->get('/portal/appointments')->assertRedirect('/login');
        $this->get('/portal/assignments')->assertRedirect('/login');
        $this->get('/portal/messages')->assertRedirect('/login');
        $this->get('/portal/assessments')->assertRedirect('/login');
        $this->get('/portal/profile')->assertRedirect('/login');
        $this->get('/portal/goals')->assertRedirect('/login');
        $this->get('/portal/notes')->assertRedirect('/login');
    }

    /**
     * A27b — Guest hits a protected staff GET route → redirect to login.
     */
    public function test_guest_redirected_from_protected_staff_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/patients')->assertRedirect('/login');
        $this->get('/appointments')->assertRedirect('/login');
        $this->get('/assignments')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
        $this->get('/notifications')->assertRedirect('/login');
    }

    /**
     * A28 — Clinician hitting admin-only /clinicians index → 403.
     */
    public function test_clinician_cannot_access_admin_clinicians_page(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'])
            ->get('/clinicians')
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Two patients (A, B) plus one clinician. Used by cross-patient IDOR cases.
     */
    private function twoPatientsAndAClinician(): array
    {
        $clinician = $this->createClinician();
        $patientA = $this->createPatient('idor-a@test.com');
        $patientB = $this->createSecondPatient();

        return [$patientA, $patientB, $clinician];
    }

    private function twoPatients(): array
    {
        $patientA = $this->createPatient('idor-only-a@test.com');
        $patientB = $this->createSecondPatient();

        return [$patientA, $patientB];
    }

    private function twoClinicians(): array
    {
        $a = $this->createClinician();
        $b = $this->createSecondClinician();

        return [$a, $b];
    }
}
