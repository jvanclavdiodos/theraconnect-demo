<?php

namespace Tests\Integration;

use App\Models\Assignment;
use App\Models\Clinician;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionPreviewTest extends TestCase
{
    private function makeClinician(string $email): array
    {
        $user = User::create([
            'name' => 'Dr. '.ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-'.strtoupper(substr(md5($email), 0, 6)),
            'specialization' => 'CBT',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    /** Assignment by $clinician for $patient, with a submission file on the fake disk. */
    private function scenario(array $clinician, array $patient): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('submissions/answer.txt', 'hello world');

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Homework 1',
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'patient_id' => $patient['patient']->id,
            'content' => 'My written answer',
            'file_path' => 'submissions/answer.txt',
            'original_name' => 'answer.txt',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return ['assignment' => $assignment, 'submission' => $submission];
    }

    public function test_clinician_can_preview_submission_inline(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $s = $this->scenario($clinician, $patient);

        $response = $this->actingAs($clinician['user'], 'web')
            ->get(route('submissions.preview', $s['submission']))
            ->assertStatus(200);

        $this->assertStringContainsString('inline', strtolower($response->headers->get('content-disposition') ?? ''));
    }

    public function test_other_clinician_cannot_preview_submission(): void
    {
        $author = $this->createClinician();
        $patient = $this->createPatient();
        $s = $this->scenario($author, $patient);

        $other = $this->makeClinician('other@test.com');

        $this->actingAs($other['user'], 'web')
            ->get(route('submissions.preview', $s['submission']))
            ->assertStatus(403);
    }

    public function test_submissions_page_has_maximize_modal(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $s = $this->scenario($clinician, $patient);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('assignments.submissions', $s['assignment']))
            ->assertStatus(200)
            ->assertSee('subModal'.$s['submission']->id)
            ->assertSee('View');
    }

    public function test_api_assignment_exposes_submission(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $s = $this->scenario($clinician, $patient);

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->getJson("/api/v1/assignments/{$s['assignment']->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.submission.content', 'My written answer')
            ->assertJsonPath('data.submission.kind', 'text')
            ->assertJsonPath('data.submission.original_name', 'answer.txt');
    }
}
