<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'clinician_id',
        'patient_id',
        'title',
        'description',
        'attachment_path',
        'attachment_name',
        'due_date',
    ];

    public function hasAttachment(): bool
    {
        return ! empty($this->attachment_path);
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
        ];
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
