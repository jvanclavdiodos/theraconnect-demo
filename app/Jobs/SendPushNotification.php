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

        foreach ($tokens as $token) {
            if ($fcm->send($token, $notification->title, $notification->body, $notification->data)) {
                $sent = true;
            }
        }

        if ($sent) {
            $notification->update(['sent_at' => now()]);
        }
    }
}
