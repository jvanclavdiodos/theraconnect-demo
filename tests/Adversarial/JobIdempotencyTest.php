<?php

namespace Tests\Adversarial;

use App\Jobs\GenerateAppointmentReminders;
use App\Jobs\GenerateAssignmentReminders;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\FcmService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Phase 2.F — Job Idempotency.
 *
 * Phase 1 confirmed the scheduler dispatches GenerateAppointmentReminders
 * daily at 08:00 and SendPushNotification is dispatched from many
 * controllers. None of these jobs has $tries, $timeout, $backoff, or
 * ShouldBeUnique; SendPushNotification has no sent_at idempotency
 * guard at the top of handle(); GenerateAppointmentReminders lacks the
 * dedup check that GenerateAssignmentReminders has.
 *
 * Retry / overlap behavior is therefore "send it twice, deliver twice" —
 * which is unacceptable for clinical notifications.
 *
 * F1, F2 are bug-proving tests. F3, F4 are defense checks.
 */
class JobIdempotencyTest extends TestCase
{
    use CreatesActors;

    /**
     * F1 — SendPushNotification is NOT idempotent on retry. If the job is
     * dispatched / runs twice (because of a queue-worker overlap or a
     * manual re-run), it sends duplicate push notifications to the same
     * device tokens.
     *
     * BUG: SendPushNotification.handle has no `if ($notification->sent_at)
     * return;` guard at the top. Also no $tries limit.
     */
    public function test_send_push_notification_is_not_idempotent_on_retry(): void
    {
        // Set up a patient with a device token + a notification row.
        $clinician = $this->createClinician();
        $patient = $this->createPatient('f1@test.com');

        DeviceToken::create([
            'user_id' => $patient['user']->id,
            'token' => 'fcm-token-fake',
            'platform' => 'android',
        ]);

        $notif = Notification::create([
            'user_id' => $patient['user']->id,
            'type' => 'appointment_approved',
            'title' => 'Test Push',
            'body' => 'Hello F1',
            'channel' => 'fcm',
            'data' => ['appointment_id' => null],
        ]);

        // Count the actual FcmService::send calls via a shared stdClass.
        $counter = (object) ['calls' => 0];

        $proxyFcm = \Mockery::mock(FcmService::class);
        $proxyFcm->shouldReceive('send')
            ->andReturnUsing(function () use ($counter) {
                $counter->calls++;

                return true;
            });

        $this->app->instance(FcmService::class, $proxyFcm);

        // Run the job twice — simulate a retry.
        $job = new SendPushNotification($notif->id);
        $job->handle($proxyFcm);
        $job->handle($proxyFcm);  // RETRY

        // The notification row should have sent_at set after the first call.
        // The KEY assertion: the second run MUST NOT re-send. Currently the
        // only way for F1 to NOT be a bug is if the FcmService::send returns
        // false on the 2nd call. The proxy always returns true → bug emerges.
        $this->assertEquals(
            1,
            $counter->calls,
            'BUG CONFIRMED: SendPushNotification retried without a sent_at guard — FCM '.
            'received '.$counter->calls.' send calls for one notification.'
        );
    }

    /**
     * F2 — GenerateAppointmentReminders has NO dedup check.
     * Running it twice on the same day (worker overlap, manual re-run)
     * creates duplicate appointment_reminder notifications.
     *
     * Compare with GenerateAssignmentReminders which HAS the check at
     * lines 27-31 (Notification::where('type','assignment_deadline')...).
     *
     * The bug is an asymmetry / regression: the assignment reminder path
     * was hardened, the appointment reminder path was not.
     */
    public function test_generate_appointment_reminders_is_not_idempotent(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('f2@test.com');

        $tomorrow = now()->addDay()->format('Y-m-d');

        // Appointment scheduled for tomorrow at 10:00, in a notified status.
        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => "$tomorrow 10:00:00",
            'scheduled_at' => "$tomorrow 10:00:00",
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        // Run the job twice.
        $job = new GenerateAppointmentReminders();
        $job->handle(app(NotificationService::class));
        $job->handle(app(NotificationService::class));

        // Defense: ideally exactly ONE appointment_reminder notification
        // exists per appointment. Currently: TWO (BUG).
        $reminderCount = Notification::where('type', 'appointment_reminder')
            ->where('user_id', $patient['user']->id)
            ->count();

        $this->assertEquals(
            1,
            $reminderCount,
            'BUG CONFIRMED: GenerateAppointmentReminders job produced '.
            "$reminderCount notifications for one appointment after two runs. " .
            'The assignment-reminder parallel has a 6h dedup guard; '.
            'appointment reminders have none.'
        );
    }

    /**
     * F3 — Control test. GenerateAssignmentReminders DOES have a dedup
     * guard (Notification::where('type','assignment_deadline')...
     * ->where('created_at','>=',now()->subHours(6))...). Running it twice
     * must produce exactly one notification per due-soon assignment.
     */
    public function test_generate_assignment_reminders_is_idempotent_control(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('f3@test.com');

        // Assignment due in 12 hours (inside the 24h window the job targets).
        Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'F3 due soon',
            'description' => 'control',
            'due_date' => now()->addHours(12),
        ]);

        $job = new GenerateAssignmentReminders();
        $job->handle(app(NotificationService::class));
        $job->handle(app(NotificationService::class));

        $count = Notification::where('type', 'assignment_deadline')
            ->where('user_id', $patient['user']->id)
            ->count();

        $this->assertEquals(1, $count, 'GenerateAssignmentReminders should be idempotent (control).');
    }

    /**
     * F4 — MarkOverdueNoShows job bulk-updates appointments to no_show.
     * Running it twice against an already-no_show appointment is a no-op
     * (the WHERE clause selects only approved/rescheduled status), so
     * the job is intrinsically idempotent. This is a defense test.
     */
    public function test_mark_overdue_no_shows_is_idempotent(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('f4@test.com');

        // An approved appointment scheduled 25 hours ago (past the 24h grace).
        $past = now()->subHours(25)->format('Y-m-d H:i:s');
        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => $past,
            'scheduled_at' => $past,
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $job = new \App\Jobs\MarkOverdueNoShows();
        $job->handle();
        $job->handle();  // second run must not flip-flop

        $this->assertEquals('no_show', $appt->fresh()->status);
    }
}
