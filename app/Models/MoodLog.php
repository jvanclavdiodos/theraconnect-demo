<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodLog extends Model
{
    protected $fillable = [
        'patient_id',
        'score',
        'note',
        'logged_on',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'logged_on' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
