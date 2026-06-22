<?php

namespace Tests\Integration;

use App\Models\TherapyGoal;
use Tests\TestCase;

class GoalTest extends TestCase
{
    private function goalFor($clinician, $patient, string $status = 'active'): TherapyGoal
    {
        return TherapyGoal::create([
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
            'description' => 'Attend a social event without leaving early',
            'status' => $status,
        ]);
    }

    public function test_clinician_creates_a_goal_for_a_caseload_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('goal-create@test.com');
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $this->actingAs($clinician['user'], 'web')
            ->post("/patients/{$patient['patient']->id}/goals", [
                'description' => 'Practice grounding daily',
                'target_date' => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect(route('patients.progress', $patient['patient']));

        $this->assertDatabaseHas('therapy_goals', [
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'description' => 'Practice grounding daily',
            'status' => 'active',
        ]);
    }

    public function test_clinician_cannot_create_a_goal_off_caseload(): void
    {
        $clinician = $this->createClinician();
        $other = $this->createPatient('goal-offcase@test.com'); // unassigned

        $this->actingAs($clinician['user'], 'web')
            ->post("/patients/{$other['patient']->id}/goals", ['description' => 'X'])
            ->assertForbidden();

        $this->assertDatabaseCount('therapy_goals', 0);
    }

    public function test_clinician_rates_a_goal_with_gas_and_marks_it_met(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('goal-rate@test.com');
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);
        $goal = $this->goalFor($clinician['clinician'], $patient['patient']);

        $this->actingAs($clinician['user'], 'web')
            ->post("/goals/{$goal->id}/ratings", ['rating' => 2, 'note' => 'Did great'])
            ->assertRedirect(route('patients.progress', $patient['patient']));

        $this->assertDatabaseHas('goal_ratings', [
            'therapy_goal_id' => $goal->id,
            'rating' => 2,
            'note' => 'Did great',
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->patch("/goals/{$goal->id}/status", ['status' => 'met'])
            ->assertRedirect(route('patients.progress', $patient['patient']));

        $this->assertSame('met', $goal->fresh()->status);
    }

    public function test_gas_rating_out_of_range_is_rejected(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('goal-badrate@test.com');
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);
        $goal = $this->goalFor($clinician['clinician'], $patient['patient']);

        $this->actingAs($clinician['user'], 'web')
            ->post("/goals/{$goal->id}/ratings", ['rating' => 3]) // valid GAS is -2..2
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('goal_ratings', 0);
    }

    public function test_non_caseload_clinician_cannot_rate_a_goal(): void
    {
        $owner = $this->createClinician();
        $patient = $this->createPatient('goal-guard@test.com');
        $patient['patient']->update(['assigned_clinician_id' => $owner['clinician']->id]);
        $goal = $this->goalFor($owner['clinician'], $patient['patient']);

        // A different clinician (the patient is not on their caseload).
        $intruder = User_makeClinician();

        $this->actingAs($intruder, 'web')
            ->post("/goals/{$goal->id}/ratings", ['rating' => 1])
            ->assertForbidden();
    }

    public function test_patient_reads_own_goals_with_latest_rating(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('goal-read@test.com');
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $active = $this->goalFor($clinician['clinician'], $patient['patient'], 'active');
        $active->ratings()->create(['rating' => -1, 'note' => 'first']);
        $active->ratings()->create(['rating' => 1, 'note' => 'better']);
        $this->goalFor($clinician['clinician'], $patient['patient'], 'met');
        $this->goalFor($clinician['clinician'], $patient['patient'], 'dropped'); // excluded

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->getJson('/api/v1/goals')
            ->assertOk()
            ->assertJsonCount(2, 'data') // active + met, not dropped
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.latest_rating.rating', 1); // most recent
    }

    public function test_patient_cannot_see_another_patients_goals(): void
    {
        $clinician = $this->createClinician();
        $owner = $this->createPatient('goal-owner@test.com');
        $owner['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);
        $this->goalFor($clinician['clinician'], $owner['patient']);

        $intruder = $this->createPatient('goal-intruder@test.com');

        $this->withHeaders($this->apiHeaders($this->getApiToken($intruder['user'])))
            ->getJson('/api/v1/goals')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

/**
 * A second clinician (the default createClinician() uses fixed unique fields,
 * so a distinct one is built inline here).
 */
function User_makeClinician(): \App\Models\User
{
    $user = \App\Models\User::create([
        'name' => 'Dr. Other',
        'email' => 'other-clinician@test.com',
        'password' => 'password',
        'role' => 'clinician',
    ]);
    \App\Models\Clinician::create([
        'user_id' => $user->id,
        'license_no' => 'LIC-OTHER-002',
        'specialization' => 'Testing',
        'contact_no' => '555-0199',
    ]);
    return $user;
}
