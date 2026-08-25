<?php

namespace Tests\Integration;

use Tests\TestCase;

class NavbarControlLayoutTest extends TestCase
{
    public function test_staff_navbar_uses_four_uniform_icon_controls(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertUniformControls($response->getContent());
    }

    public function test_patient_navbar_uses_four_uniform_icon_controls(): void
    {
        $patient = $this->createPatient('navbar-controls@test.com');
        $response = $this->actingAs($patient['user'])
            ->get(route('portal.dashboard'))
            ->assertOk();

        $this->assertUniformControls($response->getContent());
    }

    private function assertUniformControls(string $html): void
    {
        $this->assertSame(4, substr_count($html, 'tc-navbar-control'));
        $this->assertStringNotContainsString('tc-navbar-account-name', $html);
        $this->assertMatchesRegularExpression(
            '/<button[^>]+aria-label="Sign out"[^>]*>\s*<i[^>]+bi-box-arrow-right[^>]*><\/i>\s*<\/button>/s',
            $html
        );
    }
}
