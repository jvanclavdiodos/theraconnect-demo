# TheraConnect — System Notes (local experimental copy)

> Working notes for the **local experimental copy** of TheraConnect. This copy is where we
> add new features. Authored after a full read-through of the backend on 2026-06-15.
> Pair this with `CLAUDE.md` (conventions), `README.md` (setup/endpoints), and
> `TheraConnect_Agent_Spec.md` (original spec).

## 1. What this copy is

- **Backend-only.** This local copy contains the **Laravel 11 backend** (PHP 8.2). The
  `theraconnect_flutter/` Flutter app referenced in `CLAUDE.md`/`README.md` is **not present
  here** — only the API + Blade dashboard exist in this checkout.
- Three roles, two client surfaces, one service layer:
  - **Patients** → JSON API `/api/v1`, Sanctum **bearer tokens** (`auth:sanctum` + `role:patient`).
  - **Clinicians/Admins** → Blade dashboard, **session** auth (`role:admin,clinician`).
- **DB:** MySQL 8 via Docker on **port 3307** (`docker-compose.db.yml`, db `theraconnect_local`,
  root/`password`). Tests use a separate test DB and do **not** need MySQL running.

## 2. Local run

```bash
# Start MySQL (port 3307)
docker compose -f docker-compose.db.yml up -d
php artisan migrate:fresh --seed
php artisan serve --port=8081     # APP_URL in .env is :8081
```

- `.env` here points at `DB_PORT=3307`, `DB_DATABASE=theraconnect_local`, `APP_URL=http://localhost:8081`.
- FCM disabled (`FCM_PROJECT_ID`/`FCM_CREDENTIALS` blank) → push no-ops; in-app notifications still write.
- `vendor/` is installed; PHP 8.2.12 confirmed on PATH.

## 3. Data model (10 tables)

| Table | Key columns / notes |
|---|---|
| `users` | `role` ENUM(admin/clinician/patient), soft deletes, Sanctum tokens |
| `patients` | 1:1 `users`; `notes` (clinician-private), DOB, contact, emergency_contact |
| `clinicians` | 1:1 `users`; license_no, specialization, contact_no |
| `appointments` | patient+clinician(nullable), `requested_at`/`scheduled_at`, `mode`(in_person/online), `status` ENUM(pending/approved/rejected/rescheduled/completed/cancelled), `clinic_notes`(private) |
| `assignments` | clinician→patient; title/description/due_date; `attachment_path`/`attachment_name` (private disk) |
| `assignment_submissions` | unique(`assignment_id`,`patient_id`); `content`/`file_path`; `status`(submitted/reviewed) |
| `notifications` | `type` ENUM (7 kinds), `data` JSON, `sent_at`/`read_at`, channel=fcm |
| `device_tokens` | FCM tokens per user/platform |
| `chatbot_intents` / `chatbot_responses` | intent engine data (soft deletes) |

Admins have **no** profile row. Models: `app/Models/` (note `Submission` model = `assignment_submissions` table).

## 4. Where logic lives

- **Services** (`app/Services/`) hold all multi-table / shared / notification-emitting logic:
  - `AppointmentService` — slot generation (08:00–16:00 hourly, 9 slots), `isSlotAvailable`
    (conflict = active appt on same clinician+datetime, excluding cancelled/rejected/completed),
    create/cancel/approve/reject/reschedule.
  - `AssignmentService` — create (worksheet→private `local` disk), submit (upsert, replaces old
    file), review.
  - `NotificationService` — writes DB row; typed helpers per notification kind.
  - `ChatbotService` — **Jaccard token similarity** over intent training phrases, threshold
    **0.34**, DB-backed fallback intent. No external LLM.
  - `FcmService` — optional push; no-ops without credentials.
- **Jobs** (`app/Jobs/`): `SendPushNotification`, `GenerateAppointmentReminders`,
  `GenerateAssignmentReminders`. Scheduled in `routes/console.php`.
- **Authz:** `RoleMiddleware` (alias `role`) is the boundary; Policies
  (`Appointment`/`Assignment`/`Submission`) enforce patient ownership via `Gate::authorize()`.
- **Uploads are private:** stored on `local` disk, served only through authenticated download
  routes (web role-gated; API ownership-checked). Never the public disk.
- **API shape:** success `{data:...}`; lists add `meta{current_page,last_page,total}`;
  errors `{message, errors:{field:[...]}}`.

## 5. Endpoints

- API surface: see `routes/api.php` (`/api/v1`, ~20 endpoints; throttles auth=5, api=60,
  chatbot=30 per min). Patient-only behind `auth:sanctum`+`role:patient`.
- Web surface: see `routes/web.php` (dashboard, patients CRUD, appointment status actions,
  clinicians CRUD [admin-only], assignments, chatbot content CRUD, notification logs).

## 6. ⚠️ Known issue — stale test dates (found 2026-06-15)

**`php artisan test` currently reports 6 failures, all in `tests/Integration/AppointmentFlowTest.php`.**
The README claims "43 passed"; that was true when authored.

- **Root cause:** `StoreAppointmentRequest` validates `requested_at => after:now`. Six tests
  hardcode `requested_at` of **`2026-06-10 ...`**, which is now in the **past** (today = 2026-06-15),
  so booking fails with 422 and the assertions cascade.
- **Not a code bug** — it's frozen fixture dates aging out. Evidence: the tests that use a
  future date (`test_double_booking...` @ 2026-06-20) and the no-booking schedule test still pass.
- **Failing tests:** `patient_can_book_appointment`, `patient_can_view_their_appointments`,
  `patient_cannot_view_other_patients_appointments`, `patient_can_cancel_appointment`,
  `double_cancel_returns_409`, `schedule_shows_booked_slot_as_unavailable`.
- **Fix options (not yet applied):** make dates relative (e.g. `now()->addDays(5)`), or freeze
  app time in the test with `Carbon::setTestNow()`. Relative dates are the durable fix.

## 7. Feature: Jitsi video calls for online appointments (added 2026-06-15)

Online appointments now get a real video-call link (Jitsi **public server**, `meet.jit.si` — no API
key/account; a meeting is just an unguessable room URL).

- **Generation:** `JitsiService::generateMeetingLink($appointmentId)` →
  `{JITSI_BASE_URL}/{JITSI_ROOM_PREFIX}-{appointmentId}-{uuid}`. Config in `config/services.php`
  (`services.jitsi`), env `JITSI_BASE_URL`/`JITSI_ROOM_PREFIX`.
- **When:** `AppointmentService::approve()` / `reschedule()` set `meeting_link` **only** when
  `mode === 'online'`, generated **once** and kept stable across reschedules. `in_person` stays `null`.
- **Clinician web:** Join button (camera icon) in the appointments table Actions column for online
  appts with a link.
- **Patient API:** `meeting_link` already serialized by `AppointmentResource` (no change). Approved/
  rescheduled notifications carry `data.meeting_link`.
- **Flutter:** `appointment_detail_screen.dart` shows a **Join Video Call** button (url_launcher,
  `externalApplication`) for approved/rescheduled online appts. Needs `url_launcher` + the Android 11+
  `<queries>` https VIEW intent.
- **Seed:** `DemoSeeder` creates one online **approved** appointment with a live link so the Join button
  shows right after `migrate:fresh --seed`.
- **Tests:** `tests/Integration/MeetingLinkTest.php` (3 tests) — online→link, in_person→null, reschedule
  keeps room. Full suite: **40 passed**, same 6 pre-existing stale-date failures (see §6).
- **Flutter↔backend wiring:** `api_config.dart` base URL now points at the LAN backend
  (`http://10.186.183.181:8080/api/v1`). Run backend as `php artisan serve --host=0.0.0.0 --port=8080`;
  phone on same Wi-Fi. The LAN IP is machine/network-specific — update it when the PC's Wi-Fi IP changes.
- **Future:** HIPAA needs self-hosted Jitsi or JaaS/JWT — just swap `JITSI_BASE_URL` (+ add JWT). The
  public server's rooms are open to anyone with the link.

## 8. Running the full stack in Docker (gotchas hit 2026-06-15)

Bringing up the full `docker-compose.yml` (app + mysql + queue + scheduler) surfaced several issues —
all now fixed in-repo. Keep these in mind:

- **`.dockerignore` is required.** Without it the build context was 1.33GB and Docker choked on the
  `public/storage` symlink (`invalid file request public/storage`) — it targets the absolute container
  path `/var/www/storage/app/public`, which is broken on the Windows host. `.dockerignore` excludes that
  symlink (recreated at boot by `storage:link --force`), `vendor`, `theraconnect_flutter`, etc.
- **Stale package-discovery cache crashes the container.** `bootstrap/cache/packages.php` generated on
  the host (with dev deps) lists `Laravel\Pail\PailServiceProvider`, but the image is `composer install
  --no-dev`, so the container crash-loops with "Class not found". Fix: delete
  `bootstrap/cache/packages.php` + `services.php` (regenerated automatically). `.dockerignore` keeps them
  out of the image, but the runtime bind-mount can still re-introduce a host-generated one.
- **`.env` vs container DB host — the big one.** The host `.env` uses `DB_HOST=127.0.0.1:3307` (host dev
  against the db-only container). Inside Docker the DB is the `mysql` service at `mysql:3306`. The compose
  `environment:` block sets that for **CLI** (migrate/seed/tinker work), but **`php artisan serve`
  forwards the `.env` *file* values to its HTTP workers**, so every HTTP request tried `127.0.0.1:3307`
  inside the container → `SQLSTATE[HY000] [2002] Connection refused`. CLI worked, HTTP didn't.
  Fix: **`.env.docker`** (DB_HOST=mysql, etc.) is mounted over `/var/www/.env` for app/queue/scheduler in
  `docker-compose.yml`, leaving the host `.env` intact for `php artisan test`/`serve` on the host.
- **Fixed `container_name`s collide** with the original `theraconnect-master` copy (both compose files use
  `theraconnect-app`/`-mysql`/...). `docker rm -f` the stale ones if `up` reports a name conflict.

Verified working: `GET /api/v1/health` → ok; patient login over HTTP; `GET /api/v1/appointments/8`
returns `meeting_link: https://meet.jit.si/TheraConnect-8-…` (seeded online-approved appointment).

## 9. Conventions to keep when adding features

- Thin controllers → FormRequest validate → Policy/Role authorize → **Service** → Resource/view.
- Never expose clinician/admin actions on the JSON API; never mix the two auth guards.
- Any write touching >1 table, emitting a notification, or shared web+API → put it in a Service.
- MySQL-flavored migrations (ENUM columns) — don't assume SQLite/Postgres semantics.
- Patient-private fields (`patients.notes`, `appointments.clinic_notes`) must never serialize to
  the patient API — gate them in Resources.
