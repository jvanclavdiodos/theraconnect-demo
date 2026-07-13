<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\User;
use App\Services\AppointmentService;
use Tests\TestCase;

class ManyToManyClinicianRelationshipTest extends TestCase
{
    private function makeClinician(string $email, string $license): array
    {
        $user = User::create([
            'name' => 'Dr. '.ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);

        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => $license,
            'specialization' => 'Counseling',
        ]);

        return compact('user', 'clinician');
    }

    public function test_pending_booking_grants_messaging_access_only_after_approval(): void
    {
        $patient = $this->createPatient();
        $clinician = $this->createClinician();
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        app(AppointmentService::class)->approve($appointment);

        $this->assertDatabaseHas('clinician_patient', [
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.clinician_id', $clinician['clinician']->id);
    }

    public function test_approving_another_clinician_preserves_existing_relationship_and_threads(): void
    {
        $patient = $this->createPatient();
        $first = $this->makeClinician('first@test.com', 'LIC-MULTI-001');
        $second = $this->makeClinician('second@test.com', 'LIC-MULTI-002');
        $patient['patient']->assignClinician($first['clinician']);

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $second['clinician']->id,
            'requested_at' => '2030-12-31 10:00:00',
            'mode' => 'online',
            'status' => 'pending',
        ]);

        app(AppointmentService::class)->approve($appointment);

        $patient['patient']->refresh();
        $this->assertSame($first['clinician']->id, $patient['patient']->assigned_clinician_id);
        $this->assertTrue($patient['patient']->isAssignedTo($first['clinician']));
        $this->assertTrue($patient['patient']->isAssignedTo($second['clinician']));

        $headers = $this->apiHeaders($this->getApiToken($patient['user']));
        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$first['clinician']->id, $second['clinician']->id],
            collect($response->json('data'))->pluck('clinician_id')->all()
        );

        $this->withHeaders($headers)
            ->postJson('/api/v1/conversations')
            ->assertUnprocessable();
        $this->withHeaders($headers)
            ->postJson('/api/v1/conversations', ['clinician_id' => $second['clinician']->id])
            ->assertCreated()
            ->assertJsonPath('data.clinician_id', $second['clinician']->id);
        $this->withHeaders($headers)
            ->postJson('/api/v1/conversations', ['clinician_id' => 999999])
            ->assertUnprocessable();

        $this->actingAs($first['user'], 'web')->get('/patients')->assertSee($patient['user']->name);
        $this->actingAs($second['user'], 'web')->get('/patients')->assertSee($patient['user']->name);
    }

    public function test_admin_can_assign_multiple_clinicians_when_creating_a_patient(): void
    {
        $admin = $this->createAdmin();
        $first = $this->makeClinician('admin-first@test.com', 'LIC-ADMIN-001');
        $second = $this->makeClinician('admin-second@test.com', 'LIC-ADMIN-002');

        $this->actingAs($admin, 'web')->post('/patients', [
            'name' => 'Multi Care Patient',
            'email' => 'multi-care@test.com',
            'password' => 'Password1!',
            'assigned_clinician_ids' => [
                $first['clinician']->id,
                $second['clinician']->id,
            ],
        ])->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('clinician_patient', [
            'patient_id' => User::where('email', 'multi-care@test.com')->firstOrFail()->patient->id,
            'clinician_id' => $first['clinician']->id,
        ]);
        $this->assertDatabaseHas('clinician_patient', [
            'patient_id' => User::where('email', 'multi-care@test.com')->firstOrFail()->patient->id,
            'clinician_id' => $second['clinician']->id,
        ]);
    }
}
