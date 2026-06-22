<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalRating extends Model
{
    protected $fillable = [
        'therapy_goal_id',
        'appointment_id',
        'rating',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(TherapyGoal::class, 'therapy_goal_id');
    }

    public function label(): string
    {
        return TherapyGoal::RATING_LABELS[$this->rating] ?? (string) $this->rating;
    }
}
