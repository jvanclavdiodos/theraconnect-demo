<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $table = 'assignment_submissions';

    protected $fillable = [
        'assignment_id',
        'patient_id',
        'content',
        'file_path',
        'original_name',
        'status',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** Lowercased file extension of the uploaded submission (or ''). */
    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name ?? '', PATHINFO_EXTENSION));
    }

    /**
     * How the file can be previewed inline: image | pdf | text | other.
     * "other" (doc/docx/rtf) falls back to download.
     */
    public function previewKind(): string
    {
        return match ($this->extension()) {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            'pdf' => 'pdf',
            'txt' => 'text',
            default => 'other',
        };
    }

    public function isPreviewable(): bool
    {
        return $this->file_path && $this->previewKind() !== 'other';
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
