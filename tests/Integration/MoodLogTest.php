<?php

namespace Tests\Integration;

use Tests\TestCase;

class MoodLogTest extends TestCase
{
    public function test_patient_logs_a_mood_check_in(): void
    {
        $patient = $this->createPatient('mood-log@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/mood-logs', ['score' => 7, 'note' => 'Feeling steadier today'])
            ->assertCreated()
            ->assertJsonPath('data.score', 7)
            ->assertJsonPath('data.note', 'Feeling steadier today');

        $this->assertDatabaseHas('mood_logs', [
            'patient_id' => $patient['patient']->id,
            'score' => 7,
            'note' => 'Feeling steadier today',
        ]);
    }

    public function test_note_is_optional(): void
    {
        $patient = $this->createPatient('mood-nonote@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/mood-logs', ['score' => 3])
            ->assertCreated()
            ->assertJsonPath('data.score', 3)
            ->assertJsonPath('data.note', null);
    }

    public function test_score_out_of_range_is_rejected(): void
    {
        $patient = $this->createPatient('mood-range@test.com');
        $token = $this->getApiToken($patient['user']);

        foreach ([0, 11, -1] as $bad) {
            $this->withHeaders($this->apiHeaders($token))
                ->postJson('/api/v1/mood-logs', ['score' => $bad])
                ->assertStatus(422);
        }

        $this->assertDatabaseCount('mood_logs', 0);
    }

    public function test_index_returns_only_the_patients_own_logs_newest_first(): void
    {
        $me = $this->createPatient('mood-me@test.com');
        $other = $this->createPatient('mood-other@test.com');

        $me['patient']->moodLogs()->create(['score' => 4, 'created_at' => now()->subDay()]);
        $me['patient']->moodLogs()->create(['score' => 8, 'created_at' => now()]);
        $other['patient']->moodLogs()->create(['score' => 1]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($me['user'])))
            ->getJson('/api/v1/mood-logs')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.score', 8); // newest first
    }
}
