<?php

namespace Tests\Integration;

use App\Models\Assignment;
use App\Models\Submission;
use Tests\TestCase;

class AssignmentFlowTest extends TestCase
{
    public function test_patient_can_view_assignments(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        // Clinician creates assignment
        Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Daily Mood Journal',
            'description' => 'Record your mood each day for a week.',
            'due_date' => '2026-06-20 00:00:00',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/assignments');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Daily Mood Journal')
            ->assertJsonPath('data.0.clinician_name', 'Dr. Test');
    }

    public function test_patient_can_view_single_assignment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Breathing Exercise',
            'description' => 'Practice 4-7-8 breathing twice daily.',
            'due_date' => '2026-06-15 00:00:00',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson("/api/v1/assignments/{$assignment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Breathing Exercise')
            ->assertJsonPath('data.description', 'Practice 4-7-8 breathing twice daily.');
    }

    public function test_patient_can_submit_assignment_with_text(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Test Assignment',
            'description' => 'Submit your response.',
            'due_date' => '2026-06-15 00:00:00',
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => 'I completed the breathing exercises. Felt calmer afterward.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'I completed the breathing exercises. Felt calmer afterward.')
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'patient_id' => $patient['patient']->id,
            'status' => 'submitted',
        ]);
    }

    public function test_re_submission_updates_existing_row(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Test Assignment',
            'description' => 'Submit your response.',
        ]);

        // First submission
        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => 'First attempt.',
            ]);

        // Re-submission
        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => 'Updated submission with more detail.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Updated submission with more detail.');

        // Only one row should exist
        $this->assertDatabaseCount('assignment_submissions', 1);
    }

    public function test_empty_submission_is_rejected(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Test Assignment',
            'description' => 'Submit your response.',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => '',
            ])
            ->assertStatus(422);
    }
}
