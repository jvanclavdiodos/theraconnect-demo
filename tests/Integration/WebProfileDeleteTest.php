<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class WebProfileDeleteTest extends TestCase
{
    /**
     * Deleting a Patient via the web dashboard must also soft-delete the
     * related User row within the same transaction. Otherwise the email
     * stays "taken" (blocks re-registration) and the orphaned user can still
     * authenticate on the API, then 404 on getPatient().
     */
    public function test_deleting_patient_also_soft_deletes_user(): void
    {
        $admin = $this->createAdmin();
        $patient = $this->createPatient('doomed-p@test.com');

        $this->actingAs($admin, 'web')
            ->delete("/patients/{$patient['patient']->id}");

        // Profile soft-deleted.
        $this->assertSoftDeleted('patients', ['id' => $patient['patient']->id]);

        // User also soft-deleted (the bug being fixed).
        $this->assertSoftDeleted('users', ['id' => $patient['user']->id]);

        // Email is now free for re-registration (query without trashed).
        $this->assertFalse(
            User::where('email', 'doomed-p@test.com')->exists(),
            'Soft-deleted user should not block email re-registration.'
        );
    }

    /**
     * Deleting a Clinician via the web dashboard must also soft-delete the
     * related User row (admin-only route).
     */
    public function test_deleting_clinician_also_soft_deletes_user(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();

        $this->actingAs($admin, 'web')
            ->delete("/clinicians/{$clinician['clinician']->id}");

        $this->assertSoftDeleted('clinicians', ['id' => $clinician['clinician']->id]);
        $this->assertSoftDeleted('users', ['id' => $clinician['user']->id]);
    }

    /**
     * The soft-deleted user cannot obtain a fresh bearer token via /api/v1/login
     * (login queries `User::where('email', ...)` which excludes trashed rows by
     * default). This is the patient-facing impact of the fix.
     */
    public function test_soft_deleted_user_cannot_login_via_api(): void
    {
        $admin = $this->createAdmin();
        $patient = $this->createPatient('gone-p@test.com');

        $this->actingAs($admin, 'web')
            ->delete("/patients/{$patient['patient']->id}");

        // API login should fail with 401 (SoftDeletes hides the user from the
        // query). Previously, the user could still login via the API because
        // only the Patient profile was soft-deleted.
        $this->postJson('/api/v1/login', [
            'email' => 'gone-p@test.com',
            'password' => 'password',
        ])->assertStatus(401);
    }

    /**
     * Soft-deletes preserve historical data — a trashed Patient is still
     * accessible via withTrashed() for audit/restore. (Documents the
     * intended lifecycle: soft-deleted, not hard-deleted.)
     */
    public function test_soft_deleted_patient_user_restorable(): void
    {
        $admin = $this->createAdmin();
        $patient = $this->createPatient('restorable@test.com');

        $this->actingAs($admin, 'web')
            ->delete("/patients/{$patient['patient']->id}");

        $trashedPatient = Patient::withTrashed()->find($patient['patient']->id);
        $trashedUser = User::withTrashed()->find($patient['user']->id);

        $this->assertNotNull($trashedPatient);
        $this->assertNotNull($trashedUser);
        $this->assertNotNull($trashedPatient->deleted_at);
        $this->assertNotNull($trashedUser->deleted_at);

        // Restore should bring back both rows.
        $trashedPatient->restore();
        $trashedUser->restore();
        $this->assertDatabaseHas('patients', ['id' => $patient['patient']->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $patient['user']->id, 'deleted_at' => null]);
    }
}
