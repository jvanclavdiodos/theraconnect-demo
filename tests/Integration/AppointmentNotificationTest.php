<?php

namespace Tests\Integration;

use App\Models\Appointment;
use Tests\TestCase;

class AppointmentNotificationTest extends TestCase
{
    public function test_booking_notifies_the_assigned_clinician(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $clinician['user']->id,
            'type' => 'appointment_requested',
        ]);
    }

    public function test_booking_without_a_clinician_notifies_no_one(): void
    {
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
            ])
            ->assertStatus(201);

        $this->assertDatabaseMissing('notifications', [
            'type' => 'appointment_requested',
        ]);
    }

    public function test_admin_reschedule_notifies_both_patient_and_clinician(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $this->actingAs($admin, 'web')
            ->patch("/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => '2030-12-31 11:00:00',
            ])
            ->assertRedirect();

        // Patient is told their appointment moved.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'appointment_rescheduled',
        ]);
        // Clinician is told their schedule changed.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $clinician['user']->id,
            'type' => 'appointment_rescheduled',
        ]);
    }

    public function test_clinician_rescheduling_own_appointment_does_not_self_notify(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->patch("/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => '2030-12-31 11:00:00',
            ])
            ->assertRedirect();

        // Patient still notified.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $patient['user']->id,
            'type' => 'appointment_rescheduled',
        ]);
        // The clinician who performed it is NOT notified of their own action.
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $clinician['user']->id,
        ]);
    }
}
