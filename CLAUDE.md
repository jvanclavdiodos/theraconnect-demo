# CLAUDE.md — TheraConnect

Guidance for AI coding agents (Claude Code, etc.) and developers working in this repo.
Read this first, then see `README.md` (setup + endpoints) and `TheraConnect_Agent_Spec.md` (full original spec).

## What this is

A three-tier clinic management system — **one Laravel backend, two clients**:

- **Patient mobile app** — Flutter (`theraconnect_flutter/`), talks to `/api/v1` using Sanctum **bearer tokens**.
- **Clinician/admin web dashboard** — Blade + Bootstrap 5 + Alpine.js, **session** auth.
- **Backend** — Laravel 11 (PHP 8.2+), MySQL 8. Both clients call the **same service layer**.

## Run it

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --port=8080
```

- **Windows one-click:** `setup.ps1` (or double-click `setup.bat`).
- **Docker:** `docker compose up --build` → app on `:8080`, MySQL on `:3307`.
- **Tests:** `php artisan test` or `vendor/bin/phpunit` — **43 tests**. The suite runs on its own
  test DB; you do **not** need a running MySQL to run tests. (`php artisan migrate` *does* need MySQL.)
- **Flutter:** `cd theraconnect_flutter && flutter run` (or `flutter build apk --release`).

## Architecture & conventions — follow these

- **Thin controllers.** A controller validates (FormRequest), authorizes (Policy/Gate or `RoleMiddleware`),
  calls a **Service**, and returns a `JsonResource`/view. Any operation that writes >1 table, emits a
  notification, or is shared between web + API **must** live in `app/Services/`.
- **Dual auth, never mixed.** API routes use `auth:sanctum` + `role:patient`. Web routes use the session
  guard + `role:admin,clinician`. **Clinician/admin actions are never exposed on the JSON API.**
- **Roles.** `RoleMiddleware` (alias `role`) is the security boundary. The `@role` Blade directive is
  convenience only — never rely on hidden UI for access control.
- **Ownership.** Patients may only touch their own rows — enforced by Policies (`AppointmentPolicy`,
  `AssignmentPolicy`, `SubmissionPolicy`) via `Gate::authorize()`, in addition to role middleware.
- **API response shape.** Success: `{ "data": ... }` (a `JsonResource`); paginated lists add a `meta`
  block (`current_page`, `last_page`, `total`). Errors: `{ "message", "errors": { field: [...] } }` with 4xx/5xx.
- **File uploads are private.** Submissions **and** assignment worksheets are stored on the **private
  `local` disk** and served only through **authenticated download routes** — never the public disk, never
  `asset('storage/...')`. Web download routes are role-gated; API download routes check patient ownership.
  (See `AssignmentService`, `SubmissionController@downloadFile`, `AssignmentController@downloadWorksheet`.)
- **Notifications.** `NotificationService` writes the DB row (synchronous); the `SendPushNotification` job
  pushes via FCM (`FcmService`). FCM is optional — with no credentials it no-ops gracefully, and in-app
  notifications still work.
- **Appointments.** Booking a slot that's already taken for a clinician is rejected (see
  `AppointmentService::isSlotAvailable`). Reminders cover `approved` **and** `rescheduled`.
- **MySQL-flavored migrations** (ENUM columns). Don't assume SQLite/Postgres semantics in production.

## Where things are

| Area | Path |
|---|---|
| API controllers (mobile) | `app/Http/Controllers/Api/V1/` |
| Web controllers (dashboard) | `app/Http/Controllers/Web/` |
| Business logic | `app/Services/` (Appointment, Assignment, Notification, Chatbot, Fcm) |
| Validation / authz / serialization | `app/Http/Requests/Api/`, `app/Policies/`, `app/Http/Resources/` |
| Jobs / scheduler | `app/Jobs/`, `routes/console.php` |
| Routes | `routes/api.php` (`/api/v1`, Sanctum), `routes/web.php` (session) |
| Blade views | `resources/views/` |
| Tests | `tests/Integration/*FlowTest.php` |
| Flutter app | `theraconnect_flutter/lib/` |
| Flutter API base URL | `theraconnect_flutter/lib/config/api_config.dart` |
| Flutter Dio client + interceptors | `theraconnect_flutter/lib/services/api_client.dart` |
| Flutter state (Riverpod) | `theraconnect_flutter/lib/providers/` |

## Deployment (Railway, pilot)

- **Live:** https://theraconnect-demo-production.up.railway.app — repo `jvanclavdiodos/theraconnect-demo`.
- Builds the root `Dockerfile` (per `railway.json`); boot runs `storage:link → migrate --force → db:seed`
  (idempotent) → `php artisan serve` on `$PORT`. Env vars: see **`.env.railway.example`**.
- **Pilot trade-offs (not production-ready):** uploads are ephemeral (no volume/S3), `QUEUE_CONNECTION=sync`
  (no worker), no scheduler/cron (auto-reminders don't fire), FCM push disabled, demo passwords are `password`.

## Gotchas

- The IDE/PHP static analyzer flags `PHP6602` "magic method" on Eloquent `$model->prop` access and
  "undefined method `user()`" on `auth()->user()` **everywhere** — these are **false positives / noise**, not bugs.
- Tests use a separate DB connection — a missing MySQL does **not** block `php artisan test`.
- Two client surfaces share `users` (single table, `role` ENUM) with 1:1 `patients`/`clinicians` profile tables; admins have no profile row.
- After changing the Flutter API URL, you must **rebuild the APK** — pushing to GitHub does not update installed apps.

## Demo accounts (seeded)

`admin@theraconnect.test`, `clinician@theraconnect.test`, `dr.rivera@theraconnect.test`,
`patient@theraconnect.test`, `michael@theraconnect.test`, `emily@theraconnect.test` — all password `password`.
Patients use the mobile app; admin/clinician use the web dashboard.
