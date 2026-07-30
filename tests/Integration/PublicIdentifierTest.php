<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Assignment;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicIdentifierTest extends TestCase
{
    public function test_exposed_models_receive_unique_ulid_public_identifiers(): void
    {
        $first = $this->createPatient('public-one@test.com');
        $second = $this->createPatient('public-two@test.com');

        foreach ([$first['user'], $first['patient'], $second['user'], $second['patient']] as $model) {
            $this->assertTrue(Str::isUlid($model->public_id));
            $this->assertSame('public_id', $model->getRouteKeyName());
        }

        $this->assertNotSame($first['user']->public_id, $second['user']->public_id);
        $this->assertNotSame($first['patient']->public_id, $second['patient']->public_id);
    }

    public function test_numeric_malformed_and_unknown_appointment_identifiers_do_not_resolve(): void
    {
        $appointment = $this->createAppointment();
        $user = $appointment->patient->user;

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/appointments/{$appointment->id}")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/appointments/not-a-ulid')
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/appointments/01H00000000000000000000000')
            ->assertNotFound();
    }

    public function test_authorized_patient_can_use_public_id_without_exposing_numeric_ids(): void
    {
        $appointment = $this->createAppointment();

        $response = $this->actingAs($appointment->patient->user, 'sanctum')
            ->getJson("/api/v1/appointments/{$appointment->public_id}")
            ->assertOk()
            ->assertJsonPath('data.public_id', $appointment->public_id)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.patient_id')
            ->assertJsonMissingPath('data.clinician_id');

        $this->assertStringNotContainsString(
            "/appointments/{$appointment->public_id}",
            $response->getContent()
        );
    }

    public function test_patient_cannot_access_another_patients_appointment_by_public_id(): void
    {
        $appointment = $this->createAppointment();
        $other = $this->createPatient('public-other@test.com');

        $this->actingAs($other['user'], 'sanctum')
            ->getJson("/api/v1/appointments/{$appointment->public_id}")
            ->assertForbidden();
    }

    public function test_patient_cannot_mix_another_patients_assignment_into_public_route(): void
    {
        $clinician = $this->createClinician();
        $owner = $this->createPatient('assignment-owner@test.com');
        $other = $this->createPatient('assignment-other@test.com');
        $assignment = Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $owner['patient']->id,
            'title' => 'Private worksheet',
            'instructions' => 'Owner only',
            'due_at' => now()->addDay(),
            'status' => 'assigned',
        ]);

        $this->actingAs($other['user'], 'sanctum')
            ->getJson("/api/v1/assignments/{$assignment->public_id}")
            ->assertForbidden();
    }

    public function test_route_generation_uses_public_id_instead_of_primary_key(): void
    {
        $patient = $this->createPatient();

        $url = route('patients.show', $patient['patient']);

        $this->assertStringContainsString($patient['patient']->public_id, $url);
        $this->assertStringNotContainsString('/patients/'.$patient['patient']->id, $url);
    }

    private function createAppointment(): Appointment
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        return Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDay(),
            'mode' => 'in_person',
            'status' => 'pending',
        ])->load('patient.user', 'clinician.user');
    }
}
