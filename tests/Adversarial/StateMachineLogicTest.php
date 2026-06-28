<?php

namespace Tests\Adversarial;

use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AppointmentService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Phase 2.E — State Machine Logic.
 *
 * Probe every multi-step process for missing guards. Phase 1 confirmed:
 *   - `complete` correctly blocks non-approved/rescheduled states.
 *   - `cancel` correctly blocks terminal states via policy.
 *   - `submit` blocks re-submission after review via 409.
 *
 * But `approve`, `reject`, `reschedule` (and `complete` itself for
 * already-completed) have NO status precondition check at the SERVICE
 * layer — AppointmentService:163-209 just does the UPDATE. The web
 * controller doesn't guard either. E1–E7 are bug-proving tests:
 * a clinician/admin can currently approve a `completed` appointment,
 * revive a `cancelled` one via reschedule, etc.
 *
 * E9 & E10 are bug-proving tests for the password-change token/session
 * invalidation gap (H2 from prior review): neither Web\AccountController
 * nor Portal\PortalProfileController revoke Sanctum tokens / other
 * sessions after a password change — leaving stolen tokens valid for
 * up to 7 days (Sanctum expiration).
 */
class StateMachineLogicTest extends TestCase
{
    use CreatesActors;

    // ─────────────────────────────────────────────────────────────────────
    // E1–E5: missing terminal-state guards on AppointmentService actions
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E1 — `approve` should refuse a `completed` appointment.
     * BUG PROOF: AppointmentService::approve has no precondition; calling it
     * on a `completed` appointment flips it back to `approved`, desyncing
     * attendance metrics, regenerating a meeting link for a closed case.
     */
    public function test_approve_on_completed_appointment_should_be_blocked(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e1@test.com');

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'completed',
        ]);

        $service = app(AppointmentService::class);

        try {
            $service->approve($appt);

            $this->fail('BUG: AppointmentService::approve() accepted a completed appointment without a state-guard throw.');
        } catch (\Throwable $e) {
            // Acceptable: the service throws (current state) or the future
            // guard throws an explicit SlotUnavailableException / DomainException.
            $this->assertTrue(true, 'State guard threw: '.$e->getMessage());
        }

        $this->assertEquals('completed', $appt->fresh()->status, 'Status must not have flipped to approved.');
    }

    /**
     * E2 — `approve` should refuse a `cancelled` appointment.
     */
    public function test_approve_on_cancelled_appointment_should_be_blocked(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e2@test.com');

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'cancelled',
        ]);

        $service = app(AppointmentService::class);

        try {
            $service->approve($appt);
            $this->fail('BUG: AppointmentService::approve() accepted a cancelled appointment.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals('cancelled', $appt->fresh()->status);
    }

    /**
     * E3 — `approve` should refuse a `no_show` appointment.
     */
    public function test_approve_on_no_show_appointment_should_be_blocked(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e3@test.com');

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'no_show',
        ]);

        $service = app(AppointmentService::class);

        try {
            $service->approve($appt);
            $this->fail('BUG: AppointmentService::approve() accepted a no_show appointment.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals('no_show', $appt->fresh()->status);
    }

    /**
     * E4 — `reject` should refuse a `completed` appointment.
     */
    public function test_reject_on_completed_appointment_should_be_blocked(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e4@test.com');

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'completed',
        ]);

        $service = app(AppointmentService::class);

        try {
            $service->reject($appt);
            $this->fail('BUG: AppointmentService::reject() accepted a completed appointment.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals('completed', $appt->fresh()->status);
    }

    /**
     * E5 — `reschedule` should refuse a `cancelled` appointment.
     */
    public function test_reschedule_on_cancelled_appointment_should_be_blocked(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e5@test.com');

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'cancelled',
        ]);

        $service = app(AppointmentService::class);

        try {
            $service->reschedule($appt, '2030-12-31 14:00:00');
            $this->fail('BUG: AppointmentService::reschedule() accepted a cancelled appointment.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        $this->assertEquals('cancelled', $appt->fresh()->status);
    }

    /**
     * E6 — `complete` (mark as completed) should refuse an already-completed appointment.
     * Defense test — WebAppointmentController::complete already guards
     * `!in_array($status, ['approved','rescheduled'])` so this should pass.
     */
    public function test_complete_on_already_completed_appointment_is_blocked(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e6@test.com');

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin, 'web')
            ->patch("/appointments/{$appt->id}/complete", ['outcome' => 'attended']);

        // The controller returns back with errors → 302 redirect with session errors.
        $response->assertSessionHasErrors(['status']);
        $this->assertEquals('completed', $appt->fresh()->status);
    }

    /**
     * E7 — `review` an already-reviewed submission should be blocked.
     */
    public function test_review_on_already_reviewed_submission_is_blocked(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e7@test.com');

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'e7',
            'description' => 'review twice',
            'due_date' => '2030-12-31 23:59:59',
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'patient_id' => $patient['patient']->id,
            'content' => 'submitted',
            'status' => 'reviewed',
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($clinician['user'])
            ->patch("/submissions/{$submission->id}/review");

        // Controller doesn't have an explicit guard. Expected outcomes:
        //   - 302 redirect (web, possibly with "reviewed again" no-op)
        // This test asserts the system prevents double-review OR flags the gap.
        $this->assertContains($response->status(), [302, 400, 409, 422, 500]);

        // Reviewed timestamp must not be bumped if the guard exists.
        $originalReviewedAt = $submission->reviewed_at;
        $this->assertEquals($originalReviewedAt, $submission->fresh()->reviewed_at);
    }

    /**
     * E8 — Defense test: completing a `pending` appointment must be blocked.
     * The controller already does this — confirm.
     */
    public function test_complete_on_pending_appointment_is_blocked(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e8@test.com');

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'web')
            ->patch("/appointments/{$appt->id}/complete", ['outcome' => 'attended']);

        $response->assertSessionHasErrors(['status']);
        $this->assertEquals('pending', $appt->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────────────
    // E9, E10: password-change token/session invalidation gap (H2)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E9 — Web staff password change. After an admin/clinician changes
     * their password via PUT /account/password, ANY Sanctum bearer tokens
     * previously issued (e.g. by an attacker with DB access) must be
     * invalidated via $user->tokens()->delete().
     *
     * Verified via DB assertion rather than HTTP re-request — Sanctum's
     * EnsureFrontendRequestsAreStateful middleware in test env persists
     * session auth across calls, masking the bearer-token check.
     *
     * BUG PROOF: Web\AccountController::updatePassword does NOT call
     * tokens()->delete() — only the API PasswordController::update does.
     */
    public function test_web_password_change_invalidates_sanctum_tokens(): void
    {
        $admin = $this->createAdmin();

        // Mint a Sanctum token "out of band" (admin can't API-login, but
        // an attacker with DB access could insert one directly).
        $admin->createToken('stolen-session');

        $tokenCountBefore = DB::table('personal_access_tokens')
            ->where('tokenable_id', $admin->id)
            ->count();
        $this->assertEquals(1, $tokenCountBefore, 'Token should exist before password change.');

        // Change password via the web route using session auth.
        $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/account/password', [
                'current_password' => 'password',
                'password' => 'NewStrongPass1',
                'password_confirmation' => 'NewStrongPass1',
            ]);

        // Critical: tokens must be revoked.
        $tokenCountAfter = DB::table('personal_access_tokens')
            ->where('tokenable_id', $admin->id)
            ->count();

        $this->assertEquals(
            0,
            $tokenCountAfter,
            'BUG CONFIRMED: Web\AccountController::updatePassword did not revoke Sanctum tokens. '.
            "Token count was {$tokenCountAfter} (expected 0) after password change. ".
            'Web\AccountController::updatePassword does not call $user->tokens()->delete(). '.
            'Api\V1\PasswordController::update DOES — the patterns are inconsistent. '.
            'A patient who suspects compromise and changes password via /account/password '.
            'leaves any stolen bearer tokens valid for up to 7 days (Sanctum expiration).'
        );
    }

    /**
     * E10 — Same as E9 but for the patient portal: changing password via
     * /portal/profile/password must revoke any existing Sanctum tokens.
     *
     * Verified via DB assertion (see E9 note).
     *
     * BUG PROOF: Portal\PortalProfileController::updatePassword does NOT
     * revoke tokens (matches the Web AccountController bug).
     */
    public function test_portal_password_change_invalidates_sanctum_tokens(): void
    {
        $patient = $this->createPatient('e10@test.com');

        // Mint two tokens — one for "this device," one "stolen".
        $this->getApiToken($patient['user']);
        $patient['user']->createToken('stolen-mobile');

        $tokenCountBefore = DB::table('personal_access_tokens')
            ->where('tokenable_id', $patient['user']->id)
            ->count();
        $this->assertEquals(2, $tokenCountBefore, 'Both tokens should exist before password change.');

        // Change password via the portal route using session auth.
        $this->actingAs($patient['user'])
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/portal/profile/password', [
                'current_password' => 'password',
                'password' => 'NewStrongPass1',
                'password_confirmation' => 'NewStrongPass1',
            ]);

        // ALL Sanctum tokens (including the patient's own mobile device)
        // must be revoked. The API PasswordController preserves only the
        // current token; the portal controller should follow the same
        // discipline (revoke all, patient re-auths their mobile app).
        $tokenCountAfter = DB::table('personal_access_tokens')
            ->where('tokenable_id', $patient['user']->id)
            ->count();

        $this->assertEquals(
            0,
            $tokenCountAfter,
            'BUG CONFIRMED: Portal\PortalProfileController::updatePassword did not revoke Sanctum tokens. '.
            "Token count was {$tokenCountAfter} (expected 0) after password change. ".
            'Stolen bearer tokens remain valid for up to 7 days after a patient '.
            'changes their password via the portal.'
        );
    }

    /**
     * E10b — Control test for E9/E10: the API password change DOES revoke
     * other tokens. This proves the bug is an asymmetry between the API
     * controller (which does revoke) and the web/portal controllers (which
     * don't), not a system-wide miss.
     *
     * Verified via DB assertion (see E9 note).
     */
    public function test_api_password_change_DOES_invalidate_other_tokens_control(): void
    {
        $patient = $this->createPatient('e10-control@test.com');

        $tokenA = $this->getApiToken($patient['user']);
        $patient['user']->createToken('mobile-b');

        $tokenCountBefore = DB::table('personal_access_tokens')
            ->where('tokenable_id', $patient['user']->id)
            ->count();
        $this->assertEquals(2, $tokenCountBefore, 'Both tokens should exist before password change.');

        // Change password using tokenA — the API controller must preserve
        // token A (the current) and revoke all others (token B).
        $this->withHeaders($this->apiHeaders($tokenA))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewStrongPass1',
                'password_confirmation' => 'NewStrongPass1',
            ])
            ->assertStatus(204);

        // After password change, only token A (the current one) should remain.
        $tokenCountAfter = DB::table('personal_access_tokens')
            ->where('tokenable_id', $patient['user']->id)
            ->count();

        $this->assertEquals(
            1,
            $tokenCountAfter,
            'CONTROL: API PasswordController should leave only the current token (token A). '.
            "Got {$tokenCountAfter} remaining (expected 1). If this fails too, the API path itself is broken — ".
            'investigate $user->currentAccessToken() being null in tests.'
        );
    }
}
