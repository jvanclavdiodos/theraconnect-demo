<?php

namespace Tests\Integration;

use App\Models\Assignment;
use App\Models\Submission;
use Tests\TestCase;

class AssignmentNavigationBadgeTest extends TestCase
{
    public function test_clinician_badge_counts_only_unreviewed_submissions_for_own_assignments(): void
    {
        $clinician = $this->createClinicianWithEmail('badge-clinician@test.com');
        $otherClinician = $this->createClinicianWithEmail('other-clinician@test.com');
        $patient = $this->createPatient('assignment-badge-patient@test.com');

        $this->createSubmission($clinician['clinician']->id, $patient['patient']->id, 'submitted');
        $this->createSubmission($clinician['clinician']->id, $patient['patient']->id, 'reviewed');
        $this->createSubmission($otherClinician['clinician']->id, $patient['patient']->id, 'submitted');

        $response = $this->actingAs($clinician['user'])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/data-assignment-activity-count\s+data-count="1"/',
            $response->getContent()
        );
    }

    public function test_patient_badge_counts_only_their_submissions_awaiting_review(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('patient-assignment-badge@test.com');
        $otherPatient = $this->createPatient('other-assignment-badge@test.com');

        $this->createSubmission($clinician['clinician']->id, $patient['patient']->id, 'submitted');
        $this->createSubmission($clinician['clinician']->id, $patient['patient']->id, 'reviewed');
        $this->createSubmission($clinician['clinician']->id, $otherPatient['patient']->id, 'submitted');

        $response = $this->actingAs($patient['user'])
            ->get(route('portal.dashboard'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/data-assignment-activity-count\s+data-count="1"/',
            $response->getContent()
        );
    }

    public function test_assignment_badge_is_hidden_without_pending_submission_activity(): void
    {
        $patient = $this->createPatient('empty-assignment-badge@test.com');

        $response = $this->actingAs($patient['user'])
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('data-assignment-activity-count', false);

        $this->assertMatchesRegularExpression(
            '/data-assignment-activity-count\s+data-count="0"[^>]+d-none/',
            $response->getContent()
        );
    }

    private function createSubmission(int $clinicianId, int $patientId, string $status): Submission
    {
        $assignment = Assignment::create([
            'clinician_id' => $clinicianId,
            'patient_id' => $patientId,
            'title' => 'Badge assignment '.uniqid(),
        ]);

        return Submission::create([
            'assignment_id' => $assignment->id,
            'patient_id' => $patientId,
            'content' => 'Completed work',
            'status' => $status,
            'submitted_at' => now(),
            'reviewed_at' => $status === 'reviewed' ? now() : null,
        ]);
    }

    private function createClinicianWithEmail(string $email): array
    {
        $clinician = $this->createClinician();
        $clinician['user']->update(['email' => $email]);

        return $clinician;
    }
}
