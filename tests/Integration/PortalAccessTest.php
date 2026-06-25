<?php

namespace Tests\Integration;

use App\Models\User;
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

    public function test_patient_can_self_register_and_land_in_portal(): void
    {
        $this->post('/register', [
            'name' => 'New Browser Patient',
            'email' => 'browser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('portal.dashboard'));

        $user = User::where('email', 'browser@test.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('patient', $user->role);
        $this->assertNotNull($user->patient);
    }

    public function test_web_registration_captures_profile_fields(): void
    {
        $this->post('/register', [
            'name' => 'Web Profile Patient',
            'email' => 'webprofile@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
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
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }
}
