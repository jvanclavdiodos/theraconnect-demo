# Feature Index and Safe Modification Map

This index lists observable application features, their primary ownership, and the dependencies a change is likely to affect. “Repository” is shown as **none** where the code calls Eloquent/services directly; this project does not use repository/DAO abstractions.

## Identity, Accounts, and Consent

| Feature | Entry and UI | Ownership | Data/API | Dependencies and modification hazards |
|---|---|---|---|---|
| Browser login/logout | `/login`, `resources/views/auth/login.blade.php` | `AuthenticatedSessionController` | `users`, `sessions` | Role redirect goes to `/portal` for patients and `/dashboard` for staff. Preserve lowercase email and dummy hash check. |
| Patient self-registration | `/register`, `auth/register.blade.php` | `RegisterController`, `StrongPassword`, `TermsOfService` | `users`, `patients`; browser route | Requires explicit agreement. After commit, automatic-login failure has a login fallback. Changing session behavior affects this path. |
| Mobile registration/login | Flutter auth screens | `AuthNotifier` -> `AuthApi` -> `Api\V1\AuthController` | `/api/v1/register`, `/login`, `/me`; Sanctum | Patient-only API. Add registration fields in web request validation, `RegisterRequest`, controller, Flutter API/provider/model, and tests together. |
| Terms/User Agreement | web Bootstrap modal; Flutter dialog | web registration JS/Alpine + Flutter `RegisterScreen` | `users.terms_accepted_at`, `terms_version` | Backend acceptance is authoritative. Current version is `TermsOfService::CURRENT_VERSION`; update content and version together. |
| Profile/avatar/password | staff account, portal profile, Flutter profile screens | `AccountController`, `PortalProfileController`, `ProfileController`, `PasswordController` | users/patients; profile API | Avatar has authorization and storage constraints; password rule is shared conceptually with Flutter validators. |

## Appointment and Availability

| Feature | Entry and UI | Ownership | Data/API | Dependencies and modification hazards |
|---|---|---|---|---|
| Patient booking | portal book screen, Flutter schedule/book/calendar | `PortalAppointmentController`, `Api\V1\AppointmentController`, `AppointmentService` | `appointments`, availability tables; schedules/appointments API | Must preserve availability and conflict checks. Patient owns only their appointments. |
| Staff appointment operations | `appointments/index.blade.php` | `WebAppointmentController`, `AppointmentService` | appointments; staff web routes | State transitions, policies, notifications, attendance and Jitsi links intersect here. |
| Availability calendar | clinician dashboard partial | `ClinicianAvailabilityController`, `AvailabilityService` | weekly availability/date override tables | Default hours are 08:00–16:00 inclusive unless weekly configuration/overrides alter them. Changing slot semantics affects web and API booking. |
| Online meetings | appointment views | `JitsiService`, `AppointmentService` | appointment mode/meeting link fields | Links are unguessable Jitsi room URLs, not authenticated Jitsi rooms. Do not expose meeting links before policy/status checks. |
| Attendance/no-shows | staff progress/dashboard; scheduler | `AttendanceService`, `MarkOverdueNoShows` | appointments | Scheduled job changes appointment state. State machine and dashboard metrics depend on it. |

## Care Workflows

| Feature | Entry and UI | Ownership | Data/API | Dependencies and modification hazards |
|---|---|---|---|---|
| Patient/caseload management | staff patient list/show/edit/create | `PatientController`, `PatientRequestController`, `PatientRequestService` | patients/users, clinician request fields | `assigned_clinician_id` is the caseload boundary for messaging and many policies. Self-registration does not let a patient choose a clinician. |
| Clinician request approval/denial | staff patient request actions | `PatientRequestService`, `PatientPolicy` | patients, notifications | Keep update + notification transactional and dispatch after commit. |
| Assignments | staff assignment list/create/review; portal/mobile assignment screens | `WebAssignmentController`, `PortalAssignmentController`, API controller, `AssignmentService` | assignments, submissions, private files | Submission/worksheet download and preview are policy-gated. File storage must remain private. |
| Assessments | clinician progress page; patient portal/mobile | `ProgressController`, portal/API assessment controllers, `AssessmentService`, `Assessments` | assessments | Instruments are constrained to supported values (PHQ-9/GAD-7). Patient can submit only their assignment. |
| Mood logs | portal/mobile mood pages | `PortalMoodLogController`, API `MoodLogController` | mood_logs | Patient-owned, chronological mental-health data. |
| Therapy goals | clinician progress page; portal/mobile read view | `GoalService`, `ProgressController`, `PortalGoalController`, API | therapy_goals, goal_ratings | Clinicians create/rate/status-change; patient is read-only. Rating may relate to appointment. |
| Patient notes | staff patient notes; portal/mobile notes views | `PatientNoteController`, `PortalNoteController`, API note controller | patient_notes | Notes are clinician-authored and visibility is policy-scoped. Do not expose raw note queries to patients. |

## Communication, Notification, and Assistant

| Feature | Entry and UI | Ownership | Data/API | Dependencies and modification hazards |
|---|---|---|---|---|
| Direct messaging | staff and portal messaging views; Flutter inbox/thread | `MessageService`, web/portal/API conversation controllers | conversations, messages | Conversation has patient + clinician participants. Direct-message emails are intentionally disabled for privacy. |
| In-app notification inbox | staff/portal/Flutter notification lists | notification controllers, `NotificationService` | notifications; notification API | `sent_at` means push delivery state; email has separate timestamps/errors. |
| Push notifications | mobile FCM lifecycle | `FcmService`, `SendPushNotification`, Flutter `FcmService` | device_tokens, Firebase | Missing FCM configuration should no-op without breaking business workflows. Token register/delete endpoints are patient-only. |
| Transactional email | queued job/mailable | `SendEmailNotification`, `NotificationEmail` | notifications/email tracking; mail config | Allow-list specific appointment/request/assessment/assignment types. Do not add message body email casually. |
| Reminders | scheduler/queue | `GenerateAppointmentReminders`, `GenerateAssignmentReminders` | appointments/assignments/notifications | Requires scheduler and queue worker. Keep idempotency or repeated schedule runs can duplicate notices. |
| Clinic chatbot | portal/mobile chatbot screens; admin content CRUD | `ChatbotService`, `ChatbotIntent/Response`, API/portal controllers | chatbot tables; Gemini optional | Gemini sees typed message plus generic KB prompt, no derived PHI. Fallback is Jaccard matching. Crisis handling wording is high risk. |

## Administration, Audit, and Operations

| Feature | Entry and UI | Ownership | Data/API | Dependencies and modification hazards |
|---|---|---|---|---|
| Staff dashboard | `/dashboard`, `clinician/dashboard.blade.php` | `DashboardController`, `AttendanceService` | appointments, assignments, patients | Admin sees wider aggregate data; clinician scoping must not regress. |
| Clinician management | admin clinician CRUD views | `ClinicianController` | users, clinicians | Only admin. Account creation/password/avatar conventions must be retained. |
| Chatbot knowledge management | `/chatbot-content` admin views | `ChatbotContentController` | chatbot_intents/responses | Updates change both Jaccard and Gemini grounding behavior. |
| Activity log | `/activity-logs` admin | `ActivityLogService`, controller | activity_logs | Audit events are write side effects of clinical actions. Avoid logging sensitive message/body content unless established. |
| Notification delivery audit | `/notifications/logs` admin | `NotificationLogController` | notifications | Delivery timestamps/error state are operational data. |

## Safe Modification Map

### Changing appointment logic may affect

- `AppointmentService`, `AvailabilityService`, `AppointmentPolicy`, exception handling.
- Staff web, patient portal, Flutter appointment API/client/models/screens.
- Jitsi link behavior, notification type/content, reminder and no-show jobs.
- Dashboard attendance statistics, progress pages, and tests in `Appointment*`, `BookingApiTest`, `AttendanceTest`, `AvailabilityServiceTest`, `TimezoneSerializationTest`.

### Changing users, patients, or roles may affect

- Browser registration/login and Flutter auth flows.
- Caseload filters, patient requests, messaging, assignments, assessments, notes, and all policies.
- Sanctum API rejection of staff roles, web redirect destinations, seeded demo accounts.
- Terms acceptance migration/model fillable/casts and consent tests.

### Changing notifications may affect

- `NotificationService` callers across appointments, assignments, assessments, requests, messages and reminder jobs.
- `notifications` schema and semantics (`sent_at` vs email-specific fields).
- FCM service/device token API/mobile lifecycle, mail queue worker, notification inboxes and admin log.
- `EmailNotificationDeliveryTest`, `JobIdempotencyTest`, appointment notification tests.

### Changing private upload behavior may affect

- Assignment creation/submission, avatar handling, file previews/downloads, S3 configuration.
- Authorization policy checks and `filesystems.php` default disk.
- Mobile `file_picker`, `download_service`, Android permissions, storage tests.

### Changing mobile API fields may affect

- Laravel route/controller/form request/resource/model.
- Flutter `services/api/*_api.dart`, model `fromJson/toJson`, provider, screen, cache serialization.
- Postman collection and integration tests. There is no generated typed client to update automatically.

### Changing time/date behavior may affect

- `config/app.php`, `AppServiceProvider` Carbon serialization override, Laravel casts/resources.
- Flutter `date_format.dart`, appointment models/screens/calendar, availability slots.
- Tests specifically protecting the Manila wall-clock serialization contract.
