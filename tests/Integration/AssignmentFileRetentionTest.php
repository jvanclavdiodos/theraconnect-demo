<?php

namespace Tests\Integration;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentFileRetentionTest extends TestCase
{
    public function test_expired_assignment_files_are_deleted_while_records_and_text_are_retained(): void
    {
        Storage::fake('local');
        config([
            'filesystems.default' => 'local',
            'theraconnect.assignment_file_retention_days' => 30,
        ]);

        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $worksheetPath = 'assignments/old-worksheet.pdf';
        $submissionPath = 'submissions/old-answer.pdf';
        Storage::disk('local')->put($worksheetPath, 'worksheet');
        Storage::disk('local')->put($submissionPath, 'answer');

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Retained assignment',
            'description' => 'Keep this description.',
            'attachment_path' => $worksheetPath,
            'attachment_name' => 'worksheet.pdf',
        ]);
        $assignment->created_at = now()->subDays(31);
        $assignment->updated_at = now()->subDays(31);
        $assignment->saveQuietly();
        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'patient_id' => $patient['patient']->id,
            'content' => 'Keep this response.',
            'file_path' => $submissionPath,
            'original_name' => 'answer.pdf',
            'status' => 'reviewed',
            'submitted_at' => now()->subDays(31),
            'reviewed_at' => now()->subDays(30),
        ]);

        $this->artisan('assignments:purge-expired-files')
            ->expectsOutput('Purged 2 expired assignment file(s).')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing([$worksheetPath, $submissionPath]);
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'description' => 'Keep this description.',
            'attachment_path' => null,
            'attachment_name' => null,
        ]);
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'content' => 'Keep this response.',
            'status' => 'reviewed',
            'file_path' => null,
            'original_name' => null,
        ]);
    }

    public function test_files_newer_than_thirty_days_are_not_deleted(): void
    {
        Storage::fake('local');
        config([
            'filesystems.default' => 'local',
            'theraconnect.assignment_file_retention_days' => 30,
        ]);

        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $path = 'assignments/current-worksheet.pdf';
        Storage::disk('local')->put($path, 'worksheet');

        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Current assignment',
            'attachment_path' => $path,
            'attachment_name' => 'current.pdf',
        ]);
        $assignment->created_at = now()->subDays(29);
        $assignment->updated_at = now()->subDays(29);
        $assignment->saveQuietly();

        $this->artisan('assignments:purge-expired-files')
            ->expectsOutput('Purged 0 expired assignment file(s).')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $assignment->fresh()->attachment_path);
    }
}
