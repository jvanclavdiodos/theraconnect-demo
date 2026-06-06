<?php

namespace Tests\Integration;

use Tests\TestCase;

class AppointmentFlowTest extends TestCase
{
    public function test_patient_can_book_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-06-10 09:00:00',
                'mode' => 'in_person',
                'reason' => 'Initial consultation',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.mode', 'in_person')
            ->assertJsonPath('data.reason', 'Initial consultation');

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'status' => 'pending',
            'mode' => 'in_person',
        ]);
    }

    public function test_patient_can_view_their_appointments(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        // Book an appointment first
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-06-10 10:00:00',
                'mode' => 'online',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/appointments');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.mode', 'online');
    }

    public function test_patient_cannot_view_other_patients_appointments(): void
    {
        $clinician = $this->createClinician();
        $patientA = $this->createPatient('a@test.com');
        $patientB = $this->createPatient('b@test.com');

        $tokenA = $this->getApiToken($patientA['user']);

        $createResponse = $this->withHeaders($this->apiHeaders($tokenA))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-06-10 11:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $appointmentId = $createResponse->json('data.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'patient_id' => $patientA['patient']->id,
        ]);
        $this->assertNotEquals($patientA['patient']->id, $patientB['patient']->id);

        // Patient A can view (actingAs with sanctum guard)
        $this->actingAs($patientA['user'], 'sanctum')
            ->getJson("/api/v1/appointments/{$appointmentId}")
            ->assertOk();

        // Patient B should be forbidden (actingAs with sanctum guard)
        $this->actingAs($patientB['user'], 'sanctum')
            ->getJson("/api/v1/appointments/{$appointmentId}")
            ->assertForbidden();
    }

    public function test_patient_can_cancel_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $create = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-06-10 14:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $id = $create->json('data.id');

        $response = $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('appointments', [
            'id' => $id,
            'status' => 'cancelled',
        ]);
    }

    public function test_double_cancel_returns_409(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $create = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-06-10 15:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $id = $create->json('data.id');

        // First cancel
        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$id}");

        // Second cancel
        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$id}")
            ->assertStatus(409);
    }

    public function test_schedule_slots_contain_correct_data(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/schedules?date=2026-06-10');

        $response->assertStatus(200)
            ->assertJsonCount(9, 'data');

        $firstSlot = $response->json('data.0');
        $this->assertArrayHasKey('slot', $firstSlot);
        $this->assertArrayHasKey('clinician_id', $firstSlot);
        $this->assertArrayHasKey('clinician_name', $firstSlot);
        $this->assertArrayHasKey('available', $firstSlot);
        $this->assertTrue($firstSlot['available']);
    }

    public function test_schedule_shows_booked_slot_as_unavailable(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        // Book the 09:00 slot
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2026-06-10 09:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        // After booking one slot, total slots should remain 9 (all slots still listed, some unavailable)
        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/schedules?date=2026-06-10');

        $response->assertStatus(200)
            ->assertJsonCount(9, 'data');

        // At least one slot should be unavailable
        $hasUnavailable = collect($response->json('data'))->contains('available', false);
        $this->assertTrue($hasUnavailable, 'Expected at least one unavailable slot after booking');
    }
}
