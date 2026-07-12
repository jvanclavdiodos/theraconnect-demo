<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    public function test_patient_login_redirects_to_portal(): void
    {
        $this->createPatient('p@test.com');

        $this->post('/login', ['email' => 'p@test.com', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_staff_login_still_redirects_to_dashboard(): void
    {
        $clinician = $this->createClinician();

        $this->post('/login', ['email' => $clinician['user']->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
    }

    public function test_patient_can_view_portal_dashboard(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->get(route('portal.dashboard'))
            ->assertStatus(200)
            ->assertSee('overview');
    }

    public function test_patient_cannot_access_staff_dashboard(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->get('/dashboard')
            ->assertStatus(403);
    }

    public function test_staff_cannot_access_patient_portal(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->get(route('portal.dashboard'))
            ->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
    }

    public function test_registration_page_includes_the_user_agreement_modal(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('id="user-agreement-modal"', false)
            ->assertSee('id="accept-user-agreement"', false)
            ->assertSee('Object.assign(passwordField', false)
            ->assertSee('terms-revoked', false);
    }

    public function test_patient_can_self_register_and_land_in_portal(): void
    {
        $this->post('/register', [
            'name' => 'New Browser Patient',
            'email' => 'browser@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'accepted_terms' => true,
        ])->assertRedirect(route('portal.dashboard'));

        $user = User::where('email', 'browser@test.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('patient', $user->role);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(\App\Support\TermsOfService::CURRENT_VERSION, $user->terms_version);
        $this->assertNotNull($user->patient);
    }

    public function test_patient_registration_renders_the_portal_dashboard_after_redirect(): void
    {
        $this->followingRedirects()
            ->post('/register', [
                'name' => 'Redirect Patient',
                'email' => 'redirect-patient@test.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'accepted_terms' => true,
            ])
            ->assertOk()
            ->assertSee('overview');
    }

    public function test_registration_recovers_when_automatic_login_fails_after_account_creation(): void
    {
        $guestGuard = \Mockery::mock(StatefulGuard::class);
        $guestGuard->shouldReceive('check')->once()->andReturnFalse();

        Auth::shouldReceive('guard')
            ->with(null)
            ->once()
            ->andReturn($guestGuard);
        Auth::shouldReceive('login')
            ->once()
            ->andThrow(new RuntimeException('Session storage unavailable.'));

        $this->post('/register', [
            'name' => 'Fallback Patient',
            'email' => 'fallback-patient@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'accepted_terms' => true,
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account was created. Please sign in to continue.');

        $this->assertDatabaseHas('users', [
            'email' => 'fallback-patient@test.com',
            'role' => 'patient',
        ]);
    }

    public function test_web_registration_captures_profile_fields(): void
    {
        $this->post('/register', [
            'name' => 'Web Profile Patient',
            'email' => 'webprofile@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'accepted_terms' => true,
            'gender' => 'Male',
            'educational_attainment' => 'Postgraduate',
            'employment_status' => 'Employed',
            'personal_issues' => 'Work-related anxiety.',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertDatabaseHas('patients', [
            'gender' => 'Male',
            'educational_attainment' => 'Postgraduate',
            'employment_status' => 'Employed',
        ]);
        $patient = User::where('email', 'webprofile@test.com')->first()->patient;
        $this->assertSame('Work-related anxiety.', $patient->personal_issues);
    }

    public function test_web_registration_requires_critical_fields(): void
    {
        $this->from('/register')->post('/register', [])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['name', 'email', 'password', 'accepted_terms']);
    }

    public function test_web_registration_requires_terms_acceptance(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'No Consent',
            'email' => 'web-noconsent@test.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'accepted_terms' => '0',
        ])->assertRedirect('/register')->assertSessionHasErrors('accepted_terms');

        $this->assertDatabaseMissing('users', ['email' => 'web-noconsent@test.com']);
    }

    public function test_web_registration_rejects_weak_password(): void
    {
        // No uppercase, no digit — fails the StrongPassword rule.
        $this->from('/register')->post('/register', [
            'name' => 'Weak Web',
            'email' => 'weakweb@test.com',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertRedirect('/register')->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weakweb@test.com']);
    }
}
