<?php

namespace Tests\Integration;

use Tests\TestCase;

class PatientPortalNavigationTest extends TestCase
{
    public function test_profile_link_is_in_the_top_bar_and_not_the_sidebar_footer(): void
    {
        $patient = $this->createPatient('portal-navigation@test.com');

        $this->actingAs($patient['user'])
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="View profile"', false)
            ->assertSee($patient['user']->name)
            ->assertDontSee('tc-sidebar-footer', false)
            ->assertDontSee('tc-user-name', false);
    }

    public function test_clinician_profile_link_is_in_the_top_bar_and_not_the_sidebar_footer(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('account.edit').'"', false)
            ->assertSee('aria-label="View profile"', false)
            ->assertSee($clinician['user']->name)
            ->assertDontSee('tc-sidebar-footer', false)
            ->assertDontSee('tc-user-name', false);
    }
}
