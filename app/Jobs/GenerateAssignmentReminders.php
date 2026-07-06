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
        // Assignments due within the next 24 hours (datetime window, not date-granular).
        $assignments = Assignment::where('due_date', '>', now())
            ->where('due_date', '<=', now()->addDay())
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

            $service->dispatchDeliveries($notification);
        }
    }
}
