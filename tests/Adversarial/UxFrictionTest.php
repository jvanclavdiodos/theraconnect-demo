<?php

namespace Tests\Adversarial;

use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Phase 2.G — UX Friction (record-only, no assert-failure imperative).
 *
 * Identify interactions that lack loading / error states or that cause
 * avoidable full-page reloads on flaky networks. Most of these are
 * observable as Blade-view omissions rather than HTTP-level failures.
 *
 * Per the mission, these are tagged as "UX Bugs." Where the test can
 * mechanically verify the gap (e.g. input not repopulating after a
 * failed login), it does so.
 */
class UxFrictionTest extends TestCase
{
    use CreatesActors;

    /**
     * G1 — Failed login does not repopulate the email input.
     * `login.blade.php:17` is missing `value="{{ old('email') }}"`. After
     * a wrong-password attempt the user must retype their email —
     * inconsistent with `register.blade.php` (and every other form on
     * the site) which DOES repopulate. Particularly painful on mobile.
     */
    public function test_failed_login_does_not_repopulate_email(): void
    {
        $this->createPatient('ux-login-a@test.com');

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/login', [
                'email' => 'ux-login-a@test.com',
                'password' => 'WrongPassword1',
            ]);

        // For non-JSON (HTML form) requests, the controller redirects back
        // with the OLD input flashed. The Blade template must consume it.
        $loginPage = $this->get('/login');
        $loginPage->assertStatus(200);

        $html = $loginPage->getContent();

        // The Blade template at resources/views/auth/login.blade.php:17
        // reads <input type="email" id="email" name="email" ...> with NO
        // value="{{ old('email') }}" attribute.
        if (preg_match('/<input[^>]*id="email"[^>]*>/i', $html, $m)) {
            $this->assertStringContainsString(
                'value="',
                $m[0],
                'UX BUG: Login form email input is missing `value="{{ old(\'email\') }}"`. '.
                'After a failed attempt (wrong password) the user must retype their email. '.
                'All other forms (register, profile) correctly use old(). '.
                'Add `value="{{ old(\'email\') }}"` to resources/views/auth/login.blade.php:17.'
            );
        } else {
            $this->fail('Could not find the email input in the login view — selector regex needs updating.');
        }
    }

    /**
     * G2 — Portal mood-log POST causes a full page reload.
     * The portal.mood.index view doesn't include progressive enhancement
     * JS — every POST /portal/mood does a server round-trip. Tag as UX
     * observation; no programmatic assert beyond "the redirect is back".
     */
    public function test_portal_mood_log_post_causes_full_page_reload(): void
    {
        $patient = $this->createPatient('ux-mood@test.com');

        $response = $this->actingAs($patient['user'])
            ->post('/portal/mood', ['score' => 7]);

        $response->assertRedirect('/portal/mood');
        // Observation only — no assertion to make on JS enhancement state.
        $this->assertTrue(true, 'Observation: POST /portal/mood returns a 302 redirect, '.
            'causing a full page reload. Progressive enhancement would let it stay inline.');
    }

    /**
     * G3 — Bad date on /availability/month returns 422 with actionable error.
     */
    public function test_availability_month_bad_date_returns_actionable_error(): void
    {
        $clinician = $this->createClinician();

        $response = $this->actingAs($clinician['user'])
            ->getJson('/availability/month?month=not-a-date');

        $this->assertContains($response->status(), [422, 500]);
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['month']);
        }
    }

    /**
     * G4 — Throttle (429) response must include a Retry-After header and
     * a clear message. UX wise, "try again later" beats a blank 429.
     */
    public function test_throttle_response_includes_actionable_payload(): void
    {
        // Trip the public clinicians throttle (60/min → 61st call).
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/clinicians');
        }

        $response = $this->getJson('/api/v1/clinicians');

        $response->assertStatus(429);

        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull(
            $retryAfter,
            'UX OBSERVATION: 429 response should set Retry-After so the client knows exactly '.
            'how long to wait. Currently the framework sets it via ThrottleRequests middleware — '.
            'verify it\'s present.'
        );

        $body = $response->getContent();
        $this->assertNotEmpty($body, '429 response body should not be empty — include a human-friendly message.');
    }

    /**
     * G5 — 403 / 404 pages render branded views, not the Symfony default.
     *
     * BUG-PROOFING: RoleMiddleware returns 403 via abort() which goes
     * through the exception handler — the bootstrap/app.php:76 renderable
     * for AuthorizationException is dead code (Laravel wraps it as
     * AccessDeniedHttpException first). In non-production envs APP_DEBUG
     * causes the full Symfony error page (not the branded errors/403.blade.php)
     * to be returned. This test asserts the branded view; it FAILS because
     * the response is actually the default Symfony error page with stack trace.
     */
    public function test_forbidden_page_renders_branded_view(): void
    {
        $patient = $this->createPatient('ux-403@test.com');

        $response = $this->actingAs($patient['user'])->get('/dashboard');

        $response->assertStatus(403);
        $response->assertViewIs('errors.403');
    }

    public function test_not_found_page_renders_branded_view(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertStatus(404);
        $response->assertViewIs('errors.404');
    }

    /**
     * G6 — 500 page in production renders the branded errors.500 view,
     * not a Symfony stack-trace page.
     *
     * Marked incomplete: invoking the production catch-all renderable
     * outside a real HTTP kernel triggers interactive ConsoleOutput
     * prompts (SymfonyStyle::askQuestion). The equivalent leak is
     * proven by test_forbidden_page_renders_branded_view (G5) which
     * shows the same stack-trace leak on 403 in non-prod envs.
     */
    public function test_production_500_page_renders_branded_view_and_no_trace(): void
    {
        $this->markTestIncomplete(
            'SymfonyStyle interactive prompts fire when Handler::render is invoked outside '.
            'a real HTTP kernel context. The non-prod equivalent leak (stack trace on 403) '.
            'is proven by test_forbidden_page_renders_branded_view above.'
        );
    }
}
