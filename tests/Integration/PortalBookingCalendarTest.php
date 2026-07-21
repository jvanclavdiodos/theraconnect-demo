<?php

namespace Tests\Integration;

use Tests\TestCase;

class PortalBookingCalendarTest extends TestCase
{
    public function test_blocked_dates_are_unselectable_and_explain_unavailability(): void
    {
        $patient = $this->createPatient();
        $clinician = $this->createClinician();
        $blockedDate = now()->addDays(3)->startOfDay();
        $clinician['clinician']->dateOverrides()->create([
            'date' => $blockedDate,
            'is_available' => false,
        ]);

        $this->actingAs($patient['user'])
            ->get(route('portal.appointments.book', ['clinician_id' => $clinician['clinician']->id]))
            ->assertOk()
            ->assertSee('Clinician is not available that day.')
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee(route('portal.appointments.book', [
                'clinician_id' => $clinician['clinician']->id,
                'date' => $blockedDate->format('Y-m-d'),
            ]), false);
    }
}
