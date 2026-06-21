<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'patient_id',
        'clinician_id',
        'last_message_at',
        'patient_last_read_at',
        'clinician_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'patient_last_read_at' => 'datetime',
            'clinician_last_read_at' => 'datetime',
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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** Is $user a participant (the patient's user or the clinician's user)? */
    public function hasParticipant(User $user): bool
    {
        return $user->id === $this->patient?->user_id
            || $user->id === $this->clinician?->user_id;
    }

    /** Unread messages for $user (newer than their last-read, not sent by them). */
    public function unreadCountFor(User $user): int
    {
        $since = $user->id === $this->patient?->user_id
            ? $this->patient_last_read_at
            : $this->clinician_last_read_at;

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->count();
    }
}
