<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Tests\TestCase;

class MessagingWebTest extends TestCase
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

    public function test_admin_cannot_access_messages(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')->get('/messages')->assertStatus(403);
    }

    public function test_clinician_inbox_renders(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->get('/messages')
            ->assertStatus(200)
            ->assertSee('Messages')
            ->assertSee('tc-conversation-sidebar', false);
    }

    public function test_clinician_opens_and_messages_caseload_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        // Open the thread.
        $this->actingAs($clinician['user'], 'web')
            ->post('/messages/open', ['patient_id' => $patient['patient']->id])
            ->assertRedirect();

        $conversation = Conversation::firstOrFail();

        $this->actingAs($clinician['user'], 'web')
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('tc-message-composer', false)
            ->assertSee('data-realtime-fragment="messages-sidebar"', false)
            ->assertSee($patient['user']->name);

        // Send a message.
        $this->actingAs($clinician['user'], 'web')
            ->post("/messages/{$conversation->id}", ['body' => 'Hello from your clinician'])
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $clinician['user']->id,
        ]);
        $this->assertSame(
            'Hello from your clinician',
            Message::where('conversation_id', $conversation->id)->first()->body
        );
        $message = Message::where('conversation_id', $conversation->id)->firstOrFail();
        $this->actingAs($clinician['user'], 'web')
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('data-message-id="'.$message->id.'"', false);
        // Patient was notified.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'message_received',
        ]);
    }

    public function test_clinician_cannot_open_non_caseload_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient(); // not assigned to this clinician

        $this->actingAs($clinician['user'], 'web')
            ->post('/messages/open', ['patient_id' => $patient['patient']->id])
            ->assertStatus(403);
    }

    public function test_clinician_can_send_message_as_json_without_changing_normal_redirects(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->assignClinician($clinician['clinician']);
        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->postJson(route('messages.store', $conversation), ['body' => 'Sent asynchronously'])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.sender_id', $clinician['user']->id)
            ->assertJsonPath('data.body', 'Sent asynchronously')
            ->assertJsonStructure(['data' => ['id', 'created_at', 'created_at_label']]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $clinician['user']->id,
        ]);
    }

    public function test_patient_can_send_message_as_json_and_validation_remains_json(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->assignClinician($clinician['clinician']);
        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);

        $this->actingAs($patient['user'], 'web')
            ->postJson(route('portal.messages.send', $conversation), ['body' => 'Hello asynchronously'])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.sender_id', $patient['user']->id)
            ->assertJsonPath('data.body', 'Hello asynchronously');

        $this->actingAs($patient['user'], 'web')
            ->postJson(route('portal.messages.send', $conversation), ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_assigned_patient_appears_in_start_conversation_dropdown(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->assignClinician($clinician['clinician']);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('<option value="'.$patient['patient']->id.'">'.$patient['user']->name.'</option>', false);
    }

    public function test_clinician_cannot_view_another_clinicians_conversation(): void
    {
        $owner = $this->makeClinician('owner@test.com');
        $other = $this->makeClinician('other@test.com');
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $owner['clinician']->id]);

        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $owner['clinician']->id,
        ]);

        $this->actingAs($other['user'], 'web')
            ->get("/messages/{$conversation->id}")
            ->assertStatus(403);
    }

    public function test_clinician_cannot_list_or_message_a_formerly_assigned_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);
        $conversation = Conversation::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);

        $patient['patient']->update(['assigned_clinician_id' => null]);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('messages.index'))
            ->assertOk()
            ->assertDontSee($patient['user']->name);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('conversation is read-only')
            ->assertDontSee('tc-message-composer', false);

        $this->actingAs($clinician['user'], 'web')
            ->post(route('messages.store', $conversation), ['body' => 'Not allowed'])
            ->assertForbidden();

        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id]);
    }
}
