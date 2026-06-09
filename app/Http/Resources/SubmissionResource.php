<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'patient_id' => $this->patient_id,
            'content' => $this->content,
            // Authenticated download route — file is on the private disk and
            // requires the patient's bearer token (handled by the Dio client).
            'file_url' => $this->file_path ? url('/api/v1/submissions/' . $this->id . '/file') : null,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
