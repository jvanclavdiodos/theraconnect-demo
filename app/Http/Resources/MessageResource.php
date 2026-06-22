<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->relationLoaded('sender') ? $this->sender?->name : null,
            'is_mine' => $request->user() && $this->sender_id === $request->user()->id,
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
