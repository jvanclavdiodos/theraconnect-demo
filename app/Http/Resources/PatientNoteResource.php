<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'title' => $this->title,
            'body' => $this->body,
            'clinician_name' => $this->relationLoaded('clinician') ? $this->clinician?->user?->name : null,
            'created_at' => $this->created_at,
        ];
    }
}
