<?php

namespace Tests\Integration;

use Tests\TestCase;

class ClinicianDashboardNavigationTest extends TestCase
{
    public function test_clinician_dashboard_kpis_link_to_their_related_pages(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('appointments.index').'"', false)
            ->assertSee('href="'.route('appointments.index', ['status' => 'pending']).'"', false)
            ->assertSee('href="'.route('patients.index').'"', false)
            ->assertSee('href="'.route('assignments.index').'"', false)
            ->assertSee('aria-label="View appointments"', false)
            ->assertSee('aria-label="View pending appointment requests"', false)
            ->assertSee('aria-label="View active patients"', false)
            ->assertSee('aria-label="View assignments"', false);
    }
}
