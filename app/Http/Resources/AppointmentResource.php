<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'patient_public_id' => $this->patient?->public_id,
            'clinician_public_id' => $this->clinician?->public_id,
            'clinician_name' => $this->relationLoaded('clinician') && $this->clinician
                ? $this->clinician->user?->name
                : null,
            'clinician_contact' => $this->when(
                $this->relationLoaded('clinician') && $this->clinician,
                fn () => [
                    'email' => $this->clinician->user?->email,
                    'phone' => $this->clinician->contact_no,
                    'specialization' => $this->clinician->specialization,
                ]
            ),
            'requested_at' => $this->requested_at,
            'scheduled_at' => $this->scheduled_at,
            'mode' => $this->mode,
            // Online meeting links expire 5h after the appointment; once expired
            // the URL is no longer sent to the client.
            'meeting_link' => $this->meetingLinkActive() ? $this->meeting_link : null,
            'meeting_link_active' => $this->meetingLinkActive(),
            'meeting_link_expires_at' => $this->meetingLinkExpiresAt(),
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
