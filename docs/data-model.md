# Database Model and Persistence Reference

Laravel migrations in `database/migrations` are the schema history. Eloquent models in `app/Models` are the application-facing entities. The project does not contain repository or DAO classes; services/controllers query models directly.

## Entity Relationships

```text
User (admin|clinician|patient)
  1--0..1 Patient -------- * Appointment * -------- 1 Clinician --1 User
  |       |  \             * Assignment  *          |
  |       |   \            * Assessment  *          +--* availability rows/overrides
  |       |    \           * MoodLog
  |       |     \          * TherapyGoal --* GoalRating
  |       |      \         * PatientNote (clinician-owned)
  |       |       \        * Submission --1 Assignment
  |       |        \       1 Conversation --* Message
  |       +--* Clinician through clinician_patient
  |       +-- requested/legacy primary clinician references
  +--* DeviceToken
  +--* Notification
  +--* ActivityLog

ChatbotIntent --* ChatbotResponse
```

## Core Identity and Patient Tables

| Table / Model | Purpose and important fields | Relationships |
|---|---|---|
| `users` / `User` | Identity, email/password, enum role, avatar, soft delete, terms acceptance/version | hasOne patient/clinician; hasMany tokens/deviceTokens/notifications/activity logs |
| `clinicians` / `Clinician` | Staff clinical profile, license/specialization/contact fields | belongsTo user; belongsToMany patients through `clinician_patient`; hasMany appointments, assignments, availability records |
| `patients` / `Patient` | Patient profile/demographics, contact and sensitive profile fields; requested clinician and legacy primary clinician fields | belongsTo user; belongsToMany assigned clinicians through `clinician_patient`; hasMany care records |
| `clinician_patient` | Authoritative many-to-many caseload membership | Unique clinician/patient pair. Backfilled from the legacy assignment and historical approved appointments. |
| `personal_access_tokens` | Sanctum mobile access tokens | Laravel-managed polymorphic token relation |
| `sessions` | optional database-backed browser sessions | Laravel framework table |

`User` lowercases email through a mutator. It uses password hashing casts and soft deletes. `Patient` contains constants for allowed profile values and clinician request states; use them rather than literals.

## Scheduling and Work Tables

| Table / Model | Purpose | Integrity/behavior notes |
|---|---|---|
| `appointments` / `Appointment` | requested/scheduled time, mode, status, meeting link, patient/clinician | State changes are owned by `AppointmentService`; casts expose date fields and helpers determine active meeting link. |
| `clinician_weekly_availabilities` / `ClinicianWeeklyAvailability` | weekly weekday working window / on-off | availability defaults to 08:00–16:00 if no row exists. |
| `clinician_date_overrides` / `ClinicianDateOverride` | date-level whole-day or hourly blocks | applied by `AvailabilityService`; do not duplicate its filtering in a controller. |
| `assignments` / `Assignment` | clinician task, due date, optional private attachment | belongs to patient/clinician; has submissions. |
| `assignment_submissions` / `Submission` | patient text/file submission and review data | exposes extension/preview helpers; file access must be policy checked. |
| `assessments` / `Assessment` | clinician-assigned instrument and patient responses/status | model derives title and severity; support is defined in `App\Support\Assessments`. |
| `mood_logs` / `MoodLog` | patient mood check-ins | patient-owned and cast dates/metadata. |
| `therapy_goals` / `TherapyGoal` | clinician-created goals/status | has ratings and latest rating. |
| `goal_ratings` / `GoalRating` | Goal Attainment Scale-type rating/note/optional appointment link | belongs to goal; model derives human label. |
| `patient_notes` / `PatientNote` | clinician-authored notes visible under policy | belongs to patient and clinician; treat contents as highly sensitive. |

## Communication and Operations Tables

| Table / Model | Purpose | Notes |
|---|---|---|
| `conversations` / `Conversation` | patient-clinician conversation association | has messages/latestMessage; participant and unread helpers. |
| `messages` / `Message` | message text, sender, read state | direct-message notifications intentionally omit email. |
| `notifications` / `Notification` | in-app notification content/data/read and delivery state | `sent_at` = push semantics. Email uses `email_sent_at`, `email_failed_at`, `email_error`. |
| `device_tokens` / `DeviceToken` | FCM device token/platform | migration enforces uniqueness per user/token behavior. |
| `activity_logs` / `ActivityLog` | administrative/clinical action audit trail | has actor user and target type/id plus metadata. |
| `chatbot_intents` / `ChatbotIntent` | configurable assistant intent/training phrases/category | soft deletable; has responses. |
| `chatbot_responses` / `ChatbotResponse` | response text/priority/fallback marker | belongs to intent; fallback row supports rule-based response. |
| `jobs`, `job_batches`, `failed_jobs`, `cache` | Laravel queue/cache framework tables | required when database queue/cache configuration is used. |

## Migration Timeline

The migration names are a concise schema/change ledger:

- `0001_*`: users, password reset tokens, sessions, cache, jobs.
- `2026_06_03_*`: Sanctum tokens, clinicians, patients, appointments, assignments/submissions, device tokens, notifications, chatbot tables.
- `2026_06_09` to `2026_06_18`: assignment attachments/original name, device-token uniqueness.
- `2026_06_19` to `2026_06_28`: clinician assignment/request fields, availability, conversation/messages, notes, profile/avatar, no-show, assessments, mood/goals/activity log, appointment cancelled notification type.
- `2026_07_06`: email delivery tracking fields on notifications.
- `2026_07_12`: terms acceptance timestamp/version on users.
- `2026_07_13`: many-to-many clinician/patient caseload pivot and historical relationship backfill.

When adding a field, create a new forward migration. Do not edit an already-deployed migration or assume Railway’s database can be rebuilt. New migration-sensitive behavior needs an explicit deployment/migration note.

## Data Sensitivity and Storage

- Patient profile concerns, notes, messages, assessment responses, mood logs, assignments and uploads are clinical/PHI-adjacent data.
- The local disk root is `storage/app/private`; production should use a private S3-compatible bucket. Public `storage` is only for the public disk.
- Files are streamed through controllers after policy checks. Do not return raw `Storage::url()` for private records.
- Log metadata should avoid message bodies and assessment responses unless an established audit requirement requires it.
