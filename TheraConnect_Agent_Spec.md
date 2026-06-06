# TheraConnect — Agent-Ready Technical Specification & Build Plan

**Audience:** an autonomous coding agent. This document is self-contained. The agent must not require the original capstone documentation to execute it.

**Language rules in this doc:** decisions are stated as `must / will / is`. Every closed gap is tagged `[ARCHITECT DECISION]`. Every resolved contradiction in the source docs is tagged `[RESOLVED INCONSISTENCY]`. Phases needing a human gate are tagged `[HUMAN REVIEW REQUIRED]`.

**Fixed technology stack (non-negotiable):** Laravel 11 (PHP 8.2+), Blade + Bootstrap 5 web dashboard, Flutter 3.x / Dart mobile app, MySQL 8 (InnoDB, utf8mb4), REST/JSON over HTTPS, Firebase Cloud Messaging for push, Laravel Scheduler for timed jobs, an intent-based rule-guided chatbot. No LLM is used at runtime.

---

# Task 1 — Architectural Specification

## 1.1 System Overview

The system is a **three-tier client–server application** with a **single monolithic Laravel backend** serving **two clients**:

1. **Clinician Dashboard** — server-rendered Laravel Blade + Bootstrap, consumed in a browser by `admin` and `clinician` roles. Authenticated by **server session (cookie + CSRF)**.
2. **Patient Mobile App** — Flutter, consumed by the `patient` role. Authenticated by **Sanctum bearer token** over a versioned REST/JSON API.

`[RESOLVED INCONSISTENCY]` The source docs alternate between "authenticated sessions" and "API tokens." Resolution: **web = session, mobile = token**, both backed by one `users` table and one auth provider. Rationale: Blade is stateful and benefits from session+CSRF; the mobile client is stateless and needs a portable bearer token.

`[ARCHITECT DECISION]` Platform separation is by **route group and auth guard**, not by codebase. `routes/web.php` (guard `web`, session) serves the dashboard; `routes/api.php` (guard `sanctum`, prefix `/api/v1`) serves the mobile app. Both route groups call the **same service layer** (§1.2) so business rules are written once. The MySQL database is the single source of truth; the mobile app holds only a non-authoritative local cache.

`[RESOLVED INCONSISTENCY]` Video conferencing (Zoom/Google Meet) appears only in one architecture-diagram caption and contradicts the feasibility scope. Resolution: **no live video integration is built**. The `appointments.mode` column retains an `online` value for record-keeping, but the system stores a plain text meeting link only; it does not embed or call any video SDK.

---

## 1.2 Backend (Laravel)

### Directory & module structure
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/           # JSON controllers (mobile). Return JsonResource.
│   │   │   ├── AuthController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── AppointmentController.php
│   │   │   ├── AssignmentController.php
│   │   │   ├── SubmissionController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── DeviceTokenController.php
│   │   │   └── ChatbotController.php
│   │   └── Web/              # Blade controllers (dashboard). Return views.
│   │       ├── DashboardController.php
│   │       ├── AppointmentController.php
│   │       ├── PatientController.php
│   │       ├── ClinicianController.php   # admin-only
│   │       ├── AssignmentController.php
│   │       ├── ChatbotContentController.php
│   │       └── NotificationLogController.php
│   ├── Middleware/RoleMiddleware.php
│   ├── Requests/             # FormRequest validation classes (one per write action)
│   └── Resources/            # API JsonResource transformers
├── Services/                 # Business logic (see pattern below)
│   ├── AppointmentService.php
│   ├── AssignmentService.php
│   ├── NotificationService.php
│   ├── ChatbotService.php
│   └── FcmService.php
├── Jobs/
│   ├── SendPushNotification.php
│   └── GenerateScheduledReminders.php
├── Models/                   # Eloquent models (§1.5)
└── Console/Kernel.php        # Scheduler definitions (§1.8)
```

### API authentication — `[ARCHITECT DECISION]` Laravel **Sanctum**
The agent must install and use **Laravel Sanctum** for API auth. Not Passport, not custom JWT. Rationale: Sanctum is Laravel-first-party, issues simple personal-access tokens suited to a single mobile client, requires no OAuth server, and is the lightest correct option for a student-scale project. Passport (full OAuth2) is unjustified complexity; hand-rolled JWT adds maintenance and security risk.

- Tokens are issued by `POST /api/v1/login` and revoked by `POST /api/v1/logout`.
- The mobile app sends `Authorization: Bearer {token}` on every authenticated request.
- Token abilities are not granularly scoped beyond role enforcement (handled by `RoleMiddleware`).

### API versioning — `[ARCHITECT DECISION]`
All mobile endpoints are prefixed `/api/v1`. The prefix is set once in `routes/api.php` via `Route::prefix('v1')`. Future breaking changes will create `/api/v2`; v1 is never mutated incompatibly.

### Middleware stack
| Middleware | Applied to | Effect |
|---|---|---|
| `auth:sanctum` | all `/api/v1` routes except `register`, `login` | Rejects requests without a valid token (401) |
| `auth` (web session) | all dashboard routes except web login | Session guard for Blade |
| `RoleMiddleware:admin` / `:clinician` / `:patient` | per route | Enforces role (403 on mismatch). Accepts a comma list, e.g. `role:admin,clinician` |
| `throttle:auth` | login/register (both web + api) | 5 requests/min/IP |
| `throttle:chatbot` | `POST /api/v1/chatbot/message` | 30 requests/min/user |
| `throttle:api` | all other `/api/v1` | 60 requests/min/user |
| built-in HTTPS redirect | global | Force-redirect HTTP→HTTPS in production |

### Service layer vs controller logic — `[ARCHITECT DECISION]`
Controllers are **thin**: they validate (via `FormRequest`), authorize, call a service method, and format the response. **All business logic lives in `app/Services`.** Any operation that (a) writes more than one table, (b) emits a notification, or (c) is shared between the web and API controllers **must** be a service method. Simple single-table reads may query the model directly from the controller.

### Queues & jobs — `[ARCHITECT DECISION]`
The queue connection is the **database driver** (`QUEUE_CONNECTION=database`). Rationale: no Redis dependency on shared hosting; sufficient for pilot volume.
- `SendPushNotification` job: dispatched whenever a notification is created; calls `FcmService` so a slow FCM call never blocks the web/API request.
- `GenerateScheduledReminders` job: dispatched by the scheduler (§1.8).
A worker runs via `php artisan queue:work --tries=3` (documented in §1.10 as a supervisor/cron-kept process).

---

## 1.3 Mobile App (Flutter)

### Navigation — `[ARCHITECT DECISION]` **GoRouter**
Use `go_router`. Rationale: declarative, deep-link friendly (required for tapping FCM notifications into a specific screen), and the official routing recommendation for new Flutter apps. Route table:
```
/login  /register
/  (HomeShell with StatefulShellRoute / bottom nav)
   /dashboard   /schedule   /schedule/book
   /assignments /assignments/:id   /assignments/:id/submit
   /chatbot     /notifications   /profile
```
A redirect guard reads the stored token: no token → `/login`; token present → `/dashboard`.

### State management — `[ARCHITECT DECISION]` **Riverpod**
Use `flutter_riverpod`. Rationale: compile-safe, testable, no `BuildContext` coupling, and lighter ceremony than BLoC for an app of this size. Providers: `authProvider` (token + user), `appointmentsProvider`, `assignmentsProvider`, `notificationsProvider`, `chatbotProvider`. Network state exposed via `AsyncValue`.

### Local storage — `[ARCHITECT DECISION]`
| Data | Library | Reason |
|---|---|---|
| Sanctum bearer token | `flutter_secure_storage` | OS Keychain/Keystore-backed; a token is a credential and must not sit in plaintext |
| FCM device token (last sent) | `flutter_secure_storage` | credential-adjacent |
| Cached appointments/assignments, notification history, UI flags | `shared_preferences` (JSON-encoded) | non-sensitive cache only; never authoritative |
The local cache is read-through: UI renders cache immediately, then refreshes from the API. The cache is cleared on logout.

### API client — `[ARCHITECT DECISION]` **Dio**
Use `dio`. Rationale: built-in interceptors and error handling. One `Dio` instance, `baseUrl = {API_BASE}/api/v1`. Interceptors:
1. **Auth interceptor** — injects `Authorization: Bearer {token}` from secure storage on every request.
2. **Error interceptor** — on `401`, clears the token and routes to `/login`; surfaces a typed `ApiException` otherwise.
Timeouts: connect 10s, receive 15s.

---

## 1.4 Web Frontend (Laravel Blade + Bootstrap)

### Component/partial structure
```
resources/views/
├── layouts/app.blade.php          # Bootstrap 5 shell, role-aware sidebar, @yield('content')
├── partials/
│   ├── navbar.blade.php   sidebar.blade.php   flash.blade.php
│   └── tables/   modals/   forms/            # reusable fragments
├── auth/login.blade.php
├── admin/{clinicians,patients}.blade.php      # admin-only
├── clinician/{dashboard,appointments,patients,assignments,chat-content,notifications}.blade.php
```

### Role-based rendering — `[ARCHITECT DECISION]` middleware **and** Blade directives
Enforcement is server-side via `RoleMiddleware` on routes (the security boundary). UI is additionally hidden with Blade conditionals using a Gate-backed `@can`/custom `@role` directive. Blade hiding is convenience only; the route middleware is authoritative. The agent must never rely on hidden UI as the access control.

### JS interactivity — `[ARCHITECT DECISION]` **Alpine.js**
Use Alpine.js (CDN) for modals, dropdowns, inline toggles, and confirm dialogs. Not Livewire (avoids a server round-trip dependency for trivial UI) and not bare jQuery. Bootstrap 5's own JS bundle is used for its components; Alpine covers custom interactions.

---
## 1.5 Database Schema

Engine: **InnoDB**, charset **utf8mb4**, collation `utf8mb4_unicode_ci`. All `id` columns are `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`. `created_at`/`updated_at` are nullable `TIMESTAMP` (Laravel `timestamps()`). Soft-deletable tables add nullable `deleted_at TIMESTAMP` (Laravel `softDeletes()`).

`[RESOLVED INCONSISTENCY]` The docs name two roles in one section and three in the UI. Resolution: **three roles** via a single `users` table with a `role` enum, plus separate 1:1 profile tables `patients` and `clinicians`. Admins have no profile table (account only).

`[ARCHITECT DECISION]` A dedicated `device_tokens` table is added (not in source docs) because FCM delivery requires per-device tokens and one user may have multiple devices.

### `users`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(255) | NOT NULL |
| email | VARCHAR(255) | NOT NULL, UNIQUE |
| email_verified_at | TIMESTAMP | NULL |
| password | VARCHAR(255) | NOT NULL (bcrypt) |
| role | ENUM('admin','clinician','patient') | NOT NULL, DEFAULT 'patient' |
| remember_token | VARCHAR(100) | NULL |
| created_at / updated_at | TIMESTAMP | NULL |
| deleted_at | TIMESTAMP | NULL (soft deletes) |

Index: `UNIQUE(email)`.

### `patients` (1:1 with a `users` row where role='patient')
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK→users.id ON DELETE CASCADE |
| date_of_birth | DATE | NULL |
| contact_no | VARCHAR(20) | NULL |
| address | VARCHAR(255) | NULL |
| emergency_contact | VARCHAR(255) | NULL |
| notes | TEXT | NULL (clinical notes, clinician-visible only) |
| timestamps / deleted_at | | soft deletes |

### `clinicians` (1:1 with a `users` row where role='clinician')
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK→users.id ON DELETE CASCADE |
| license_no | VARCHAR(100) | NULL |
| specialization | VARCHAR(255) | NULL |
| contact_no | VARCHAR(20) | NULL |
| timestamps / deleted_at | | soft deletes |

### `appointments`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| patient_id | BIGINT UNSIGNED | NOT NULL, FK→patients.id ON DELETE CASCADE |
| clinician_id | BIGINT UNSIGNED | NULL, FK→clinicians.id ON DELETE SET NULL |
| requested_at | DATETIME | NOT NULL |
| scheduled_at | DATETIME | NULL (set on approval/reschedule) |
| mode | ENUM('in_person','online') | NOT NULL, DEFAULT 'in_person' |
| meeting_link | VARCHAR(512) | NULL (plain text; only when mode='online') |
| status | ENUM('pending','approved','rejected','rescheduled','completed','cancelled') | NOT NULL, DEFAULT 'pending' |
| reason | VARCHAR(500) | NULL (patient's stated reason) |
| clinic_notes | TEXT | NULL (clinician-only) |
| timestamps / deleted_at | | soft deletes |

Indexes: `INDEX(patient_id, status)`, `INDEX(clinician_id, scheduled_at)`.

### `assignments`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| clinician_id | BIGINT UNSIGNED | NOT NULL, FK→clinicians.id ON DELETE CASCADE |
| patient_id | BIGINT UNSIGNED | NOT NULL, FK→patients.id ON DELETE CASCADE |
| title | VARCHAR(255) | NOT NULL |
| description | TEXT | NULL |
| due_date | DATETIME | NULL |
| timestamps / deleted_at | | soft deletes |

Index: `INDEX(patient_id, due_date)`.

### `assignment_submissions`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| assignment_id | BIGINT UNSIGNED | NOT NULL, FK→assignments.id ON DELETE CASCADE |
| patient_id | BIGINT UNSIGNED | NOT NULL, FK→patients.id ON DELETE CASCADE |
| content | TEXT | NULL (text answer) |
| file_path | VARCHAR(512) | NULL (uploaded file, storage path) |
| status | ENUM('submitted','reviewed') | NOT NULL, DEFAULT 'submitted' |
| submitted_at | DATETIME | NOT NULL |
| reviewed_at | DATETIME | NULL |
| timestamps | | |

Constraint: at least one of `content`/`file_path` must be non-null (enforced in `SubmissionRequest`). Index: `INDEX(assignment_id)`, `UNIQUE(assignment_id, patient_id)` (one submission per assignment per patient; re-submission updates the row).

### `device_tokens`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | NOT NULL, FK→users.id ON DELETE CASCADE |
| token | VARCHAR(512) | NOT NULL, UNIQUE |
| platform | ENUM('android','ios') | NOT NULL, DEFAULT 'android' |
| last_used_at | TIMESTAMP | NULL |
| timestamps | | |

Index: `INDEX(user_id)`.

### `notifications`
This is a custom table (the agent must NOT use Laravel's default UUID notifications migration).
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | NOT NULL, FK→users.id ON DELETE CASCADE |
| type | ENUM('appointment_approved','appointment_rejected','appointment_rescheduled','appointment_reminder','assignment_created','assignment_deadline','generic') | NOT NULL |
| title | VARCHAR(255) | NOT NULL |
| body | TEXT | NOT NULL |
| data | JSON | NULL (deep-link payload, e.g. {"appointment_id":12}) |
| channel | ENUM('fcm') | NOT NULL, DEFAULT 'fcm' |
| sent_at | TIMESTAMP | NULL |
| read_at | TIMESTAMP | NULL |
| timestamps | | |

Index: `INDEX(user_id, sent_at)`.

### `chatbot_intents`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| intent_key | VARCHAR(100) | NOT NULL, UNIQUE |
| display_name | VARCHAR(255) | NOT NULL |
| category | ENUM('faq','scheduling','smalltalk','fallback') | NOT NULL |
| training_phrases | JSON | NOT NULL (array of strings) |
| is_active | BOOLEAN | NOT NULL, DEFAULT 1 |
| timestamps | | |

### `chatbot_responses`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| intent_id | BIGINT UNSIGNED | NOT NULL, FK→chatbot_intents.id ON DELETE CASCADE |
| response_text | TEXT | NOT NULL |
| is_fallback | BOOLEAN | NOT NULL, DEFAULT 0 |
| priority | INT | NOT NULL, DEFAULT 0 (higher wins when multiple responses) |
| timestamps | | |

### `personal_access_tokens`
Created by Sanctum's published migration. The agent must run it unmodified.

---

## 1.6 API Contract

Two surfaces. **REST API** (`/api/v1`, Sanctum token, JSON) is the mobile/patient surface. **Web routes** (session, Blade) are the clinician/admin surface. Both are listed; the agent must build both.

All API error responses use shape: `{ "message": string, "errors": { field: [string] } }` with HTTP 4xx/5xx. All success envelopes use `{ "data": ... }` (Laravel `JsonResource`).

### REST API — Auth (public unless noted)
| Method | URI | Auth | Request | Response |
|---|---|---|---|---|
| POST | /api/v1/register | none | `{name,email,password,password_confirmation,contact_no?}` | `201 {data:{user}, token}` |
| POST | /api/v1/login | none | `{email,password}` | `200 {data:{user}, token}` |
| POST | /api/v1/logout | token | — | `204` |
| GET | /api/v1/me | token | — | `200 {data:{user,patient_profile}}` |

### REST API — Profile (patient)
| Method | URI | Auth | Request | Response |
|---|---|---|---|---|
| GET | /api/v1/profile | token(patient) | — | `200 {data:{patient}}` |
| PUT | /api/v1/profile | token(patient) | `{date_of_birth?,contact_no?,address?,emergency_contact?}` | `200 {data:{patient}}` |

### REST API — Appointments (patient)
| Method | URI | Auth | Request | Response |
|---|---|---|---|---|
| GET | /api/v1/schedules | token | query `?date=` | `200 {data:[{slot,clinician_id,available}]}` |
| GET | /api/v1/appointments | token(patient) | — | `200 {data:[appointment]}` |
| POST | /api/v1/appointments | token(patient) | `{requested_at,mode,reason?,clinician_id?}` | `201 {data:{appointment}}` (status=pending) |
| GET | /api/v1/appointments/{id} | token(patient,owner) | — | `200 {data:{appointment}}` |
| DELETE | /api/v1/appointments/{id} | token(patient,owner) | — | `200 {data:{appointment}}` (status=cancelled) |

### REST API — Assignments & submissions (patient)
| Method | URI | Auth | Request | Response |
|---|---|---|---|---|
| GET | /api/v1/assignments | token(patient) | — | `200 {data:[assignment+submission_status]}` |
| GET | /api/v1/assignments/{id} | token(patient,owner) | — | `200 {data:{assignment}}` |
| POST | /api/v1/assignments/{id}/submit | token(patient,owner) | multipart `{content?, file?}` | `201 {data:{submission}}` |

### REST API — Notifications & device tokens
| Method | URI | Auth | Request | Response |
|---|---|---|---|---|
| GET | /api/v1/notifications | token | — | `200 {data:[notification]}` |
| POST | /api/v1/notifications/{id}/read | token(owner) | — | `200 {data:{notification}}` |
| POST | /api/v1/device-token | token | `{token, platform}` | `201 {data:{device_token}}` (upsert on token) |
| DELETE | /api/v1/device-token | token | `{token}` | `204` |

### REST API — Chatbot (patient)
| Method | URI | Auth | Request | Response |
|---|---|---|---|---|
| POST | /api/v1/chatbot/message | token | `{message}` | `200 {data:{reply, intent_key, is_fallback}}` |

### Web routes (session; clinician/admin) — resourceful
| Method | URI | Role | Action |
|---|---|---|---|
| GET | /login · POST /login · POST /logout | guest/any | session auth |
| GET | /dashboard | clinician,admin | overview |
| GET | /appointments | clinician,admin | list/calendar |
| PATCH | /appointments/{id}/approve · /reject · /reschedule | clinician,admin | status change (triggers notification) |
| GET POST | /patients · /patients/create | clinician,admin | list / create patient record |
| GET PUT DELETE | /patients/{id} · /patients/{id}/edit | clinician,admin | view/update/soft-delete |
| GET POST | /clinicians | **admin only** | manage clinician accounts |
| GET POST | /assignments · /assignments/create | clinician,admin | create assignment (triggers notification) |
| GET | /assignments/{id}/submissions | clinician,admin | view submissions |
| PATCH | /submissions/{id}/review | clinician,admin | mark reviewed |
| GET POST PUT DELETE | /chatbot-content | clinician,admin | CRUD intents & responses |
| GET | /notifications/logs | clinician,admin | notification audit log |

`[ARCHITECT DECISION]` Clinician/admin actions are NOT exposed on the JSON API. They run only through session-authenticated web routes. This keeps the public token API surface limited to patient-owned data.

---

## 1.7 AI Chatbot

`[RESOLVED INCONSISTENCY]` The docs describe a rule-guided intent chatbot but also price Dialogflow ES. Resolution: the runtime chatbot is **fully in-app and rule-guided. Dialogflow is NOT integrated.** Rationale: zero recurring cost, full control, no external dependency or hallucination risk, trivially testable. Dialogflow is explicitly out of scope for v1.

### Intent data structure — `[ARCHITECT DECISION]` lives in a **seeded DB table**
Chatbot data is stored in `chatbot_intents` + `chatbot_responses` (§1.5) and populated by a seeder (`ChatbotSeeder`). Not a flat file, not hardcoded — so clinic staff edit intents via the dashboard (`/chatbot-content`) without a deploy.

Each intent: `intent_key` (e.g. `clinic_hours`), `display_name`, `category` (faq|scheduling|smalltalk|fallback), `training_phrases` (JSON array), and ≥1 `chatbot_responses` row. Seeder must include at minimum these intents: `clinic_hours`, `clinic_location`, `appointment_steps`, `schedule_reminder`, `assignment_followup`, `greeting`, `thanks`, `goodbye`, and one `category='fallback'` intent with `is_fallback=1`.

### Matching algorithm — `[ARCHITECT DECISION]` normalized token-overlap scoring
`ChatbotService::resolve(string $message): array` executes:
1. Normalize: lowercase, trim, strip punctuation, collapse whitespace.
2. Tokenize input and each `training_phrase`.
3. Score each active intent = max over its phrases of `(|intersection of tokens| / |union of tokens|)` (Jaccard similarity).
4. If best score ≥ **0.34** → return that intent's highest-`priority` response.
5. Else → fallback (below).
No external NLP library. This is deterministic, dependency-free, and tunable via the single threshold constant.

### Fallback behavior — `[ARCHITECT DECISION]` precise
When best score < 0.34, the service returns the response from the intent where `is_fallback=1`, with `intent_key='fallback'` and `is_fallback=true` in the API payload. The fallback text directs the user to contact the clinic directly. If no active fallback row exists, return a hardcoded constant string `ChatbotService::DEFAULT_FALLBACK`. Small-talk (`category='smalltalk'`) is matched by the same algorithm and is never treated as fallback.

---

## 1.8 Notification System

### FCM device token collection & storage — `[ARCHITECT DECISION]`
1. On mobile login/app-start, Flutter requests notification permission and obtains the FCM token via `firebase_messaging`.
2. Flutter `POST`s it to `/api/v1/device-token` `{token, platform}`. The backend **upserts** into `device_tokens` keyed on the unique `token`, associating it with `auth()->id()`.
3. On token refresh (`onTokenRefresh`), Flutter re-posts. On logout, Flutter `DELETE`s the token.
4. The backend sends to **all** of a user's `device_tokens` rows; tokens rejected by FCM as unregistered are deleted.

### Laravel Scheduler cron jobs — `[ARCHITECT DECISION]` (defined in `Console/Kernel.php`)
A single system cron line drives the scheduler: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.
| Schedule | Command/Job | Trigger condition |
|---|---|---|
| `->hourly()` | `GenerateScheduledReminders` (assignment deadlines) | assignments with `due_date` within next 24h not yet reminded |
| `->dailyAt('08:00')` | `GenerateScheduledReminders` (appointment reminders) | appointments with `scheduled_at` on the next calendar day, status='approved' |
Each generated notification is written to `notifications` then dispatched via `SendPushNotification` job (database queue).

### Notification event map
| Event | Trigger point | Audience | type | Title / Body template |
|---|---|---|---|---|
| Appointment approved | clinician approves (web) | the patient | appointment_approved | "Appointment Approved" / "Your appointment on {scheduled_at} is confirmed." |
| Appointment rejected | clinician rejects (web) | the patient | appointment_rejected | "Appointment Update" / "Your requested appointment was not approved. Please rebook or contact the clinic." |
| Appointment rescheduled | clinician reschedules (web) | the patient | appointment_rescheduled | "Appointment Rescheduled" / "Your appointment is now set for {scheduled_at}." |
| Appointment reminder | scheduler (day before) | the patient | appointment_reminder | "Appointment Reminder" / "Reminder: you have an appointment tomorrow at {time}." |
| Assignment created | clinician creates (web) | the patient | assignment_created | "New Assignment" / "{clinician} assigned you: {title}." |
| Assignment deadline | scheduler (≤24h to due) | the patient | assignment_deadline | "Assignment Due Soon" / "'{title}' is due {due_date}." |
All templates are produced by `NotificationService`; the `data` JSON carries the relevant id for deep-linking.

---

## 1.9 Security

- **API auth:** Laravel Sanctum bearer tokens (§1.2), applied consistently to every `/api/v1` route except register/login. Web uses the session guard.
- **Password hashing — `[ARCHITECT DECISION]`:** bcrypt with **cost factor 12** (`config/hashing.php` → `'bcrypt' => ['rounds' => 12]`). Never store or log plaintext.
- **Roles & permission matrix:**

| Capability | Admin | Clinician | Patient |
|---|---|---|---|
| Manage clinician accounts | ✅ | ❌ | ❌ |
| View/manage all patients | ✅ | ✅ | ❌ |
| Approve/reject/reschedule appointments | ✅ | ✅ | ❌ |
| Create/review assignments | ✅ | ✅ | ❌ |
| Manage chatbot content | ✅ | ✅ | ❌ |
| View notification logs | ✅ | ✅ | ❌ |
| Register / log in (mobile) | ❌ | ❌ | ✅ |
| Book/cancel own appointments | ❌ | ❌ | ✅ |
| View/submit own assignments | ❌ | ❌ | ✅ |
| Edit own profile | ❌ | ❌ | ✅ |
| Use chatbot | ❌ | ❌ | ✅ |

Ownership checks (patient may only touch own rows) are enforced via Laravel Policies in addition to `RoleMiddleware`.

- **Rate limiting (per §1.2 named limiters):** `auth` = 5/min/IP; `chatbot` = 30/min/user; `api` = 60/min/user; web dashboard = default Laravel session protection + 1000/min global.
- **Transport:** HTTPS enforced; HSTS header set in production. All FCM and API traffic is TLS.
- **Validation:** every write endpoint uses a `FormRequest`; server-side validation is authoritative.

---

## 1.10 Deployment Architecture

- **Web server — `[ARCHITECT DECISION]` Nginx** with PHP-FPM. Rationale: lower memory footprint than Apache on a low-cost VPS and cleaner Laravel config. Root → `public/`; standard Laravel `try_files $uri /index.php?$query_string`.
- **Environments:** three: `local` (developer machine, `.env` with `APP_ENV=local`, `APP_DEBUG=true`), `staging` (VPS, mirrors production, seeded test data), `production` (VPS, `APP_DEBUG=false`, real FCM project). Migrations run per environment; never run `migrate:fresh` against staging/production after launch.
- **Process management:** `php artisan queue:work` kept alive by Supervisor (or a cron-restart guard if Supervisor is unavailable on shared hosting). Scheduler cron line per §1.8.
- **`.env` variables that MUST be defined before the agent begins:**
```
APP_NAME=TheraConnect
APP_ENV=local|staging|production
APP_KEY=            # php artisan key:generate
APP_URL=
APP_DEBUG=
DB_CONNECTION=mysql
DB_HOST= DB_PORT=3306 DB_DATABASE= DB_USERNAME= DB_PASSWORD=
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=          # web dashboard domain
FCM_PROJECT_ID=
FCM_CREDENTIALS=                   # path to Firebase service-account JSON
MAIL_*=                            # optional, for password reset
```
- **Mobile build targets — `[ARCHITECT DECISION]`:** pilot ships an **Android APK** (`flutter build apk --release`) for sideloading onto test devices; a signed **AAB** (`flutter build appbundle --release`) is produced and kept Play-Store-ready but not published in v1. iOS is code-compatible but not built in this phase.

---
# Task 2 — Phased Development Plan (Agent Execution Order)

Phases are sequential and dependency-aware. The agent must complete and pass the **Verification** of each phase before starting the next. Effort is in dev-days for a single agent working continuously.

---

### Phase 1 — Project Scaffolding & Environment Setup
**Goal:** Stand up empty Laravel and Flutter projects with all dependencies and a connected database.
**Inputs:** Stack list (§intro); `.env` variables (§1.10) provided by the operator.
**Deliverables:**
- Laravel 11 project; install `laravel/sanctum`, configure `config/hashing.php` rounds=12, queue=database, session=database.
- Publish Sanctum migration; configure `routes/api.php` with `/v1` prefix and named rate limiters (`auth`, `chatbot`, `api`).
- Flutter project; add `flutter_riverpod`, `go_router`, `dio`, `flutter_secure_storage`, `shared_preferences`, `firebase_messaging`, `firebase_core`.
- Bootstrap 5 + Alpine.js wired into `layouts/app.blade.php`.
- `RoleMiddleware` stub registered.
**Verification:** `php artisan serve` boots; `GET /api/v1/health` returns 200; `flutter run` launches a placeholder home; DB connection succeeds (`php artisan migrate:status`).
**Estimated effort:** 1.5 days. **Risk:** Low — boilerplate.

### Phase 2 — Database Schema & Migrations
**Goal:** Create every table in §1.5 exactly.
**Inputs:** Phase 1; §1.5 schema.
**Deliverables:** One migration per table with all columns, types, FKs, nullables, indexes, soft deletes; Eloquent models with relationships, `$fillable`, casts (`training_phrases`/`data` → array), and `SoftDeletes` traits; `ChatbotSeeder` populating the minimum intent set (§1.7); a `DatabaseSeeder` creating one admin and one clinician account.
**Verification:** `php artisan migrate:fresh --seed` runs clean; a tinker check confirms relationships resolve (e.g. `Patient::first()->user`); `chatbot_intents` has ≥9 rows including one fallback.
**Estimated effort:** 2 days. **Risk:** Medium — FK ordering and the soft-delete/unique-index interaction must be correct.
**[HUMAN REVIEW REQUIRED]** — confirm schema matches clinic data expectations before any later phase builds on it.

### Phase 3 — Authentication & Role System
**Goal:** Working registration/login for both surfaces with role enforcement.
**Inputs:** Phases 1–2; §1.2, §1.9.
**Deliverables:** API `register/login/logout/me` issuing Sanctum tokens; web session login; `RoleMiddleware` enforcing admin/clinician/patient; Policies for ownership; bcrypt rounds=12 confirmed; `throttle:auth` applied.
**Verification:** Postman: register→login returns a token; an authenticated `GET /api/v1/me` returns the user; a patient token hitting a clinician web route returns 403; brute-forcing login trips the limiter at request 6.
**Estimated effort:** 2 days. **Risk:** Medium — token + session coexistence and role guards are foundational; errors here cascade.
**[HUMAN REVIEW REQUIRED]** — security boundary; verify before exposing further endpoints.

### Phase 4 — Core API: Appointments Module
**Goal:** Patients request appointments; backend stores and exposes status.
**Inputs:** Phase 3; §1.5 appointments, §1.6.
**Deliverables:** `AppointmentService`; API `GET /schedules`, `GET/POST /appointments`, `GET/DELETE /appointments/{id}`; `AppointmentRequest` validation; JsonResources; ownership policy.
**Verification:** Postman: a patient creates an appointment (status=pending); lists it; cannot read another patient's appointment (403); cancel sets status=cancelled.
**Estimated effort:** 2 days. **Risk:** Medium — first cross-resource service; slot/availability logic must be deterministic.

### Phase 5 — Core API: Patient & Clinician Management
**Goal:** Clinic staff manage patient and clinician records via the dashboard backend.
**Inputs:** Phases 3–4.
**Deliverables:** Web controllers + routes for patient CRUD (clinician,admin) and clinician CRUD (admin only); profile API `GET/PUT /profile` for patients; appointment status-change web actions (`approve/reject/reschedule`) wired to `AppointmentService`.
**Verification:** As clinician, create/edit/soft-delete a patient; as clinician, approving an appointment flips status to approved and sets `scheduled_at`; as clinician (non-admin), accessing `/clinicians` returns 403.
**Estimated effort:** 2 days. **Risk:** Low–Medium — mostly CRUD; risk is RBAC on the admin-only clinician routes.

### Phase 6 — Core API: Assignment Module
**Goal:** Clinicians assign work; patients view and submit.
**Inputs:** Phases 3–5.
**Deliverables:** `AssignmentService`; web create/list/review; API `GET /assignments`, `GET /assignments/{id}`, `POST /assignments/{id}/submit` (multipart upload to Laravel storage); upsert submission honoring `UNIQUE(assignment_id,patient_id)`; `SubmissionRequest` enforcing content-or-file.
**Verification:** Clinician creates an assignment for a patient; patient lists it, submits text+file; re-submitting updates the same row; clinician sees the submission and marks it reviewed.
**Estimated effort:** 2 days. **Risk:** Medium — file uploads + storage permissions are a common failure point.

### Phase 7 — Notification System (FCM + Laravel Scheduler)
**Goal:** Event and scheduled notifications delivered to devices and logged.
**Inputs:** Phases 3–6; Firebase project + service-account JSON (§1.10); §1.8.
**Deliverables:** `device_tokens` API (`POST/DELETE /device-token`); `FcmService`; `NotificationService` writing `notifications` rows; `SendPushNotification` queued job; `GenerateScheduledReminders` job; `Console/Kernel.php` schedules (hourly + dailyAt 08:00); notification triggers wired into appointment approve/reject/reschedule and assignment create; unregistered-token cleanup.
**Verification:** Approving an appointment writes a notification row and (with a real device token) delivers a push; running `php artisan schedule:run` at a forced time generates deadline reminders; `queue:work` drains the job; rejected FCM tokens are deleted.
**Estimated effort:** 3 days. **Risk:** **High** — FCM credentials, device-token lifecycle, queue worker, and scheduler timing are the most error-prone integration. Allocate buffer.
**[HUMAN REVIEW REQUIRED]** — verify push delivery on a physical Android device before proceeding.

### Phase 8 — AI Chatbot: Intent Engine
**Goal:** Deterministic intent matching with fallback, fully in-app.
**Inputs:** Phase 2 (seeded intents); §1.7.
**Deliverables:** `ChatbotService::resolve()` with normalization, Jaccard scoring, threshold 0.34, fallback; API `POST /api/v1/chatbot/message`; web `/chatbot-content` CRUD for intents/responses; `throttle:chatbot`.
**Verification:** Unit tests: a known training phrase returns its intent; a nonsense string returns `is_fallback=true`; a small-talk phrase returns a smalltalk response (not fallback); editing an intent in the dashboard changes the live reply.
**Estimated effort:** 2 days. **Risk:** Medium — intent coverage is open-ended; threshold tuning may need iteration, but the engine itself is bounded.

### Phase 9 — Web Dashboard (Blade): All Clinician Views
**Goal:** Complete, role-aware clinician/admin UI over the existing backend.
**Inputs:** Phases 3–8 (all web backend actions exist).
**Deliverables:** Blade views for dashboard, appointments (list/calendar + approve/reject/reschedule), patient management, clinician management (admin), assignments + submissions review, chatbot content, notification logs; Bootstrap layout; Alpine.js interactions; `@role`/`@can` UI gating layered over middleware.
**Verification:** Manual walkthrough as admin and as clinician confirms every documented screen renders, every action persists, and admin-only screens are hidden+blocked for clinicians.
**Estimated effort:** 3 days. **Risk:** Low–Medium — UI volume, not complexity.

### Phase 10 — Mobile App (Flutter): All Patient Views
**Goal:** Complete patient app consuming the v1 API.
**Inputs:** Phases 3–8; running API; Firebase config files in the Flutter project.
**Deliverables:** GoRouter routes + auth redirect; Riverpod providers; Dio client with auth/error interceptors; screens: sign-up, sign-in, dashboard, schedule+book, assignments+submit, chatbot, notifications, profile; secure-storage token handling; shared_preferences cache; FCM token registration + tap-to-deep-link.
**Verification:** On a device/emulator: register→login persists token across restart; book an appointment and see it; submit an assignment; chat returns intent replies; a push notification arrives and deep-links to the right screen; logout clears cache.
**Estimated effort:** 4 days. **Risk:** **High** — cross-platform Flutter behavior (token persistence/refresh, push handling, cache consistency) concentrates here. Allocate buffer.

### Phase 11 — Web–Mobile Integration & API Wiring
**Goal:** Prove end-to-end flows across both clients against one backend.
**Inputs:** Phases 9–10.
**Deliverables:** Integration test scripts/checklist covering the documented scenarios; resolution of any contract mismatches between Flutter models and JsonResources; consistent error handling.
**Verification (scenario-based):** patient books on mobile → appears in dashboard; clinician approves → patient receives push + sees updated status; clinician posts assignment → patient retrieves and submits → clinician sees submission; deadline reminder fires on schedule.
**Estimated effort:** 2 days. **Risk:** **High** — timing, token expiry, and serialization mismatches surface here. Allocate buffer.

### Phase 12 — System Testing & Usability Evaluation
**Goal:** Validate against quality criteria and run the prototype usability evaluation.
**Inputs:** Phase 11 (integrated system).
**Deliverables:** Automated test suite (PHPUnit feature tests on every API + web action; Flutter widget tests on key screens); Postman collection for the full API; an ISO/IEC 25010 test matrix (functional suitability, usability, performance, reliability, security, compatibility, notification delivery) with pass/fail per item; a purposive-sample usability session plan (≥1 clinic decision-maker, ≥1 staff user, several patient testers) using **System Usability Scale (SUS)** scoring + task-success rates over the documented tasks (login, book, view schedule, receive reminder, access/submit assignment, use chatbot).
**Verification:** test suite green; SUS questionnaire administered and scored; task-success rates computed; defect log triaged.
**Estimated effort:** 3 days (excluding human participant scheduling). **Risk:** Medium–High — recruiting the purposive sample and collecting clean SUS data is a schedule dependency.
**[HUMAN REVIEW REQUIRED]** — usability evaluation requires human participants and cannot be agent-completed; the agent prepares instruments and harness, humans run sessions.

### Phase 13 — Deployment & Handoff
**Goal:** Ship the pilot to a VPS and hand off to the clinic.
**Inputs:** Phase 12 passed.
**Deliverables:** Nginx + PHP-FPM config; production `.env`; `php artisan migrate --force` + seed admin/clinician; SSL/HTTPS + HSTS; Supervisor for `queue:work`; scheduler cron line; Firebase production credentials installed; signed Android **APK** for pilot devices + Play-ready **AAB**; README/runbook (deploy steps, env vars, backup/restore, how to add an intent).
**Verification:** production URL serves over HTTPS; a smoke test of login/book/notify passes on the live server; scheduled job runs once on the server; APK installs and connects to production API.
**Estimated effort:** 2 days. **Risk:** Medium — shared-hosting/VPS limits (cron, PHP extensions, storage perms, SSL) are typical last-mile snags.
**[HUMAN REVIEW REQUIRED]** — final go-live sign-off by the clinic before pilot distribution.

---

## Critical-path summary

```
P1 ─► P2[HR] ─► P3[HR] ─► P4 ─► P5 ─► P6 ─┬─► P7[HR,HIGH] ─┐
                                          └─► P8 ───────────┤
                                                            ├─► P9 ──┐
                                                            └─► P10[HIGH] ─┤
                                                                           └─► P11[HIGH] ─► P12[HR] ─► P13[HR]
```
Total estimated effort ≈ **30.5 dev-days** (sequential). High-risk, buffer-this phases: **P7 (FCM), P10 (Flutter cross-platform), P11 (integration)**. Human-gated phases: **P2, P3, P7, P12, P13**.
