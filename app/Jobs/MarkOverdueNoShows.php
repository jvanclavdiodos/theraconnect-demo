<?php

namespace App\Jobs;

use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Backstop for attendance tracking: an approved/rescheduled appointment whose
 * scheduled time passed more than a day ago and was never concluded is treated
 * as a no-show, so a clinician forgetting to close a case doesn't silently rot
 * the attendance metrics. The clinician can still correct it via the UI.
 */
class MarkOverdueNoShows implements ShouldQueue
{
    use Queueable;

    /** How long after the scheduled time an unconcluded session becomes a no-show. */
    public const GRACE_HOURS = 24;

    public function handle(): void
    {
        Appointment::whereIn('status', ['approved', 'rescheduled'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now()->subHours(self::GRACE_HOURS))
            ->update(['status' => 'no_show']);
    }
}
