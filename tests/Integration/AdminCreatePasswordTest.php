<?php

namespace Tests\Integration;

use Tests\TestCase;

/**
 * The StrongPassword rule also guards admin-provisioned accounts (create
 * patient / create clinician), not just self-registration.
 */
class AdminCreatePasswordTest extends TestCase
{
    public function test_admin_create_patient_rejects_weak_password(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->from(route('patients.create'))
            ->post(route('patients.store'), [
                'name' => 'New Patient',
                'email' => 'weakpatient@test.com',
                'password' => 'weakpassword', // no uppercase, no digit
            ])->assertRedirect(route('patients.create'))->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weakpatient@test.com']);
    }

    public function test_admin_create_patient_accepts_strong_password(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->post(route('patients.store'), [
                'name' => 'Good Patient',
                'email' => 'goodpatient@test.com',
                'password' => 'Str0ngPass',
            ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'goodpatient@test.com']);
    }

    public function test_admin_create_clinician_rejects_weak_password(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->from(route('clinicians.create'))
            ->post(route('clinicians.store'), [
                'name' => 'Dr. Weak',
                'email' => 'weakclin@test.com',
                'password' => 'weakpassword',
            ])->assertRedirect(route('clinicians.create'))->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weakclin@test.com']);
    }
}
