<?php

namespace Tests\Integration;

use App\Models\ActivityLog;
use App\Models\Clinician;
use App\Models\User;
use Tests\TestCase;

class UserGuideAndPatientExportTest extends TestCase
{
    public function test_guides_are_available_only_to_the_supported_role_and_client(): void
    {
        $patient = $this->createPatient();
        $clinician = $this->createClinician();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-guide@test.com', 'password' => 'password', 'role' => 'admin']);

        $this->actingAs($patient['user'])->get('/portal/guide')->assertOk()->assertSee('Book and manage appointments');
        $this->actingAs($patient['user'])->get('/portal')->assertOk()->assertSee('Open Joy assistant');
        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))->getJson('/api/v1/guide')->assertOk()->assertJsonPath('data.version', '1.0');
        $this->actingAs($clinician['user'])->get('/guide')->assertOk()->assertSee('Review appointment requests');
        $this->actingAs($admin)->get('/guide')->assertForbidden();
    }

    public function test_only_an_assigned_clinician_can_export_a_patient_record(): void
    {
        $patient = $this->createPatient();
        $assigned = $this->createClinician();
        $unassignedUser = User::create(['name' => 'Dr. Other', 'email' => 'other-clinician@test.com', 'password' => 'password', 'role' => 'clinician']);
        $unassigned = ['user' => $unassignedUser, 'clinician' => Clinician::create(['user_id' => $unassignedUser->id, 'license_no' => 'LIC-OTHER', 'specialization' => 'Testing'])];
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-export@test.com', 'password' => 'password', 'role' => 'admin']);
        $patient['patient']->assignClinician($assigned['clinician']);

        $response = $this->actingAs($assigned['user'])->get(route('patients.record.pdf', $patient['patient']));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertTrue(ActivityLog::where('event', 'patient.record_exported')->where('target_id', $patient['patient']->id)->exists());

        $this->actingAs($unassigned['user'])->get(route('patients.record.pdf', $patient['patient']))->assertForbidden();
        $this->actingAs($admin)->get(route('patients.record.pdf', $patient['patient']))->assertForbidden();
        $this->actingAs($patient['user'])->get(route('patients.record.pdf', $patient['patient']))->assertForbidden();
    }
}
