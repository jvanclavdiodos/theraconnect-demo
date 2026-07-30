<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'clinician_public_id' => $this->clinician?->public_id,
            'clinician_name' => $this->relationLoaded('clinician') && $this->clinician
                ? $this->clinician->user?->name
                : null,
            'patient_public_id' => $this->patient?->public_id,
            'title' => $this->title,
            'description' => $this->description,
            'attachment_name' => $this->attachment_name,
            // Authenticated download route — worksheet is on the private disk and
            // requires the patient's bearer token (handled by the Dio client).
            'attachment_url' => $this->attachment_path
                ? url('/api/v1/assignments/'.$this->public_id.'/worksheet')
                : null,
            'due_date' => $this->due_date,
            'submission_status' => $this->when(
                $this->relationLoaded('submissions') && $this->submissions->isNotEmpty(),
                fn () => $this->submissions->first()->status
            ),
            'submitted_at' => $this->when(
                $this->relationLoaded('submissions') && $this->submissions->isNotEmpty(),
                fn () => $this->submissions->first()->submitted_at
            ),
            // The patient's own submission (content + file), for in-app preview.
            'submission' => $this->when(
                $this->relationLoaded('submissions') && $this->submissions->isNotEmpty(),
                function () {
                    $s = $this->submissions->first();

                    return [
                        'public_id' => $s->public_id,
                        'content' => $s->content,
                        'original_name' => $s->original_name,
                        'kind' => $s->previewKind(),
                        // Authenticated download route (private disk + bearer token).
                        'file_url' => $s->file_path
                            ? url('/api/v1/submissions/'.$s->public_id.'/file')
                            : null,
                    ];
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
