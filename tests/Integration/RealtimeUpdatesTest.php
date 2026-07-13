<?php

namespace Tests\Integration;

use App\Events\AppointmentUpdated;
use App\Events\MessageCreated;
use App\Events\NotificationCreated;
use App\Models\Conversation;
use App\Services\AppointmentService;
use App\Services\MessageService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RealtimeUpdatesTest extends TestCase
{
    public function test_notification_message_and_appointment_changes_dispatch_realtime_events(): void
    {
        Event::fake([
            NotificationCreated::class,
            MessageCreated::class,
            AppointmentUpdated::class,
        ]);

        $clinician = $this->createClinician();
        $patient = $this->createPatient('realtime-patient@test.com');
        $patient['patient']->assignClinician($clinician['clinician']->id);

        app(NotificationService::class)->appointmentApproved(
            $patient['user']->id,
            'July 20, 2026 at 10:00 AM',
        );

        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);
        app(MessageService::class)->send($conversation, $patient['user'], 'Hello');

        $appointment = app(AppointmentService::class)->create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDay(),
            'mode' => 'online',
        ]);

        Event::assertDispatched(NotificationCreated::class);
        Event::assertDispatched(MessageCreated::class, fn (MessageCreated $event) => $event->broadcastWith()['conversation_id'] === $conversation->id
        );
        Event::assertDispatched(AppointmentUpdated::class, fn (AppointmentUpdated $event) => $event->broadcastWith()['appointment_id'] === $appointment->id
                && $event->broadcastWith()['change'] === 'created'
        );
    }

    public function test_realtime_payloads_do_not_expose_message_or_notification_bodies(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('realtime-payload@test.com');
        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $patient['user']->id,
            'body' => 'Sensitive message body',
        ]);
        $notification = $patient['user']->notifications()->create([
            'type' => 'message_received',
            'title' => 'Private title',
            'body' => 'Sensitive notification body',
            'channel' => 'fcm',
        ]);

        $messagePayload = (new MessageCreated($message))->broadcastWith();
        $notificationPayload = (new NotificationCreated($notification))->broadcastWith();

        $this->assertArrayNotHasKey('body', $messagePayload);
        $this->assertArrayNotHasKey('title', $notificationPayload);
        $this->assertArrayNotHasKey('body', $notificationPayload);
    }

    public function test_broadcast_failure_does_not_undo_persisted_notification(): void
    {
        $patient = $this->createPatient('realtime-failure@test.com');

        Event::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Realtime event dispatch failed', \Mockery::on(
                fn (array $context) => $context['event'] === NotificationCreated::class
            ));

        $notification = app(NotificationService::class)->appointmentRejected($patient['user']->id);

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_realtime_event_is_not_dispatched_when_transaction_rolls_back(): void
    {
        Event::fake([NotificationCreated::class]);
        $patient = $this->createPatient('realtime-rollback@test.com');

        try {
            DB::transaction(function () use ($patient) {
                app(NotificationService::class)->appointmentRejected($patient['user']->id);
                throw new \RuntimeException('Roll back');
            });
        } catch (\RuntimeException) {
            // Expected: the domain write and its after-commit event are discarded.
        }

        Event::assertNotDispatched(NotificationCreated::class);
        $this->assertDatabaseMissing('notifications', ['user_id' => $patient['user']->id]);
    }

    public function test_patient_can_authorize_only_their_user_and_conversation_channels(): void
    {
        $this->configureTestBroadcaster();

        $clinician = $this->createClinician();
        $patient = $this->createPatient('realtime-auth@test.com');
        $otherPatient = $this->createPatient('realtime-other@test.com');
        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-users.'.$patient['user']->id,
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->withHeaders($this->apiHeaders($token))->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-conversations.'.$conversation->id,
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->withHeaders($this->apiHeaders($token))->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-users.'.$otherPatient['user']->id,
        ])->assertForbidden();
    }

    public function test_realtime_config_returns_public_connection_data_without_secret(): void
    {
        $this->configureTestBroadcaster();
        $patient = $this->createPatient('realtime-config@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/realtime/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.app_key', 'test-key')
            ->assertJsonMissingPath('data.secret');

        $this->assertSame('realtime.test', $response->json('data.host'));
    }

    public function test_only_admin_can_authorize_the_admin_appointment_channel(): void
    {
        $this->configureTestBroadcaster();
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();
        $payload = [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-admin.appointments',
        ];

        $this->actingAs($admin)->postJson('/broadcasting/auth', $payload)
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($clinician['user'])->postJson('/broadcasting/auth', $payload)
            ->assertForbidden();
    }

    private function configureTestBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => 'realtime.test',
            'broadcasting.connections.reverb.options.port' => 443,
            'broadcasting.connections.reverb.options.scheme' => 'https',
            'broadcasting.connections.reverb.options.useTLS' => true,
        ]);

        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
    }
}
