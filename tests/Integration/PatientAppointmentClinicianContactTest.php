<?php

namespace Tests\Integration;

use App\Models\Appointment;
use Tests\TestCase;

class PatientAppointmentClinicianContactTest extends TestCase
{
    public function test_patient_sees_booked_clinician_contact_details_in_portal_and_api(): void
    {
        $patient = $this->createPatient();
        $clinician = $this->createClinician();
        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDay(),
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $this->actingAs($patient['user'])
            ->get(route('portal.appointments.show', $appointment))
            ->assertOk()
            ->assertSee('Clinician contact')
            ->assertSee('clinician@test.com')
            ->assertSee('555-0100');

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->getJson('/api/v1/appointments/'.$appointment->id)
            ->assertOk()
            ->assertJsonPath('data.clinician_contact.email', 'clinician@test.com')
            ->assertJsonPath('data.clinician_contact.phone', '555-0100')
            ->assertJsonPath('data.clinician_contact.specialization', 'Testing');
    }

    public function test_another_patient_cannot_access_the_appointment_or_contact_details(): void
    {
        $owner = $this->createPatient('owner-contact@test.com');
        $other = $this->createPatient('other-contact@test.com');
        $clinician = $this->createClinician();
        $appointment = Appointment::create([
            'patient_id' => $owner['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDay(),
            'mode' => 'online',
            'status' => 'pending',
        ]);

        $this->actingAs($other['user'])->get(route('portal.appointments.show', $appointment))->assertForbidden();
        $this->withHeaders($this->apiHeaders($this->getApiToken($other['user'])))
            ->getJson('/api/v1/appointments/'.$appointment->id)
            ->assertForbidden()
            ->assertDontSee('clinician@test.com');
    }
}
