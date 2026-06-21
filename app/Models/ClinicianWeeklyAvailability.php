<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicianWeeklyAvailability extends Model
{
    protected $fillable = [
        'clinician_id',
        'day_of_week',
        'is_available',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class);
    }
}
