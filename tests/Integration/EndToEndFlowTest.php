<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Notification;
use Tests\TestCase;

class EndToEndFlowTest extends TestCase
{
    public function test_full_appointment_lifecycle(): void
    {
        // 1. Create clinician and patient
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e2e-patient@test.com');
        $patientToken = $this->getApiToken($patient['user']);

        // 2. Patient books an appointment via API (mobile app)
        $bookResponse = $this->withHeaders($this->apiHeaders($patientToken))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-07-01 09:00:00',
                'mode' => 'in_person',
                'reason' => 'Follow-up session',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $bookResponse->assertStatus(201);
        $appointmentId = $bookResponse->json('data.id');
        $this->assertEquals('pending', $bookResponse->json('data.status'));

        // 3. Patient can see their appointment
        $this->withHeaders($this->apiHeaders($patientToken))
            ->getJson('/api/v1/appointments')
            ->assertJsonCount(1, 'data');

        // 4. Patient views appointment detail
        $this->withHeaders($this->apiHeaders($patientToken))
            ->getJson("/api/v1/appointments/{$appointmentId}")
            ->assertStatus(200)
            ->assertJsonPath('data.reason', 'Follow-up session');

        // 5. Patient cancels the appointment
        $cancelResponse = $this->withHeaders($this->apiHeaders($patientToken))
            ->deleteJson("/api/v1/appointments/{$appointmentId}");

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // 6. Database reflects cancelled state
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => 'cancelled',
        ]);

        // 7. Patient books another appointment
        $this->withHeaders($this->apiHeaders($patientToken))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-07-02 10:00:00',
                'mode' => 'online',
                'reason' => 'Video check-in',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(201);

        // Should now have 2 appointment records (1 cancelled, 1 pending)
        $this->assertEquals(2, Appointment::count());
    }

    public function test_full_assignment_lifecycle(): void
    {
        // 1. Setup
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e2e-assign@test.com');
        $patientToken = $this->getApiToken($patient['user']);

        // 2. Clinician creates assignment (simulated directly since web routes need session)
        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Weekly Reflection',
            'description' => 'Write a 500-word reflection on your week.',
            'due_date' => '2026-07-10 00:00:00',
        ]);

        // 3. Patient can see the assignment
        $listResponse = $this->withHeaders($this->apiHeaders($patientToken))
            ->getJson('/api/v1/assignments');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Weekly Reflection')
            ->assertJsonPath('data.0.submission_status', null); // Not submitted yet

        // 4. Patient views assignment detail
        $this->withHeaders($this->apiHeaders($patientToken))
            ->getJson("/api/v1/assignments/{$assignment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Weekly Reflection');

        // 5. Patient submits assignment
        $submitResponse = $this->withHeaders($this->apiHeaders($patientToken))
            ->postJson("/api/v1/assignments/{$assignment->id}/submit", [
                'content' => 'This week I practiced mindfulness daily and noticed reduced anxiety.',
            ]);

        $submitResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'patient_id' => $patient['patient']->id,
            'content' => 'This week I practiced mindfulness daily and noticed reduced anxiety.',
        ]);

        // 6. Assignment list now shows submission_status
        $this->withHeaders($this->apiHeaders($patientToken))
            ->getJson('/api/v1/assignments')
            ->assertJsonPath('data.0.submission_status', 'submitted');
    }

    public function test_notification_creation_on_appointment_and_assignment(): void
    {
        // 1. Setup
        $clinician = $this->createClinician();
        $patient = $this->createPatient('e2e-notif@test.com');
        $patientToken = $this->getApiToken($patient['user']);

        // 2. Create a notification directly (simulates what happens when clinician approves)
        Notification::create([
            'user_id' => $patient['user']->id,
            'type' => 'appointment_approved',
            'title' => 'Appointment Approved',
            'body' => 'Your appointment on July 1 at 09:00 is confirmed.',
            'data' => json_encode(['appointment_id' => 1]),
            'channel' => 'fcm',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $patient['user']->id,
            'type' => 'assignment_created',
            'title' => 'New Assignment',
            'body' => 'Dr. Test assigned you: Weekly Reflection.',
            'data' => json_encode(['assignment_id' => 1]),
            'channel' => 'fcm',
        ]);

        // 3. Patient sees notifications
        $response = $this->withHeaders($this->apiHeaders($patientToken))
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $types = collect($response->json('data'))->pluck('type')->toArray();
        $this->assertContains('appointment_approved', $types);
        $this->assertContains('assignment_created', $types);

        // 4. Patient marks first as read
        $firstId = $response->json('data.0.id');
        $this->withHeaders($this->apiHeaders($patientToken))
            ->postJson("/api/v1/notifications/{$firstId}/read")
            ->assertStatus(200);

        // 5. Reload - first should be read, second unread
        $recheck = $this->withHeaders($this->apiHeaders($patientToken))
            ->getJson('/api/v1/notifications');

        $readIds = collect($recheck->json('data'))
            ->whereNotNull('read_at')
            ->pluck('id')
            ->toArray();

        $this->assertContains($firstId, $readIds);
    }

    public function test_schedule_conflict_detection(): void
    {
        $clinician = $this->createClinician();
        $patientA = $this->createPatient('conflict-a@test.com');
        $patientB = $this->createPatient('conflict-b@test.com');
        $tokenA = $this->getApiToken($patientA['user']);
        $tokenB = $this->getApiToken($patientB['user']);

        // Patient A books 09:00 slot
        $this->withHeaders($this->apiHeaders($tokenA))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-07-15 09:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(201);

        // Patient B still sees 9 slots (all listed, some unavailable)
        $slots = $this->withHeaders($this->apiHeaders($tokenB))
            ->getJson('/api/v1/schedules?date=2026-07-15');

        $slots->assertStatus(200)
            ->assertJsonCount(9, 'data');

        // At least one slot should be unavailable
        $hasUnavailable = collect($slots->json('data'))->contains('available', false);
        $this->assertTrue($hasUnavailable, 'Expected at least one unavailable slot after booking');
    }
}
