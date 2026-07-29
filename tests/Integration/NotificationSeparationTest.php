<?php

namespace Tests\Integration;

use App\Models\Notification;
use App\Services\MessageService;
use Tests\TestCase;

class NotificationSeparationTest extends TestCase
{
    public function test_clinician_message_activity_uses_messages_badge_not_general_notifications(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('notification-separation-clinician@test.com');
        $patient['patient']->assignClinician($clinician['clinician']);
        $conversation = app(MessageService::class)
            ->conversationFor($patient['patient'], $clinician['clinician']);

        app(MessageService::class)->send($conversation, $patient['user'], 'Private message');
        $this->generalNotification($clinician['user']->id, 'General update');

        $response = $this->actingAs($clinician['user'])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('General update')
            ->assertDontSee('New message from '.$patient['user']->name);

        $this->assertMatchesRegularExpression(
            '/data-realtime-notification-count\s+data-count="1"/',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/data-realtime-message-count\s+data-count="1"/',
            $response->getContent()
        );
    }

    public function test_patient_message_activity_is_excluded_from_portal_and_api_notifications(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('notification-separation-patient@test.com');
        $patient['patient']->assignClinician($clinician['clinician']);
        $conversation = app(MessageService::class)
            ->conversationFor($patient['patient'], $clinician['clinician']);

        app(MessageService::class)->send($conversation, $clinician['user'], 'Private message');
        $general = $this->generalNotification($patient['user']->id, 'Appointment update');
        $messageNotification = Notification::where('user_id', $patient['user']->id)
            ->where('type', Notification::MESSAGE_RECEIVED)
            ->firstOrFail();

        $response = $this->actingAs($patient['user'])
            ->get(route('portal.notifications.index'))
            ->assertOk()
            ->assertSee('Appointment update')
            ->assertDontSee('New message from '.$clinician['user']->name);

        $this->assertMatchesRegularExpression(
            '/data-realtime-notification-count\s+data-count="1"/',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/data-realtime-message-count\s+data-count="1"/',
            $response->getContent()
        );

        $headers = $this->apiHeaders($this->getApiToken($patient['user']));
        $this->withHeaders($headers)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $general->id)
            ->assertJsonMissing(['id' => $messageNotification->id]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/notifications/{$messageNotification->id}/read")
            ->assertNotFound();
    }

    public function test_mark_all_read_leaves_message_delivery_rows_untouched(): void
    {
        $clinician = $this->createClinician();
        $message = Notification::create([
            'user_id' => $clinician['user']->id,
            'type' => Notification::MESSAGE_RECEIVED,
            'title' => 'New message',
            'body' => 'Private message',
        ]);
        $general = $this->generalNotification($clinician['user']->id, 'General update');

        $this->actingAs($clinician['user'])
            ->post(route('notifications.readAll'))
            ->assertRedirect();

        $this->assertNotNull($general->fresh()->read_at);
        $this->assertNull($message->fresh()->read_at);
    }

    private function generalNotification(int $userId, string $title): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => 'appointment_approved',
            'title' => $title,
            'body' => 'General notification body.',
        ]);
    }
}
