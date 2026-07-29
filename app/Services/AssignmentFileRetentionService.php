<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AssignmentFileRetentionService
{
    public function purgeExpired(): int
    {
        $retentionDays = max(1, (int) config('theraconnect.assignment_file_retention_days', 30));
        $cutoff = now()->subDays($retentionDays);

        return $this->purgeAssignmentAttachments($cutoff)
            + $this->purgeSubmissionFiles($cutoff);
    }

    private function purgeAssignmentAttachments(Carbon $cutoff): int
    {
        $purged = 0;

        Assignment::withTrashed()
            ->whereNotNull('attachment_path')
            ->where('created_at', '<=', $cutoff)
            ->select(['id', 'attachment_path'])
            ->chunkById(100, function ($assignments) use ($cutoff, &$purged) {
                foreach ($assignments as $assignment) {
                    $path = $assignment->attachment_path;
                    Storage::disk()->delete($path);

                    $purged += Assignment::withTrashed()
                        ->whereKey($assignment->id)
                        ->where('attachment_path', $path)
                        ->where('created_at', '<=', $cutoff)
                        ->update([
                            'attachment_path' => null,
                            'attachment_name' => null,
                        ]);
                }
            });

        return $purged;
    }

    private function purgeSubmissionFiles(Carbon $cutoff): int
    {
        $purged = 0;

        Submission::query()
            ->whereNotNull('file_path')
            ->where('submitted_at', '<=', $cutoff)
            ->select(['id', 'file_path'])
            ->chunkById(100, function ($submissions) use ($cutoff, &$purged) {
                foreach ($submissions as $submission) {
                    $path = $submission->file_path;
                    Storage::disk()->delete($path);

                    $purged += Submission::query()
                        ->whereKey($submission->id)
                        ->where('file_path', $path)
                        ->where('submitted_at', '<=', $cutoff)
                        ->update([
                            'file_path' => null,
                            'original_name' => null,
                        ]);
                }
            });

        return $purged;
    }
}
