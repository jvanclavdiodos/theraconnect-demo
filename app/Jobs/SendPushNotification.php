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

    public function __construct(
        private int $notificationId,
    ) {}

    public function handle(FcmService $fcm): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
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
