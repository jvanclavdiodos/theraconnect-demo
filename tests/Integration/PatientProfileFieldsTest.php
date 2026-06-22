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
            'personal_issues' => 'Anxiety around exams.',
        ]);
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
