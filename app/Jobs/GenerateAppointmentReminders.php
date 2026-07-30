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

    public const DAY_BEFORE = NotificationService::APPOINTMENT_REMINDER_DAY_BEFORE;

    public const NIGHT_BEFORE = NotificationService::APPOINTMENT_REMINDER_NIGHT_BEFORE;

    public function __construct(public string $reminderKind = self::DAY_BEFORE) {}

    public function handle(NotificationService $service): void
    {
        if (! in_array($this->reminderKind, [self::DAY_BEFORE, self::NIGHT_BEFORE], true)) {
            throw new \InvalidArgumentException('Unsupported appointment reminder kind.');
        }

        $tomorrow = now()->addDay()->toDateString();

        $appointments = Appointment::whereDate('scheduled_at', $tomorrow)
            ->whereIn('status', ['approved', 'rescheduled'])
            ->whereHas('patient.user')
            ->with('patient.user')
            ->get();

        foreach ($appointments as $appointment) {
            $reminderFor = $appointment->scheduled_at->format('Y-m-d H:i:s');

            // A phase + scheduled-time key allows the morning and night
            // reminders once each, while a later reschedule can generate a
            // fresh pair for the new appointment time.
            $alreadyReminded = Notification::where('type', 'appointment_reminder')
                ->where('user_id', $appointment->patient->user->id)
                ->whereJsonContains('data->appointment_public_id', $appointment->public_id)
                ->whereJsonContains('data->reminder_kind', $this->reminderKind)
                ->whereJsonContains('data->reminder_for', $reminderFor)
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            $time = $appointment->scheduled_at->format('h:i A');
            $notification = $service->appointmentReminder(
                $appointment->patient->user->id,
                $appointment->id,
                $time,
                $this->reminderKind,
                $reminderFor
            );

            $service->dispatchDeliveries($notification);
        }
    }
}
