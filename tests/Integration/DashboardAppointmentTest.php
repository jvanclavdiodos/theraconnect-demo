<?php

namespace Tests\Integration;

use App\Models\Appointment;
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
}
