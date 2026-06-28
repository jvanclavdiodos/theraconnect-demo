<?php

namespace App\Services;

use Illuminate\Support\Str;

class JitsiService
{
    /**
     * Build a video-call room URL on the configured Jitsi server.
     *
     * The room name embeds the appointment id (traceable in logs) plus a UUID.
     * Rooms on the public meet.jit.si server are open to anyone who knows the
     * name, so the UUID keeps the link unguessable.
     */
    public function generateMeetingLink(int $appointmentId): string
    {
        $base = rtrim(config('services.jitsi.base_url'), '/');
        $prefix = config('services.jitsi.room_prefix', 'TheraConnect');

        return "{$base}/{$prefix}-{$appointmentId}-".Str::uuid();
    }
}
