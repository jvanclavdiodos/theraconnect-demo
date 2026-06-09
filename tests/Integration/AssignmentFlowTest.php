<?php

namespace Tests\Integration;

use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AssignmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_resubmission_after_review_is_blocked(): void
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

        // Patient submits, clinician reviews
        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => 'First attempt.',
            ])
            ->assertStatus(201);

        Submission::where('assignment_id', $assignment->id)
            ->update(['status' => 'reviewed', 'reviewed_at' => now()]);

        // Re-submission must be rejected and the review preserved
        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => 'Trying to change it after review.',
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'status' => 'reviewed',
            'content' => 'First attempt.',
        ]);
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

    public function test_clinician_worksheet_stored_privately_and_exposed_to_owner(): void
    {
        Storage::fake('local');

        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        // Clinician attaches a worksheet (exercise the service directly — web routes need a session).
        $assignment = app(AssignmentService::class)->create(
            [
                'clinician_id' => $clinician['clinician']->id,
                'patient_id' => $patient['patient']->id,
                'title' => 'CBT Thought Record',
            ],
            UploadedFile::fake()->create('worksheet.pdf', 200, 'application/pdf'),
        );

        // File landed on the private disk under assignments/, original name recorded.
        Storage::disk('local')->assertExists($assignment->attachment_path);
        $this->assertStringStartsWith('assignments/', $assignment->attachment_path);
        $this->assertSame('worksheet.pdf', $assignment->attachment_name);

        // Patient sees an authenticated download URL in the API payload.
        $this->withHeaders($this->apiHeaders($token))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.attachment_name', 'worksheet.pdf')
            ->assertJsonPath('data.attachment_url', url("/api/v1/assignments/{$assignment->id}/worksheet"));

        // Owner can download it.
        $this->withHeaders($this->apiHeaders($token))
            ->get("/api/v1/assignments/{$assignment->id}/worksheet")
            ->assertStatus(200)
            ->assertDownload('worksheet.pdf');
    }

    public function test_patient_cannot_download_another_patients_worksheet(): void
    {
        Storage::fake('local');

        $clinician = $this->createClinician();
        $owner = $this->createPatient('owner@test.com');
        $intruder = $this->createPatient('intruder@test.com');

        $assignment = app(AssignmentService::class)->create(
            [
                'clinician_id' => $clinician['clinician']->id,
                'patient_id' => $owner['patient']->id,
                'title' => 'Private Worksheet',
            ],
            UploadedFile::fake()->create('worksheet.pdf', 50, 'application/pdf'),
        );

        $this->withHeaders($this->apiHeaders($this->getApiToken($intruder['user'])))
            ->get("/api/v1/assignments/{$assignment->id}/worksheet")
            ->assertStatus(403);
    }

    public function test_no_worksheet_returns_null_attachment_url(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'No attachment',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.attachment_url', null)
            ->assertJsonPath('data.attachment_name', null);
    }
}
