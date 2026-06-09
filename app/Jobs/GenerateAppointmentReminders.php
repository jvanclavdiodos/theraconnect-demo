<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAppointmentReminders implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $service): void
    {
        $tomorrow = now()->addDay()->toDateString();

        $appointments = Appointment::whereDate('scheduled_at', $tomorrow)
            ->whereIn('status', ['approved', 'rescheduled'])
            ->with('patient.user')
            ->get();

        foreach ($appointments as $appointment) {
            $time = $appointment->scheduled_at->format('h:i A');
            $notification = $service->appointmentReminder(
                $appointment->patient->user->id,
                $appointment->id,
                $time
            );

            SendPushNotification::dispatch($notification->id)->afterCommit();
        }
    }
}
