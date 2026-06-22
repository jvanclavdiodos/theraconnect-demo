<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\User;
use Tests\TestCase;

class AppointmentCompleteTest extends TestCase
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

    private function appointmentFor(Clinician $clinician, string $status = 'approved'): Appointment
    {
        $patient = $this->createPatient('p-'.$clinician->id.'@test.com');

        return Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician->id,
            'requested_at' => now()->addDay(),
            'scheduled_at' => now()->addDay(),
            'mode' => 'online',
            'status' => $status,
        ]);
    }

    public function test_clinician_can_complete_own_approved_appointment(): void
    {
        $clinician = $this->createClinician();
        $appt = $this->appointmentFor($clinician['clinician']);

        $this->actingAs($clinician['user'], 'web')
            ->patch("/appointments/{$appt->id}/complete")
            ->assertRedirect(route('appointments.index'));

        $this->assertSame('completed', $appt->fresh()->status);
    }

    public function test_cannot_complete_a_pending_appointment(): void
    {
        $clinician = $this->createClinician();
        $appt = $this->appointmentFor($clinician['clinician'], 'pending');

        $this->actingAs($clinician['user'], 'web')
            ->patch("/appointments/{$appt->id}/complete")
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $appt->fresh()->status);
    }

    public function test_other_clinician_cannot_complete(): void
    {
        $owner = $this->makeClinician('owner@test.com');
        $other = $this->makeClinician('other@test.com');
        $appt = $this->appointmentFor($owner['clinician']);

        $this->actingAs($other['user'], 'web')
            ->patch("/appointments/{$appt->id}/complete")
            ->assertStatus(403);

        $this->assertSame('approved', $appt->fresh()->status);
    }

    public function test_index_shows_conclude_prompt(): void
    {
        $clinician = $this->createClinician();
        $this->appointmentFor($clinician['clinician']);

        $this->actingAs($clinician['user'], 'web')
            ->get('/appointments')
            ->assertStatus(200)
            ->assertSee('open-conclude')
            ->assertSee('close case');
    }
}
