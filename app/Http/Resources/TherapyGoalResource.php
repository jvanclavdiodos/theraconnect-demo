<?php

namespace App\Http\Resources;

use App\Models\TherapyGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TherapyGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->whenLoaded('latestRating');

        return [
            'id' => $this->id,
            'description' => $this->description,
            'status' => $this->status,
            'target_date' => $this->target_date?->toDateString(),
            'latest_rating' => $latest && $this->latestRating
                ? [
                    'rating' => $this->latestRating->rating,
                    'label' => TherapyGoal::RATING_LABELS[$this->latestRating->rating] ?? null,
                    'note' => $this->latestRating->note,
                    'created_at' => $this->latestRating->created_at,
                ]
                : null,
        ];
    }
}
