<?php

namespace Tests\Integration;

use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    public function test_patient_registration_returns_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'New Patient',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'contact_no' => '555-1111',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ])
            ->assertJsonPath('data.user.name', 'New Patient')
            ->assertJsonPath('data.user.role', 'patient');

        $this->assertNotNull($response->json('data.token'));

        // Verify patient profile was created
        $this->assertDatabaseHas('patients', [
            'contact_no' => '555-1111',
        ]);
    }

    public function test_patient_login_returns_token(): void
    {
        $patient = $this->createPatient('login@test.com');

        $response = $this->postJson('/api/v1/login', [
            'email' => 'login@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ])
            ->assertJsonPath('data.user.email', 'login@test.com')
            ->assertJsonPath('data.user.role', 'patient');

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_invalid_credentials_return_401(): void
    {
        $patient = $this->createPatient('bad@test.com');

        $this->postJson('/api/v1/login', [
            'email' => 'bad@test.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    public function test_me_endpoint_returns_user_and_patient_profile(): void
    {
        $patient = $this->createPatient('me@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['user', 'patient_profile'],
            ])
            ->assertJsonPath('data.user.email', 'me@test.com')
            ->assertJsonPath('data.patient_profile.contact_no', '555-0200');

        // Patients should NOT see clinical notes
        $this->assertArrayNotHasKey('notes', $response->json('data.patient_profile'));
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
        $this->getJson('/api/v1/appointments')->assertStatus(401);
        $this->getJson('/api/v1/assignments')->assertStatus(401);
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_non_patient_role_is_blocked_from_api(): void
    {
        $admin = $this->createAdmin();
        $token = $this->getApiToken($admin);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/me')
            ->assertStatus(403);
    }

    public function test_logout_revokes_token(): void
    {
        $patient = $this->createPatient('logout@test.com');
        $token = $this->getApiToken($patient['user']);

        // Token works before logout
        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/me')
            ->assertStatus(200);

        // Verify token exists in database
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Logout
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/logout')
            ->assertStatus(204);

        // Token record deleted from database
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Token re-created to verify auth still works
        $newToken = $this->getApiToken($patient['user']);
        $this->assertNotNull($newToken);
    }

    public function test_profile_update_works(): void
    {
        $patient = $this->createPatient('update@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->putJson('/api/v1/profile', [
                'contact_no' => '555-9999',
                'address' => '456 Updated St',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.contact_no', '555-9999')
            ->assertJsonPath('data.address', '456 Updated St');
    }

    /**
     * The JSON API is the patient mobile-app surface — clinicians/admins must
     * NOT be able to mint bearer tokens via /api/v1/login (mirrors the
     * AuthenticatedSessionController which blocks patients from the web
     * login). Prevents personal_access_tokens pollution and account
     * enumeration via the API.
     */
    public function test_clinician_cannot_get_api_token_via_login(): void
    {
        $clinician = $this->createClinician();

        $this->postJson('/api/v1/login', [
            'email' => $clinician['user']->email,
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account is not permitted to use the mobile app. Please use the web dashboard.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_cannot_get_api_token_via_login(): void
    {
        $admin = $this->createAdmin();

        $this->postJson('/api/v1/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertStatus(403);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
