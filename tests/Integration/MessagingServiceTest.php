<?php

namespace Tests\Integration;

use App\Models\Conversation;
use App\Services\MessageService;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MessagingServiceTest extends TestCase
{
    private MessageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageService::class);
    }

    public function test_conversation_for_is_idempotent(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $a = $this->service->conversationFor($patient['patient'], $clinician['clinician']);
        $b = $this->service->conversationFor($patient['patient'], $clinician['clinician']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Conversation::count());
    }

    public function test_patient_send_stores_message_and_notifies_clinician(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $conversation = $this->service->conversationFor($patient['patient'], $clinician['clinician']);
        $message = $this->service->send($conversation, $patient['user'], 'Hello doctor');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $patient['user']->id,
            'body' => 'Hello doctor',
        ]);
        $this->assertNotNull($conversation->fresh()->last_message_at);

        // Clinician (the recipient) is notified.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $clinician['user']->id,
            'type' => 'message_received',
        ]);
        // Sender is NOT notified.
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'message_received',
        ]);
    }

    public function test_clinician_reply_notifies_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $conversation = $this->service->conversationFor($patient['patient'], $clinician['clinician']);
        $this->service->send($conversation, $clinician['user'], 'How are you?');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'message_received',
        ]);
    }

    public function test_unread_count_and_mark_read(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $conversation = $this->service->conversationFor($patient['patient'], $clinician['clinician']);
        $this->service->send($conversation, $patient['user'], 'One');
        $this->service->send($conversation, $patient['user'], 'Two');

        $conversation->refresh()->load('patient', 'clinician');

        // Two unread for the clinician, zero for the patient (the sender).
        $this->assertSame(2, $conversation->unreadCountFor($clinician['user']));
        $this->assertSame(0, $conversation->unreadCountFor($patient['user']));

        $this->service->markRead($conversation, $clinician['user']);

        $this->assertSame(0, $conversation->fresh()->load('patient', 'clinician')
            ->unreadCountFor($clinician['user']));
    }

    public function test_policy_blocks_non_participants(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $outsider = $this->createPatient('outsider@test.com');

        $conversation = $this->service->conversationFor($patient['patient'], $clinician['clinician'])
            ->load('patient', 'clinician');

        $this->assertTrue(Gate::forUser($patient['user'])->allows('participate', $conversation));
        $this->assertTrue(Gate::forUser($clinician['user'])->allows('participate', $conversation));
        $this->assertFalse(Gate::forUser($outsider['user'])->allows('participate', $conversation));
    }
}
