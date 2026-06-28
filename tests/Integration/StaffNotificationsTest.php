<?php

namespace Tests\Integration;

use App\Models\Notification;
use Tests\TestCase;

class StaffNotificationsTest extends TestCase
{
    private function notifyUser(int $userId, string $title = 'New Appointment Request'): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => 'appointment_requested',
            'title' => $title,
            'body' => 'A patient requested an appointment.',
        ]);
    }

    public function test_clinician_sees_only_their_own_notifications(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $this->notifyUser($clinician['user']->id, 'Mine');
        $this->notifyUser($patient['user']->id, 'Not mine');

        $this->actingAs($clinician['user'], 'web')
            ->get(route('notifications.index'))
            ->assertStatus(200)
            ->assertSee('Mine')
            ->assertDontSee('Not mine');
    }

    public function test_clinician_can_mark_a_notification_read(): void
    {
        $clinician = $this->createClinician();
        $note = $this->notifyUser($clinician['user']->id);

        $this->actingAs($clinician['user'], 'web')
            ->post(route('notifications.read', $note->id))
            ->assertRedirect();

        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_clinician_can_mark_all_read(): void
    {
        $clinician = $this->createClinician();
        $this->notifyUser($clinician['user']->id);
        $this->notifyUser($clinician['user']->id);

        $this->actingAs($clinician['user'], 'web')
            ->post(route('notifications.readAll'))
            ->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $clinician['user']->id)
            ->whereNull('read_at')->count());
    }

    public function test_clinician_cannot_mark_another_users_notification_read(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $note = $this->notifyUser($patient['user']->id);

        $this->actingAs($clinician['user'], 'web')
            ->post(route('notifications.read', $note->id))
            ->assertStatus(404);

        $this->assertNull($note->fresh()->read_at);
    }

    public function test_patient_cannot_access_staff_notifications(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->get(route('notifications.index'))
            ->assertStatus(403);
    }
}
