# TheraConnect

A three-tier clinic management system connecting patients, clinicians, and administrators. Patients use a Flutter mobile app (JSON API), while clinicians and admins use a server-rendered Blade dashboard.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 (PHP 8.2+) |
| Web Dashboard | Blade + Bootstrap 5 + Alpine.js |
| Mobile App | Flutter 3.x / Dart |
| Database | MySQL 8 / MariaDB 10.2+ (InnoDB, utf8mb4) |
| API Auth | Laravel Sanctum (bearer tokens) |
| Push Notifications | Firebase Cloud Messaging (Phase 7) |
| Background Jobs | Laravel Scheduler + Database Queue |

## Build Status

| Phase | Description | Status |
|---|---|---|
| 1 | Project scaffolding, Sanctum, Blade+Bootstrap+Alpine | Done |
| 2 | 10-table MySQL schema, 10 Eloquent models, seeders | Done |
| 3 | Auth (API token + web session), roles, policies | Done |
| 4 | Appointments module (patient API) | Done |
| 5 | Patient & clinician management (web dashboard) | Done |
| 6 | Assignment module | Done |
| 7 | Notifications (FCM + scheduler) | Done |
| 8 | Chatbot intent engine | Done |
| 9 | Web dashboard (all views + UI polish) | Done |
| 10 | Flutter mobile app (all screens) | Done |
| 11 | Integration & API wiring | Done |
| 12 | System testing & usability | Pending |
| 13 | Deployment & handoff | Pending |

## Prerequisites

- **PHP 8.2+** with extensions: `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `zip`
- **Composer** ([getcomposer.org](https://getcomposer.org))
- **MySQL 8+** or **MariaDB 10.2+** running on port 3306
- (Optional) **Flutter SDK** for the mobile app in `theraconnect_flutter/`

## Quick Start

### One-click (Windows)

Double-click `setup.bat` or run in PowerShell:

```powershell
.\setup.ps1
```

This auto-installs dependencies, creates the database, runs migrations, and seeds data in ~90 seconds.

### Manual setup

```powershell
# 1. Install PHP dependencies
composer install

# 2. Create your environment file
copy .env.example .env
php artisan key:generate

# 3. Create the database (choose one)
# Option A — via command line:
mysql -u root -e "CREATE DATABASE theraconnect"

# 4. Run migrations and seed with demo data
php artisan migrate:fresh --seed

# 5. Start the dev server
php artisan serve --port=8080
```

Open **http://localhost:8080/login** and sign in with one of the demo accounts below.

### Demo Data (loaded by setup)

The seeder creates realistic demo data for walkthroughs:

| Entity | Count | Details |
|---|---|---|
| Users | 6 | 1 admin, 2 clinicians, 3 patients |
| Appointments | 9 | 3 pending, 2 approved, 2 completed, 1 rejected, 1 cancelled |
| Assignments | 4 | 2 with submissions (1 submitted, 1 reviewed) |
| Notifications | 5 | Various types with read/unread states |

### Demo Accounts

| Role | Email | Password | Dashboard |
|---|---|---|---|
| Admin | `admin@theraconnect.test` | `password` | Full access |
| Clinician (CBT) | `clinician@theraconnect.test` | `password` | All except Clinicians page |
| Clinician (Family) | `dr.rivera@theraconnect.test` | `password` | All except Clinicians page |
| Patient | `patient@theraconnect.test` | `password` | Mobile app only |
| Patient | `michael@theraconnect.test` | `password` | Mobile app only |
| Patient | `emily@theraconnect.test` | `password` | Mobile app only |

### Postman Collection

Import `postman/TheraConnect_API_v1.postman_collection.json` for all 20 API endpoints with an 8-step end-to-end flow.

## Deployment (Railway)

A pilot/demo instance runs on Railway:

- **Live API + dashboard:** https://theraconnect-demo-production.up.railway.app
- **Health check:** https://theraconnect-demo-production.up.railway.app/api/v1/health
- **Repo Railway deploys from:** `jvanclavdiodos/theraconnect-demo`
- The Flutter app's API base URL lives in `theraconnect_flutter/lib/config/api_config.dart` (point it at the live URL, then `flutter build apk --release`).

### How it deploys
- Railway builds the root `Dockerfile` (configured by `railway.json`).
- On boot the container runs `storage:link → migrate --force → db:seed` (seeder is idempotent) → `php artisan serve` on `$PORT`.
- Environment variables are documented in **`.env.railway.example`** — set them in the Railway service's **Variables** tab. The `${{MySQL.*}}` references auto-fill from the Railway MySQL plugin. `bootstrap/app.php` trusts the Railway proxy so HTTPS + secure cookies work.

### Pilot trade-offs (NOT production-hardened)
- **Uploaded files are ephemeral** — submissions/worksheets reset on each redeploy (no volume/S3 yet).
- **`QUEUE_CONNECTION=sync`** with no scheduler/cron — in-app notifications still write, but hourly/daily auto-reminders don't fire.
- **Push (FCM) is disabled** — no Firebase credentials configured.
- **Demo accounts use password `password`** — rotate before any real use.
- Uses single-process `php artisan serve`, and migrations run on every boot — fine for one instance only.

## Testing

### PHPUnit

```powershell
php artisan test
```

**Test results: 43 passed, 167 assertions**

| Suite | Tests | Status |
|-------|-------|--------|
| Unit | 1 | Pass |
| Feature | 1 | Pass |
| Integration (AppointmentFlow) | 7 | Pass |
| Integration (AssignmentFlow) | 10 | Pass |
| Integration (AuthFlow) | 8 | Pass |
| Integration (ChatbotFlow) | 5 | Pass |
| Integration (EndToEndFlow) | 4 | Pass |
| Integration (NotificationFlow) | 6 | Pass |
| Integration (Policy) | 1 | Pass |

### Postman Collection

Import `postman/TheraConnect_API_v1.postman_collection.json` for all 20 endpoints with an 8-step end-to-end flow.

### Browser

| Page | URL |
|---|---|
| Landing (marketing) | [http://localhost:8080/](http://localhost:8080/) |
| Login | [http://localhost:8080/login](http://localhost:8080/login) |
| Dashboard (requires login) | [http://localhost:8080/dashboard](http://localhost:8080/dashboard) |
| Patients | [http://localhost:8080/patients](http://localhost:8080/patients) |
| Clinicians | [http://localhost:8080/clinicians](http://localhost:8080/clinicians) |
| Appointments | [http://localhost:8080/appointments](http://localhost:8080/appointments) |
| API Health Check | [http://localhost:8080/api/v1/health](http://localhost:8080/api/v1/health) |

### API (PowerShell)

Register a patient, login, and check your profile:

```powershell
$base = "http://localhost:8080/api/v1"

# Register a new patient
$r = Invoke-RestMethod -Uri "$base/register" -Method POST -Body (@{
    name = "Jane Doe"
    email = "jane@test.com"
    password = "password123"
    password_confirmation = "password123"
} | ConvertTo-Json) -ContentType "application/json"

# Login to get a token
$login = Invoke-RestMethod -Uri "$base/login" -Method POST -Body (@{
    email = "jane@test.com"
    password = "password123"
} | ConvertTo-Json) -ContentType "application/json"
$token = $login.data.token

# Use the token for authenticated requests
Invoke-RestMethod -Uri "$base/me" `
    -Headers @{Authorization = "Bearer $token"; Accept = "application/json"}

Invoke-RestMethod -Uri "$base/profile" `
    -Headers @{Authorization = "Bearer $token"; Accept = "application/json"}

# Logout
Invoke-RestMethod -Uri "$base/logout" -Method POST `
    -Headers @{Authorization = "Bearer $token"; Accept = "application/json"}
```

### Using Postman / Insomnia

Import the following endpoints into your API client:

| Method | URL | Auth | Notes |
|---|---|---|---|
| `GET` | `/api/v1/health` | None | Health check |
| `POST` | `/api/v1/register` | None | `{name, email, password, password_confirmation, contact_no?}` |
| `POST` | `/api/v1/login` | None | `{email, password}` |
| `POST` | `/api/v1/logout` | Bearer | Revokes all user tokens |
| `GET` | `/api/v1/me` | Bearer | Current user + patient profile |
| `GET` | `/api/v1/profile` | Bearer | Patient details |
| `PUT` | `/api/v1/profile` | Bearer | Update `{date_of_birth?, contact_no?, address?, emergency_contact?}` |
| `GET` | `/api/v1/schedules?date=` | Bearer | Available time slots per clinician |
| `GET` | `/api/v1/appointments` | Bearer | Patient's own appointments (paginated) |
| `POST` | `/api/v1/appointments` | Bearer | `{requested_at, mode, reason?, clinician_id?}` |
| `GET` | `/api/v1/appointments/{id}` | Bearer | Single appointment with ownership check |
| `DELETE` | `/api/v1/appointments/{id}` | Bearer | Cancel appointment (soft delete) |
| `GET` | `/api/v1/assignments` | Bearer | Patient's assignments with submission status |
| `GET` | `/api/v1/assignments/{id}` | Bearer | Single assignment (incl. `attachment_url`/`attachment_name`), ownership check |
| `GET` | `/api/v1/assignments/{id}/worksheet` | Bearer | Download clinician's worksheet attachment (private disk, owner only) |
| `POST` | `/api/v1/assignments/{id}/submit` | Bearer | Multipart `{content?, file?}`; blocked once reviewed |
| `GET` | `/api/v1/submissions/{id}/file` | Bearer | Download own submission file (private disk, owner only) |
| `GET` | `/api/v1/notifications` | Bearer | Patient's notifications (paginated) |
| `POST` | `/api/v1/notifications/{id}/read` | Bearer | Mark notification as read |
| `POST` | `/api/v1/device-token` | Bearer | Register FCM token `{token, platform}` |
| `DELETE` | `/api/v1/device-token` | Bearer | Remove FCM token `{token}` |

Add header: `Authorization: Bearer <token>` for authenticated routes.
Add header: `Accept: application/json` for proper error responses.

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/          # JSON controllers (mobile patient API)
│   │   └── Web/             # Blade controllers (clinician/admin dashboard)
│   ├── Middleware/RoleMiddleware.php
│   ├── Requests/Api/        # FormRequest validation
│   └── Resources/           # JSON resource transformers
├── Models/                  # Eloquent models (10 tables)
├── Policies/                # Ownership policies
└── Services/                # Business logic (Phase 4+)
config/
├── hashing.php              # bcrypt rounds=12
├── sanctum.php              # Sanctum configuration
database/
├── migrations/              # 13 migration files
└── seeders/                 # DatabaseSeeder, ChatbotSeeder
routes/
├── api.php                  # /api/v1 (Sanctum token auth)
└── web.php                  # / (session auth, Blade views)
resources/views/
├── layouts/app.blade.php    # Bootstrap 5 + Alpine.js shell
├── partials/                # navbar, sidebar, flash messages
├── landing.blade.php        # Marketing landing page
├── auth/login.blade.php     # Web login form
└── clinician/               # Dashboard views (Phase 9)
theraconnect_flutter/        # Flutter mobile app (Phase 10)
```

## QA Testing Checklist

### Web Dashboard (Browser — sign in as admin or clinician)

| # | Test | Steps | Expected |
|---|---|---|---|
| W1 | Landing page | Open `http://localhost:8080/` | Hero section, 3 feature cards, "Sign In" button |
| W2 | Login page | Click "Sign In" | Email + password form |
| W3 | Admin login | `admin@theraconnect.test` / `password` | Redirected to dashboard with 3 stat cards |
| W4 | Clinician login | `clinician@theraconnect.test` / `password` | Dashboard renders, no "Clinicians" sidebar link |
| W5 | Patient blocked | Try `patient@...` login | Error: "Patients must use the mobile app" |
| W6 | Dashboard counts | View dashboard | Total patients, pending appointments, today's appointments |
| W7 | Patient list | Click "Patients" in sidebar | Table of all patients with actions |
| W8 | Create patient | Click "Add Patient", fill form, submit | Redirect to list with success flash |
| W9 | Edit patient | Click pencil icon, change name, save | Name updated in list |
| W10 | View patient | Click eye icon | Details + recent appointments table |
| W11 | Delete patient | Click trash icon, confirm | Patient removed from list |
| W12 | Clinicians (admin) | Click "Clinicians" | Table of clinicians |
| W13 | Create clinician | "Add Clinician", fill form | Redirect to list with success flash |
| W14 | Appointments list | Click "Appointments" | Table with status filter tabs |
| W15 | Status filter | Click "Pending" filter | Only pending appointments shown |
| W16 | Approve appointment | Click green checkmark on pending | Status changes to approved, notification dispatched |
| W17 | Reject appointment | Click red X on pending, confirm | Status changes to rejected |
| W18 | Reschedule | Click calendar icon on approved, pick date | Modal opens, date set, appointment rescheduled |
| W19 | Assignments list | Click "Assignments" | Table with submission count badges |
| W20 | Create assignment | "New Assignment", pick patient, fill form | Returns to list with success |
| W21 | View submissions | Click "View" on assignment | Submission table with content, file, status |
| W22 | Mark reviewed | Click "Mark Reviewed" on submitted | Status badge changes to "Reviewed" |
| W23 | Notification logs | Click "Notification Logs" | Paginated table of all sent notifications |
| W24 | Logout | Click "Sign Out" in navbar | Redirected to landing page |

### API (PowerShell — requires patient token)

```powershell
$base = "http://localhost:8080/api/v1"
```

| # | Test | Command | Expected |
|---|---|---|---|
| A1 | Health check | `Invoke-RestMethod "$base/health"` | `{"status":"ok"}` |
| A2 | Register | POST `/register` `{name,email,password,password_confirmation}` | 201 + `{data:{user,token}}` |
| A3 | Login | POST `/login` `{email,password}` | 200 + `{data:{user,token}}` |
| A4 | Get profile | GET `/me` with Bearer token | User + patient_profile (no notes field) |
| A5 | Update profile | PUT `/profile` `{contact_no?, address?}` | Updated patient data |
| A6 | Logout | POST `/logout` with Bearer token | 204 |
| A7 | Token revoked | GET `/me` with old token | 401 Unauthorized |
| A8 | View schedules | GET `/schedules?date=YYYY-MM-DD` | 9+ slot-clinician pairs |
| A9 | Book appointment | POST `/appointments` `{requested_at, mode, clinician_id?}` | 201 pending |
| A10 | List appointments | GET `/appointments` | Paginated own appointments |
| A11 | Cancel appointment | DELETE `/appointments/{id}` | status=cancelled |
| A12 | Cannot view other's | GET `/appointments/{other_id}` | 403 Forbidden |
| A13 | List assignments | GET `/assignments` | With submission_status |
| A14 | Submit assignment | POST `/assignments/{id}/submit` `{content}` | 201 submitted |
| A15 | Empty submit blocked | POST `/assignments/{id}/submit` empty | 422 content-or-file required |
| A16 | List notifications | GET `/notifications` | Paginated with meta |
| A17 | Mark read | POST `/notifications/{id}/read` | read_at updated |
| A18 | Register device | POST `/device-token` `{token, platform}` | 201 stored |
| A19 | Remove device | DELETE `/device-token` `{token}` | 204 |
| A20 | Admin blocked | Admin token → GET `/me` | 403 Forbidden (role:patient) |

### Rate Limiting

| # | Test | Expected |
|---|---|---|
| R1 | 6 rapid logins | 5th returns 429 Too Many Requests |
| R2 | 60 rapid API calls | 61st returns 429 Too Many Requests |

### Security

| # | Check | Verified |
|---|---|---|
| S1 | bcrypt rounds = 12 | `.env` `BCRYPT_ROUNDS=12` |
| S2 | Sanctum tokens expire after 30 days | `config/sanctum.php` |
| S3 | `clinic_notes` not visible to patients | Conditional in resources |
| S4 | `notes` not visible to patients | `PatientResource` gate |
| S5 | CSRF on all web forms | `@csrf` in every form |
| S6 | Delete confirmations | Alpine.js `confirm()` dialogs |
| S7 | Role middleware is authoritative | Server-side, not Blade-only |

#   t h e r a c o n n e c t  
 