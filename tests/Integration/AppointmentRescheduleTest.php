<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\User;
use Tests\TestCase;

class AppointmentRescheduleTest extends TestCase
{
    private function approvedAppointment(array $clinician, array $patient, string $at = '2030-12-31 09:00:00'): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => $at,
            'scheduled_at' => $at,
            'mode' => 'in_person',
            'status' => 'approved',
        ]);
    }

    private function secondClinician(): array
    {
        $user = User::create([
            'name' => 'Dr. Other',
            'email' => 'other-clinician@test.com',
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-TEST-002',
            'specialization' => 'Testing',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    public function test_slots_endpoint_returns_open_whole_hour_slots(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $appt = $this->approvedAppointment($clinician, $patient);

        // 2030-12-31 is a weekday → default 08:00–16:00 window (9 whole-hour slots).
        $slots = $this->actingAs($clinician['user'], 'web')
            ->getJson(route('appointments.reschedule-slots', $appt) . '?date=2030-12-31')
            ->assertStatus(200)
            ->json('slots');

        $this->assertContains('09:00', $slots);
        $this->assertContains('15:00', $slots);
        $this->assertNotContains('07:00', $slots); // before the window
        $this->assertNotContains('09:30', $slots); // not a whole-hour slot
    }

    public function test_slots_exclude_a_time_taken_by_another_appointment(): void
    {
        $clinician = $this->createClinician();
        $mine = $this->createPatient('mine@test.com');
        $other = $this->createPatient('other@test.com');
        $appt = $this->approvedAppointment($clinician, $mine);
        // Same clinician already booked at 10:00 for a different patient.
        $this->approvedAppointment($clinician, $other, '2030-12-31 10:00:00');

        $slots = $this->actingAs($clinician['user'], 'web')
            ->getJson(route('appointments.reschedule-slots', $appt) . '?date=2030-12-31')
            ->json('slots');

        $this->assertContains('09:00', $slots);
        $this->assertNotContains('10:00', $slots);
    }

    public function test_slots_endpoint_forbidden_for_non_managing_clinician(): void
    {
        $owner = $this->createClinician();
        $patient = $this->createPatient();
        $appt = $this->approvedAppointment($owner, $patient);
        $stranger = $this->secondClinician();

        $this->actingAs($stranger['user'], 'web')
            ->getJson(route('appointments.reschedule-slots', $appt) . '?date=2030-12-31')
            ->assertStatus(403);
    }

    public function test_reschedule_to_an_open_slot_succeeds(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $appt = $this->approvedAppointment($clinician, $patient);

        $this->actingAs($clinician['user'], 'web')
            ->patch(route('appointments.reschedule', $appt), [
                'scheduled_at' => '2030-12-31 14:00:00',
            ])
            ->assertRedirect(route('appointments.index'));

        $appt->refresh();
        $this->assertSame('rescheduled', $appt->status);
        $this->assertSame('2030-12-31 14:00:00', $appt->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_reschedule_to_an_out_of_window_time_is_rejected(): void
    {
        // Defense-in-depth: even posted directly, a non-slot time is refused.
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $appt = $this->approvedAppointment($clinician, $patient);

        $this->actingAs($clinician['user'], 'web')
            ->from(route('appointments.index'))
            ->patch(route('appointments.reschedule', $appt), [
                'scheduled_at' => '2030-12-31 03:00:00', // before the 08:00 window
            ])
            ->assertSessionHasErrors('scheduled_at');

        $this->assertSame('approved', $appt->fresh()->status);
    }
}
