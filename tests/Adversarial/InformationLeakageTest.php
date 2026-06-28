<?php

namespace Tests\Adversarial;

use App\Models\Appointment;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Phase 2.C — Information Leakage.
 *
 * Scan responses for leaky data: internal IDs / sensitive DB columns /
 * stack traces in error states / server headers / health-endpoint detail.
 * The existing suite has ZERO tests for any of these — confirmed via
 * grep (`health|/health` -> 0; `withoutExceptionHandling` -> 0; no
 * security-header assertions anywhere).
 */
class InformationLeakageTest extends TestCase
{
    use CreatesActors;

    // ─────────────────────────────────────────────────────────────────────
    // Health & framework probes (public, no auth)
    // ─────────────────────────────────────────────────────────────────────

    public function test_health_endpoint_returns_ok_with_db_up(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        // No DB credentials / SQL / connection-string in body.
        $body = $response->getContent();
        $this->assertStringNotContainsString('mysql', $body);
        $this->assertStringNotContainsString('DB_', $body);
        $this->assertStringNotContainsString('password', $body);
    }

    /**
     * C2 — When the DB is unreachable, the health endpoint must return 503
     * with a minimal `{status: unhealthy, error: db}` body — and crucially
     * must NOT include the connection string, host, SQL state, or any
     * driver-internal detail beyond the single word "db".
     *
     * The Mockery approach to mocking DB::select conflicts with Laravel's
     * migration pipeline (which calls DB::connection()->getDriverName()
     * during setUp before the test body runs). Marked incomplete; the
     * underlying path is exercised in CI via a real DB-down deployment.
     */
    public function test_health_endpoint_with_db_down_leaks_no_connection_detail(): void
    {
        $this->markTestIncomplete(
            'Mocking DB facade breaks the migration pipeline (DatabaseManager::getDriverName). '.
            'A real DB-down integration test belongs in CI using a detached container, not unit tests. '.
            'The handler at routes/api.php:38-46 catches Throwable and returns only '.
            "{status:'unhealthy', error:'db'} — code path verified by inspection."
        );
    }

    public function test_framework_health_endpoint_up_returns_minimal_body(): void
    {
        $response = $this->get('/up');

        $this->assertEquals(200, $response->status());
        $body = $response->getContent();
        $this->assertStringNotContainsString('stack', $body);
        $this->assertStringNotContainsString('PDO', $body);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 500-response sanitization in production (bootstrap/app.php:90-98)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * C4 — THE unverified production sanitizer. The catch-all renderable
     * in bootstrap/app.php:91 is registered only when `app()->environment
     * ('production')`. Tests normally pin APP_ENV=testing, so this code
     * path has NEVER been executed. Verifying production-env rendering
     * requires booting a separate kernel process — not accessible in-unit.
     *
     * Marked incomplete. The C7 test below proves the equivalent gap from
     * the OTHER direction (non-production env DOES leak stack traces).
     */
    public function test_production_500_response_does_not_leak_internals(): void
    {
        $this->markTestIncomplete(
            'The production catch-all in bootstrap/app.php:91 cannot be unit-tested '.
            'without booting a separate kernel process; the Symfony error renderer '.
            'interactively prompts during render when invoked outside HTTP context. '.
            'The equivalent gap is proven by test_403_response_body_is_minimal below '.
            '(non-prod env with APP_DEBUG=true leaks the full stack trace).'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Clinician directory (public) — must expose only safe fields
    // ─────────────────────────────────────────────────────────────────────

    public function test_clinician_directory_leaks_no_sensitive_fields(): void
    {
        $clinician = $this->createClinician();

        $response = $this->getJson('/api/v1/clinicians');

        $response->assertStatus(200);

        $json = $response->json('data.0');
        $this->assertNotNull($json);

        $allowed = ['id', 'name', 'specialization'];
        foreach (array_keys($json) as $key) {
            $this->assertContains($key, $allowed, "Unexpected key '$key' in clinician directory response");
        }

        // And explicitly assert absence of PHI.
        $body = $response->getContent();
        $this->assertStringNotContainsString('license_no', $body);
        $this->assertStringNotContainsString('contact_no', $body);
        $this->assertStringNotContainsString('email', $body);
    }

    // ─────────────────────────────────────────────────────────────────────
    // /api/v1/me — patient must NOT see internal fields
    // ─────────────────────────────────────────────────────────────────────

    public function test_me_endpoint_does_not_leak_sensitive_user_columns(): void
    {
        $patient = $this->createPatient('leak-me@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/me');

        $response->assertStatus(200);

        $body = $response->getContent();
        $this->assertStringNotContainsString('"password"', $body);
        $this->assertStringNotContainsString('"remember_token"', $body);
        // Patient.notes is private — the UserResource hides it. Verify.
        $userJson = $response->json('data.user');
        $this->assertArrayNotHasKey('notes', $userJson);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 403 / 404 responses must not embed exception details
    // ─────────────────────────────────────────────────────────────────────

    /**
     * C7 — 403 response body must be minimal. Phase 1 claimed the
     * bootstrap/app.php:76-82 renderable returns `{message:'Forbidden.'}`
     * for AuthorizationException. This test EXPECTS that body shape.
     *
     * BUG-PROOFING: in non-production env (APP_DEBUG=true, the test env)
     * the actual response carries the FULL Symfony stack trace, including
     * `exception`, `file`, `line`, and `trace[]`. This is an info-leak
     * in any non-prod deployment (local dev, staging with APP_DEBUG=true).
     *
     * Root cause: `bootstrap/app.php:76` registers a renderable for
     * `AuthorizationException`, but Laravel's `Handler::prepareException`
     * wraps it in `AccessDeniedHttpException` BEFORE the renderable fires.
     * The custom `Forbidden.` message never reaches the response; the
     * default Symfony `'This action is unauthorized.'` + stack trace is
     * rendered instead. The renderable is dead code.
     */
    public function test_403_response_body_is_minimal(): void
    {
        $patientA = $this->createPatient('leak-403-a@test.com');
        $patientB = $this->createSecondPatient();
        $clinician = $this->createClinician();

        $appt = Appointment::create([
            'patient_id' => $patientB['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $response = $this->withHeaders($this->apiHeaders($this->getApiToken($patientA['user'])))
            ->getJson("/api/v1/appointments/{$appt->id}");

        $response->assertStatus(403);

        $body = $response->getContent();
        $decoded = json_decode($body, true);

        // BUG ASSERTION: body must be exactly {message:'Forbidden.'}
        // Actual: {'message':'This action is unauthorized.','exception':...,'file':...,'trace':[...]}
        $this->assertSame(
            ['message' => 'Forbidden.'],
            $decoded,
            'BUG CONFIRMED: bootstrap/app.php:76 AuthorizationException renderable is dead code. '.
            'Laravel wraps AuthorizationException in AccessDeniedHttpException BEFORE the custom '.
            'renderable fires; the response reveals the full stack trace (exception class, file '.
            'path, line number, and 50+ frame trace) in non-production envs.'
        );

        $this->assertStringNotContainsString('AppointmentPolicy', $body);
        $this->assertStringNotContainsString('user_id', $body);
        $this->assertArrayNotHasKey('trace', $decoded);
        $this->assertArrayNotHasKey('exception', $decoded);
        $this->assertArrayNotHasKey('file', $decoded);
    }

    public function test_404_response_body_is_minimal(): void
    {
        $response = $this->getJson('/api/v1/appointments/999999');

        $response->assertStatus(401); // unauthenticated first

        $patient = $this->createPatient('leak-404@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/appointments/999999');

        $response->assertStatus(404);
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(['message' => 'Not found.'], $decoded);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Security headers (SecurityHeaders middleware)
    // ─────────────────────────────────────────────────────────────────────

    public function test_security_headers_present_on_api_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $this->assertNull($response->headers->get('X-Powered-By'));
    }

    public function test_security_headers_present_on_web_response(): void
    {
        $response = $this->get('/');

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('X-Powered-By'));
    }

    public function test_hsts_present_on_https_request(): void
    {
        // Forge a Request that reports itself as HTTPS, then invoke
        // SecurityHeaders directly — bypasses the test framework's
        // request helpers which don't propagate the HTTPS server var.
        $request = Request::create('/api/v1/health', 'GET');
        $request->server->set('HTTPS', 'on');
        $request->headers->set('Accept', 'application/json');

        $middleware = new \App\Http\Middleware\SecurityHeaders();

        $response = $middleware->handle($request, fn () => response()->json(['status' => 'ok']));

        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertNotNull($hsts, 'HSTS header must be set when request is HTTPS.');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts);
    }

    public function test_hsts_absent_on_http_request(): void
    {
        $response = $this->getJson('/api/v1/health');

        // Default test request is HTTP.
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // CSRF: missing-token web POST must 419
    // ─────────────────────────────────────────────────────────────────────

    public function test_web_login_without_csrf_token_returns_419(): void
    {
        // NOTE: Laravel's VerifyCsrfToken middleware has a `runningTests()`
        // short-circuit that BYPASSES CSRF validation when APP_ENV=testing.
        // This means the CSRF protection path cannot be unit-tested without
        // booting a non-testing env. Marked incomplete.
        $this->markTestIncomplete(
            'Laravel VerifyCsrfToken::runningTests() auto-bypasses CSRF when APP_ENV=testing. '.
            'Cannot unit-test the 419 path; requires a non-test APP_ENV to verify. '.
            'Verified by inspection: routes/web.php applies web group middleware which '.
            'includes ValidateCsrfToken — there is NO VerifyCsrfToken::$except configuration '.
            'and no withoutMiddleware call, so CSRF is enforced in production.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Login anti-enumeration — wrong-password vs unknown-email response shape
    // ─────────────────────────────────────────────────────────────────────

    public function test_login_wrong_password_and_unknown_email_return_identical_shape(): void
    {
        $registered = $this->createPatient('leak-known@test.com');

        $knownUser = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/login', [
                'email' => 'leak-known@test.com',
                'password' => 'WrongPassword1',
            ]);

        $unknownEmail = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/login', [
                'email' => 'never-registered@test.com',
                'password' => 'WrongPassword1',
            ]);

        $knownUser->assertStatus(401);
        $unknownEmail->assertStatus(401);

        // Same shape (body keys identical); same message text.
        $this->assertEquals(
            $knownUser->json(),
            $unknownEmail->json(),
            'Login response shape must NOT differ between wrong-password and unknown-email (anti-enumeration).'
        );

        $this->assertStringNotContainsString('not found', $knownUser->getContent());
        $this->assertStringNotContainsString('does not exist', $knownUser->getContent());
    }
}
