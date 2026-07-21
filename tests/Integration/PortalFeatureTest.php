<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\Notification;
use Tests\TestCase;

class PortalFeatureTest extends TestCase
{
    public function test_patient_can_book_an_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->post(route('portal.appointments.store'), [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
                'reason' => 'First visit',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'status' => 'pending',
            'mode' => 'in_person',
        ]);
    }

    public function test_patient_can_cancel_their_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        $this->actingAs($patient['user'], 'web')
            ->delete(route('portal.appointments.destroy', $appt))
            ->assertRedirect(route('portal.appointments.index'));

        $this->assertDatabaseHas('appointments', ['id' => $appt->id, 'status' => 'cancelled']);
    }

    public function test_patient_cannot_view_another_patients_appointment(): void
    {
        $clinician = $this->createClinician();
        $mine = $this->createPatient('mine@test.com');
        $other = $this->createPatient('other@test.com');

        $appt = Appointment::create([
            'patient_id' => $other['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        $this->actingAs($mine['user'], 'web')
            ->get(route('portal.appointments.show', $appt))
            ->assertStatus(403);
    }

    public function test_patient_can_submit_an_assignment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Mindfulness journal',
        ]);

        $this->actingAs($patient['user'], 'web')
            ->post(route('portal.assignments.submit', $assignment), [
                'content' => 'I practiced breathing for 10 minutes daily.',
            ])
            ->assertRedirect(route('portal.assignments.show', $assignment));

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'patient_id' => $patient['patient']->id,
            'status' => 'submitted',
        ]);
    }

    public function test_patient_can_message_their_clinician(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        // Opening the inbox creates the conversation.
        $this->actingAs($patient['user'], 'web')
            ->get(route('portal.messages.index'))
            ->assertStatus(200)
            ->assertSee('tc-conversation-sidebar', false)
            ->assertSee('tc-message-composer', false)
            ->assertSee('Dr. Test')
            ->assertSee('Testing');

        $conversation = Conversation::firstOrFail();

        $this->actingAs($patient['user'], 'web')
            ->post(route('portal.messages.send', $conversation), ['body' => 'Hello doctor'])
            ->assertRedirect(route('portal.messages.index'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $patient['user']->id,
        ]);
        $this->assertSame('Hello doctor', $conversation->messages()->first()->body);
    }

    public function test_patient_can_complete_an_assessment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $assessment = Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'phq9',
            'status' => 'pending',
        ]);

        $this->actingAs($patient['user'], 'web')
            ->post(route('portal.assessments.submit', $assessment), [
                'responses' => [1, 2, 1, 0, 1, 2, 1, 0, 1], // sum = 9
            ])
            ->assertRedirect(route('portal.assessments.show', $assessment));

        $assessment->refresh();
        $this->assertSame('completed', $assessment->status);
        $this->assertSame(9, $assessment->score);
    }

    public function test_patient_can_log_mood(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->post(route('portal.mood.store'), ['score' => 7, 'note' => 'Decent day'])
            ->assertRedirect(route('portal.mood.index'));

        $this->assertDatabaseHas('mood_logs', [
            'patient_id' => $patient['patient']->id,
            'score' => 7,
        ]);
    }

    public function test_patient_can_update_profile(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->put(route('portal.profile.update'), [
                'gender' => 'Female',
                'employment_status' => 'Student',
            ])
            ->assertRedirect(route('portal.profile.show'));

        $this->assertDatabaseHas('patients', [
            'id' => $patient['patient']->id,
            'gender' => 'Female',
            'employment_status' => 'Student',
        ]);
    }

    public function test_patient_can_mark_notification_read(): void
    {
        $patient = $this->createPatient();
        $note = Notification::create([
            'user_id' => $patient['user']->id,
            'type' => 'appointment_approved',
            'title' => 'Approved',
            'body' => 'Your appointment was approved.',
        ]);

        $this->actingAs($patient['user'], 'web')
            ->post(route('portal.notifications.read', $note->id))
            ->assertRedirect();

        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_chatbot_returns_a_reply(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->post(route('portal.chatbot.message'), ['message' => 'How do I book an appointment?'])
            ->assertRedirect();

        // A reply was flashed for the page to render.
        $this->assertNotNull(session('chat'));
    }

    public function test_all_portal_pages_render(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $appt = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'online',
            'status' => 'approved',
        ]);
        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Worksheet',
        ]);
        $assessment = Assessment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'instrument' => 'gad7',
            'status' => 'pending',
        ]);

        $get = fn (string $url) => $this->actingAs($patient['user'], 'web')->get($url)->assertStatus(200);

        $get(route('portal.dashboard'));
        $get(route('portal.appointments.index'));
        $get(route('portal.appointments.book'));
        $get(route('portal.appointments.show', $appt));
        $get(route('portal.assignments.index'));
        $get(route('portal.assignments.show', $assignment));
        $get(route('portal.assessments.index'));
        $get(route('portal.assessments.show', $assessment));
        $get(route('portal.messages.index'));
        $get(route('portal.mood.index'));
        $get(route('portal.goals.index'));
        $get(route('portal.notes.index'));
        $get(route('portal.notifications.index'));
        $get(route('portal.profile.show'));
        $get(route('portal.profile.edit'));
        $get(route('portal.chatbot.index'));
    }
}
