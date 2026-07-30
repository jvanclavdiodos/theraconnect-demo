<?php

namespace Tests\Integration;

use App\Models\MoodLog;
use App\Support\MoodLogDates;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoodLogTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_patient_logs_a_mood_check_in(): void
    {
        $patient = $this->createPatient('mood-log@test.com');
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/mood-logs', ['score' => 7, 'note' => 'Feeling steadier today'])
            ->assertCreated()
            ->assertJsonPath('data.score', 7)
            ->assertJsonPath('data.note', 'Feeling steadier today')
            ->assertJsonPath('data.logged_on', MoodLogDates::today());

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

        $me['patient']->moodLogs()->create([
            'score' => 4,
            'logged_on' => now()->subDay()->toDateString(),
            'created_at' => now()->subDay(),
        ]);
        $me['patient']->moodLogs()->create([
            'score' => 8,
            'logged_on' => now()->toDateString(),
            'created_at' => now(),
        ]);
        $other['patient']->moodLogs()->create([
            'score' => 1,
            'logged_on' => now()->toDateString(),
        ]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($me['user'])))
            ->getJson('/api/v1/mood-logs')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.score', 8)
            ->assertJsonPath('meta.today', MoodLogDates::today())
            ->assertJsonPath('meta.today_completed', true);

        $this->actingAs($me['user'])
            ->get(route('portal.mood.index'))
            ->assertOk()
            ->assertSee('8')
            ->assertDontSee('1/10', false)
            ->assertViewHas('logs', fn ($logs) => $logs->every(
                fn ($log) => $log->patient_id === $me['patient']->id
            ));
    }

    public function test_second_api_check_in_on_the_same_manila_date_returns_conflict(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 16:30:00', 'UTC'));
        $patient = $this->createPatient('mood-duplicate@test.com');
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/mood-logs', ['score' => 6])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/mood-logs', ['score' => 9])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Today\'s mood check-in has already been completed.')
            ->assertJsonPath('data.id', $first)
            ->assertJsonPath('data.score', 6);

        $this->assertSame(1, $patient['patient']->moodLogs()->count());
    }

    public function test_database_constraint_prevents_duplicate_patient_date_rows(): void
    {
        $patient = $this->createPatient('mood-constraint@test.com');
        $date = '2026-08-11';

        MoodLog::create([
            'patient_id' => $patient['patient']->id,
            'score' => 5,
            'logged_on' => $date,
        ]);

        $this->expectException(QueryException::class);

        MoodLog::create([
            'patient_id' => $patient['patient']->id,
            'score' => 7,
            'logged_on' => $date,
        ]);
    }

    public function test_manila_today_is_authoritative_across_the_utc_date_boundary(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 15:59:00', 'UTC'));
        $beforeMidnight = $this->createPatient('mood-before-midnight@test.com');

        $this->withHeaders($this->apiHeaders($this->getApiToken($beforeMidnight['user'])))
            ->postJson('/api/v1/mood-logs', ['score' => 6])
            ->assertCreated()
            ->assertJsonPath('data.logged_on', '2026-08-11');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 16:01:00', 'UTC'));
        $afterMidnight = $this->createPatient('mood-after-midnight@test.com');
        $headers = $this->apiHeaders($this->getApiToken($afterMidnight['user']));

        $this->withHeaders($headers)
            ->postJson('/api/v1/mood-logs', ['score' => 8])
            ->assertCreated()
            ->assertJsonPath('data.logged_on', '2026-08-12');

        $this->withHeaders($headers)
            ->getJson('/api/v1/mood-logs')
            ->assertOk()
            ->assertJsonPath('meta.today', '2026-08-12')
            ->assertJsonPath('meta.today_completed', true)
            ->assertJsonPath('meta.today_log.logged_on', '2026-08-12');
    }

    public function test_legacy_timestamp_conversion_respects_the_manila_cutover(): void
    {
        $this->assertSame(
            '2026-06-27',
            MoodLogDates::fromLegacyTimestamp('2026-06-26 17:30:00')
        );
        $this->assertSame(
            '2026-06-27',
            MoodLogDates::fromLegacyTimestamp('2026-06-27 02:30:00')
        );
        $this->assertSame(
            '2026-06-26',
            MoodLogDates::fromLegacyTimestamp('2026-06-26 10:00:00')
        );
    }

    public function test_portal_and_api_share_the_same_daily_completion_rule(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 09:00:00', 'Asia/Manila'));
        $patient = $this->createPatient('mood-surfaces@test.com');

        $this->actingAs($patient['user'])
            ->post(route('portal.mood.store'), ['score' => 7, 'note' => 'Steady'])
            ->assertRedirect(route('portal.mood.index'));

        $this->actingAs($patient['user'])
            ->get(route('portal.mood.index'))
            ->assertOk()
            ->assertSee('Today\'s check-in is complete', false)
            ->assertViewHas('todayLog', fn ($log) => $log?->score === 7);

        $this->actingAs($patient['user'], 'sanctum')
            ->postJson('/api/v1/mood-logs', ['score' => 4])
            ->assertStatus(409);

        $this->assertSame(1, $patient['patient']->moodLogs()->count());
    }

    public function test_clinician_progress_and_record_export_view_use_logged_on_and_score(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('mood-consumers@test.com');
        $patient['patient']->assignClinician($clinician['clinician']->id);

        $log = MoodLog::create([
            'patient_id' => $patient['patient']->id,
            'score' => 9,
            'note' => 'A much better day',
            'logged_on' => '2026-08-09',
            'created_at' => '2026-08-10 01:00:00',
        ]);

        $this->actingAs($clinician['user'])
            ->get(route('patients.progress', $patient['patient']))
            ->assertOk()
            ->assertSee('Aug 09');

        $patient['patient']->load('moodLogs');
        $html = view('patients.record-pdf', ['patient' => $patient['patient']])->render();

        $this->assertStringContainsString('Aug 09, 2026', $html);
        $this->assertStringContainsString('9/10', $html);
        $this->assertSame('2026-08-09', $log->fresh()->logged_on->toDateString());
    }

    public function test_migration_archives_legacy_duplicates_and_retains_the_newest_entry(): void
    {
        $patient = $this->createPatient('mood-migration@test.com');
        $migration = require database_path('migrations/2026_07_30_000002_add_logged_on_to_mood_logs.php');
        $migration->down();

        $olderId = DB::table('mood_logs')->insertGetId([
            'patient_id' => $patient['patient']->id,
            'score' => 4,
            'note' => 'Earlier response',
            'created_at' => '2026-06-26 14:00:00',
            'updated_at' => '2026-06-26 14:00:00',
        ]);
        $newerId = DB::table('mood_logs')->insertGetId([
            'patient_id' => $patient['patient']->id,
            'score' => 7,
            'note' => 'Final response',
            'created_at' => '2026-06-26 15:00:00',
            'updated_at' => '2026-06-26 15:00:00',
        ]);

        $migration->up();

        $this->assertDatabaseHas('mood_logs', [
            'id' => $newerId,
            'patient_id' => $patient['patient']->id,
            'score' => 7,
            'logged_on' => '2026-06-26',
        ]);
        $this->assertDatabaseMissing('mood_logs', ['id' => $olderId]);
        $this->assertDatabaseHas('mood_log_archives', [
            'original_mood_log_id' => $olderId,
            'retained_mood_log_id' => $newerId,
            'patient_id' => $patient['patient']->id,
            'score' => 4,
            'logged_on' => '2026-06-26',
            'reason' => 'duplicate_daily_cleanup',
        ]);
    }
}
