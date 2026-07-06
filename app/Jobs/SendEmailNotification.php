<?php

namespace App\Jobs;

use App\Mail\NotificationEmail;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private int $notificationId,
    ) {}

    public function handle(NotificationService $notifications): void
    {
        $notification = Notification::with('user')->find($this->notificationId);

        if (! $notification || ! $notifications->shouldEmail($notification)) {
            return;
        }

        if ($notification->email_sent_at !== null) {
            return;
        }

        if (! $notification->user?->email) {
            $notification->update([
                'email_failed_at' => now(),
                'email_error' => 'Recipient email address is missing.',
            ]);

            return;
        }

        try {
            Mail::to($notification->user->email)->send(new NotificationEmail($notification));

            $notification->update([
                'email_sent_at' => now(),
                'email_failed_at' => null,
                'email_error' => null,
            ]);
        } catch (Throwable $e) {
            $notification->update([
                'email_failed_at' => now(),
                'email_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }
}
