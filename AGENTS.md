# AGENTS.md — TheraConnect

Practical orientation for AI coding agents. Read this first, then dive into code.
Companion docs: **`README.md`** (setup + full API table) and **`SYSTEM_OVERVIEW.md`**
(deep architecture). This file is the *how to work in here safely* layer; it does not
repeat those in full.

---

## 1. What this is

TheraConnect is a **mental-health clinic management platform**: a single **Laravel 11
(PHP 8.2+)** backend + MySQL that serves **three** front-end surfaces through **one shared
service layer**:

| Surface | Who | Auth | Routes | Code |
|---|---|---|---|---|
| Flutter mobile app | Patients | Sanctum **bearer tokens** | `/api/v1/*` | `theraconnect_flutter/`, `app/Http/Controllers/Api/V1/` |
| Browser portal (parity with app) | Patients | **session** | `/portal/*` | `app/Http/Controllers/Portal/` |
| Staff dashboard | Clinicians + Admins | **session** | `/dashboard`, `/patients`, … | `app/Http/Controllers/Web/` |

Three roles, one `users.role` ENUM: `admin | clinician | patient`. **The JSON API is
patient-only** — clinician/admin functionality is never exposed on `/api/v1`.

---

## 2. Tech stack

- **Backend:** Laravel 11, PHP 8.2+ (CI tests 8.2 + 8.3). Sanctum 4 (API tokens),
  `league/flysystem-aws-s3-v3` (prod uploads). No other notable deps — see `composer.json`.
- **Web UI:** Blade + Bootstrap 5 + Alpine.js (no SPA build for the dashboard/portal).
  Vite exists but is minimal (`vite.config.js`, `resources/`).
- **DB:** MySQL 8 / MariaDB in dev & prod (port 3306 locally, 3307 under Docker).
  **Tests run on in-memory SQLite** — see §9 for the divergence this causes.
- **Mobile:** Flutter 3 / Dart, Riverpod state, Dio HTTP. ARB localization.
- **Async:** Laravel queue (`database` driver in prod, `sync` in tests) + scheduler.
- **Optional integrations:** Firebase Cloud Messaging (push), Jitsi (video links),
  Google Gemini (chatbot). All degrade gracefully to no-ops when unconfigured.

---

## 3. Repo map — where things live

```
app/
├── Http/Controllers/Api/V1/   # patient JSON API (thin)
├── Http/Controllers/Portal/   # patient browser portal (thin)
├── Http/Controllers/Web/      # clinician/admin dashboard (thin)
├── Http/Requests/Api/         # FormRequest validation (API)
├── Http/Resources/            # JsonResource transformers (API output shape)
├── Http/Middleware/           # RoleMiddleware, SecurityHeaders
├── Models/                    # 20 Eloquent models
├── Policies/                  # 8 ownership policies (per-row authorization)
├── Services/                  # 13 services — ALL real business logic lives here
├── Jobs/                      # SendPushNotification + 3 scheduled-reminder jobs
├── Rules/StrongPassword.php   # password policy (8–20, upper+digit, no spaces)
├── Support/Assessments.php    # PHQ-9 / GAD-7 question banks + scoring bands
├── Exceptions/                # InvalidStateException, SlotUnavailableException, ApiException
└── Providers/AppServiceProvider.php  # rate limiters, Gates, Carbon serialization, route patterns
routes/  api.php (Sanctum) · web.php (session: dashboard + /portal) · console.php (scheduler)
config/  app, auth, sanctum, cors, filesystems, queue, services (FCM/Jitsi/Gemini)
database/ migrations/ (ENUM-flavored) · seeders/ (DemoSeeder, ChatbotSeeder — idempotent)
resources/views/ layouts/ partials/ errors/ clinician/ portal/ landing.blade.php
tests/  Integration/ (39) · Adversarial/ (7) · Unit/+Feature/ (stubs) · TestCase.php · Concerns/
theraconnect_flutter/lib/  models/ providers/ screens/ services/ theme/ l10n/ config/
docker/  entrypoint.sh · wait-for-db.sh · php.ini
bootstrap/app.php  # middleware aliases, exception renderables (403/404/419/429/500), trustProxies
```

---

## 4. The one rule that matters most: layering

```
Request
  → Controller   (thin: validate via FormRequest, authorize via Policy/role middleware)
  → Service      (app/Services/* — ALL multi-table writes, notifications, shared logic)
  → JsonResource (API)  |  Blade view (web/portal)
```

**Controllers must stay thin.** Anything that writes more than one table, emits a
notification, or is shared between API + web/portal **belongs in a Service**. The portal
and API controllers deliberately reuse the *same* Services and Policies — when you add
patient functionality, do it in the Service so all three surfaces get it.

Example to copy: `AppointmentService` (`app/Services/AppointmentService.php`) — it owns
booking, approval, reschedule, completion, slot math, and state guards.

---

## 5. Auth, roles, and authorization (two layers, both required)

1. **Role gating — `RoleMiddleware`** (alias `role`): coarse boundary on route groups.
   - `/api/v1/*` (authed) → `role:patient`
   - `/dashboard` + staff routes → `role:admin,clinician`, with nested `role:admin`
     (clinician CRUD, chatbot content, logs, patient edit/delete) and `role:clinician`
     (availability, notes, progress, messaging) sub-groups
   - `/portal/*` → `role:patient`
2. **Ownership — Policies** (`app/Policies/`, called via `Gate::authorize(...)` /
   `$this->authorize(...)`): per-row checks. Patients touch only their own rows;
   clinicians act only on their caseload. **Always used in addition to role middleware,
   never instead of it.**

Key facts:
- API `/login` and web login **both reject the wrong role** (API refuses non-patients with
  403; web routes patients to `/portal`, staff to `/dashboard`).
- `@role` Blade directive is **UI convenience only** — never an access-control boundary.
- Route-model-bound params (`{appointment}`) 404 on bad IDs automatically; raw `{id}`
  params are constrained to `[0-9]+` in `AppServiceProvider` so a non-numeric id 404s
  cleanly instead of throwing a 500 TypeError.
- Authorization failures flow through `bootstrap/app.php` renderables → `{message:'Forbidden.'}`
  JSON or the branded `errors.403` view. **Throw `AuthorizationException`, don't `abort(403)`**
  (abort bypasses the unified renderable).

---

## 6. Data model — quick reference + gotchas

Relationship sketch (full version in `SYSTEM_OVERVIEW.md §7`):

```
User(role) ─1:1─ Patient   (belongsTo assignedClinician + requestedClinician)
           └1:1─ Clinician ─hasMany─ Appointment, Assignment, weeklyAvailabilities, dateOverrides
Patient ─hasMany─ Appointment, Assignment, Submission, Assessment, MoodLog, TherapyGoal, PatientNote
Appointment(status ENUM, mode) · Assignment ─hasMany─ Submission · TherapyGoal ─hasMany─ GoalRating
Conversation(patient+clinician) ─hasMany─ Message · Notification(typed) · ActivityLog · ChatbotIntent ─hasMany─ ChatbotResponse
```

Things that bite if you forget them:
- **Encrypted-at-rest columns** (`cast => 'encrypted'`): `appointments.reason` /
  `clinic_notes`, `patients.personal_issues` / `notes`. **You cannot `WHERE`/search these
  in SQL** — they're ciphertext at the DB. Decrypt in PHP only.
- **Appointment status is a state machine**: `pending → approved | rejected | rescheduled
  → completed | cancelled | no_show`. The Service enforces guards
  (`InvalidStateException`) — e.g. only `pending|rescheduled` can be approved, only
  `approved|rescheduled` can be rescheduled. **Don't bypass the Service to flip status.**
- **Double-booking is race-protected**: `bookAppointment` / `reschedule` re-check
  availability + slot inside `DB::transaction` with `lockForUpdate`. Any new slot-claiming
  path must do the same — don't add a check-then-write outside a locked transaction.
- **Soft deletes** on users, patients, clinicians, appointments, assignments, assessments,
  goals, chatbot tables. Deleting a `Patient`/`Clinician` also soft-deletes its `User` in
  the same transaction. Use `withTrashed()` deliberately; expect filtered queries.
- **`device_tokens` is unique on `(user_id, token)`** (not token alone) — two patients can
  share a physical device.
- **Email is lowercased on save** (`User::setEmailAttribute`) and on login lookup. Keep
  both sides lowercased if you touch auth.
- Admins have **no profile row** (no `patients`/`clinicians` record) — guard against that.

---

## 7. Timezone — read before touching any date

The app runs **`Asia/Manila`** (`config/app.php`) and stores **PH-local wall-clock** times.
`AppServiceProvider::boot()` overrides Carbon JSON serialization to emit the wall-clock with
a **fake trailing `Z`** (`Y-m-d\TH:i:s.u\Z`) — *not* a real UTC conversion. The Flutter app
(`date_format.dart`) deliberately ignores the offset and shows the wall-clock as-is. This is
an intentional API contract so the mobile app shows clinic-local time without a rebuild.

- **Don't "fix" this to real UTC** — it will shift every displayed time by +8h on mobile.
- **Tests pin `APP_TIMEZONE=UTC`** (`phpunit.xml`) for deterministic date assertions, so a
  test passing under UTC does not prove Manila wall-clock behavior. Cross-check timezone
  logic against `tests/Integration/TimezoneSerializationTest.php`.
- Appointment booking is **whole-hour only** (`HH:00`); slot generation lives in
  `AvailabilityService`.

---

## 8. Commands

Setup (Windows one-click): `.\setup.ps1` (auto installs, creates DB, migrates, seeds demo).
`-SkipLocalGuard` is required to run `migrate:fresh` against a non-local DB host (refused by
default — a guard against nuking a remote DB).

Manual / cross-platform:
```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed        # needs MySQL on :3306, or docker compose -f docker-compose.db.yml up -d (:3307)
php artisan serve --port=8080           # http://localhost:8080/login
```

Run / test / lint (run these before declaring work done):
```bash
php artisan test                # full suite on in-memory SQLite — NO MySQL needed
php artisan test --filter=Foo   # single test/class
vendor/bin/pint --test          # style check (CI fails on style violations)
vendor/bin/pint                 # auto-fix style
composer validate --strict      # CI runs this + `composer audit`
```

Docker full stack: `docker compose up --build` (app `:8080`, MySQL `:3307`, queue-worker,
scheduler). Flutter: `cd theraconnect_flutter && flutter pub get && flutter gen-l10n &&
flutter analyze` (CI runs `flutter analyze`; no SDK is installed in this workspace).

Demo accounts (password `password`, **rotate before real use**):
`admin@theraconnect.test`, `clinician@theraconnect.test`, `patient@theraconnect.test`, … (README has the full list).

---

## 9. Conventions & patterns to follow

- **New patient feature?** Add the logic to a Service, then wire it into *all three* of:
  API controller (`Api/V1`) + `Http/Requests/Api` + `Http/Resources`, Portal controller,
  and the relevant Blade view — plus the Flutter app. The integration tests assume parity
  (`PortalFeatureTest`, `PortalAccessTest`).
- **Validation:** API uses `FormRequest` classes in `app/Http/Requests/Api/`. Web/portal
  controllers usually validate inline. Passwords go through `App\Rules\StrongPassword`.
- **API responses** are shaped by `JsonResource` classes — `{ data, meta? }`. Don't return
  raw models from API controllers.
- **Notifications:** call `NotificationService` to write the DB row (synchronous, inside the
  same transaction as the action); push is dispatched **after commit** via
  `SendPushNotification::dispatch(...)->afterCommit()` so a push never fires for a rolled-back
  write. FCM no-ops gracefully when unconfigured. Notification `type` is an ENUM — adding a
  new type **requires a migration** (see the `add_*_to_notifications_type` migrations).
- **Files are always private + ownership-checked.** Uploads go to the private disk
  (`storage/app/private` locally, `s3` in prod), MIME/type-restricted, and are served
  *only* through authenticated download routes that re-check ownership via Policy. **Never**
  use the public disk or `asset('storage/...')` for patient/clinical files.
- **Rate limiters** are defined centrally in `AppServiceProvider` (`login` 5/min/IP,
  `account-login` 5/min/email+IP, `register` 3/min, `api` 60/min, `chatbot` 30/min,
  `password-change` 10/min). Apply via `throttle:<name>` middleware; don't invent ad-hoc limits.
- **Migrations are MySQL-ENUM heavy.** New enum values (status, mode, notification type)
  need an explicit migration that alters the ENUM. Match the existing dated-file style.
- **Style is enforced by Pint** (PSR-12-ish Laravel preset). Run `vendor/bin/pint` before
  committing; CI rejects violations.

---

## 10. Fragile areas — change with care

- **`bootstrap/app.php` exception renderables** — order matters and there are subtle notes in
  the comments (e.g. `AuthorizationException` must be matched alongside the wrapped
  `AccessDeniedHttpException`, and the 500 catch-all is gated to `production` so tests can pin
  `APP_DEBUG`). Read the inline comments before editing.
- **Appointment state machine + booking transactions** (`AppointmentService`) — the guards and
  `lockForUpdate` exist because adversarial tests (`tests/Adversarial/StateMachineLogicTest`,
  `JobIdempotencyTest`) target exactly these. Don't relax them.
- **Carbon serialization override** (§7) — touching it shifts every mobile timestamp.
- **`TRUST_PROXIES`** — `bootstrap/app.php` reads `env('TRUST_PROXIES')`. Set to `*` *only*
  behind a PaaS proxy; leaving it spoofable on a direct deploy is a security issue.
- **Test-vs-prod DB divergence:** SQLite (tests) is forgiving about ENUMs and some column
  semantics that MySQL (prod) enforces. A green test suite does **not** guarantee a migration
  runs on MySQL — sanity-check schema changes against a real MySQL/Docker DB.
- **`@json()` in Blade** truncates expressions with >2 commas → compile-time ParseError; wrap
  the argument in parentheses or a variable.
- **FCM is background-only** on Flutter and disabled by default; don't assume push is live.
  Config/env naming differs between docs — verify against `config/services.php` and
  `FCM_SETUP.md` before wiring credentials.

---

## 11. Configuration / env vars (highlights)

Full template: `.env.example`; prod template: `.env.railway.example`. Key ones:

- `APP_TIMEZONE=Asia/Manila` (prod) — see §7. `APP_DEBUG` defaults **false** in the template.
- `DB_*` — MySQL on 3306 (local) / 3307 (Docker).
- `FILESYSTEM_DISK` — `local` (dev, ephemeral) vs `s3` (prod, needs `AWS_*`, private bucket).
- `QUEUE_CONNECTION` — `database` (real deploy, needs a worker) vs `sync` (inline/tests).
- `SESSION_ENCRYPT=true`, `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`.
- `FCM_PROJECT_ID` / `FCM_CREDENTIALS` — push (optional; empty = disabled).
- `JITSI_BASE_URL` / `JITSI_ROOM_PREFIX` — video links (public server, no creds).
- `GEMINI_API_KEY` / `GEMINI_CHATBOT_MODEL` — optional AI chatbot; empty falls back to the
  built-in rule-based (Jaccard) matcher. (No PHI is sent to Gemini.)
- Seeding is env-gated on deploys: `db:seed --force` runs only when `APP_ENV=local` or
  `SEED_DEMO=true`. For a demo deploy set `SEED_DEMO=true` + a strong `DEMO_PASSWORD`.

---

## 12. How to verify changes safely before committing

1. **Run the relevant tests first, then the whole suite:** `php artisan test --filter=<Area>`
   then `php artisan test`. The suite is fast (SQLite) and the real safety net here is the
   **integration + adversarial** tests, not unit tests — match that style for new coverage.
2. **Lint:** `vendor/bin/pint --test` (or `vendor/bin/pint` to auto-fix). CI will reject style
   failures.
3. **If you changed schema/migrations or anything ENUM-ish, also test against MySQL** (Docker:
   `docker compose -f docker-compose.db.yml up -d` then point `.env` at `:3307`) — SQLite
   won't catch MySQL ENUM/constraint problems.
4. **If you changed an authorization path**, confirm both layers (role middleware + Policy)
   and check the relevant `tests/Adversarial/IdorBypassTest` / `PolicyTest` still pass.
5. **Manual smoke** when touching UI/flows: `php artisan serve --port=8080`, log in with a
   demo account for each affected role/surface.
6. **Don't commit or push unless asked.** Branch off `main` if you do (default working branch
   here is `master`). CI (`.github/workflows/ci.yml`) runs Pint + `composer audit` +
   `php artisan test` on PHP 8.2 & 8.3 + `flutter analyze` for every PR to `main`.

---

## 13. Flutter app (`theraconnect_flutter/`)

Patient-only client. Riverpod providers in `lib/providers/`, screens in `lib/screens/`,
HTTP/auth/FCM/cache in `lib/services/`. API base URL is in `lib/config/api_config.dart`
(point it at the backend, then `flutter build apk --release`). Errors are sanitized via
`ApiError.fromException` so backend internals never reach patients. There is **no Flutter SDK
in this workspace** — you can read/edit Dart but cannot build/run it here; rely on
`flutter analyze` semantics and the backend integration tests for verification.
