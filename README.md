# TheraConnect

A three-tier clinic management system connecting patients, clinicians, and administrators. Patients use a Flutter mobile app (JSON API), while clinicians and admins use a server-rendered Blade dashboard. One Laravel backend serves both clients through the same service layer.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 (PHP 8.2+) |
| Web Dashboard | Blade + Bootstrap 5 + Alpine.js |
| Mobile App | Flutter 3.x / Dart |
| Database | MySQL 8 / MariaDB 10.2+ (InnoDB, utf8mb4) |
| API Auth | Laravel Sanctum (bearer tokens) |
| Push Notifications | Firebase Cloud Messaging (optional) |
| Background Jobs | Laravel Scheduler + Database Queue |

## Prerequisites

- **PHP 8.2+** with extensions: `curl`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `zip`
- **Composer** ([getcomposer.org](https://getcomposer.org))
- **MySQL 8+** or **MariaDB 10.2+** running on port 3306
- (Optional) **Flutter SDK** for the mobile app in `theraconnect_flutter/`

## Quick Start

### One-click (Windows)

Double-click `setup.bat` or run in PowerShell:

```powershell
.\setup.ps1
# To run migrate:fresh against a non-local DB host (refused by default):
.\setup.ps1 -SkipLocalGuard
```

Auto-installs dependencies, creates the database, runs migrations, and seeds demo data in ~90 seconds.

### Manual setup (cross-platform)

```bash
composer install
cp .env.example .env && php artisan key:generate    # Windows cmd: copy .env.example .env

# Create the database (choose one):
mysql -u root -e "CREATE DATABASE theraconnect"
#   ...or Docker (DB only):
docker compose -f docker-compose.db.yml up -d        # MySQL on :3307

php artisan migrate:fresh --seed
php artisan serve --port=8080
```

> PowerShell 5+ accepts `cp`; on Windows `cmd.exe` use `copy` instead.

Open **http://localhost:8080/login** and sign in with one of the demo accounts below.

### Docker (full stack)

```bash
docker compose up --build
# app on :8080, MySQL on :3307, plus queue-worker + scheduler services
```

### Demo Data

The seeder creates realistic demo data for walkthroughs:

| Entity | Count | Details |
|---|---|---|
| Users | 6 | 1 admin, 2 clinicians, 3 patients |
| Appointments | 9 | 3 pending, 2 approved, 2 completed, 1 rejected, 1 cancelled |
| Assignments | 4 | 2 with submissions (1 submitted, 1 reviewed) |
| Notifications | 5 | Various types with read/unread states |

### Demo Accounts

All passwords are `password`. Patients use the mobile app; admin/clinician use the web dashboard.

| Role | Email | Access |
|---|---|---|
| Admin | `admin@theraconnect.test` | Full access |
| Clinician (CBT) | `clinician@theraconnect.test` | All except Clinicians page |
| Clinician (Family) | `dr.rivera@theraconnect.test` | All except Clinicians page |
| Patient | `patient@theraconnect.test` | Mobile app only |
| Patient | `michael@theraconnect.test` | Mobile app only |
| Patient | `emily@theraconnect.test` | Mobile app only |

> Demo accounts use password `password` — rotate before any real use.

## API Reference

Base URL: `/api/v1`. Add `Authorization: Bearer <token>` for authenticated routes and `Accept: application/json` for proper error responses. A Postman collection lives at `postman/TheraConnect_API_v1.postman_collection.json` (includes an 8-step end-to-end flow).

| Method | URL | Auth | Description |
|---|---|---|---|
| `GET` | `/health` | None | Health check (verifies DB reachable) |
| `POST` | `/register` | None | `{name, email, password, password_confirmation, contact_no?}` |
| `POST` | `/login` | None | `{email, password}` — patients only |
| `POST` | `/logout` | Bearer | Revokes all user tokens |
| `GET` | `/me` | Bearer | Current user + patient profile |
| `GET` | `/profile` | Bearer | Patient details |
| `PUT` | `/profile` | Bearer | Update `{date_of_birth?, contact_no?, address?, emergency_contact?}` |
| `POST` | `/profile/avatar` | Bearer | Multipart avatar upload (JPG/PNG/WebP, ≤4 MB, ≤1024×1024) |
| `GET` | `/profile/avatar` | Bearer | Serve own avatar inline |
| `GET` | `/schedules?date=` | Bearer | Available time slots per clinician |
| `GET` | `/appointments` | Bearer | Patient's own appointments (paginated) |
| `POST` | `/appointments` | Bearer | `{requested_at, mode, reason?, clinician_id?}` — whole-hour only |
| `GET` | `/appointments/{id}` | Bearer | Single appointment (ownership check) |
| `DELETE` | `/appointments/{id}` | Bearer | Cancel appointment (terminal states blocked) |
| `GET` | `/assignments` | Bearer | Patient's assignments with submission status |
| `GET` | `/assignments/{id}` | Bearer | Single assignment (ownership check) |
| `GET` | `/assignments/{id}/worksheet` | Bearer | Download worksheet (private disk, owner only) |
| `POST` | `/assignments/{id}/submit` | Bearer | Multipart `{content?, file?}`; blocked once reviewed |
| `GET` | `/submissions/{id}/file` | Bearer | Download own submission file |
| `GET` | `/clinicians` | None | Public directory (id, name, specialization) |
| `GET` | `/notifications` | Bearer | Patient's notifications (paginated) |
| `POST` | `/notifications/{id}/read` | Bearer | Mark notification as read |
| `POST` | `/device-token` | Bearer | Register FCM token `{token, platform}` |
| `DELETE` | `/device-token` | Bearer | Remove FCM token `{token}` |
| `GET` | `/mood-logs` | Bearer | Mood check-in history |
| `POST` | `/mood-logs` | Bearer | `{score (1-10), note?}` |
| `GET` | `/goals` | Bearer | Therapy goals (read-only, clinician-authored) |
| `GET` | `/notes` | Bearer | Shared clinician notes (read-only) |
| `GET` | `/assessments` | Bearer | Assigned questionnaires (PHQ-9 / GAD-7) |
| `GET` | `/assessments/{assessment}` | Bearer | Single assessment |
| `POST` | `/assessments/{assessment}/submit` | Bearer | Submit responses |
| `GET` | `/conversations` | Bearer | Patient's conversation threads |
| `POST` | `/conversations` | Bearer | Open a conversation |
| `GET` | `/conversations/{conversation}/messages` | Bearer | Messages in a thread |
| `POST` | `/conversations/{conversation}/messages` | Bearer | Send a message |
| `POST` | `/chatbot/message` | Bearer | Ask the Joy chatbot `{message}` |

### Web Dashboard Routes

| Page | URL |
|---|---|
| Landing (marketing) | `/` |
| Login | `/login` |
| Dashboard (clinician/admin) | `/dashboard` |
| Patients | `/patients` |
| Clinicians (admin) | `/clinicians` |
| Appointments | `/appointments` |
| Assignments | `/assignments` |
| Messages (clinician) | `/messages` |
| Notification logs (admin) | `/notifications/logs` |
| Activity audit (admin) | `/activity-logs` |
| Chatbot content (admin) | `/chatbot-content` |
| Account (own profile/password) | `/account` |
| Patient portal | `/portal` (patients land here after login) |

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/          # JSON controllers (mobile patient API)
│   │   ├── Web/             # Blade controllers (clinician/admin dashboard)
│   │   └── Portal/          # Blade controllers (patient browser portal)
│   ├── Middleware/           # RoleMiddleware, SecurityHeaders
│   ├── Requests/Api/        # FormRequest validation
│   └── Resources/           # JSON resource transformers
├── Models/                  # Eloquent models
├── Policies/                # Ownership policies (Appointment, Assignment, Submission, …)
├── Services/                # Business logic (Appointment, Assignment, Notification, Chatbot, Fcm, Jitsi, Message, …)
└── Jobs/                    # SendPushNotification, GenerateAppointmentReminders, …
config/                      # app, auth, sanctum, cors, filesystems, queue, …
database/
├── migrations/
└── seeders/                 # DatabaseSeeder, DemoSeeder (idempotent)
routes/
├── api.php                  # /api/v1 (Sanctum bearer auth)
└── web.php                  # / (session auth, Blade views)
resources/views/
├── layouts/                 # app.blade.php, portal.blade.php
├── partials/                # navbar, sidebar, flash messages, password-strength
├── errors/                  # 403, 404, 419, 500 branded pages
├── clinician/               # Admin/clinician dashboard views
├── portal/                  # Patient portal views
└── landing.blade.php        # Marketing landing page
public/
├── css/theraconnect.css     # Theme tokens + dark-mode overrides
└── js/file-upload.js        # Client-side upload size/type validation
theraconnect_flutter/         # Flutter mobile app
├── lib/
│   ├── models/              # Data classes
│   ├── providers/           # Riverpod state
│   ├── screens/             # UI
│   ├── services/            # ApiClient, Auth, Fcm, Cache, …
│   └── theme/app_theme.dart # light() + dark()
└── pubspec.yaml
docker/                      # entrypoint.sh, wait-for-db.sh, php.ini
```

## Testing

```bash
php artisan test        # or: vendor/bin/phpunit
vendor/bin/pint --test  # Laravel Pint style check
```

Tests run on an **in-memory SQLite** DB — no MySQL needed. Run `php artisan test` for the current count (the suite grows as fixes land).

## Deployment

A pilot/demo instance runs on Railway:

- **Live app:** https://theraconnect-demo-production.up.railway.app
- **Health check:** https://theraconnect-demo-production.up.railway.app/api/v1/health
- **Repo Railway deploys from:** `jvanclavdiodos/theraconnect-demo`
- The Flutter app's API base URL lives in `theraconnect_flutter/lib/config/api_config.dart` (point it at the live URL, then `flutter build apk --release`).

Deployment configuration lives in:
- **`.env.railway.example`** — production-flavored env vars (`APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `FILESYSTEM_DISK=s3`, `QUEUE_CONNECTION=database`, `LOG_STACK=stderr`). Set these in the Railway service's **Variables** tab; the `${{MySQL.*}}` references auto-fill from the Railway MySQL plugin.
- **`Dockerfile`** — non-root `www-data` container, `HEALTHCHECK` probing `/api/v1/health`, `intl` extension installed, pinned to `php:8.2.25-cli-alpine`.
- **`docker/entrypoint.sh`** — boot sequence: `storage:link` → wait for DB (PDO probe) → `migrate --force` → `db:seed --force` (idempotent, aborts on failure) → `php artisan serve` on `$PORT`.
- **`railway.worker.json`** / **`railway.scheduler.json`** — auxiliary queue-worker and scheduler Railway services. Both use the shared `docker/wait-for-db.sh` probe. Required for `SendPushNotification` delivery and appointment/assignment reminders.
- **`docker-compose.yml`** — full local stack: `app`, `mysql`, `queue-worker`, `scheduler` (via `schedule:work`).

### Persistent uploads (S3)

Set `FILESYSTEM_DISK=s3` plus the `AWS_*` env vars. Patient submissions and assignment worksheets are served **only** through authenticated download routes — the bucket must be **private** (block-public-access on). With no S3 creds, the app falls back to the private `local` disk (uploads reset on redeploy).

See `CLAUDE.md` for architectural conventions and `TheraConnect_Agent_Spec.md` for the original full specification.
