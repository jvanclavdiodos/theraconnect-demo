<?php

namespace Tests\Integration;

use Tests\TestCase;

class AvatarAuthorizationTest extends TestCase
{
    public function test_clinician_cannot_view_off_caseload_patient_avatar(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient(); // not assigned to this clinician

        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $patient['user']))
            ->assertStatus(403);
    }

    public function test_clinician_can_view_own_avatar(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $clinician['user']))
            ->assertStatus(200);
    }

    public function test_clinician_can_view_caseload_patient_avatar(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $patient['user']))
            ->assertStatus(200);
    }

    public function test_admin_can_view_any_avatar(): void
    {
        $admin = $this->createAdmin();
        $patient = $this->createPatient();

        $this->actingAs($admin, 'web')
            ->get(route('avatars.show', $patient['user']))
            ->assertStatus(200);
    }
}
