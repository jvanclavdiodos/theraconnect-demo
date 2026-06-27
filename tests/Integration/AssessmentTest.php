<?php

namespace Tests\Integration;

use App\Models\Assessment;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    public function test_clinician_assigns_a_questionnaire_and_notifies_the_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('asmt-assign@test.com');
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $this->actingAs($clinician['user'], 'web')
            ->post("/patients/{$patient['patient']->id}/assessments", ['instrument' => 'phq9'])
            ->assertRedirect(route('patients.progress', $patient['patient']));

        $this->assertDatabaseHas('assessments', [
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'assessment_assigned',
        ]);
    }

    public function test_clinician_cannot_assign_to_a_patient_off_their_caseload(): void
    {
        $clinician = $this->createClinician();
        $other = $this->createPatient('asmt-offcaseload@test.com'); // no assigned_clinician_id

        $this->actingAs($clinician['user'], 'web')
            ->post("/patients/{$other['patient']->id}/assessments", ['instrument' => 'gad7'])
            ->assertForbidden();

        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_patient_submits_phq9_and_score_is_summed_with_severity(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('asmt-submit@test.com');
        $token = $this->getApiToken($patient['user']);

        $assessment = Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'pending',
        ]);

        // 9 items, all "Nearly every day" (3) = 27 → Severe.
        $responses = array_fill(0, 9, 3);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", ['responses' => $responses])
            ->assertOk()
            ->assertJsonPath('data.score', 27)
            ->assertJsonPath('data.severity', 'Severe')
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'score' => 27,
            'status' => 'completed',
        ]);
    }

    public function test_phq9_moderate_band_boundary(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('asmt-band@test.com');
        $token = $this->getApiToken($patient['user']);

        $assessment = Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'pending',
        ]);

        // Score 10 → "Moderate" lower boundary.
        $responses = [3, 3, 3, 1, 0, 0, 0, 0, 0];

        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", ['responses' => $responses])
            ->assertOk()
            ->assertJsonPath('data.score', 10)
            ->assertJsonPath('data.severity', 'Moderate');
    }

    public function test_bad_response_length_or_range_is_rejected(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('asmt-bad@test.com');
        $token = $this->getApiToken($patient['user']);

        $assessment = Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'gad7',
            'status' => 'pending',
        ]);

        // GAD-7 expects 7 answers — send 5.
        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", ['responses' => [0, 1, 2, 3, 0]])
            ->assertStatus(422);

        // Correct length, out-of-range value.
        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", ['responses' => [0, 1, 2, 3, 0, 1, 9]])
            ->assertStatus(422);

        $this->assertSame('pending', $assessment->fresh()->status);
    }

    public function test_double_submit_is_rejected_with_conflict(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('asmt-double@test.com');
        $token = $this->getApiToken($patient['user']);

        $assessment = Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'gad7',
            'status' => 'completed',
            'score' => 5,
            'responses' => [1, 1, 1, 1, 1, 0, 0],
            'completed_at' => now(),
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", ['responses' => array_fill(0, 7, 0)])
            ->assertStatus(409);
    }

    public function test_patient_cannot_access_another_patients_assessment(): void
    {
        $clinician = $this->createClinician();
        $owner = $this->createPatient('asmt-owner@test.com');
        $intruder = $this->createPatient('asmt-intruder@test.com');
        $token = $this->getApiToken($intruder['user']);

        $assessment = Assessment::create([
            'patient_id' => $owner['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'pending',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson("/api/v1/assessments/{$assessment->id}")
            ->assertForbidden();

        $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/assessments/{$assessment->id}/submit", ['responses' => array_fill(0, 9, 0)])
            ->assertForbidden();
    }

    public function test_index_lists_patients_own_assessments_pending_first(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('asmt-index@test.com');
        $token = $this->getApiToken($patient['user']);

        Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'completed',
            'score' => 4,
            'responses' => array_fill(0, 9, 0),
            'completed_at' => now()->subDay(),
        ]);
        Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'gad7',
            'status' => 'pending',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/assessments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'pending'); // pending sorts first
    }

    public function test_show_returns_items_and_options_to_render_the_form(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('asmt-show@test.com');
        $token = $this->getApiToken($patient['user']);

        $assessment = Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'gad7',
            'status' => 'pending',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson("/api/v1/assessments/{$assessment->id}")
            ->assertOk()
            ->assertJsonCount(7, 'data.items')
            ->assertJsonCount(4, 'data.options')
            ->assertJsonPath('data.instrument', 'gad7');
    }
}
