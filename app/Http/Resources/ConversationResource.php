<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinician_id' => $this->clinician_id,
            'clinician_name' => $this->relationLoaded('clinician') ? $this->clinician?->user?->name : null,
            'last_message' => $this->relationLoaded('latestMessage') ? $this->latestMessage?->body : null,
            'last_message_at' => $this->last_message_at,
            'unread_count' => $request->user() ? $this->unreadCountFor($request->user()) : 0,
        ];
    }
}
