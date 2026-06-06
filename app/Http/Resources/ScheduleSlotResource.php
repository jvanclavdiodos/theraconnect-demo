<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slot' => $this['slot'],
            'clinician_id' => $this['clinician_id'],
            'clinician_name' => $this['clinician_name'],
            'available' => $this['available'],
        ];
    }
}
