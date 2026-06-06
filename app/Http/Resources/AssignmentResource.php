<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinician_id' => $this->clinician_id,
            'clinician_name' => $this->relationLoaded('clinician') && $this->clinician
                ? $this->clinician->user?->name
                : null,
            'patient_id' => $this->patient_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date,
            'submission_status' => $this->when(
                $this->relationLoaded('submissions') && $this->submissions->isNotEmpty(),
                fn() => $this->submissions->first()->status
            ),
            'submitted_at' => $this->when(
                $this->relationLoaded('submissions') && $this->submissions->isNotEmpty(),
                fn() => $this->submissions->first()->submitted_at
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
