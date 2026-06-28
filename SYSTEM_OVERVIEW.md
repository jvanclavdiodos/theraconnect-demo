# TheraConnect — System Overview

> Generated from a code-level analysis of this repository. Where a detail could not be
> confirmed directly in code, it is marked **(inferred from code structure)**.

---

## 1. System Overview

**TheraConnect** is a three-tier **clinic management platform** for a mental-health / therapy
practice. It coordinates the full therapy lifecycle — intake, scheduling, therapeutic
homework, standardized assessments, progress tracking, and patient↔clinician communication.

**Who it serves**

- **Patients** — book appointments, complete assigned work and questionnaires, log mood,
  view goals/notes, message their clinician, and get a chatbot help assistant. They use a
  **Flutter mobile app** *or* an equivalent **browser portal** (full feature parity).
- **Clinicians** — manage their caseload, run their availability calendar, approve/schedule
  appointments, assign therapeutic work and questionnaires, set goals, write notes, and
  message patients. They use the **web dashboard**.
- **Admins** — provision clinicians, manage all patients, curate the chatbot knowledge base,
  and review notification/activity audit logs. They use the **web dashboard**.

**Main purpose:** one shared backend and business-logic layer driving two client surfaces —
a patient-facing app/portal and a staff-facing dashboard — so clinic operations and patient
self-service stay in sync.

---

## 2. Architecture

A single **Laravel 11 (PHP 8.2+)** backend with **MySQL 8** serves **three** front-end
surfaces, all routing through the **same service layer**.

| Layer | Technology | Notes |
|---|---|---|
| **Patient mobile app** | Flutter (Dart), Riverpod state, Dio HTTP | Talks to `/api/v1`; **Sanctum bearer tokens** |
| **Patient browser portal** | Blade + Bootstrap 5 + Alpine.js | **Session** auth; `/portal/*` routes; parity with the app |
| **Clinician/admin dashboard** | Blade + Bootstrap 5 + Alpine.js | **Session** auth; `/dashboard` + staff routes |
| **Backend API + Web** | Laravel 11 | Thin controllers → Services → Resources/Views |
| **Database** | MySQL 8 (SQLite in tests) | ENUM-flavored migrations; soft deletes widely used |
| **Auth** | Laravel Sanctum (API tokens) + session guard (web) | Dual auth, never mixed |
| **Background jobs** | Laravel Queue (`database` driver in prod) | Push delivery + scheduled reminders |
| **Scheduler** | Laravel scheduler (cron `schedule:run`) | Hourly/daily reminder + no-show jobs |
| **Notifications** | DB rows (sync) + FCM push (async via queue) | FCM optional; no-ops without credentials |
| **File storage** | Private `local` disk, or `s3` in prod | Served only through authenticated download routes |

**Layering convention (enforced by project rules):**

```
Request → Controller (validate via FormRequest, authorize via Policy/RoleMiddleware)
        → Service (app/Services/*)  ← all multi-table writes / notifications / shared logic
        → JsonResource (API) | Blade view (web)
```

Controllers are **thin**. Any operation that writes more than one table, emits a
notification, or is shared between web + API lives in `app/Services/`.

### Backend component map

| Area | Path |
|---|---|
| API controllers (mobile) | `app/Http/Controllers/Api/V1/` |
| Web controllers (staff dashboard) | `app/Http/Controllers/Web/` |
| Portal controllers (patient browser) | `app/Http/Controllers/Portal/` |
| Business logic (13 services) | `app/Services/` |
| Authorization | `app/Policies/` (8 policies) + `RoleMiddleware` |
| Serialization / validation | `app/Http/Resources/`, `app/Http/Requests/Api/` |
| Jobs / scheduler | `app/Jobs/` (4 jobs), `routes/console.php` |
| Routes | `routes/api.php` (Sanctum), `routes/web.php` (session) |
| Flutter app | `theraconnect_flutter/lib/` |

---

## 3. User Roles

Roles are a single `role` ENUM on the `users` table (`admin` | `clinician` | `patient`),
enforced by `RoleMiddleware` (alias `role`). Patients have a 1:1 `patients` profile row;
clinicians have a 1:1 `clinicians` row; admins have **no** profile row.

| Capability | Admin | Clinician | Patient |
|---|:---:|:---:|:---:|
| Surface used | Web dashboard | Web dashboard | Mobile app / browser portal |
| Manage clinicians (CRUD) | ✅ | — | — |
| Create patients | ✅ | ✅ (auto-assigned to self) | — (self-register only) |
| Edit/delete patient records | ✅ | — | — |
| View patients | ✅ (all) | ✅ (own caseload) | own profile only |
| Approve/deny clinician requests | ✅ | ✅ (if requested clinician) | — |
| Availability calendar | — | ✅ (own) | — |
| Approve/reject/reschedule/complete appointments | ✅ | ✅ (own, per-row Gate) | — |
| Book / cancel appointments | — | — | ✅ (own) |
| Assign worksheets & questionnaires | — | ✅ | — |
| Submit assignments / fill questionnaires | — | — | ✅ |
| Set therapy goals & GAS ratings | — | ✅ | view-only |
| Patient notes | — | ✅ (private + shared) | view shared only |
| Messaging | — | ✅ (with caseload) | ✅ (with assigned clinician) |
| Chatbot knowledge base | ✅ | — | — |
| Use chatbot assistant | — | — | ✅ |
| Notification & activity audit logs | ✅ | — | — |

> **Security boundary note:** Clinician/admin actions are **never** exposed on the JSON API.
> The mobile API is patient-only (`auth:sanctum` + `role:patient`); the API `/login` refuses
> non-patient roles with 403.

---

## 4. Core Features (Modules)

### Authentication & Profile Management
- **API (patients):** `register`, `login` (token issue), `logout`, `me`, change password,
  profile read/update, avatar upload/serve.
- **Web:** session login (`AuthenticatedSessionController`), patient self-registration
  (`RegisterController`), staff account/password/avatar management.
- Passwords hashed (`casts: password => hashed`); a `StrongPassword` rule applies on register.

### Patient Management
- Admin/clinician create & view; admin-only edit/delete. Clinician queries are **scoped to
  their caseload**. Rich profile fields (gender, education, employment, contact, emergency
  contact, encrypted `personal_issues`/`notes`).
- **Clinician request workflow:** self-registered patients pick a preferred clinician at
  sign-up; that clinician (or an admin) approves/denies to add them to the caseload.

### Clinician Management
- Admin CRUD for clinicians (`license_no`, `specialization`, `contact_no`).
- **Availability:** weekly recurring availability + per-date overrides power a booking
  calendar and slot generation (`AvailabilityService`).

### Appointment Booking & Approval
- Patient requests a slot (clinician-first). Status lifecycle:
  `pending → approved | rejected | rescheduled → completed | cancelled | no_show`.
- **Double-booking protection:** availability check + insert run inside a `DB::transaction`
  with `lockForUpdate`, preventing a TOCTOU race (`AppointmentService::bookAppointment` /
  `reschedule`).
- **Online sessions** get a stable **Jitsi** meeting link (kept across reschedules);
  links expire 5 hours after the scheduled time (`Appointment::meetingLinkActive`).

### Assignments & Submissions
- Clinicians assign therapeutic worksheets (optional file attachment, due date).
- Patients submit text and/or a file; clinicians review (`status`, `reviewed_at`).
- **Files are private + type-restricted** (`pdf,doc,docx,txt,rtf,jpg,jpeg,png`), stored on
  the private disk, served only via authenticated, ownership-checked download routes.
  Submissions support inline preview (image/pdf/text) with download fallback.

### Assessments, Mood Logs & Therapy Goals
- **Assessments:** standardized questionnaires (**PHQ-9 / GAD-7**); clinician assigns,
  patient completes; auto-scored with clinical severity bands (`app/Support/Assessments`).
- **Mood logs:** quick 1–10 daily check-ins by the patient.
- **Therapy goals:** clinician-authored, status `active|met|dropped`, tracked with
  **Goal Attainment Scaling (GAS)** ratings (−2…+2).

### Messaging
- One conversation per patient↔assigned-clinician pair, with unread tracking
  (`patient_last_read_at` / `clinician_last_read_at`) and participant-only access.
- Available on all three surfaces (API, portal, dashboard).

### Notifications
- In-app DB notifications written synchronously by `NotificationService` (typed: appointment
  approved/rejected/rescheduled/requested/reminder, assignment created/deadline,
  assessment assigned, message received, patient-request submitted/approved/denied).
- Optional **FCM push** delivered asynchronously via the `SendPushNotification` queue job.

### Chatbot Support
- Intent/response knowledge base (`ChatbotIntent` / `ChatbotResponse`, admin-curated).
- Patients query it through the app/portal (`ChatbotService`); rate-limited 30/min/user.

---

## 5. Data Flow

**Mobile API request (patient):**
```
Flutter (Dio + bearer token)
  → /api/v1/* → auth:sanctum + role:patient (+ throttle)
  → Controller (FormRequest validate, Policy authorize)
  → Service (business logic, transactional writes)
  → JsonResource → JSON { data, meta? }
```

**Web request (staff or patient portal):**
```
Browser (session cookie + CSRF)
  → /dashboard|/portal/* → auth + role:* (+ Gate per row)
  → Controller → Service → Blade view (HTML)
```

**Notification flow (write path):**
```
Service action (e.g. appointment approve)
  └─ inside DB::transaction:
       Service write  +  NotificationService->create() (DB row, synchronous)
  └─ after commit:
       SendPushNotification::dispatch(...)->afterCommit()  → queue
         → FcmService.send() per device token → FCM (or no-op if unconfigured)
```

**Scheduled/background flow:**
```
cron → schedule:run → dispatch jobs:
  • GenerateAssignmentReminders  (hourly)
  • GenerateAppointmentReminders (daily 08:00)
  • MarkOverdueNoShows           (daily 02:00)
→ each creates Notification rows → push job
```

**Multi-table writes are transactional**, and push dispatch uses `afterCommit()` so a push
is never sent for a write that rolled back.

---

## 6. Key Workflows (end-to-end)

**Patient registration & login**
1. Patient self-registers (app `/api/v1/register` or portal `/register`), optionally picking
   a preferred clinician → creates `User(role=patient)` + `Patient` (+ pending clinician
   request) in one transaction.
2. Login issues a Sanctum token (app) or a session (portal). Web login routes patients to
   `portal.dashboard`, staff to `/dashboard`.

**Booking an appointment**
1. Patient browses available slots (`/schedules`, `/schedules/availability`) computed from
   clinician availability minus active appointments.
2. `POST /appointments` → `bookAppointment()` re-checks availability + slot under a row lock
   inside a transaction → creates a `pending` appointment → notifies the clinician
   (`appointment_requested`).

**Clinician approve / reject / reschedule**
- **Approve:** sets `approved`, fixes `scheduled_at`, generates a Jitsi link if online →
  notifies patient (`appointment_approved`).
- **Reject:** sets `rejected` → notifies patient.
- **Reschedule:** transactional slot re-check → `rescheduled` + new time → notifies patient
  (and clinician if admin-initiated). Reminders cover `approved` **and** `rescheduled`.
- Patients cannot cancel `completed`/`rejected` appointments (Policy refuses terminal states).

**Assigning & submitting therapeutic work**
1. Clinician creates an assignment (optional worksheet) → notifies patient
   (`assignment_created`).
2. Patient downloads the worksheet, submits text/file → clinician reviews. Hourly job sends
   deadline reminders; daily job marks overdue appointments `no_show`.

**Sending messages**
1. Sender posts to the conversation (participant-only per `ConversationPolicy`).
2. `MessageService` stores the message, bumps `last_message_at`, and notifies the recipient
   (`message_received`).

**Generating & delivering notifications**
1. A service action (or scheduled job) calls `NotificationService` → writes a DB row.
2. `SendPushNotification` is dispatched after commit → `FcmService` pushes to each device
   token, sets `sent_at`, and prunes tokens FCM reports as `UNREGISTERED`.

---

## 7. Important Entities & Relationships

```
User (role: admin|clinician|patient)
 ├─1:1─ Patient ──── belongsTo assignedClinician / requestedClinician (Clinician)
 └─1:1─ Clinician
 ├─hasMany DeviceToken
 └─hasMany Notification

Clinician ─hasMany─ Appointment, Assignment, ClinicianWeeklyAvailability, ClinicianDateOverride

Patient ─hasMany─ Appointment, Assignment, Submission, PatientNote,
                  Assessment, MoodLog, TherapyGoal

Appointment      belongsTo Patient, Clinician   (mode, status, meeting_link, encrypted reason/notes)
Assignment       belongsTo Patient, Clinician ─hasMany─ Submission
Submission       belongsTo Assignment, Patient  (table: assignment_submissions)
Assessment       belongsTo Patient, Clinician   (instrument PHQ-9/GAD-7, score, responses[])
MoodLog          belongsTo Patient              (1–10 check-in)
TherapyGoal      belongsTo Patient, Clinician ─hasMany─ GoalRating (latestRating)
Conversation     belongsTo Patient, Clinician ─hasMany─ Message (+ read timestamps)
Message          belongsTo Conversation, sender (User)
PatientNote      belongsTo Patient, Clinician   (private | shared)
Notification     belongsTo User                 (typed; data JSON; channel; read/sent_at)
ChatbotIntent ─hasMany─ ChatbotResponse
ActivityLog                                       (audit trail)
```

Key column facts (from migrations/models):
- `appointments.status` ENUM: `pending, approved, rejected, rescheduled, completed, cancelled, no_show`; `mode`: `in_person|online`.
- Sensitive fields **encrypted at rest**: `appointments.reason`/`clinic_notes`,
  `patients.personal_issues`/`notes`.
- **Soft deletes** on users, patients, clinicians, appointments, assignments, assessments,
  goals, chatbot tables. Deleting a `Patient`/`Clinician` also soft-deletes its `User` in the
  same transaction.
- `device_tokens` unique on `(user_id, token)` — two patients can share a physical device.

---

## 8. Security & Access Control

- **Dual auth, never mixed.** API = `auth:sanctum` (bearer tokens); web/portal = session
  guard. API `/login` rejects non-patient roles (403), mirroring the web login blocking
  patients from the dashboard.
- **Role gating (`RoleMiddleware`)** is the primary boundary. Route groups:
  - `/api/v1/*` (authed) → `role:patient`.
  - `/dashboard` + staff routes → `role:admin,clinician`; nested `role:admin` (clinician CRUD,
    chatbot content, logs, patient edit/delete) and `role:clinician` (availability, notes,
    progress, messaging) sub-groups.
  - `/portal/*` → `role:patient`.
- **Ownership via Policies** (`AppointmentPolicy`, `AssignmentPolicy`, `SubmissionPolicy`,
  `ConversationPolicy`, `PatientPolicy`, `PatientNotePolicy`, `AssessmentPolicy`,
  `UserPolicy`) enforced with `Gate::authorize()` — patients touch only their own rows;
  clinicians act only on their caseload/own rows. Used **in addition to** role middleware.
- **Private, ownership-checked file downloads** — never the public disk, never
  `asset('storage/...')`; uploads are MIME-restricted.
- **Encryption at rest** for clinically sensitive fields (see §7).
- **Rate limiting:** `auth` (login/register), `chatbot` (30/min/user), `api` (60/min/user),
  plus tighter limits on avatar upload.
- **The `@role` Blade directive is convenience only** — never relied on for access control.
- **Proxy trust:** `bootstrap/app.php` trusts all proxies (correct behind a PaaS; should be
  narrowed if ever exposed without a proxy).
- **Mobile error hygiene:** `ApiError.fromException` collapses unexpected errors to a generic
  message so stack traces / backend internals never leak to patients.

---

## 9. Integrations & Infrastructure

| Piece | Detail |
|---|---|
| **Laravel Sanctum** | Bearer-token auth for the mobile API (`personal_access_tokens`). |
| **Firebase Cloud Messaging** | Optional push via `FcmService` (OAuth2 JWT → FCM v1). No-ops gracefully without credentials; auto-prunes unregistered tokens. |
| **Jitsi** | `JitsiService` generates online-session meeting links (public Jitsi server). |
| **Queues** | `SendPushNotification` + reminder jobs; `database` queue connection in prod. |
| **Scheduler** | `routes/console.php`: assignment reminders (hourly), appointment reminders (08:00), no-show marking (02:00). |
| **File storage** | Private `local` disk in dev; `s3` (private bucket) in prod via `FILESYSTEM_DISK=s3`. |
| **Timezone** | Runs on **Asia/Manila**; naive wall-clock times serialized with a trailing `Z` so the mobile app shows clinic-local time without conversion. Tests pin UTC. |
| **Deployment** | Railway (root `Dockerfile`, `railway*.json`) with a live pilot; Docker Compose full stack (`:8080` app, `:3307` MySQL, queue-worker + scheduler services). Boot gates on DB readiness, then `migrate --force` + `db:seed --force`; runs as non-root with a `/api/v1/health` healthcheck. |
| **i18n** | Flutter localization via ARB files (`lib/l10n/app_*.arb`, generated `app_localizations*.dart`). |

---

## 10. Testing & Readiness

**Testing**
- **~240 test methods across 41 test files** (inferred from a test-method name scan), almost
  all **integration/flow tests** in `tests/Integration/` (auth, booking, reschedule,
  completion, assignments, assessments, mood, goals, messaging API/web, notifications,
  policies, portal access/parity, clinician scoping, availability, chatbot, timezone,
  avatars, activity logs).
- Tests run on **in-memory SQLite** (no MySQL needed); production migrations are MySQL-ENUM
  flavored, so a few behaviors differ between test and prod DBs by design.
- Coverage emphasizes **authorization and workflow correctness** (role boundaries, ownership,
  transactional booking) — the security-critical paths.

**Production readiness — present**
- Clean layered architecture, transactional multi-table writes, race-safe booking,
  policy-based authorization, private file handling, encryption of sensitive fields,
  rate limiting, graceful FCM degradation, portable Railway/Docker deploy with health checks.

**Production readiness — deferred / limitations (from project docs & code)**
- App service runs single-process `php artisan serve` (swap to PHP-FPM + Nginx for prod).
- `queue-worker` / `scheduler` exist as deploy templates but require manual service creation.
- FCM is **background-only** on the Flutter side — foreground display and tap-to-deeplink are
  not implemented (deferred until FCM credentials are provisioned).
- Demo/seeded accounts all use password `password` — must be rotated before real use.
- Jitsi rooms on the public server cannot be cryptographically revoked; link expiry only
  gates the UI from surfacing them.

---

## Overall Assessment

**Strengths.** TheraConnect is a well-structured, convention-driven Laravel system with a
genuinely shared service/policy layer behind three clients. The security model is thoughtful
and consistent — strict dual auth, role middleware plus per-row policies, private
ownership-checked file access, encryption of clinical data, and race-safe appointment booking.
Notifications and reminders are cleanly separated into synchronous DB writes plus
after-commit async push, and the broad integration-test suite concentrates on exactly the
authorization and workflow paths that matter clinically.

**Current limitations.** It is positioned as a **pilot**, not hardened production: a
single-process app server, manually-provisioned queue/scheduler services, background-only
push notifications, and shared demo passwords. The public-Jitsi link model and the
test-vs-prod database divergence (SQLite vs MySQL ENUMs) are pragmatic trade-offs to be aware
of. None are architectural dead-ends — they are operational hardening items — but they should
be closed before handling real patient data.
