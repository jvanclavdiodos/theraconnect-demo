<?php

namespace App\Http\Resources;

use App\Support\Assessments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $def = Assessments::definition($this->instrument);

        return [
            'id' => $this->id,
            'instrument' => $this->instrument,
            'title' => $this->title(),
            'name' => $def['name'] ?? null,
            'explanation' => Assessments::explanation($this->instrument),
            'status' => $this->status,
            'score' => $this->score,
            'max' => $def['max'] ?? null,
            'severity' => $this->severity(),
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
