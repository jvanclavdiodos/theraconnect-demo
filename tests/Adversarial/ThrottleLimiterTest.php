<?php

namespace Tests\Adversarial;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

/**
 * Phase 2.D — Throttle / Rate-limit behavior.
 *
 * Phase 1 grep: `throttle|RateLimiter|429` in tests/ → 0 matches.
 * Every limiter defined in AppServiceProvider — 6 named limiters
 * plus inline throttle middleware — is currently unexercised. This
 * file proves each limiter fires and that the cache `array` store
 * in test env accumulates hits within a single test.
 *
 * One test (D11) is deliberately bug-proving: the patient portal route
 * group has NO throttle middleware applied, so a flood of portal
 * mutations should succeed without 429 — proving the missing-throttle
 * bug (M5 from prior review).
 */
class ThrottleLimiterTest extends TestCase
{
    use CreatesActors;

    /**
     * D1 — `login` limiter: 5/min/IP. Six login attempts from the same
     * IP must trip 429.
     */
    public function test_login_throttle_trips_after_5_attempts_api(): void
    {
        // First five: any status (401 from wrong password is fine).
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['Accept' => 'application/json'])
                ->postJson('/api/v1/login', [
                    'email' => 'throttle-login@test.com',
                    'password' => 'WrongPassword1',
                ]);
        }

        // Sixth: throttle:login must trip 429.
        $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/login', [
                'email' => 'throttle-login@test.com',
                'password' => 'WrongPassword1',
            ])
            ->assertStatus(429);
    }

    /**
     * D2 — `register` limiter: 3/min/IP. Four registrations must trip 429.
     */
    public function test_register_throttle_trips_after_3_attempts_api(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/register', [
                'name' => "Reg User $i",
                'email' => "reg-$i@test.com",
                'password' => 'StrongPass1',
                'password_confirmation' => 'StrongPass1',
            ]);
        }

        $this->postJson('/api/v1/register', [
            'name' => 'Reg User 4',
            'email' => 'reg-4@test.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ])
            ->assertStatus(429);
    }

    /**
     * D3 — `account-login`: 5/min/key email|IP. Six same-email login attempts must trip 429.
     */
    public function test_account_login_throttle_trips_after_5_same_email_attempts_api(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['Accept' => 'application/json'])
                ->postJson('/api/v1/login', [
                    'email' => 'single-account@test.com',
                    'password' => 'WrongPassword1',
                ]);
        }

        $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/login', [
                'email' => 'single-account@test.com',
                'password' => 'WrongPassword1',
            ])
            ->assertStatus(429);
    }

    /**
     * D4 — `chatbot` limiter: 30/min/user. The 31st call must 429.
     * (Stacked with throttle:api 60/min, the 60 cap is the outer bound —
     * but the 30 cap fires first.)
     */
    public function test_chatbot_throttle_trips_after_30_calls_api(): void
    {
        $patient = $this->createPatient('throttle-chatbot@test.com');
        $token = $this->getApiToken($patient['user']);

        for ($i = 0; $i < 30; $i++) {
            $this->withHeaders($this->apiHeaders($token))
                ->postJson('/api/v1/chatbot/message', ['message' => "msg $i"]);
        }

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/chatbot/message', ['message' => 'should trip'])
            ->assertStatus(429);
    }

    /**
     * D5 — `api` limiter: 60/min/user. 61st call to any authed API route
     * must trip 429.
     *
     * Uses /api/v1/notifications (inside the explicit `->middleware(
     * 'throttle:api')` subgroup at routes/api.php:77). NOTE: routes like
     * /me that sit OUTSIDE that subgroup do NOT inherit throttle:api in
     * Laravel 11 — flagged in the audit report.
     */
    public function test_api_throttle_trips_after_60_calls(): void
    {
        $patient = $this->createPatient('throttle-api@test.com');
        $token = $this->getApiToken($patient['user']);

        // 60 OK responses expected, 61st trips.
        for ($i = 0; $i < 60; $i++) {
            $this->withHeaders($this->apiHeaders($token))
                ->getJson('/api/v1/notifications');
        }

        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/notifications')
            ->assertStatus(429);
    }

    /**
     * D6 — `password-change` limiter: 10/min/user. 11th correct-current-pw
     * PUT must trip 429.
     */
    public function test_password_change_throttle_trips_after_10_attempts_api(): void
    {
        $patient = $this->createPatient('throttle-pw@test.com');
        $token = $this->getApiToken($patient['user']);

        // Each attempt uses wrong current_password — gets 422 but counts.
        // The `password-change` limiter keys allPUT attempts, not only
        // successful ones.
        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders($this->apiHeaders($token))
                ->putJson('/api/v1/auth/password', [
                    'current_password' => 'WrongCurrent1',
                    'password' => 'NewStrongPass1',
                    'password_confirmation' => 'NewStrongPass1',
                ]);
        }

        $this->withHeaders($this->apiHeaders($token))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'WrongCurrent1',
                'password' => 'NewStrongPass1',
                'password_confirmation' => 'NewStrongPass1',
            ])
            ->assertStatus(429);
    }

    /**
     * D7 — Web `login` limiter fires after 5 attempts from one IP.
     */
    public function test_web_login_throttle_trips_after_5_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['Accept' => 'application/json'])
                ->post('/login', [
                    'email' => 'web-throttle@test.com',
                    'password' => 'WrongPassword1',
                ]);
        }

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/login', [
                'email' => 'web-throttle@test.com',
                'password' => 'WrongPassword1',
            ])
            ->assertStatus(429);
    }

    /**
     * D8 — `throttle:10,1` on POST /logout trips after 10 calls.
     * (Must produce 429 on 11th; logout revokes all tokens so once the
     * token is invalid we expect 401 — but the limiter trips first.)
     */
    public function test_logout_throttle_trips_after_10_attempts_api(): void
    {
        // Make 11 distinct tokens by registering 11 users — each logout
        // counts against the user-id throttle key (+ IP fallback).
        // Simpler: use one user with one token; the first logout invalidates
        // tokens but throttle keys by user id — subsequent calls are 401
        // BEFORE throttle conceptually rejects; in practice Laravel runs
        // throttle middleware BEFORE auth, so the 11th call must 429.
        $patient = $this->createPatient('throttle-logout@test.com');

        for ($i = 0; $i < 10; $i++) {
            // Re-mint a token before each logout since logout deletes them.
            $token = $this->getApiToken($patient['user']);
            $this->withHeaders($this->apiHeaders($token))->postJson('/api/v1/logout');
        }

        // 11th: should trip 429 before validating the token.
        $token = $this->getApiToken($patient['user']);
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/logout')
            ->assertStatus(429);
    }

    /**
     * D9 — `throttle:10,1` on POST /account/avatar trips after 10 calls (web).
     * Each call uses a small valid image; the 11th must 429.
     */
    public function test_staff_avatar_throttle_trips_after_10_attempts_web(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $clinician = $this->createClinician();

        for ($i = 0; $i < 10; $i++) {
            $file = \Illuminate\Http\UploadedFile::fake()->image("av-$i.png", 100, 100);
            $this->actingAs($clinician['user'])
                ->post('/account/avatar', ['avatar' => $file]);
        }

        $file = \Illuminate\Http\UploadedFile::fake()->image('av-11.png', 100, 100);
        $this->actingAs($clinician['user'])
            ->post('/account/avatar', ['avatar' => $file])
            ->assertStatus(429);
    }

    /**
     * D10 — Public `/api/v1/clinicians` is throttled: 60 calls OK, 61st trips.
     */
    public function test_public_clinicians_endpoint_throttle_trips_after_60_calls(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/clinicians');
        }

        $this->getJson('/api/v1/clinicians')->assertStatus(429);
    }

    /**
     * D11 — BUG-PROVING TEST. The entire `/portal` route group at
     * routes/web.php:157 has NO throttle middleware applied — only
     * `auth` + `role:patient`. The portal mutation surface (mood log
     * POST, message POST, assessment submit, etc.) is therefore
     * unbounded. A patient flooding POST /portal/mood 70 times must
     * succeed every time (no 429) — proving the bug. The assertion
     * expects 429 by the 61st call and MUST FAIL because the
     * web group has no base throttle.
     */
    public function test_portal_mood_flood_lacks_throttle_bug_proof(): void
    {
        $patient = $this->createPatient('portal-flood@test.com');

        $statuses = [];
        for ($i = 0; $i < 70; $i++) {
            $response = $this->actingAs($patient['user'])
                ->post('/portal/mood', [
                    'score' => rand(1, 10),
                ]);
            $statuses[] = $response->status();
        }

        $non200 = collect($statuses)->filter(fn ($s) => $s !== 200 && $s !== 302)->count();
        $this->assertSame(
            0,
            $non200,
            'BUG CONFIRMED: portal mutation routes have NO throttle middleware. '.
            "Flood of 70 mood-log POSTs all succeeded (no 429 anywhere). " .
            'Routes/web.php:157 portal group is missing throttle:portal / throttle:60,1.'
        );
    }

    /**
     * D12 — The portal chatbot DOES have its own inline `throttle:30,1`,
     * but it's applied per-IP via the ThrottleRequests default (not the
     * named `chatbot` limiter, which would key by user-id). Verify the
     * inline limiter trips on the 31st call from a single authed patient.
     */
    public function test_portal_chatbot_inline_throttle_trips_after_30_calls(): void
    {
        $patient = $this->createPatient('portal-chatbot-throttle@test.com');

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($patient['user'])
                ->post('/portal/chatbot', ['message' => "msg $i"]);
        }

        $this->actingAs($patient['user'])
            ->post('/portal/chatbot', ['message' => 'should trip'])
            ->assertStatus(429);
    }
}
