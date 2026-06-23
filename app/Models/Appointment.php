<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    use SoftDeletes;

    /** Online meeting links stop working this many hours after the appointment. */
    public const MEETING_LINK_TTL_HOURS = 5;

    protected $fillable = [
        'patient_id',
        'clinician_id',
        'requested_at',
        'scheduled_at',
        'mode',
        'meeting_link',
        'status',
        'reason',
        'clinic_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'reason'       => 'encrypted',
            'clinic_notes' => 'encrypted',
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

    /** When the online meeting link stops being offered (scheduled_at + TTL). */
    public function meetingLinkExpiresAt(): ?Carbon
    {
        return $this->scheduled_at
            ? $this->scheduled_at->copy()->addHours(self::MEETING_LINK_TTL_HOURS)
            : null;
    }

    /**
     * Is the online meeting link still usable? Online appointment with a link,
     * a scheduled time, and now before scheduled_at + TTL. Note: rooms on the
     * public Jitsi server can't be cryptographically revoked — this gates the
     * app/dashboard from surfacing the link once expired.
     */
    public function meetingLinkActive(): bool
    {
        return $this->mode === 'online'
            && ! empty($this->meeting_link)
            && $this->scheduled_at !== null
            && now()->lessThan($this->meetingLinkExpiresAt());
    }
}
