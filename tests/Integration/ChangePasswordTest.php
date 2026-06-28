<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    // ── API (patient mobile) ────────────────────────────────────────────────

    public function test_patient_can_change_password_via_api(): void
    {
        $patient = $this->createPatient('cp@test.com'); // seeded password: "password"
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertStatus(204);

        // New password works, old one no longer does.
        $this->postJson('/api/v1/login', ['email' => 'cp@test.com', 'password' => 'NewPass123'])
            ->assertStatus(200);
        $this->postJson('/api/v1/login', ['email' => 'cp@test.com', 'password' => 'password'])
            ->assertStatus(401);
    }

    public function test_api_change_password_rejects_wrong_current(): void
    {
        $patient = $this->createPatient('cp2@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'not-my-password',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $patient['user']->fresh()->password));
    }

    public function test_api_change_password_rejects_weak_new(): void
    {
        $patient = $this->createPatient('cp3@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'alllowercase', // no uppercase, no digit
                'password_confirmation' => 'alllowercase',
            ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_api_change_password_rejects_mismatched_confirmation(): void
    {
        $patient = $this->createPatient('cp4@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewPass123',
                'password_confirmation' => 'Different123',
            ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_api_change_password_revokes_other_tokens(): void
    {
        $patient = $this->createPatient('cp5@test.com');
        $otherToken = $this->getApiToken($patient['user']); // a "different device"
        $currentToken = $this->getApiToken($patient['user']);
        $otherId = (int) explode('|', $otherToken)[0];
        $currentId = (int) explode('|', $currentToken)[0];

        $this->withHeaders($this->apiHeaders($currentToken))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertStatus(204);

        // The token that made the change survives; the other device's is revoked.
        // (Asserted at the DB level — a follow-up authenticated request in the same
        // test would hit Laravel's cached guard user and mask the revocation.)
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentId]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherId]);
    }

    // ── Web (staff dashboard) ───────────────────────────────────────────────

    public function test_staff_can_change_password_via_web(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->from(route('account.edit'))
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertRedirect();

        $this->assertTrue(Hash::check('NewPass123', $clinician['user']->fresh()->password));
    }

    public function test_staff_change_password_rejects_wrong_current(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->from(route('account.edit'))
            ->put(route('account.password.update'), [
                'current_password' => 'wrong',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertRedirect(route('account.edit'))->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $clinician['user']->fresh()->password));
    }

    // ── Web (patient portal) ────────────────────────────────────────────────

    public function test_patient_can_change_password_via_portal(): void
    {
        $patient = $this->createPatient('pp@test.com');

        $this->actingAs($patient['user'], 'web')
            ->from(route('portal.profile.edit'))
            ->put(route('portal.profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertRedirect(route('portal.profile.show'));

        $this->assertTrue(Hash::check('NewPass123', $patient['user']->fresh()->password));
    }
}
