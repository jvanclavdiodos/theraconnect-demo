# Audit Follow-ups — Known Concerns Not Yet Addressed

This file tracks production-readiness gaps surfaced by the deep-dive audit
that are **not** blocked by the fixes bundled on this branch. Each item is
credited to the area(s) it touches and ordered by rough priority inside each
section. Use this as the backlog for the next hardening pass.

The companion audit narrative (six areas: Resource Integrity, Input
Resilience, Boundary Protection, System Transparency, User Flow & State,
Accessibility & Experience) lives in the project's planning notes; the
file:line citations there are the source of truth for the entries below.

---

## P0 — Production-blocking, defer only with stakeholder sign-off

### 1. Registration is a direct email-existence oracle
- **Where:** `app/Http/Requests/Api/RegisterRequest.php:22`,
  `app/Http/Controllers/Web/RegisterController.php:39` both use
  `'unique:users'` → 422 if the email exists, 201 if it doesn't.
- **Impact:** With `throttle:register` at 3/min/IP, one IP can enumerate
  4,320 emails/day against a mental-health patient roster — unbounded
  from rotating IPs. Successful "probes" persist `User` + `Patient`
  rows, polluting the directory with junk.
- **Fix:** Drop `'unique:users'` from validation; resolve existence
  inside the controller's `DB::transaction` with a silent "pretend
  success" branch that dispatches a notification to the existing owner
  and returns the same generic confirmation regardless. Implement
  `MustVerifyEmail` on `User` and gate portal/API write actions on
  `email_verified_at`. Optionally require click-through before
  `User`+`Patient` rows are created.

### 2. `session.cookie_secure` has no production default
- **Where:** `config/session.php:172` — `'secure' => env('SESSION_SECURE_COOKIE')`
  returns `null` when unset → cookies travel over plain HTTP.
  `.env.example` does not set `SESSION_SECURE_COOKIE`.
- **Impact:** A developer who copies `.env.example → .env` for a prod
  deploy ships without secure cookies. The `SecurityHeaders` HSTS
  middleware only protects the *next* visit — the first request over
  HTTP still leaks the cookie.
- **Fix:** Change to
  `'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')`
  and add `SESSION_SECURE_COOKIE=true` to `.env.example`.

### 3. No body-size cap on `application/json` API requests
- **Where:** No `Content-Length` middleware exists in `app/` or `config/`.
  `php.ini`'s `post_max_size = 12M` (docker/php.ini:9-10) only applies to
  `multipart/form-data` and `application/x-www-form-urlencoded` —
  **not** to `php://input` JSON bodies that Laravel decodes via
  `Request::getJsonPayload()` before any validator runs.
- **Impact:** A patient with one valid Sanctum token can POST a
  ~128 MB JSON body to `/api/v1/conversations/{id}/messages` and OOM
  the worker. `throttle:api` bounds rate, not size.
- **Fix:** Add a ~5-line global middleware reading `Content-Length`
  and aborting 413 above 1 MB on JSON routes; register via
  `$middleware->api(prepend: [...])` in `bootstrap/app.php`. Also set
  `memory_limit = 128M` explicitly in `docker/php.ini`.

### 4. Code guard forcing `APP_DEBUG=false` in production
- **Where:** `config/app.php:42` is
  `'debug' => (bool) env('APP_DEBUG', false)`. Config default is fine,
  but no code-level guard prevents an operator from setting
  `APP_DEBUG=true` in production — Whoops would then serve full stack
  traces, env-var dumps, and request details on any error.
- **Fix:** Change to
  `'debug' => (bool) env('APP_DEBUG', false) && ! app()->environment('production')`.

### 5. No `Content-Security-Policy` header anywhere
- **Where:** Grep across `*.php`, `.htaccess`, `*.ini`, `*.conf` → 0
  matches. The dashboard pulls Bootstrap + Bootstrap Icons from
  `cdn.jsdelivr.net` (`resources/views/errors/layout.blade.php:7-8`),
  so any user content reaching a page is XSS-exposed.
- **Fix:** Add to `app/Http/Middleware/SecurityHeaders.php`:
  ```php
  $response->headers->set('Content-Security-Policy',
      "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; ".
      "style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; ".
      "connect-src 'self'; frame-ancestors 'none'"
  );
  ```
  (Iterate `'unsafe-inline'` out later via nonces.)

---

## P1 — High-value, ship before patient data is loaded

### 6. Migrate sensitive models to ULIDs (kills enumeration)
- **Where:** All 20 models in `app/Models/` use bigint auto-increment PKs
  with default `getRouteKeyName() = 'id'`. URLs are
  `appointments/15`, `submissions/42`, etc. — enumerable.
- **Impact:** Combined with Laravel's split between
  `ModelNotFoundException` → 404 and `AuthorizationException` → 403
  (`bootstrap/app.php:61-67` & `:85-91`), an authenticated patient can
  probe `/api/v1/appointments/1..N` and distinguish 404 (no record) from
  403 (exists, not theirs) — a clean enumeration oracle for
  appointments, submissions, and assignments.
- **Fix:** Add `use HasUlids;` + a `ulid` column with unique index per
  table (migrations), switch `$incrementing = false` / `$keyType =
  'string'` or override `getRouteKeyName()`. Priority order:
  `Appointment`, `Assignment`, `Submission`, `Conversation`,
  `PatientNote`, `TherapyGoal`, `Assessment`, `Notification`. Switch the
  four raw-`int $id` API controllers
  (`Api\V1/AppointmentController.php:172,183`,
  `AssignmentController.php:40,51`, `SubmissionController.php:24,58`)
  to route-model binding on the ULID key.

### 7. Add `ClinicianPolicy` + `ChatbotIntentPolicy` for defence-in-depth
- **Where:** `routes/web.php:94,97` `Route::resource`s back
  `ClinicianController` and `ChatbotContentController`; entire surface
  gated only by `role:admin` middleware — no policy exists; no
  `Gate::authorize()` in the controller methods. Likewise
  `PatientController::edit/update/destroy`
  (`web/PatientController.php:141,149,192`) rely on route-level
  `role:admin` alone.
- **Impact:** Not actively exploitable (role middleware holds), but if
  `role` middleware is ever loosened or a token-reuse bug appears,
  there is no second object-level gate.
- **Fix:** Add `ClinicianPolicy` and `ChatbotIntentPolicy` with
  `view/update/delete` returning `in_array($user->role, ['admin'])`.
  Call `Gate::authorize(...)` as the first line of
  `PatientController::edit/update/destroy`.

### 8. Account-keyed brute-force lockout independent of IP
- **Where:** `app/Providers/AppServiceProvider.php:64-66` — the
  `account-login` limiter is keyed `email|ip`. An attacker rotating
  IPs gets 5×N attempts/min against one account. No progression to
  lockout, no CAPTCHA escalation, no notification to the account
  owner. No `failed_login_attempts` table.
- **Fix:** Key the limiter on `'acct:'.$email` alone. Add progressive
  backoff + CAPTCHA via `RateLimiter::attempts()` after N failures,
  write to the existing `ActivityLog` model so clinic admins can see
  ongoing attacks.

### 9. Sanctum token abilities + secret-scanner prefix
- **Where:** `app/Http/Controllers/Api/V1/AuthController.php:56,99`
  calls `createToken('mobile')` with no `$abilities` arg → defaults to
  `['*']` (full read/write/delete). Grep for `tokenCan(`/`abilities(`
  across `app/` returns zero enforcement. `SANCTUM_TOKEN_PREFIX` is
  unset (`config/sanctum.php:73`).
- **Impact:** A leaked token grants full scope over the patient's
  resources. Committed tokens aren't flagged by GitHub/GitLab secret
  scanning.
- **Fix:** Define abilities (`appointments:read`, `appointments:write`,
  `messages:read`, `messages:write`, …) and pass to `createToken()`,
  then `abort_unless($request->user()->tokenCan('appointments:write'),
  403)` in each controller. Set `SANCTUM_TOKEN_PREFIX=tc1_` in
  `.env.example`.

### 10. Implement email verification (`MustVerifyEmail`)
- **Where:** `app/Models/User.php` does not implement
  `MustVerifyEmail`. `email_verified_at` is cast but never set.
  `Web/RegisterController.php:82` and `Api/V1/AuthController.php:56`
  auto-login / mint a Sanctum token immediately after registration.
- **Impact:** A patient can register with an email they don't own and
  instantly access the portal.
- **Fix:** Implement `MustVerifyEmail` on `User`, fire the `Registered`
  event, gate the `/register` flow to send a verification link before
  issuing a session/token. At minimum, block portal/API write actions
  until `email_verified_at` is non-null via a middleware.

---

## P2 — User flow & UX

### 11. No real-time channel anywhere
- **Where:** No `app/Notifications/` classes; custom `Notification`
  model + custom table. Delivery is FCM-only
  (`Services/NotificationService.php`, `Jobs/SendPushNotification.php`,
  `Services/FcmService.php`). `config/broadcasting.php` does not exist;
  no `routes/channels.php`; `composer.json` has no
  `pusher/pusher-php-server` or `laravel/reverb`; `package.json` has no
  `laravel-echo` or `pusher-js`. Grep for `ShouldBroadcast`/`Echo` → 0.
- **Impact:** A clinician on `/dashboard` will not see an incoming
  patient message until they manually reload. No web delivery path.
- **Fix:** Stand up Laravel Reverb + `laravel-echo`; broadcast new
  `Notification` rows over a private channel `notifications.{user_id}`
  and `Message` rows over `conversations.{id}`. Subscribe in
  `layouts/app.blade.php` and `layouts/portal.blade.php`.

### 12. Patient↔clinician messaging is full-page-reload per message
- **Where:** `resources/views/portal/messages/index.blade.php:44` and
  `resources/views/messages/show.blade.php:31` are plain
  `method="POST"` forms → controller `RedirectResponse` → entire
  thread re-renders. No optimistic echo of the sent text.
- **Fix:** Apply the chatbot's `awaiting`+`:disabled`+spinner pattern
  (already proven in `portal/chatbot/index.blade.php:49-56,63`) to
  the messaging forms. For messaging, push the typed text into the
  transcript immediately (optimistic), reconcile on response.

### 13. Gemini is called synchronously inside the HTTP request
- **Where:** `app/Services/ChatbotService.php:40-92` makes a blocking
  `Http::timeout(20)->post(... ':generateContent' ...)`, holding a PHP
  worker up to 20 seconds. Called directly from
  `Portal/PortalChatbotController.php:27` and `Api/V1/ChatbotController.php:22`.
- **Fix:** Switch to `:streamGenerateContent?alt=sse` and return a
  `response()->stream(...)` with `Content-Type: text/event-stream`; the
  chatbot `fetch` becomes `EventSource`, the Flutter side uses Dio
  `responseType: ResponseType.stream`. OR queue the call on
  `onQueue('ai')` with `tries=3, backoff=5` and broadcast the reply via
  Reverb.

### 14. Loading states missing on high-traffic actions
- **Where:** No `:disabled` + spinner on
  `portal/appointments/book.blade.php:104`, `auth/login.blade.php:25`,
  `portal/messages/index.blade.php:44`. The chatbot/notifications/
  reschedule-modal patterns are correct but inconsistently applied.
- **Fix:** Apply the proven `awaiting`+`:disabled`+spinner pattern to
  booking, login, and message-send. Add `XMLHttpRequest.upload.onprogress`
  to drive a `.progress-bar` for the avatar + submission upload forms.

### 15. Global Alpine toast bus
- **Where:** Every critical action's confirmation is reload-gated
  session flash (`partials/flash.blade.php`).
  `PortalAppointmentController.php:138,148,168` and
  `AuthenticatedSessionController.php:43` redirect-with-flash.
- **Fix:** Seed a global Alpine toast from `portal/notifications/index.blade.php`'s
  `toast-error.window` event; have server actions return JSON + a flash
  envelope on AJAX submit.

### 16. Named queues
- **Where:** `SendPushNotification`, `GenerateAssignmentReminders`,
  `GenerateAppointmentReminders`, `MarkOverdueNoShows` all on
  `default` (`config/queue.php:41`). An FCM outage's retries contend
  with appointment reminders.
- **Fix:** `SendPushNotification` → `onQueue('notifications')`,
  reminder jobs → `onQueue('reminders')`, future AI job →
  `onQueue('ai')`. Run separate workers with independent concurrency.

### 17. Flutter has no retry/backoff
- **Where:** Each `*_api.dart` method is a single
  `await client.post/get(...)` with one `try/catch`. Transient network
  blips surface as user-facing errors. `notification_provider.dart`
  has no `Timer` — relies entirely on FCM `onMessage`.
- **Fix:** Add a Dio `InterceptorsWrapper.onError` that retries
  idempotent GETs with exponential backoff (3 attempts, 1s/2s/4s +
  jitter). For live messaging, subscribe to the same Reverb channel
  via `laravel_echo` + `socket_io_client` with a 15-30s polling
  `Timer` fallback.

---

## P3 — Accessibility & polish

### 18. Decide on the Tailwind/Vite toolchain
- **Where:** `package.json:14` declares `tailwindcss`,
  `resources/css/app.css` has `@tailwind base/components/utilities`,
  `vite.config.js` wires inputs — but no `tailwind.config.js` exists,
  no `@vite([...])` directive appears in any Blade view, and
  `public/build/` is absent. All layouts actually pull Bootstrap +
  Alpine from jsdelivr CDN.
- **Impact:** Dead weight that confuses maintainers and risks shipping
  empty CSS if someone ever runs `npm run build`.
- **Fix:** Either (a) — recommended given the Bootstrap basis — delete
  `resources/css/app.css`, drop `tailwindcss`/`postcss`/`autoprefixer`/
  `vite`/`laravel-vite-plugin` from `package.json`, delete
  `vite.config.js` and `resources/js/app.js`/`bootstrap.js`; or (b)
  actually wire `@vite(...)` into both layouts, add a
  `tailwind.config.js` with `darkMode: 'class'`, commit `public/build/`.

### 19. Add a skip-to-content link
- **Where:** Grep for `skip-to`/`skipnav`/`skip-link` across views →
  no matches. Neither auth layout has a skip link.
- **Fix:** Add as the first child of `<body>` in
  `layouts/app.blade.php` and `layouts/portal.blade.php`:
  `<a href="#main-content" class="visually-hidden-focusable">Skip to content</a>`
  with `<main id="main-content">`.

### 20. Add a global `aria-live="polite"` status region
- **Where:** No app-wide `role="status"`/`aria-live="polite"` region
  for successful async operations. The chatbot reply, reschedule
  success, and availability toggle all mutate the DOM silently —
  screen-reader users get no audible confirmation. Only *errors* on
  the portal notifications page push to a live region.
- **Fix:** Add
  `<div role="status" aria-live="polite" class="visually-hidden" x-data x-text="$store.announce.message"></div>`
  to both layouts, populated via an Alpine store + `announce`
  CustomEvent.

### 21. Add `:focus-visible` rules
- **Where:** `public/css/theraconnect.css` defines zero focus-visible
  rules. Custom components (`.tc-nav-item`, `.tc-filter`, `.tc-cal-day`,
  `.tc-kpi`) inherit only the browser default — some resets remove
  even that.
- **Fix:** Add `:focus-visible` outline rules in `theraconnect.css`
  for the custom `.tc-*` components.

### 22. Theme-init on landing + error pages
- **Where:** `landing.blade.php` and `errors/layout.blade.php` are
  hardcoded light with no `tc-theme` script. A user who toggled dark
  mode and then visits `/` (or hits a 404) sees a white flash.
- **Fix:** Extract the inline FOUC `<script>` to
  `resources/views/partials/theme-init.blade.php` and `@include` it in
  `landing.blade.php` and `errors/layout.blade.php`.

### 23. Standardise page headings to `<h1>`
- **Where:** Pages with mixed first heading: `<h2>` at
  `account/edit.blade.php:10`, `messages/index.blade.php:11`,
  `messages/show.blade.php:12`, `patients/show.blade.php:17`;
  `<h4>` at `auth/login.blade.php:10`, `auth/register.blade.php:10`.
- **Fix:** Make every page's first content heading `<h1>`.

### 24. Replace 9 native `confirm()` dialogs
- **Where:** `appointments/index.blade.php:92`,
  `patients/show.blade.php:137`, `clinicians/index.blade.php:43`,
  `patients/index.blade.php:61,66,129`,
  `portal/appointments/show.blade.php:65`,
  `portal/profile/show.blade.php:45`,
  `chatbot-content/index.blade.php:53`.
- **Impact:** Browser-blocking, unstyleable, inconsistently announced by
  screen readers.
- **Fix:** Replace with the existing Alpine + `x-trap` modal pattern
  (the app already loads the Focus plugin and uses it for the
  reschedule/conclude modals).

---

## P4 — Operational hygiene

### 25. Logging posture mismatch
- **Where:** `.env:29` ships `LOG_STACK=single` → framework logs go to
  `storage/logs/laravel.log` (ephemeral on Railway, wiped on redeploy),
  contradicting `SecurityHeaders.php:26` comment claiming
  "the `LOG_STACK=stderr` config also writes framework logs here".
- **Fix:** Set `LOG_STACK=stderr` in `.env` and `.env.example`. Add a
  `sentry` channel via `sentry/sentry-laravel` for aggregated
  production visibility.

### 26. Map exception classes → fixed string messages in appointment controllers
- **Where:** `$e->getMessage()` is echoed in
  `Api/V1/AppointmentController.php:154-161`,
  `Portal/PortalAppointmentController.php:134-136`,
  `Web/WebAppointmentController.php:64,86,186`.
- **Impact:** Safe today because the exceptions are authored with
  user-facing messages; a future
  `throw new InvalidStateException($databaseError)` would leak.
- **Fix:** Map exception class → fixed string in the controller
  (ignore dynamic message). Add an adversarial test (next to
  `tests/Adversarial/InformationLeakageTest.php`) that triggers each
  state-machine error and asserts no `trace`/`file`/`exception` keys
  or SQL/path substrings leak.

### 27. Enable `TrustHosts` middleware
- **Where:** `bootstrap/app.php:25` only calls
  `$middleware->trustProxies(at: ...)`, never `$middleware->trustHosts(at: ...)`.
- **Impact:** Latent today (no signed routes in use) but breaks
  Host-header validation if signed routes are ever added.
- **Fix:** Add
  `$middleware->trustHosts(at: env('APP_TRUSTED_HOSTS'))` in
  `bootstrap/app.php`. Set `APP_TRUSTED_HOSTS=theraconnect-demo-production.up.railway.app`
  in production `.env`.

### 28. Tighten `.env.example` CORS + session defaults
- **Where:** `.env.example:99` ships `CORS_ALLOWED_ORIGINS=*` (fine for
  dev since `supports_credentials=false`, but a footgun for copy-paste
  deploys). `.env.example` lacks `SESSION_SECURE_COOKIE`.
- **Fix:** Change to `CORS_ALLOWED_ORIGINS=` (blank). Add
  `SESSION_SECURE_COOKIE=true`. Consider `SESSION_LIFETIME=30` +
  `SESSION_EXPIRE_ON_CLOSE=true` for healthcare-adjacent posture.

### 29. Add a partial unique index on appointment slots
- **Where:** `database/migrations/2026_06_03_073856_create_appointments_table.php:25-26`
  only adds non-unique composite indexes.
  `AppointmentService::bookAppointment` (`:110-127`) uses
  `DB::transaction()` + `lockForUpdate()` at the app layer — correct,
  but depends on transaction isolation + index coverage of an OR
  predicate over `scheduled_at`/`requested_at`.
- **Fix:** Add a partial unique index as a DB-level backstop, or move
  reservation logic to a sentinel "slot_reservations" table. Index
  `requested_at` (currently unindexed — OR-branch scans).

### 30. Upload malware/content scanning
- **Where:** `mimes:` sniffs magic bytes — it'll happily accept a PDF
  with embedded JS or a DOCX with macros. The file goes to S3 and
  gets streamed down to a clinician's machine on download.
- **Fix:** ClamAV-in-sidecar or AWS GuardDuty/Macie on the bucket.

### 31. Sweep `NotificationService` events for PHI in `data` JSON column
- **Where:** `app/Models/Notification.php` fillable includes `data`;
  `notification_service` writes structured payloads. Unverified
  whether appointment `reason` or message text leaks in — sits
  unencrypted-at-rest in MySQL.
- **Fix:** Audit each `NotificationService` event method; redact any
  PHI before persisting to the `data` JSON column.

### 32. Docker container hardening audit
- **Where:** `Dockerfile` and `docker-compose.yml` not deeply audited.
- **Fix:** Verify running as non-root (`User www-data`), no debug
  ports exposed, `HEALTHCHECK` present (README mentions it on
  `README.md:225` but worth confirming), read-only root FS where
  feasible.

### 33. Backup/DR strategy documentation
- **Where:** `.env.example` mentions S3 for uploads but nothing about
  DB backups. For a healthcare-adjacent app, RPO/RTO has no answer in
  the repo.
- **Fix:** Document RPO/RTO + the database backup cadence (e.g.
  Railway's managed MySQL automated backups, daily + 7-day retention,
  plus a restore-test runbook).

### 34. Compliance adjacency decision
- **Where:** `.env:118` states: *"No PHI is sent — only the patient's
  typed message + generic clinic FAQ. (Note: Railway is not
  HIPAA-eligible.)"* — so the team is aware. But the `patients` table
  holds names, contact numbers, addresses, emergency contacts, and
  personal issues (per `UpdateProfileRequest` max-lengths).
- **Impact:** If real patients are onboarded, the demo/non-HIPAA
  framing stops being protective.
- **Fix:** Product/scope decision, not a code change. Either restrict
  to synthetic/demo data forever, or relocate to a HIPAA-eligible
  host (AWS Business Associate Agreement, Azure, GCP healthcare
  tier), sign BAAs, and complete a HIPAA risk assessment. Update the
  README "Limitations" section accordingly.
