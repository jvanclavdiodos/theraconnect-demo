<?php

namespace Tests\Integration;

use Tests\TestCase;

class PatientNoteApiTest extends TestCase
{
    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/notes')->assertStatus(401);
    }

    public function test_patient_sees_only_shared_notes(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $patient['patient']->clinicianNotes()->create([
            'clinician_id' => $clinician['clinician']->id,
            'title' => 'Prescription',
            'body' => 'Sertraline 50mg',
            'is_shared' => true,
        ]);
        $patient['patient']->clinicianNotes()->create([
            'clinician_id' => $clinician['clinician']->id,
            'body' => 'Private clinical observation',
            'is_shared' => false,
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->getJson('/api/v1/notes')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Prescription')
            ->assertJsonPath('data.0.clinician_name', $clinician['user']->name);

        $this->assertStringNotContainsString('Private clinical observation', $response->getContent());
    }

    public function test_patient_only_sees_their_own_shared_notes(): void
    {
        $clinician = $this->createClinician();
        $mine = $this->createPatient('mine@test.com');
        $other = $this->createPatient('other@test.com');
        $mine['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);
        $other['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $other['patient']->clinicianNotes()->create([
            'clinician_id' => $clinician['clinician']->id,
            'body' => 'Someone else',
            'is_shared' => true,
        ]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($mine['user'])))
            ->getJson('/api/v1/notes')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
