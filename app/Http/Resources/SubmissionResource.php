<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'assignment_public_id' => $this->assignment?->public_id,
            'patient_public_id' => $this->patient?->public_id,
            'content' => $this->content,
            // Authenticated download route — file is on the private disk and
            // requires the patient's bearer token (handled by the Dio client).
            'file_url' => $this->file_path ? url('/api/v1/submissions/'.$this->public_id.'/file') : null,
            'file_name' => $this->original_name,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
