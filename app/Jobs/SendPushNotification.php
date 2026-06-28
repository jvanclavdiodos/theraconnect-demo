<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Cap retry attempts. Without this Laravel's queue defaults to unlimited
     * retries; a persistently-failing FCM call would loop forever, generating
     * duplicate push notifications on each retry (the job had no sent_at
     * idempotency guard before this change either).
     */
    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private int $notificationId,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            return;
        }

        // Idempotency guard: if a previous run already dispatched at least one
        // successful FCM send, the notification row's sent_at is set. A retry
        // (queue-worker overlap, manual re-run, or failure-recovery requeue)
        // would otherwise re-iterate every device token and fan out duplicate
        // pushes to every device that already received one. Early-return keeps
        // the job at-most-effectively-once for the user-visible side (push
        // delivery), acceptable because FCM has its own delivery-retry layer.
        if ($notification->sent_at !== null) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $notification->user_id)->pluck('token');
        $sent = false;

        // Carry the notification type + id in the data payload so the app can
        // deep-link to the right screen when the user taps the push.
        $data = array_merge(
            [
                'type' => $notification->type,
                'notification_id' => $notification->id,
            ],
            $notification->data ?? []
        );

        foreach ($tokens as $token) {
            if ($fcm->send($token, $notification->title, $notification->body, $data)) {
                $sent = true;
            }
        }

        if ($sent) {
            $notification->update(['sent_at' => now()]);
        }
    }
}
