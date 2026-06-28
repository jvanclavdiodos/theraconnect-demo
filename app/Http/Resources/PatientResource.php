<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            // Clinician connection state — lets the app show a "pending approval"
            // banner instead of failing silently when messaging/booking.
            'assigned_clinician_id' => $this->assigned_clinician_id,
            'requested_clinician_id' => $this->requested_clinician_id,
            'clinician_request_status' => $this->clinician_request_status,
            'has_avatar' => $this->relationLoaded('user') ? (bool) $this->user?->avatar_path : false,
            'avatar_url' => url('/api/v1/profile/avatar'),
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'educational_attainment' => $this->educational_attainment,
            'employment_status' => $this->employment_status,
            'personal_issues' => $this->personal_issues,
            'contact_no' => $this->contact_no,
            'address' => $this->address,
            'emergency_contact' => $this->emergency_contact,
            'notes' => $this->when(
                auth()->check() && in_array(auth()->user()->role, ['admin', 'clinician']),
                $this->notes
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
