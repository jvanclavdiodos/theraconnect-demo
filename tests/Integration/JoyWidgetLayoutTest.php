<?php

namespace Tests\Integration;

use Tests\TestCase;

class JoyWidgetLayoutTest extends TestCase
{
    public function test_floating_widget_uses_wrapping_bubbles_and_an_icon_only_header(): void
    {
        $patient = $this->createPatient('joy-widget-layout@test.com');

        $this->actingAs($patient['user'])
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('tc-joy-message-row outgoing', false)
            ->assertSee('tc-joy-message-row incoming', false)
            ->assertSee('tc-joy-bubble', false)
            ->assertSee('aria-label="Close Joy assistant"', false)
            ->assertDontSee('<strong>Joy</strong>', false);
    }
}
