<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            // `last_used_at` is a Carbon instance — passing it through a
            // JsonResource applies AppServiceProvider's serializeUsing
            // callback so the mobile app sees the same wall-clock + "Z"
            // format as every other timestamped endpoint.
            'last_used_at' => $this->last_used_at,
        ];
    }
}
