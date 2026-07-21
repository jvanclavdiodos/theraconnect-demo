<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MessageService;
use Tests\TestCase;

class MessagingApiTest extends TestCase
{
    private function makeClinician(string $email): array
    {
        $user = User::create([
            'name' => 'Dr. '.ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-'.strtoupper(substr(md5($email), 0, 6)),
            'specialization' => 'CBT',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    /** A patient assigned to a fresh clinician, with an API token. */
    private function assignedPatient(string $email = 'patient@test.com'): array
    {
        $clinician = $this->makeClinician('dr-'.$email);
        $patient = $this->createPatient($email);
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        return [
            'clinician' => $clinician,
            'patient' => $patient,
            'headers' => $this->apiHeaders($this->getApiToken($patient['user'])),
        ];
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->postJson('/api/v1/conversations')->assertStatus(401);
    }

    public function test_patient_without_clinician_cannot_open_conversation(): void
    {
        $patient = $this->createPatient(); // no assigned clinician
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        $this->withHeaders($headers)
            ->postJson('/api/v1/conversations')
            ->assertStatus(422);
    }

    public function test_patient_opens_thread_and_sends_message(): void
    {
        $ctx = $this->assignedPatient();

        $conv = $this->withHeaders($ctx['headers'])
            ->postJson('/api/v1/conversations')
            ->assertStatus(201)
            ->json('data');

        $this->assertSame($ctx['clinician']['clinician']->id, $conv['clinician_id']);

        $this->withHeaders($ctx['headers'])
            ->postJson("/api/v1/conversations/{$conv['id']}/messages", ['body' => 'Hi doc'])
            ->assertStatus(201)
            ->assertJsonPath('data.body', 'Hi doc')
            ->assertJsonPath('data.is_mine', true);

        // Clinician notified.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $ctx['clinician']['user']->id,
            'type' => 'message_received',
        ]);
    }

    public function test_list_reports_unread_and_messages_marks_read(): void
    {
        $ctx = $this->assignedPatient();

        // Clinician sends two messages to the patient.
        $conversation = app(MessageService::class)
            ->conversationFor($ctx['patient']['patient'], $ctx['clinician']['clinician']);
        app(MessageService::class)
            ->send($conversation, $ctx['clinician']['user'], 'one');
        app(MessageService::class)
            ->send($conversation, $ctx['clinician']['user'], 'two');

        // Patient's inbox shows unread.
        $this->withHeaders($ctx['headers'])
            ->getJson('/api/v1/conversations')
            ->assertStatus(200)
            ->assertJsonPath('data.0.unread_count', 2);

        // Reading the thread marks it read.
        $this->withHeaders($ctx['headers'])
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $this->withHeaders($ctx['headers'])
            ->getJson('/api/v1/conversations')
            ->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_patient_cannot_access_another_pairs_conversation(): void
    {
        $a = $this->assignedPatient('a@test.com');
        $b = $this->assignedPatient('b@test.com');

        $conversation = Conversation::create([
            'patient_id' => $a['patient']['patient']->id,
            'clinician_id' => $a['clinician']['clinician']->id,
        ]);

        $this->withHeaders($b['headers'])
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertStatus(403);
    }

    public function test_former_assignment_can_read_history_but_cannot_send(): void
    {
        $ctx = $this->assignedPatient('former@test.com');
        $conversation = app(MessageService::class)
            ->conversationFor($ctx['patient']['patient'], $ctx['clinician']['clinician']);
        $ctx['patient']['patient']->update(['assigned_clinician_id' => null]);

        $this->withHeaders($ctx['headers'])
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk();

        $this->withHeaders($ctx['headers'])
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Not allowed'])
            ->assertForbidden();
    }
}
