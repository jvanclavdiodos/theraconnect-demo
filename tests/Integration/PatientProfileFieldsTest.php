<?php

namespace Tests\Integration;

use Tests\TestCase;

class PatientProfileFieldsTest extends TestCase
{
    public function test_admin_can_create_patient_with_profile_fields(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->post('/patients', [
                'name' => 'New Patient',
                'email' => 'newp@test.com',
                'password' => 'password123',
                'gender' => 'Female',
                'educational_attainment' => 'College',
                'employment_status' => 'Student',
                'personal_issues' => 'Anxiety around exams.',
            ])
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'gender' => 'Female',
            'educational_attainment' => 'College',
            'employment_status' => 'Student',
        ]);
        // personal_issues is encrypted at rest — verify via model
        $this->assertSame(
            'Anxiety around exams.',
            \App\Models\Patient::latest()->first()->personal_issues
        );
    }

    public function test_invalid_option_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->post('/patients', [
                'name' => 'Bad',
                'email' => 'bad@test.com',
                'password' => 'password123',
                'gender' => 'Martian',
            ])
            ->assertSessionHasErrors('gender');
    }

    public function test_patient_updates_profile_fields_via_api(): void
    {
        $patient = $this->createPatient();

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->putJson('/api/v1/profile', [
                'gender' => 'Other',
                'educational_attainment' => 'Vocational',
                'employment_status' => 'Unemployed',
                'personal_issues' => 'Sleep trouble.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.gender', 'Other')
            ->assertJsonPath('data.educational_attainment', 'Vocational')
            ->assertJsonPath('data.employment_status', 'Unemployed')
            ->assertJsonPath('data.personal_issues', 'Sleep trouble.');

        $this->assertDatabaseHas('patients', [
            'id' => $patient['patient']->id,
            'gender' => 'Other',
            'employment_status' => 'Unemployed',
        ]);
    }

    public function test_api_rejects_invalid_employment_status(): void
    {
        $patient = $this->createPatient();

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->putJson('/api/v1/profile', ['employment_status' => 'Astronaut'])
            ->assertStatus(422);
    }

    public function test_admin_can_update_and_show_displays_fields(): void
    {
        $admin = $this->createAdmin();
        $patient = $this->createPatient();

        $this->actingAs($admin, 'web')
            ->put("/patients/{$patient['patient']->id}", [
                'name' => $patient['user']->name,
                'email' => $patient['user']->email,
                'gender' => 'Male',
                'educational_attainment' => 'Postgraduate',
                'employment_status' => 'Employed',
                'personal_issues' => 'Work stress.',
            ])
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'id' => $patient['patient']->id,
            'gender' => 'Male',
            'employment_status' => 'Employed',
        ]);

        $this->actingAs($admin, 'web')
            ->get(route('patients.show', $patient['patient']))
            ->assertStatus(200)
            ->assertSee('Postgraduate')
            ->assertSee('Work stress.')
            ->assertSee('Employment Status');
    }
}
