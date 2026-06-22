<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TherapyGoal extends Model
{
    use SoftDeletes;

    public const STATUSES = ['active', 'met', 'dropped'];

    /** Goal Attainment Scaling labels, keyed by rating value (-2…+2). */
    public const RATING_LABELS = [
        -2 => 'Much less than expected',
        -1 => 'Somewhat less than expected',
        0 => 'Expected level',
        1 => 'Somewhat more than expected',
        2 => 'Much more than expected',
    ];

    protected $fillable = [
        'patient_id',
        'clinician_id',
        'description',
        'status',
        'target_date',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(GoalRating::class);
    }

    /** Most recent GAS rating for the goal. */
    public function latestRating(): HasOne
    {
        return $this->hasOne(GoalRating::class)->latestOfMany();
    }
}
