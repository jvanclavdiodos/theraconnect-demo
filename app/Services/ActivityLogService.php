<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    /**
     * Record a clinical access or write event.
     *
     * @param  array<string,mixed>  $meta
     */
    public function log(User $actor, string $event, ?Model $target = null, array $meta = []): void
    {
        ActivityLog::create([
            'user_id' => $actor->id,
            'event' => $event,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'meta' => empty($meta) ? null : $meta,
        ]);
    }
}
