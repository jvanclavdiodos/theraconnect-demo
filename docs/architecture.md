# Architecture, Data Flow, State, Navigation, and Authentication

## Backend Layers

| Layer | Location | Responsibility |
|---|---|---|
| Routes | `routes/web.php`, `routes/api.php`, `routes/console.php` | HTTP/CLI entry points, middleware and scheduled jobs |
| Controllers | `app/Http/Controllers/{Web,Portal,Api/V1}` | Surface-specific orchestration, response/view selection |
| Form requests | `app/Http/Requests/Api` | Reusable validation and payload normalization |
| Services | `app/Services` | Transactional/domain workflows and external integration boundaries |
| Policies | `app/Policies` | Model-level ownership/caseload/role authorization |
| Models | `app/Models` | Eloquent persistence, relationships, casts, domain helpers |
| Resources | `app/Http/Resources` | JSON API response shape |
| Jobs/Mail | `app/Jobs`, `app/Mail` | Asynchronous reminders and delivery |
| Views | `resources/views` | Bootstrap/Blade browser UI |

### Recurring Patterns

- **Surface adapters**: portal and API controllers are thin adapters over the same services (`AppointmentService`, `AssignmentService`, `AssessmentService`, `MessageService`).
- **Explicit transactions**: appointment status transitions, registration/profile creation, assignment/assessment operations, and patient requests generally use `DB::transaction` around related writes.
- **Policy before access**: controllers use `Gate::authorize` or `$this->authorize` for model-specific operations; broad role restrictions are route middleware.
- **Notification as an after-effect**: services/controllers create a database `Notification`, then `NotificationService::dispatchDeliveries()` schedules push/email after transaction commit. Delivery dispatch failures are logged and intentionally do not undo the clinical/business operation.
- **API resource boundary**: API controllers return `*Resource` classes instead of Eloquent records directly.

## Major Data Flows

### Patient registration

```text
Web register view / Flutter RegisterScreen
  -> POST /register (session) or POST /api/v1/register (JSON)
  -> RegisterController or Api\V1\AuthController
  -> validation: strong password + accepted_terms + profile fields
  -> DB transaction: users(role=patient) + patients
  -> record terms_accepted_at + terms_version
  -> web: session login then patient portal redirect
     mobile: Sanctum token returned, stored in FlutterSecureStorage, then GET /me
```

The browser registration controller contains a post-commit fallback: if automatic session login/regeneration fails after the user was committed, it logs the failure and redirects to login with a success message rather than rendering a 500. The mobile registration path does not use browser sessions.

### Appointment lifecycle

```text
Patient UI
  -> PortalAppointmentController / Api\V1\AppointmentController
  -> AppointmentService::bookAppointment()
  -> AvailabilityService + conflict/state validation
  -> appointments row (pending), optional Jitsi link only when appropriate
  -> NotificationService creates clinician/patient notification
  -> after commit: SendPushNotification + eligible SendEmailNotification jobs

Staff appointment UI
  -> WebAppointmentController
  -> AppointmentService::approve/reject/reschedule/complete/cancel/no-show
  -> policy/caseload check + transition validation + notifications/activity log
```

`AppointmentService` is the critical state-machine owner. `InvalidStateException` and `SlotUnavailableException` represent expected business failures and should remain mapped to user-safe responses.

### Assignment and submission

```text
Clinician staff form
  -> WebAssignmentController + StoreAssignmentRequest
  -> AssignmentService::create() writes assignment and optional private attachment
  -> patient notification dispatched after commit

Patient portal/mobile
  -> PortalAssignmentController / SubmissionController
  -> SubmissionRequest validates text/file
  -> AssignmentService::submit() writes Submission and private file
  -> clinician reviews via AssignmentService::review()
```

Files stay on the configured private disk and are streamed only through authenticated, policy-checked endpoints. Do not replace these download routes with public URLs.

### Messaging

```text
Patient or clinician thread UI
  -> PortalMessageController / Web MessageController / API ConversationController
  -> MessageService::ensureAssignedConversations() (all approved clinicians)
  -> MessageService::conversationFor() (one conversation per patient-clinician pair)
  -> MessageService::send()
  -> messages row + unread markers + recipient in-app/push notification
```

`message_received` is intentionally push/in-app only; it is excluded from transactional email to avoid sending sensitive snippets by email.

### Assessments, mood, and goals

```text
Clinician progress page -> ProgressController
  -> AssessmentService::assign(), GoalService::create/rate/setStatus()
  -> Assessment / TherapyGoal / GoalRating rows + patient notification

Patient portal/mobile -> assessment/mood/goals controllers
  -> policy check -> submit responses / create MoodLog / fetch read-only goals
```

The defined instruments are in `App\Support\Assessments`; assessment model helpers derive titles/severity. Treat response payloads as clinical data.

### Notifications

```text
Domain action -> NotificationService::create()
  -> notifications table (synchronous, source of truth for in-app state)
  -> DB::afterCommit()
     -> SendPushNotification (FCM device tokens)
     -> SendEmailNotification for allow-listed transactional types
```

Email state is separate from push state: `sent_at` retains push semantics; `email_sent_at`, `email_failed_at`, and `email_error` track email. Jobs are idempotent around delivery timestamps.

## Web Navigation and Guards

### Browser route groups

| Group | Guard | Surface |
|---|---|---|
| `/login`, `/register` | `guest`; rate-limited | Session auth entry |
| staff routes | `auth`, `role:admin,clinician` | Dashboard, appointments, assignments, patient management |
| admin subset | staff guard + `role:admin` | Clinicians, chatbot content, audit/notification logs, patient editing/deleting |
| clinician subset | staff guard + `role:clinician` | Availability, notes, goals/assessments, messages |
| `/portal/*` | `auth`, `role:patient` | Patient browser portal |

`RoleMiddleware` redirects unauthenticated browser users to login, emits JSON 401 for API requests, and raises authorization errors for incorrect roles. Policies apply the finer-grained patient/caseload/participant checks.

### Flutter routes

`theraconnect_flutter/lib/router.dart` uses GoRouter. The root redirect is based on `authProvider` state:

- unauthenticated users may reach `/login` and `/register`;
- authenticated users are sent to `/dashboard`;
- unauthenticated access to protected pages redirects to `/login`.

The shell route (`HomeShell`) provides authenticated patient navigation. Detail routes use route parameters for appointments, assignments, conversations, assessments, and submissions. There is no declared universal-link/app-link configuration in the inspected Flutter sources; deep-link handling beyond GoRouter path parsing is **not confirmed**.

## Flutter State Management

The mobile app uses Riverpod, primarily `StateNotifierProvider` and `FutureProvider`. There are no BLoCs, Cubits, Redux stores, hooks, or repository classes.

| State | Owner | Purpose |
|---|---|---|
| Authentication | `AuthNotifier` / `authProvider` | token lifecycle, current user/patient, login/register/logout errors |
| Appointments | `AppointmentNotifier` | appointment list/cache and mutations; detail provider is family/autoDispose |
| Assignments | `AssignmentNotifier` | list/cache and submit actions |
| Assessments | assessment providers | list/detail and submission state |
| Chatbot | `ChatbotNotifier` | in-memory transcript and optimistic placeholder reply |
| Notifications | `NotificationNotifier` | cached list, loading/error, unread count |
| Profile | `ProfileNotifier` | cached patient profile/avatar mutations |
| Downloads | `DownloadsNotifier` | locally recorded downloaded files |
| Theme | `ThemeModeNotifier` | persisted `ThemeMode` preference |
| Read-mostly features | `FutureProvider.autoDispose` | goals, mood logs, notes, conversations and several details |

`AuthService` stores only the Sanctum token in `flutter_secure_storage`; `CacheService` stores non-secret JSON snapshots and theme/download metadata in `shared_preferences`. `ApiClient.onUnauthorized` clears auth/cache when a 401 is received. There is no refresh-token flow; Sanctum tokens expire server-side after seven days and users reauthenticate.

## Authentication and Authorization

### Browser

- Laravel `web` guard with sessions.
- Patient self-registration always creates `role=patient`; staff accounts are admin-provisioned.
- Login normalizes email lower-case and performs a dummy hash check when no user exists to reduce enumeration timing signals.
- Session regeneration follows login. Logout invalidates session and regenerates CSRF token.

### Mobile API

- `POST /api/v1/login` and registration return `createToken('mobile')->plainTextToken`.
- All mobile domain routes require `auth:sanctum` and `role:patient`.
- `POST /api/v1/logout` deletes the current user's tokens (not merely the current token).
- The API deliberately rejects clinician/admin mobile login with 403.

### Authorization rules with broad impact

- `AppointmentPolicy`: patient owns appointment; clinician/admin manages based on caseload/role.
- `AssignmentPolicy` and `SubmissionPolicy`: patient ownership and clinician assignment ownership.
- `ConversationPolicy`: only the patient and clinician recorded on an existing thread participate. Thread creation and inbox discovery remain assignment-gated.
- `PatientPolicy`: clinician visibility and request response is caseload/request scoped.
- `AssessmentPolicy`, `PatientNotePolicy`, `UserPolicy`: clinical ownership/view boundaries.

## Time and Serialization Contract

The application timezone is Asia/Manila. `AppServiceProvider` serializes Carbon dates as Manila wall-clock with a trailing `Z`; Flutter deliberately displays that wall-clock without converting the offset. This is a legacy API contract. Do not “correct” timestamps to UTC in one client or one resource without coordinated backend/mobile migration and tests.
