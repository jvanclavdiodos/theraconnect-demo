<?php

namespace Tests\Integration;

use App\Jobs\SendEmailNotification;
use App\Jobs\SendPushNotification;
use App\Mail\NotificationEmail;
use App\Models\Conversation;
use App\Services\MessageService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class EmailNotificationDeliveryTest extends TestCase
{
    public function test_transactional_notifications_dispatch_push_and_email_jobs(): void
    {
        Queue::fake();

        $patient = $this->createPatient();
        $notification = app(NotificationService::class)->appointmentApproved(
            $patient['user']->id,
            'Jul 10, 2026 09:00 AM',
            'https://meet.example.test/session'
        );

        app(NotificationService::class)->dispatchDeliveries($notification);

        Queue::assertPushed(SendPushNotification::class, fn ($job) => $this->privateJobNotificationId($job) === $notification->id);
        Queue::assertPushed(SendEmailNotification::class, fn ($job) => $this->privateJobNotificationId($job) === $notification->id);
    }

    public function test_direct_message_notifications_do_not_dispatch_email_jobs(): void
    {
        Queue::fake();

        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);

        app(MessageService::class)->send($conversation, $patient['user'], 'Please do not email this message body.');

        Queue::assertPushed(SendPushNotification::class);
        Queue::assertNotPushed(SendEmailNotification::class);
    }

    public function test_email_job_sends_once_and_records_delivery_timestamp(): void
    {
        Mail::fake();

        $patient = $this->createPatient();
        $notification = app(NotificationService::class)->appointmentReminder(
            $patient['user']->id,
            123,
            '09:00 AM'
        );

        $job = new SendEmailNotification($notification->id);
        $job->handle(app(NotificationService::class));
        $job->handle(app(NotificationService::class));

        Mail::assertSent(NotificationEmail::class, 1);

        $notification->refresh();
        $this->assertNotNull($notification->email_sent_at);
        $this->assertNull($notification->email_failed_at);
        $this->assertNull($notification->email_error);
        $this->assertNull($notification->sent_at);
    }

    public function test_notification_email_renders_with_relevant_patient_cta(): void
    {
        $patient = $this->createPatient();
        $notification = app(NotificationService::class)->assignmentCreated(
            $patient['user']->id,
            'Dr. Test',
            'Mood tracking worksheet'
        );
        $notification->load('user');

        $html = (new NotificationEmail($notification))->render();

        $this->assertStringContainsString('New Assignment', $html);
        $this->assertStringContainsString(route('portal.assignments.index'), $html);
    }

    public function test_ineligible_email_job_returns_without_sending(): void
    {
        Mail::fake();

        $patient = $this->createPatient();
        $notification = app(NotificationService::class)->messageReceived(
            $patient['user']->id,
            'Dr. Test',
            'A sensitive message snippet'
        );

        (new SendEmailNotification($notification->id))->handle(app(NotificationService::class));

        Mail::assertNothingSent();
        $notification->refresh();
        $this->assertNull($notification->email_sent_at);
    }

    public function test_email_job_records_failure_before_retrying(): void
    {
        $patient = $this->createPatient();
        $notification = app(NotificationService::class)->appointmentRejected($patient['user']->id);

        Mail::shouldReceive('to')
            ->once()
            ->with($patient['user']->email)
            ->andThrow(new RuntimeException('SMTP transport unavailable'));

        try {
            (new SendEmailNotification($notification->id))->handle(app(NotificationService::class));
            $this->fail('Expected mail transport failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('SMTP transport unavailable', $e->getMessage());
        }

        $notification->refresh();
        $this->assertNull($notification->email_sent_at);
        $this->assertNotNull($notification->email_failed_at);
        $this->assertSame('SMTP transport unavailable', $notification->email_error);
    }

    private function privateJobNotificationId(object $job): int
    {
        $property = new \ReflectionProperty($job, 'notificationId');
        $property->setAccessible(true);

        return $property->getValue($job);
    }
}
