<?php

namespace Tests\Integration;

use App\Jobs\GenerateAppointmentReminders;
use App\Jobs\SendEmailNotification;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    public function test_day_and_night_before_reminders_are_each_sent_once(): void
    {
        Queue::fake();
        Carbon::setTestNow('2030-01-01 08:00:00');

        try {
            $appointment = $this->approvedAppointment('2030-01-02 10:00:00');
            $service = app(NotificationService::class);

            $dayJob = new GenerateAppointmentReminders(GenerateAppointmentReminders::DAY_BEFORE);
            $dayJob->handle($service);
            $dayJob->handle($service);

            Carbon::setTestNow('2030-01-01 20:00:00');
            $nightJob = new GenerateAppointmentReminders(GenerateAppointmentReminders::NIGHT_BEFORE);
            $nightJob->handle($service);
            $nightJob->handle($service);

            $reminders = Notification::where('type', 'appointment_reminder')
                ->whereJsonContains('data->appointment_id', $appointment->id)
                ->orderBy('id')
                ->get();

            $this->assertCount(2, $reminders);
            $this->assertSame(
                [GenerateAppointmentReminders::DAY_BEFORE, GenerateAppointmentReminders::NIGHT_BEFORE],
                $reminders->pluck('data')->map(fn (array $data) => $data['reminder_kind'])->all()
            );
            $this->assertSame('Appointment Tomorrow', $reminders->last()->title);
            $this->assertStringContainsString('tomorrow at 10:00 AM', $reminders->last()->body);
            $this->assertSame('2030-01-02 10:00:00', $reminders->last()->data['reminder_for']);

            Queue::assertPushed(SendPushNotification::class, 2);
            Queue::assertPushed(SendEmailNotification::class, 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rescheduled_appointment_can_receive_a_new_night_before_reminder(): void
    {
        Queue::fake();
        Carbon::setTestNow('2030-01-01 20:00:00');

        try {
            $appointment = $this->approvedAppointment('2030-01-02 10:00:00');
            $job = new GenerateAppointmentReminders(GenerateAppointmentReminders::NIGHT_BEFORE);
            $job->handle(app(NotificationService::class));

            $appointment->update([
                'scheduled_at' => '2030-01-03 02:00:00',
                'status' => 'rescheduled',
            ]);
            Carbon::setTestNow('2030-01-02 20:00:00');
            $job->handle(app(NotificationService::class));

            $this->assertSame(
                2,
                Notification::where('type', 'appointment_reminder')
                    ->whereJsonContains('data->appointment_id', $appointment->id)
                    ->whereJsonContains('data->reminder_kind', GenerateAppointmentReminders::NIGHT_BEFORE)
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_night_before_job_ignores_ineligible_dates_and_statuses(): void
    {
        Queue::fake();
        Carbon::setTestNow('2030-01-01 20:00:00');

        try {
            $this->approvedAppointment('2030-01-01 22:00:00');
            $this->approvedAppointment('2030-01-03 10:00:00');
            $this->approvedAppointment('2030-01-02 10:00:00', 'pending');
            $this->approvedAppointment('2030-01-02 11:00:00', 'cancelled');

            (new GenerateAppointmentReminders(GenerateAppointmentReminders::NIGHT_BEFORE))
                ->handle(app(NotificationService::class));

            $this->assertDatabaseCount('notifications', 0);
            Queue::assertNothingPushed();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function approvedAppointment(string $scheduledAt, string $status = 'approved'): Appointment
    {
        $suffix = uniqid('', true);
        $clinicianUser = User::create([
            'name' => 'Dr. Reminder',
            'email' => "reminder-clinician-{$suffix}@test.com",
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $clinicianUser->id,
            'license_no' => 'REM-'.$suffix,
            'specialization' => 'Testing',
        ]);
        $patient = $this->createPatient(uniqid('reminder-', true).'@test.com');

        return Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician->id,
            'requested_at' => $scheduledAt,
            'scheduled_at' => $scheduledAt,
            'mode' => 'in_person',
            'status' => $status,
        ]);
    }
}
