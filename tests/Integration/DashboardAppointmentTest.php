<?php

namespace Tests\Integration;

use App\Models\Appointment;
use Carbon\Carbon;
use Tests\TestCase;

class DashboardAppointmentTest extends TestCase
{
    private function appointment(array $clinician, string $patientEmail, string $patientName, array $attributes): Appointment
    {
        $patient = $this->createPatient($patientEmail);
        $patient['user']->update(['name' => $patientName]);

        return Appointment::create(array_merge([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDay(),
            'scheduled_at' => null,
            'mode' => 'in_person',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_dashboard_only_lists_active_future_appointments(): void
    {
        $clinician = $this->createClinician();

        $this->appointment($clinician, 'dashboard-past@test.com', 'Past Appointment', [
            'requested_at' => now()->subDay(),
            'scheduled_at' => now()->subDay(),
            'status' => 'approved',
        ]);
        $this->appointment($clinician, 'dashboard-cancelled@test.com', 'Cancelled Appointment', [
            'requested_at' => now()->addDay(),
            'scheduled_at' => now()->addDay(),
            'status' => 'cancelled',
        ]);
        $this->appointment($clinician, 'dashboard-future@test.com', 'Future Appointment', [
            'requested_at' => now()->addHours(2),
            'scheduled_at' => now()->addHours(2),
            'status' => 'approved',
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Future Appointment')
            ->assertDontSee('Past Appointment')
            ->assertDontSee('Cancelled Appointment');
    }

    public function test_dashboard_uses_scheduled_time_and_orders_soonest_first(): void
    {
        $clinician = $this->createClinician();

        $this->appointment($clinician, 'dashboard-later@test.com', 'Later Appointment', [
            'requested_at' => now()->addDays(3),
            'scheduled_at' => now()->addDays(3),
            'status' => 'approved',
        ]);
        $this->appointment($clinician, 'dashboard-rescheduled@test.com', 'Rescheduled Future', [
            'requested_at' => now()->subDay(),
            'scheduled_at' => now()->addDay(),
            'status' => 'rescheduled',
        ]);
        $this->appointment($clinician, 'dashboard-moved-past@test.com', 'Moved Into Past', [
            'requested_at' => now()->addDays(4),
            'scheduled_at' => now()->subHour(),
            'status' => 'approved',
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Rescheduled Future', 'Later Appointment'])
            ->assertDontSee('Moved Into Past');
    }

    public function test_clinician_dashboard_displays_effective_future_time_for_rescheduled_appointment(): void
    {
        $now = Carbon::parse('2030-07-30 12:00:00');
        $this->travelTo($now);
        $clinician = $this->createClinician();

        $this->appointment($clinician, 'dashboard-effective@test.com', 'Effective Future', [
            'requested_at' => $now->copy()->subDays(2),
            'scheduled_at' => $now->copy()->addDay()->setTime(15, 30),
            'status' => 'rescheduled',
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Effective Future')
            ->assertSee('Jul 31, 03:30 PM')
            ->assertDontSee('Jul 28, 12:00 PM');
    }

    public function test_patient_dashboard_counts_all_future_appointments_and_excludes_elapsed_entries(): void
    {
        $now = Carbon::parse('2030-07-30 12:00:00');
        $this->travelTo($now);
        $clinician = $this->createClinician();
        $patient = $this->createPatient('dashboard-patient@test.com');

        foreach (range(1, 6) as $offset) {
            Appointment::create([
                'patient_id' => $patient['patient']->id,
                'clinician_id' => $clinician['clinician']->id,
                'requested_at' => $now->copy()->subDay(),
                'scheduled_at' => $now->copy()->addHours($offset),
                'mode' => 'in_person',
                'status' => 'approved',
            ]);
        }

        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => $now->copy()->addDay(),
            'scheduled_at' => $now->copy()->subMinute(),
            'mode' => 'in_person',
            'status' => 'approved',
        ]);
        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => $now->copy()->addDay(),
            'scheduled_at' => null,
            'mode' => 'online',
            'status' => 'cancelled',
        ]);

        $otherPatient = $this->createPatient('dashboard-other-patient@test.com');
        Appointment::create([
            'patient_id' => $otherPatient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => $now->copy()->addHour(),
            'scheduled_at' => null,
            'mode' => 'online',
            'status' => 'pending',
        ]);

        $this->actingAs($patient['user'], 'web')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertViewHas('upcomingAppointmentsCount', 6)
            ->assertViewHas('upcoming', function ($appointments) use ($now, $patient): bool {
                return $appointments->count() === 5
                    && $appointments->every(
                        fn (Appointment $appointment) => $appointment->patient_id === $patient['patient']->id
                            && $appointment->appointmentAt()->greaterThanOrEqualTo($now)
                    );
            });
    }
}
