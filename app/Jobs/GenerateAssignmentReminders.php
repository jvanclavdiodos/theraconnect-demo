<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAssignmentReminders implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $service): void
    {
        $tomorrow = now()->addDay();

        $assignments = Assignment::whereDate('due_date', '<=', $tomorrow)
            ->whereDate('due_date', '>', now())
            ->whereDoesntHave('submissions', function ($q) {
                $q->where('status', 'reviewed');
            })
            ->with('patient.user')
            ->get();

        foreach ($assignments as $assignment) {
            $alreadyReminded = Notification::where('type', 'assignment_deadline')
                ->where('user_id', $assignment->patient->user->id)
                ->where('created_at', '>=', now()->subHours(6))
                ->whereJsonContains('data->assignment_id', $assignment->id)
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            $notification = $service->assignmentDeadline(
                $assignment->patient->user->id,
                $assignment->id,
                $assignment->title,
                $assignment->due_date->format('M d, Y')
            );

            SendPushNotification::dispatch($notification->id)->afterCommit();
        }
    }
}
