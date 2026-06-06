<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'clinician_id' => $this->clinician_id,
            'clinician_name' => $this->relationLoaded('clinician') && $this->clinician
                ? $this->clinician->user?->name
                : null,
            'requested_at' => $this->requested_at,
            'scheduled_at' => $this->scheduled_at,
            'mode' => $this->mode,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status,
            'reason' => $this->reason,
            'clinic_notes' => $this->when(
                $request->user() && in_array($request->user()->role, ['admin', 'clinician']),
                $this->clinic_notes
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
