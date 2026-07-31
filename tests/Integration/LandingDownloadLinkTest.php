<?php

namespace Tests\Integration;

use Tests\TestCase;

class LandingDownloadLinkTest extends TestCase
{
    public function test_configured_app_download_is_available_in_header_and_patient_section(): void
    {
        config()->set(
            'app.download_url',
            'https://github.com/example/theraconnect/releases/download/v1.4/app-release.apk'
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="lp-download"', false)
            ->assertSee('Download app')
            ->assertSee('Download the Android app')
            ->assertSee(
                'https://github.com/example/theraconnect/releases/download/v1.4/app-release.apk',
                false
            );
    }

    public function test_header_download_is_hidden_without_a_configured_url(): void
    {
        config()->set('app.download_url', null);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="lp-download"', false);
    }
}
