# TheraConnect — Session Handoff

**Date:** June 3, 2026  
**Repo:** https://github.com/TatoesTV/theraconnect  
**Branch:** `master` (commit `05d6a7b`)  
**Phases completed:** 1 through 9 (of 13)  

---

## What Was Built This Session

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
- File upload to `storage/app/public/submissions/` via `storage:link`
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

---

## Project Structure

```
theraconnect-b/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/        # 9 JSON controllers (patient API)
│   │   │   └── Web/           # 8 Blade controllers (dashboard)
│   │   ├── Middleware/RoleMiddleware.php
│   │   ├── Requests/Api/      # 5 FormRequest classes
│   │   └── Resources/         # 6 JsonResource classes
│   ├── Jobs/                  # 3 queued jobs
│   ├── Models/                # 10 Eloquent models
│   ├── Policies/              # 3 ownership policies
│   ├── Providers/             # AppServiceProvider (rate limiters, pagination, @role, policies)
│   └── Services/              # 4 services (Appointment, Assignment, Notification, Chatbot) + 1 FCM
├── config/                    # 13 config files (hashing, sanctum, queue, services, etc.)
├── database/
│   ├── migrations/            # 14 migration files
│   └── seeders/               # DatabaseSeeder, ChatbotSeeder
├── resources/views/           # 25+ Blade views across 8 directories
├── routes/
│   ├── api.php                # 20+ API endpoints under /api/v1
│   ├── web.php                # 25+ web routes
│   └── console.php            # Scheduler definitions
├── theraconnect_flutter/      # Flutter stub (pubspec.yaml + main.dart)
├── setup.ps1 + setup.bat      # One-click QA setup scripts
├── WALKTHROUGH.md             # Client demo guide (10 parts)
├── README.md                  # Project README with QA checklist (24 web + 20 API tests)
└── TheraConnect_Agent_Spec.md # Original architectural specification
```

---

## Test Results

**Comprehensive suite:** 94/94 tests passed across all phases

| Phase | Tests | Status |
|---|---|---|
| 1-2 Schema & Models | 15 | Pass |
| 3 Auth & Roles | 9 | Pass |
| 4 Appointments | 7 | Pass |
| 5 Web Dashboard CRUD | 9 | Pass |
| 6 Assignments | 9 | Pass |
| 7 Notifications + FCM | 11 | Pass |
| 8 Chatbot | 7 | Pass |
| 9 UI Polish | 9 | Pass |
| Data Integrity | 3 | Pass |
| Routes | 15 | Pass |

---

## Credentials

| Role | Email | Password | Dashboard Access |
|---|---|---|---|
| Admin | `admin@theraconnect.test` | `password` | Full access (all pages) |
| Clinician | `clinician@theraconnect.test` | `password` | All except Clinicians page |
| Patient | `patient@theraconnect.test` | `password` | Flutter mobile app |

---

## Immediate Next Steps

### Phase 10 — Flutter Mobile App (est. 4 days)
- Build all screens: login, register, dashboard, schedule, appointments, assignments, chatbot, notifications, profile
- GoRouter with `StatefulShellRoute` for bottom navigation
- Dio client with auth/error interceptors
- Riverpod providers for state management
- `flutter_secure_storage` for token persistence
- Firebase Messaging integration for push notifications
- **All 20+ API endpoints are ready and tested** — Flutter just needs to consume them

### Phase 11 — Integration & API Wiring (est. 2 days)
- End-to-end scenario testing: patient books on mobile → appears in dashboard → clinician approves → patient gets push → sees updated status
- Resolve any contract mismatches between Flutter models and JsonResources

### Phase 12 — System Testing & Usability (est. 3 days)
- PHPUnit feature tests on all API + web actions
- Postman collection for full API
- ISO/IEC 25010 test matrix
- System Usability Scale (SUS) evaluation with clinic staff

### Phase 13 — Deployment (est. 2 days)
- Nginx + PHP-FPM config for production VPS
- SSL/HTTPS via Let's Encrypt
- Supervisor for `queue:work`
- Crontab entry for scheduler
- Firebase production credentials
- Signed Android APK + Play-ready AAB
- Deploy runbook

---

## How to Resume

```powershell
cd C:\projects\theraconnect-b
git pull

# Start MySQL (if not running)
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini

# Start the server
php -d PHP_CLI_SERVER_WORKERS=4 artisan serve --port=8080
```

Open `http://localhost:8080` — login with `admin@theraconnect.test` / `password`.
