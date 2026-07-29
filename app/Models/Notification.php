<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    public const PER_PAGE = 10;

    public const MESSAGE_RECEIVED = 'message_received';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'channel',
        'sent_at',
        'email_sent_at',
        'email_failed_at',
        'email_error',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'sent_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'email_failed_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Notifications shown in the general inbox; messages have their own unread UI. */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('type', '!=', self::MESSAGE_RECEIVED);
    }

    public function scopeUnreadGeneral(Builder $query): Builder
    {
        return $query->general()->whereNull('read_at');
    }
}
