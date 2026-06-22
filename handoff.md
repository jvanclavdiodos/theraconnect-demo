# TheraConnect — Session Handoff

A living session-by-session changelog. Newest session first. Pair with `CLAUDE.md`
(conventions), `README.md` (setup/endpoints), and `SYSTEM_NOTES.md` (working notes).

---

## Session 3 — Security & Ops Hardening (June 18, 2026)

**Repo:** `jvanclavdiodos/theraconnect-demo` · **Live:** https://theraconnect-demo-production.up.railway.app
**Tests:** 65 passing, 229 assertions. No Flutter SDK on this machine — Dart changes are
unverified by `flutter analyze` (PHP suite stays green throughout).

A multi-lens code review (backend + frontend + devops subagents) surfaced 0 Critical, 3 High,
12 Medium, 19 Low findings. This session remediated all of them across three phases.

### Phase 1 — Correctness & security (backend)
- **Double-booking race fixed** — `AppointmentService::bookAppointment` + `reschedule` now run the
  availability check + insert inside `DB::transaction` with `lockForUpdate`, closing the TOCTOU
  race. New `SlotUnavailableException` → 409. (Previously the check was non-transactional.)
- **Web controllers transactional** — `WebAppointmentController::approve/reject/reschedule` and
  `WebAssignmentController::store` wrap the service call + `NotificationService->…()` in
  `DB::transaction` so `SendPushNotification::dispatch(...)->afterCommit()` actually means "after commit."
- **Schedule N+1 fixed** — `getScheduleSlots()` eager-loads the clinician instead of lazily
  loading per slot.
- **Upload mimes restricted** — `SubmissionRequest` now enforces
  `mimes:pdf,doc,docx,txt,rtf,jpg,jpeg,png` (matches the web allow-list).
- **Device tokens composite-unique** — new migration
  `2026_06_18_000000_make_device_token_unique_per_user.php` makes `(user_id, token)` unique, so
  two patients on a shared device can register the same physical FCM token without a 500.
- **SRI on CDN tags** — Subresource Integrity hashes added to the 4 Blade CDN `<script>`/`<link>` tags.
- **Chatbot scroll listener leak** — removed the listener that was never detached.
- **Flutter null-check** — `book_appointment_screen.dart` guarded a nullable access.

### Phase 2 — Deployment hardening (devops)
- **Dockerfile rewritten** — runs as **non-root `www-data`**, layer-cached, DB-ready gate
  (`until php artisan db:show …`) before `migrate --force` → `db:seed --force` (seed failures
  abort boot — dropped the `|| true` swallow), `HEALTHCHECK` probing `/api/v1/health`.
- **Railway configs** — `railway.json` updated; new `railway.worker.json` (queue worker) and
  `railway.scheduler.json` (cron-style scheduler) for the two auxiliary services.
- **Docker Compose hardened** — `docker-compose.yml` + `docker-compose.db.yml` updated with the
  new boot sequence + non-root user; includes `queue-worker` + `scheduler` services.
- **`setup.ps1` non-local guard** — refuses `migrate:fresh` against non-local DB hosts; bypass
  with `-SkipLocalGuard`. Also fixed em-dash encoding (added UTF-8 BOM, swapped em dashes for ASCII).
- **S3 support** — `league/flysystem-aws-s3-v3 ^3.0` added; `FILESYSTEM_DISK=s3` + `AWS_*` env
  vars documented in `.env.railway.example`. Patient submissions/worksheets served only through
  authenticated download routes; bucket must be private.
- **CORS published** — `config/cors.php`; `CORS_ALLOWED_ORIGINS` env var. `.env.example` +
  `.env.railway.example` hardened (`SESSION_ENCRYPT=true`, `QUEUE_CONNECTION=database`,
  `FILESYSTEM_DISK=s3` defaults for production).
- **`bootstrap/app.php`** — `trustProxies(at: '*')` (correct behind PaaS reverse proxy; restrict
  if directly exposed).

### Phase 3 — Code polish & Flutter (frontend)
- **Patient-only API login** — API `/login` now refuses non-`patient` roles with 403 (mirrors web
  login blocking patients).
- **`?date=` validation** — schedules endpoint validates the date format.
- **`AppointmentPolicy::delete`** — refuses cancel of `completed`/`rejected` (terminal states);
  still allows `cancelled` so the controller's 409 short-circuit fires.
- **`SubmissionPolicy`** — added `view` + `delete`; `SubmissionController@downloadFile` now uses
  `Gate::authorize('view', $submission)` (not an inline `abort_unless`).
- **Soft-delete User on profile delete** — deleting a `Patient`/`Clinician` row soft-deletes the
  related `User` in the same transaction (`WebProfileDeleteTest`).
- **`NotificationResource`** — created; `NotificationController` refactored to return
  `NotificationResource::collection(...)` (no hand-rolled `->map()` payloads).
- **`ApiError.fromException`** — Flutter factory that collapses any non-`ApiError` exception to a
  generic `"Something went wrong. Please try again."` (never leaks stack traces / API paths to
  patients). Applied across 8 screens.
- **Chatbot `_sending` reset** — flag now resets in a `finally` block on error.
- **FCM documented** — Flutter `FcmService` is **background-only** (foreground messages silently
  dropped; no tap-to-deeplink). Documented inline + in `CLAUDE.md`; foreground display deferred
  (needs `flutter_local_notifications`).
- **i18n scaffold** — `flutter_localizations` + `intl` added; `l10n.yaml`, `app_en.arb` created;
  `main.dart` wired. `flutter gen-l10n` auto-generates `app_localizations*.dart` (commit those).
- **Router refresh** — replaced global `_authNotifier` with a `_GoRouterRefreshAuth`
  ChangeNotifier subscribed to the auth state.
- **Filtered log interceptor** — `_FilteredLogInterceptor` suppresses `/login` + `/register`
  request/response bodies (tokens/credentials) from the Dio debug log.

### Test deltas this session
| Before | After |
|---|---|
| 53 tests (post-Session 2) | 65 tests / 229 assertions |
| Double-booking check non-transactional | `lockForUpdate` in `DB::transaction` |
| 0 tests on web profile delete | `WebProfileDeleteTest` (soft-deletes User) |
| `downloadFile` used `abort_unless` | `Gate::authorize('view', $submission)` |

---

## Session 2 — Bug Fixes, Worksheet Attachments & Railway Deployment (June 10, 2026)

**Repo:** `jvanclavdiodos/theraconnect-demo` · **Live:** https://theraconnect-demo-production.up.railway.app
**Tests:** 43 passing, 167 assertions.

### Correctness & security fixes
- **Double-booking prevented** — `AppointmentService::isSlotAvailable()` now guards API booking and web reschedule (was unchecked). *(Note: made fully race-safe in Session 3.)*
- **Admins can create assignments** — web create form has a clinician picker; `WebAssignmentController` resolves the author (clinician = self, admin = chosen). Previously admins were blocked.
- **Submission files made private** — moved from the public disk to the private `local` disk, served via authenticated download routes (`/api/v1/submissions/{id}/file`, web `submissions.file`). Previously world-readable via `asset('storage/...')`.
- **Reminders include rescheduled appointments**; assignment-deadline reminder uses a true 24h window.
- **Re-submitting a reviewed assignment is blocked** (was silently reverting the review).

### Feature — worksheet attachments
- Clinicians attach a worksheet when creating an assignment (web, multipart, private disk).
- Patients/staff download via authenticated routes; API exposes `attachment_url` + `attachment_name` on the assignment resource.
- Migration: `2026_06_09_000000_add_attachment_to_assignments_table.php`.

### Deployment (Railway, pilot)
- Added `railway.json`, `.env.railway.example`; `bootstrap/app.php` trusts the proxy (`trustProxies(at: '*')`); `DemoSeeder` made idempotent (safe on per-boot `db:seed`).
- Chosen trade-offs (at the time): ephemeral storage, `QUEUE_CONNECTION=sync`, no cron, FCM deferred. *(All improved in Session 3 — see S3 + queue worker + scheduler templates.)*

### Flutter
- API base URL pointed at the live Railway URL (`lib/config/api_config.dart`).
- **Fixed infinite sign-in/up spinner** — `AuthNotifier.login()/register()` only caught `ApiError`; any other thrown error left the state stuck on `AuthState.loading`. Added a catch-all that resets state and surfaces the message.
- Closing-brace fixes in the six `lib/services/api/*.dart` files and a dashboard nav fix (committed earlier).

---

## Session 1 — Core Build, Phases 1–9

`Phases completed: 1 through 9 (of 13).`

### Phase 1 — Project Scaffolding
- Laravel 11.6.1 installed with PHP 8.2.12 (XAMPP)
- Laravel Sanctum 4.3 for API token auth
- Bootstrap 5 + Alpine.js CDN wired into `layouts/app.blade.php`
- Rate limiters: `auth` (5/min/IP), `chatbot` (30/min/user), `api` (60/min/user)
- `RoleMiddleware` registered as `role` alias in `bootstrap/app.php`
- Flutter project stub (`theraconnect_flutter/`) with `pubspec.yaml` + `main.dart`
- `.env.example` pre-configured with MySQL defaults, Sanctum domains, FCM placeholders
- **Key decision:** Database driver defaults to MySQL (not SQLite) per spec §1.5

### Phase 2 — Database Schema
- 10-table MySQL schema matching spec §1.5 exactly
- Tables: `users` (role ENUM), `patients`, `clinicians`, `appointments`, `assignments`, `assignment_submissions`, `device_tokens`, `notifications`, `chatbot_intents`, `chatbot_responses`
- All ENUM columns, FKs with correct `ON DELETE` actions, composite indexes, JSON columns
- `ChatbotSeeder` — 9 intents (clinic_hours, clinic_location, appointment_steps, schedule_reminder, assignment_followup, greeting, thanks, goodbye, fallback)
- `DatabaseSeeder` — 1 admin + 1 clinician account
- 10 Eloquent models with relationships, `$fillable`, casts, `SoftDeletes`
- **Key decision:** MariaDB 10.4.32 from XAMPP used — fully compatible with spec's MySQL 8 requirement

### Phase 3 — Authentication & Role System
- API auth: `POST register/login/logout/me` issuing Sanctum bearer tokens
- Web auth: session-based login via `AuthenticatedSessionController`
- `RoleMiddleware` — comma-separated role enforcement, 401/403 for JSON vs web
- Ownership policies: `AppointmentPolicy`, `AssignmentPolicy`, `SubmissionPolicy`
- FormRequests: `RegisterRequest`, `LoginRequest`, `UpdateProfileRequest`
- JsonResources: `UserResource`, `PatientResource`
- Password hashing via Eloquent `hashed` cast (bcrypt rounds=12)
- `DB::transaction()` on register
- **Key decision:** API routes use `role:patient` middleware; web routes use `role:admin,clinician` — no cross-contamination

### Phase 4 — Appointments Module
- `AppointmentService` — create, cancel, approve, reject, reschedule, `getScheduleSlots()`
- 9 hourly slots (08:00–16:00) per clinician with conflict detection
- API: `GET /schedules`, `GET/POST /appointments`, `GET/DELETE /appointments/{id}`
- `StoreAppointmentRequest`, `AppointmentResource`, `ScheduleSlotResource`
- Ownership enforcement via `Gate::authorize()`
- Paginated index with `meta` envelope
- Double-cancel returns 409 Conflict
- `clinic_notes` conditionally hidden from patients
- **Key decision:** Conflict check uses both `scheduled_at` (approved/rescheduled) and `requested_at` (pending fallback)

### Phase 5 — Patient & Clinician Web Dashboard
- `PatientController` — full CRUD with `DB::transaction()` on store
- `ClinicianController` — admin-only CRUD (route wrapped in `role:admin`)
- `WebAppointmentController` — list/filter, approve, reject, reschedule (Alpine.js modal)
- `DashboardController` — real counts (total patients, pending, today's)
- 7 Blade views: patients/{index,create,edit,show}, clinicians/{index,create,edit}, appointments/index
- Sidebar with `request()->routeIs()` active highlighting + admin-only link gating
- Delete confirmations via Alpine.js dialogs
- RBAC: admin sees all; clinician sees all except Clinicians page
- **Key decision:** Web controllers use inline `$request->validate()` (not FormRequest) to keep iteration speed high; acceptable for web surface per spec

### Phase 6 — Assignment Module
- `AssignmentService` — create, submit (upsert via `updateOrCreate`), review
- `SubmissionRequest` — enforces content-or-file constraint via `passedValidation()`
- API: `GET /assignments`, `GET /assignments/{id}`, `POST /assignments/{id}/submit`
- Web: `WebAssignmentController` — index, create, store, submissions, review
- File upload to `storage/app/public/submissions/` via `storage:link` *(moved to private disk in Session 2)*
- `AssignmentResource` with conditional `submission_status`, `SubmissionResource` with `file_url`
- 3 Blade views: assignments/{index,create,submissions}
- Re-submit preserves existing `file_path` on text-only updates
- Old files deleted on re-upload
- **Key decision:** `file_path` only set when file is uploaded (not clobbered to null)

### Phase 7 — Notification System (FCM + Scheduler)
- `NotificationService` — 6 notification types per spec event map
- `FcmService` — Firebase HTTP v1 API with OAuth JWT, RFC 7519 base64url encoding, graceful skip without credentials, UNREGISTERED token cleanup
- `SendPushNotification` job — queried, sent_at gated on success
- `GenerateAssignmentReminders` job — hourly, deduplicates within 6h
- `GenerateAppointmentReminders` job — dailyAt 08:00, tomorrow's approved appointments
- `DeviceTokenController` — upsert on token, scoped delete
- `NotificationController` — paginated list, owner-scoped mark-read
- `NotificationLogController` — web audit log with paginated table
- `routes/console.php` scheduler — two separate schedule entries
- Notification triggers wired into appointment approve/reject/reschedule + assignment create
- `->afterCommit()` on all job dispatches
- **Key decision:** Split `GenerateScheduledReminders` into two separate jobs to prevent appointment reminders firing every hour

### Phase 8 — Chatbot Intent Engine
- `ChatbotService::resolve()` — normalize → tokenize → Jaccard similarity → threshold 0.34 → fallback
- `array_unique()` on intersection to prevent duplicate-token score inflation
- API: `POST /api/v1/chatbot/message` → `{data:{reply, intent_key, is_fallback}}`
- Web: `ChatbotContentController` — full CRUD for intents and responses
- Alpine.js dynamic forms for phrases + responses (add/remove, priority, is_fallback)
- Edit view: responses editor with delete + recreate sync
- SoftDeletes added to `chatbot_intents` + `chatbot_responses`
- Sidebar link uses named route (no stub badge)
- **Key decision:** Rule-guided, deterministic, zero-external-dependency. Can be swapped for API integration (OpenAI/Claude) by changing one service method.

### Phase 9 — Dashboard UI Polish
- Responsive sidebar: Alpine.js toggle with overlay backdrop, close button, escape-key listener
- Patient search: name/email/contact filter with clear button, pagination preserved
- `@error` directives with `is-invalid` on all create/edit forms
- Breadcrumbs on all 18+ views with `@hasSection` guard
- Dashboard activity feed: Recent Appointments + Pending Assignments
- Pagination: Clinicians + ChatbotContent upgraded from `->get()` to `->paginate(20)`
- Empty states: icons + CTA buttons on all 6 list views
- `@role` Blade directive registered in `AppServiceProvider` via `Gate::define()` + `Blade::if()`
- `Paginator::useBootstrapFive()` for consistent pagination styling
- **Key decision:** UI gating via `@role` is convenience only; middleware is authoritative

---

## Key Architectural Decisions

| Decision | Context | Rationale |
|---|---|---|
| **Dual auth surfaces** | Web = session + CSRF, API = Sanctum tokens | Blade benefits from stateful auth; mobile needs stateless bearer tokens |
| **Single `users` table with `role` ENUM** | Three roles share one table, 1:1 profile tables | Simpler auth, single login provider, cleaner than polymorphic |
| **Service layer pattern** | All multi-table writes, notifications, business logic in `app/Services` | Controllers stay thin; shared between web + API |
| **Database queue driver** | `QUEUE_CONNECTION=database` | No Redis dependency on shared hosting; sufficient for pilot volume |
| **Rule-guided chatbot** | Jaccard similarity, no LLM | Zero cost, no hallucination, tunable, staff-editable via dashboard |
| **MySQL via MariaDB** | XAMPP bundled MariaDB 10.4.32 | Drop-in compatible; ENUM, InnoDB, and utf8mb4 all work identically |
| **Sanctum token expiry** | 30 days (was null/unbounded) | Security hardening for PHI-adjacent data |
| **Separated scheduler jobs** | `GenerateAssignmentReminders` (hourly) + `GenerateAppointmentReminders` (dailyAt 08:00) | Prevents 24× duplicate appointment notifications |
| **`file_path` conditional persistence** | Only set when new file uploaded | Prevents clobbering existing files on text-only re-submit |
| **`@role` Blade directive** | `Gate::define('role', ...)` + `Blade::if('role', ...)` | Spec-compliant UI gating, convenience layer over middleware |
| **`lockForUpdate` double-booking** | Check + insert in `DB::transaction` (Session 3) | Closes TOCTOU race without a schema-level unique constraint |
| **S3 for uploads** | `FILESYSTEM_DISK=s3` + private bucket (Session 3) | Persistent across redeploys; private by default |
| **Queue worker + scheduler as separate services** | `railway.worker.json`, `railway.scheduler.json` (Session 3) | Lets `SendPushNotification::afterCommit` + reminders actually run on Railway |

---

## Credentials

| Role | Email | Password | Dashboard Access |
|---|---|---|---|
| Admin | `admin@theraconnect.test` | `password` | Full access (all pages) |
| Clinician | `clinician@theraconnect.test` | `password` | All except Clinicians page |
| Clinician | `dr.rivera@theraconnect.test` | `password` | Family Therapy specialist |
| Patient | `patient@theraconnect.test` | `password` | Jane Doe — mobile app only |
| Patient | `michael@theraconnect.test` | `password` | Michael Torres — mobile app only |
| Patient | `emily@theraconnect.test` | `password` | Emily Watson — mobile app only |

> Demo accounts use `password` — rotate before any real use. Patients use the Flutter mobile app;
> admin/clinician use the web dashboard.

---

## How to Resume

```bash
cd C:\projects\theraconnect-demo-main
git pull

# Start MySQL:
#   Docker (DB only):  docker compose -f docker-compose.db.yml up -d   # MySQL on :3307
#   Or host MySQL (XAMPP/Laragon) on :3306

# Start the backend
php artisan serve --port=8080
# (for phone access on LAN:  php artisan serve --host=0.0.0.0 --port=8080)

# Run the test suite (no MySQL needed — in-memory SQLite)
php artisan test
```

Open `http://localhost:8080` — login with `admin@theraconnect.test` / `password`.
