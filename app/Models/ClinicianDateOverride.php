<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicianDateOverride extends Model
{
    protected $fillable = [
        'clinician_id',
        'date',
        'is_available',
        'start_time',
        'end_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class);
    }
}
