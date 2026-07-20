<?php

namespace Tests\Integration;

use App\Models\Appointment;
use Tests\TestCase;

class PatientAppointmentIndexTest extends TestCase
{
    public function test_portal_sorts_by_the_effective_date_shown_to_the_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('portal-sort@test.com');

        $this->appointment($patient['patient']->id, $clinician['clinician']->id, '2030-01-01 09:00:00', [
            'scheduled_at' => '2030-03-01 09:00:00',
            'reason' => 'March effective date',
        ]);
        $this->appointment($patient['patient']->id, $clinician['clinician']->id, '2030-02-01 09:00:00', [
            'reason' => 'February effective date',
        ]);

        $this->actingAs($patient['user'])
            ->get('/portal/appointments?sort=appointment_date&direction=asc')
            ->assertOk()
            ->assertSeeInOrder(['Feb 1, 2030', 'Mar 1, 2030']);

        $this->actingAs($patient['user'])
            ->get('/portal/appointments?sort=appointment_date&direction=desc')
            ->assertOk()
            ->assertSeeInOrder(['Mar 1, 2030', 'Feb 1, 2030']);
    }

    public function test_portal_filters_and_paginates_without_exposing_another_patient(): void
    {
        $clinician = $this->createClinician();
        $owner = $this->createPatient('portal-filter@test.com');
        $other = $this->createPatient('portal-filter-other@test.com');

        for ($i = 1; $i <= 16; $i++) {
            $this->appointment($owner['patient']->id, $clinician['clinician']->id, sprintf('2030-04-%02d 09:00:00', $i), [
                'status' => 'approved',
                'mode' => 'online',
            ]);
        }
        $this->appointment($owner['patient']->id, $clinician['clinician']->id, '2030-05-01 09:00:00', [
            'status' => 'pending',
            'mode' => 'in_person',
        ]);
        $this->appointment($other['patient']->id, $clinician['clinician']->id, '2030-06-01 09:00:00', [
            'status' => 'approved',
            'mode' => 'online',
            'reason' => 'OTHER PATIENT MARKER',
        ]);

        $response = $this->actingAs($owner['user'])
            ->get('/portal/appointments?status=approved&mode=online&sort=appointment_date&direction=desc');

        $response->assertOk()
            ->assertViewHas('appointments', fn ($appointments) => $appointments->count() === 15 && $appointments->total() === 16)
            ->assertSee('status=approved', false)
            ->assertDontSee('OTHER PATIENT MARKER');
    }

    public function test_api_filters_sorts_paginates_and_isolates_patient_appointments(): void
    {
        $clinician = $this->createClinician();
        $owner = $this->createPatient('api-filter@test.com');
        $other = $this->createPatient('api-filter-other@test.com');
        $token = $this->getApiToken($owner['user']);

        $older = $this->appointment($owner['patient']->id, $clinician['clinician']->id, '2030-07-01 09:00:00', [
            'status' => 'approved',
            'mode' => 'online',
        ]);
        $newer = $this->appointment($owner['patient']->id, $clinician['clinician']->id, '2030-08-01 09:00:00', [
            'status' => 'approved',
            'mode' => 'online',
        ]);
        $this->appointment($owner['patient']->id, $clinician['clinician']->id, '2030-09-01 09:00:00', [
            'status' => 'pending',
            'mode' => 'in_person',
        ]);
        $this->appointment($other['patient']->id, $clinician['clinician']->id, '2030-10-01 09:00:00', [
            'status' => 'approved',
            'mode' => 'online',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/appointments?status=approved&mode=online&sort=appointment_date&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/appointments?direction=sideways')
            ->assertUnprocessable();
    }

    private function appointment(int $patientId, int $clinicianId, string $requestedAt, array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'patient_id' => $patientId,
            'clinician_id' => $clinicianId,
            'requested_at' => $requestedAt,
            'mode' => 'in_person',
            'status' => 'pending',
        ], $overrides));
    }
}
