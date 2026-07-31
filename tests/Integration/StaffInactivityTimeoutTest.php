<?php

namespace Tests\Integration;

use Tests\TestCase;

class StaffInactivityTimeoutTest extends TestCase
{
    public function test_clinician_is_logged_out_after_ten_minutes_without_activity(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'])
            ->withSession(['staff_last_activity_at' => now()->subMinutes(10)->timestamp])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas(
                'status',
                'You have been logged out after 10 minutes of inactivity.'
            );

        $this->assertGuest();
    }

    public function test_admin_activity_refreshes_the_server_timeout(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->withSession(['staff_last_activity_at' => now()->subMinutes(9)->timestamp])
            ->post(route('session.activity'))
            ->assertNoContent();

        $this->assertAuthenticatedAs($admin);
        $this->assertGreaterThan(
            now()->subMinute()->timestamp,
            session('staff_last_activity_at')
        );
    }

    public function test_expired_activity_heartbeat_returns_login_instructions(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->withSession(['staff_last_activity_at' => now()->subMinutes(10)->timestamp])
            ->postJson(route('session.activity'))
            ->assertUnauthorized()
            ->assertJsonPath('redirect', route('login', ['inactivity' => 1]));

        $this->assertGuest();
        $this->get(route('login', ['inactivity' => 1]))
            ->assertOk()
            ->assertSee('You have been logged out after 10 minutes of inactivity.');
    }

    public function test_staff_layout_enables_the_browser_inactivity_monitor(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-staff-inactivity-timeout="600"', false)
            ->assertSee('js/staff-inactivity.js', false);
    }

    public function test_browser_inactivity_logout_returns_to_login_with_message(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('logout'), ['inactivity' => '1'])
            ->assertRedirect(route('login'))
            ->assertSessionHas(
                'status',
                'You have been logged out after 10 minutes of inactivity.'
            );

        $this->assertGuest();
    }
}
