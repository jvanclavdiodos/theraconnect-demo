# CLAUDE.md — TheraConnect

Guidance for AI coding agents (Claude Code, etc.) and developers working in this repo.
Read this first, then see `README.md` (setup + endpoints) and `TheraConnect_Agent_Spec.md` (full original spec).
Working notes for the local experimental copy live in `SYSTEM_NOTES.md`; a session-by-session changelog lives in `handoff.md`.

## What this is

A three-tier clinic management system — **one Laravel backend, two clients**:

- **Patient mobile app** — Flutter (`theraconnect_flutter/`), talks to `/api/v1` using Sanctum **bearer tokens**.
- **Clinician/admin web dashboard** — Blade + Bootstrap 5 + Alpine.js, **session** auth.
- **Backend** — Laravel 11 (PHP 8.2+), MySQL 8. Both clients call the **same service layer**.

## Run it

Commands below use `php` / `composer` on PATH. On Windows, `setup.ps1` finds XAMPP/Laragon PHP automatically; on macOS/Linux, install PHP 8.2+ via Homebrew/apt.

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --port=8080
```

> Use `copy .env.example .env` on Windows `cmd.exe`; `cp` works in PowerShell 5+.

- **Windows one-click:** `setup.ps1` (or double-click `setup.bat`). The script refuses to run `migrate:fresh` against non-local DB hosts — pass `-SkipLocalGuard` to override.
- **Docker (full stack):** `docker compose up --build` → app on `:8080`, MySQL on `:3307`, plus a `queue-worker` + `scheduler` service.
- **Docker (DB only, for host-native PHP):** `docker compose -f docker-compose.db.yml up -d` (MySQL on `:3307`).
- **Tests:** `php artisan test` or `vendor/bin/phpunit` — runs on an in-memory SQLite DB; **no MySQL needed for tests**. (`php artisan migrate` *does* need MySQL.)
- **Flutter:** `cd theraconnect_flutter && flutter run` (or `flutter build apk --release`). After `flutter pub get`, `flutter gen-l10n` auto-generates `lib/l10n/app_localizations*.dart` from `lib/l10n/app_*.arb` — commit those generated files.

Run `php artisan test` for the current count — the suite grows as fixes land.

## Architecture & conventions — follow these

- **Thin controllers.** A controller validates (FormRequest), authorizes (Policy/Gate or `RoleMiddleware`),
  calls a **Service**, and returns a `JsonResource`/view. Any operation that writes >1 table, emits a
  notification, or is shared between web + API **must** live in `app/Services/`.
- **Dual auth, never mixed.** API routes use `auth:sanctum` + `role:patient`. Web routes use the session
  guard + `role:admin,clinician`. **Clinician/admin actions are never exposed on the JSON API.** The API
  `/login` endpoint additionally refuses non-`patient` roles with 403 (mirrors the web login blocking patients).
- **Roles.** `RoleMiddleware` (alias `role`) is the security boundary. The `@role` Blade directive is
  convenience only — never rely on hidden UI for access control.
- **Ownership.** Patients may only touch their own rows — enforced by Policies (`AppointmentPolicy`,
  `AssignmentPolicy`, `SubmissionPolicy`) via `Gate::authorize()`, in addition to role middleware.
  `SubmissionController@downloadFile` uses `Gate::authorize('view', $submission)` (not an inline `abort_unless`).
- **API response shape.** Success: `{ "data": ... }` (a `JsonResource`); paginated lists add a `meta`
  block (`current_page`, `last_page`, `total`). Errors: `{ "message", "errors": { field: [...] } }` with 4xx/5xx.
  All API controllers return `JsonResource` collections (e.g. `NotificationResource::collection($x)`) —
  don't hand-roll `->map()` payloads.
- **File uploads are private AND type-restricted.** Submissions **and** assignment worksheets are stored on
  the **private `local` disk** (or `s3` when `FILESYSTEM_DISK=s3` is set with `AWS_BUCKET`) and served only
  through **authenticated download routes** — never the public disk, never `asset('storage/...')`. Web
  download routes are role-gated; API download routes check patient ownership via Policy.
  `SubmissionRequest` restricts uploads to `mimes:pdf,doc,docx,txt,rtf,jpg,jpeg,png` (matches the web allow-list).
  (See `AssignmentService`, `SubmissionController@downloadFile`, `AssignmentController@downloadWorksheet`.)
- **Notifications.** `NotificationService` writes the DB row (synchronous); the `SendPushNotification` job
  pushes via FCM (`FcmService`). FCM is optional — with no credentials it no-ops gracefully, and in-app
  notifications still work. The Flutter `FcmService` is **background-only** today (foreground messages are
  silently dropped; tapping a notification does not deep-link). See [.env.railway.example FCM section] for
  provisioning credentials.
- **Appointments.** Booking a slot that's already taken for a clinician is rejected. The check + insert run
  inside `DB::transaction` with `lockForUpdate` (`AppointmentService::bookAppointment` + `reschedule`),
  preventing a TOCTOU double-booking race. Reminders cover `approved` **and** `rescheduled`. Patients
  cannot cancel `completed` or `rejected` appointments — `AppointmentPolicy::delete` refuses terminal states.
- **Multi-table writes are transactional.** `WebAppointmentController::approve/reject/reschedule` and
  `WebAssignmentController::store` wrap the service call + `NotificationService->…()` in `DB::transaction`
  so `SendPushNotification::dispatch(...)->afterCommit()` actually means "after commit." Deleting a
  `Patient`/`Clinician` row also soft-deletes the related `User` in the same transaction.
- **MySQL-flavored migrations** (ENUM columns). Don't assume SQLite/Postgres semantics in production.
  Tests default to in-memory SQLite (see `phpunit.xml`).
- **Device tokens.** Unique on `(user_id, token)` — two patients on a shared device can each register the
  same physical FCM token without a 500.

## Where things are

| Area | Path |
|---|---|
| API controllers (mobile) | `app/Http/Controllers/Api/V1/` |
| Web controllers (dashboard) | `app/Http/Controllers/Web/` |
| Business logic | `app/Services/` (Appointment, Assignment, Notification, Chatbot, Fcm, Jitsi) |
| Validation / authz / serialization | `app/Http/Requests/Api/`, `app/Policies/`, `app/Http/Resources/` |
| Jobs / scheduler | `app/Jobs/`, `routes/console.php` |
| Routes | `routes/api.php` (`/api/v1`, Sanctum), `routes/web.php` (session) |
| Blade views | `resources/views/` |
| Tests | `tests/Integration/*FlowTest.php` (+ `WebProfileDeleteTest.php`, `MeetingLinkTest.php`) |
| Flutter app | `theraconnect_flutter/lib/` |
| Flutter API base URL | `theraconnect_flutter/lib/config/api_config.dart` |
| Flutter Dio client + interceptors | `theraconnect_flutter/lib/services/api_client.dart` |
| Flutter state (Riverpod) | `theraconnect_flutter/lib/providers/` |
| Flutter i18n (l10n) | `theraconnect_flutter/lib/l10n/app_*.arb` + `l10n.yaml` |

## Deployment

The repo ships portable deployment configs for **Railway** and **Docker Compose**. Adapting to a plain VPS
(Nginx + PHP-FPM + Supervisor + cron) is straightforward — the Dockerfile boot sequence is the reference.

- **Railway pilot (live):** https://theraconnect-demo-production.up.railway.app — repo `jvanclavdiodos/theraconnect-demo`.
- **Builds:** root `Dockerfile` per `railway.json`. Container runs as **non-root `www-data`**, with a
  `HEALTHCHECK` probing `/api/v1/health`, and a DB-ready gate (`until php artisan db:show …`) before
  running `migrate --force` and `db:seed --force` (seed failures abort the boot — no `|| true`).
- **Boot sequence:** `storage:link` → wait-for-db → `migrate --force` → `db:seed --force` → `php artisan serve` on `$PORT` (or `8080`).
- **Env vars:** see **`.env.railway.example`** — production-flavored defaults (`APP_DEBUG=false`,
  `SESSION_ENCRYPT=true`, `FILESYSTEM_DISK=s3`, `QUEUE_CONNECTION=database`). `${{MySQL.*}}` references
  auto-fill from the Railway MySQL plugin. CORS, FCM, Jitsi, AWS S3 env all documented inline.
- **Auxiliary services (optional but recommended):**
  - **Queue worker:** deploy a second Railway service pointing at `railway.worker.json` (or use the `queue-worker` service in `docker-compose.yml`). Runs `php artisan queue:work --tries=3 --max-time=3600`.
  - **Scheduler:** deploy a third Railway service pointing at `railway.scheduler.json` (or the `scheduler` compose service). Runs as a Railway Cron service firing `php artisan schedule:run` every minute.
- **Persistent uploads:** set `FILESYSTEM_DISK=s3` + the `AWS_*` env vars (see `.env.railway.example`). A
  PRIVATE S3 bucket (block-public-access on) is required for patient submissions/worksheets.
- **`bootstrap/app.php` trusts all proxies** (`trustProxies(at: '*')`) — correct behind a PaaS reverse
  proxy. If the app is ever directly exposed (no proxy), restrict to the proxy's CIDR.

### Pilot trade-offs (still deferred — not production-ready)

- Single-process `php artisan serve` for the app service (acceptable for pilot; swap to PHP-FPM + Nginx for prod).
- Demo accounts use password `password` — rotate before any real use.
- Foreground FCM notifications not implemented (background-only). Foreground display + tap-to-deeplink
  require `flutter_local_notifications` (deferred until FCM credentials are provisioned).
- `scheduler`/`queue-worker` services exist as templates but are not auto-deployed — Railway requires
  manual service creation per `railway.*.json`.

## Gotchas

- The IDE/PHP static analyzer flags `PHP6602` "magic method" on Eloquent `$model->prop` access and
  "undefined method `user()`" on `auth()->user()` **everywhere** — these are **false positives / noise**, not bugs.
- Tests use a separate DB connection (in-memory SQLite) — a missing MySQL does **not** block `php artisan test`.
- Two client surfaces share `users` (single table, `role` ENUM) with 1:1 `patients`/`clinicians` profile tables; admins have no profile row.
- After changing the Flutter API URL, you must **rebuild the APK** — pushing to GitHub does not update installed apps.
- Flutter `ApiError.fromException(e)` collapses any non-`ApiError` exception to a generic
  `"Something went wrong. Please try again."` — never leak stack traces / API paths / backend
  exception text to patients on the mobile app.

## Demo accounts (seeded)

`admin@theraconnect.test`, `clinician@theraconnect.test`, `dr.rivera@theraconnect.test`,
`patient@theraconnect.test`, `michael@theraconnect.test`, `emily@theraconnect.test` — all password `password`.
Patients use the mobile app; admin/clinician use the web dashboard.
