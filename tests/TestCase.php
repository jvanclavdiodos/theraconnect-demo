<?php

namespace Tests;

use App\Models\Clinician;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    protected function createAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $admin->save();

        return $admin;
    }

    protected function createClinician(): array
    {
        $user = User::create([
            'name' => 'Dr. Test',
            'email' => 'clinician@test.com',
            'password' => 'password',
            'role' => 'clinician',
        ]);

        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-TEST-001',
            'specialization' => 'Testing',
            'contact_no' => '555-0100',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    protected function createPatient(string $email = 'patient@test.com'): array
    {
        $user = User::create([
            'name' => 'Jane Patient',
            'email' => $email,
            'password' => 'password',
            'role' => 'patient',
        ]);

        $patient = Patient::create([
            'user_id' => $user->id,
            'date_of_birth' => '1995-03-15',
            'contact_no' => '555-0200',
            'address' => '123 Test St',
            'emergency_contact' => 'John Doe - 555-0300',
        ]);

        return ['user' => $user, 'patient' => $patient];
    }

    protected function getApiToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    protected function apiHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
