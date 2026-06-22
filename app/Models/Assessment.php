<?php

namespace App\Models;

use App\Support\Assessments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'clinician_id',
        'instrument',
        'status',
        'score',
        'responses',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'array',
            'completed_at' => 'datetime',
            'score' => 'integer',
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

    public function title(): string
    {
        return Assessments::title($this->instrument);
    }

    /** Clinical severity band for a completed assessment (null while pending). */
    public function severity(): ?string
    {
        return $this->score === null
            ? null
            : Assessments::severity($this->instrument, $this->score);
    }
}
