<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AssignmentService
{
    public function create(array $data, ?UploadedFile $attachment = null): Assignment
    {
        $attachmentPath = null;
        $attachmentName = null;

        if ($attachment) {
            // Private disk — worksheets are reachable only via authenticated routes.
            $attachmentPath = $attachment->store('assignments', 'local');
            $attachmentName = $attachment->getClientOriginalName();
        }

        return Assignment::create([
            'clinician_id' => $data['clinician_id'],
            'patient_id' => $data['patient_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'due_date' => $data['due_date'] ?? null,
        ]);
    }

    public function submit(int $assignmentId, int $patientId, ?string $content, ?UploadedFile $file): Submission
    {
        $submissionData = [
            'content' => $content,
            'status' => 'submitted',
            'submitted_at' => now(),
            'reviewed_at' => null,
        ];

        if ($file) {
            $existing = Submission::where('assignment_id', $assignmentId)
                ->where('patient_id', $patientId)
                ->first();

            if ($existing && $existing->file_path) {
                Storage::disk('local')->delete($existing->file_path);
            }

            // Private disk — submissions may contain sensitive patient content and
            // are only reachable through an authenticated download route.
            $submissionData['file_path'] = $file->store('submissions', 'local');
        }

        $submission = Submission::updateOrCreate(
            [
                'assignment_id' => $assignmentId,
                'patient_id' => $patientId,
            ],
            $submissionData
        );

        return $submission;
    }

    public function review(Submission $submission): Submission
    {
        $submission->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
        ]);

        return $submission->fresh();
    }
}
