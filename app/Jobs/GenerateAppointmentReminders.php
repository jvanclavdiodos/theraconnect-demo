<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Notification;
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
            // Idempotency guard: skip if we already reminded this user about
            // this appointment within the last day. Without this check, a
            // scheduler overlap / manual re-run / retry-after-failure would
            // fan out duplicate reminder notifications + duplicate push
            // dispatches for the same appointment. Mirrors the dedup pattern
            // in GenerateAssignmentReminders::handle (lines 27-31).
            $alreadyReminded = Notification::where('type', 'appointment_reminder')
                ->where('user_id', $appointment->patient->user->id)
                ->where('created_at', '>=', now()->subDay())
                ->whereJsonContains('data->appointment_id', $appointment->id)
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            $time = $appointment->scheduled_at->format('h:i A');
            $notification = $service->appointmentReminder(
                $appointment->patient->user->id,
                $appointment->id,
                $time
            );

            $service->dispatchDeliveries($notification);
        }
    }
}
