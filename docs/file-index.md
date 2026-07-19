# File Index

This is an index of important, maintained source files. It intentionally excludes `vendor/`, generated Android plugin registration, build caches, lockfiles, compiled assets, and framework boilerplate unless they change runtime behavior.

## Laravel Entry, Routes, and Middleware

| File | Purpose / public entry points | Depends on / used by |
|---|---|---|
| `bootstrap/app.php` | Laravel routing, global middleware, branded exception responses | all HTTP requests; `RoleMiddleware`, `SecurityHeaders` |
| `routes/web.php` | named browser routes and role groups | all Blade controllers/views |
| `routes/api.php` | `/api/v1` patient endpoint map | API controllers, Flutter API services |
| `routes/console.php` | appointment/assignment reminder and no-show schedule | scheduler service |
| `routes/channels.php` | private Reverb channel authorization for users, conversations, and admin appointments | web sessions and Sanctum mobile tokens |
| `app/Providers/AppServiceProvider.php` | rate limits, policy registrations, Blade role helper, Carbon serialization | all routing/API time behavior |
| `app/Http/Middleware/RoleMiddleware.php` | `role:*` authorization middleware | all guarded route groups |
| `app/Http/Middleware/SecurityHeaders.php` | response security headers/HSTS | all HTTP responses |

## Controllers and Views

### Web staff/auth controllers

| Files | Purpose |
|---|---|
| `Web/AuthenticatedSessionController.php`, `auth/login.blade.php` | session login/logout and role redirect |
| `Web/RegisterController.php`, `auth/register.blade.php`, `partials/terms-and-conditions.blade.php`, `partials/password-strength.blade.php` | patient browser signup, agreement, password UI, post-commit login recovery |
| `Web/DashboardController.php`, `clinician/dashboard.blade.php` | admin/clinician dashboard metrics |
| `Web/PatientController.php`, `patients/*` | staff patient list/create/show/edit/delete, caseload views |
| `Web/PatientRequestController.php` | approve/deny pending patient requests |
| `Web/WebAppointmentController.php`, `appointments/index.blade.php` | staff appointment list/filter/state operations |
| `Web/ClinicianAvailabilityController.php`, `partials/availability-calendar.blade.php` | clinician availability JSON/calendar controls |
| `Web/WebAssignmentController.php`, `assignments/*` | staff assignment creation/review/private downloads |
| `Web/MessageController.php`, `messages/*` | clinician messaging interface |
| `Web/ProgressController.php`, `patients/progress.blade.php` | clinician assessments/goals/ratings/progress |
| `Web/PatientNoteController.php` | clinician note CRUD |
| `Web/ClinicianController.php`, `clinicians/*` | admin clinician CRUD |
| `Web/AccountController.php`, `account/edit.blade.php` | staff password/avatar/account changes |
| `Web/NotificationController.php`, `notifications/index.blade.php` | staff notification inbox/read state |
| `Web/NotificationLogController.php`, `notifications/logs.blade.php` | admin notification delivery audit |
| `Web/ActivityLogController.php`, `activity-logs/index.blade.php` | admin activity audit |
| `Web/ChatbotContentController.php`, `chatbot-content/*` | admin chatbot KB CRUD |

### Patient portal controllers and views

| Files | Purpose |
|---|---|
| `Portal/PortalDashboardController.php`, `portal/dashboard.blade.php` | patient summary dashboard |
| `Portal/PortalAppointmentController.php`, `portal/appointments/*` | patient booking/list/detail/cancel |
| `Portal/PortalAssignmentController.php`, `portal/assignments/*` | patient assignments/submissions/downloads |
| `Portal/PortalAssessmentController.php`, `portal/assessments/*` | list/fill/submit assessments |
| `Portal/PortalMessageController.php`, `portal/messages/*` | patient conversation and sending |
| `Portal/PortalMoodLogController.php`, `portal/mood/index.blade.php` | patient mood logs |
| `Portal/PortalGoalController.php`, `portal/goals/index.blade.php` | read-only goals |
| `Portal/PortalNoteController.php`, `portal/notes/index.blade.php` | shared notes |
| `Portal/PortalNotificationController.php`, `portal/notifications/*` | notification inbox/read state |
| `Portal/PortalProfileController.php`, `portal/profile/*` | profile/avatar/password |
| `Portal/PortalChatbotController.php`, `portal/chatbot/index.blade.php` | patient assistant UI |

### Mobile API controllers

`app/Http/Controllers/Api/V1/*Controller.php` mirrors patient capabilities: `Auth`, `Password`, `Profile`, `DeviceToken`, `Notification`, `Appointment`, `Clinician`, `Assignment`, `Submission`, `Assessment`, `MoodLog`, `Goal`, `PatientNote`, `Conversation`, and `Chatbot`. Each returns resources rather than views. Route ownership is detailed in [api.md](api.md).

## Services, Jobs, Policies, and Support

| File(s) | Public responsibilities | Dependents |
|---|---|---|
| `AppointmentService.php` | booking, conflict test, approve/reject/reschedule/cancel/complete/no-show, meeting links | all appointment controllers/jobs/tests |
| `AvailabilityService.php` | slot generation and override logic | booking/availability controllers, appointment service |
| `AssignmentService.php` | create, submit, review with storage | staff/portal/API assignment controllers |
| `AssessmentService.php`, `GoalService.php`, `AttendanceService.php` | assessments, goal lifecycle/rating, attendance statistics | progress/dashboard/portal/API |
| `MessageService.php` | conversation creation, send, unread/read | web/portal/API messaging |
| `NotificationService.php` | database notification factories and after-commit push/email dispatch | nearly every workflow and reminder job |
| `RealtimeEventDispatcher.php` | after-commit, failure-contained broadcast dispatch | appointment, message, and notification services |
| `FcmService.php` | OAuth/JWT FCM v1 delivery and invalid-token cleanup | push job |
| `ChatbotService.php` | Gemini and Jaccard resolution/fallback | portal/API chatbot controller |
| `PatientRequestService.php` | patient clinician-request transition + notification | request controller |
| `ActivityLogService.php` | audit row creation | clinical/staff controllers |
| `JitsiService.php` | meeting URL generation | appointment service |
| `SendPushNotification.php`, `SendEmailNotification.php` | queued idempotent channel delivery | NotificationService |
| `GenerateAppointmentReminders.php`, `GenerateAssignmentReminders.php`, `MarkOverdueNoShows.php` | scheduled domain maintenance | console schedule |
| `NotificationEmail.php`, `emails/notification.blade.php` | transactional email rendering | email job |
| `app/Events/{NotificationCreated,MessageCreated,AppointmentUpdated}.php` | minimal private-channel broadcast contracts | Realtime dispatcher, Echo, Flutter RealtimeService |
| `resources/js/{echo,realtime}.js`, `views/partials/realtime.blade.php` | browser Reverb setup, subscriptions, counters, safe refresh behavior | authenticated staff/portal layouts |
| `config/broadcasting.php`, `config/reverb.php` | broadcaster client and Reverb server/app policy | API, worker, Reverb service |
| `*Policy.php` | model authorization rules | controllers using `Gate`/`authorize` |
| `StrongPassword.php`, `Support/Assessments.php`, `Support/TermsOfService.php` | shared validation/domain constants | auth/assessment/terms flows |

## Models, Requests, Resources

- `app/Models/*.php`: every durable entity listed in [data-model.md](data-model.md); each is an Eloquent active-record model, not a repository DTO.
- `app/Http/Requests/Api/*.php`: shared validation for auth/password/profile/avatar/appointments/assignments/submissions. Some portal/staff controllers reuse these request classes; changing a rule can change both surfaces.
- `app/Http/Resources/*.php`: JSON response serializers. Match each changed resource with Flutter models and API parsers.

## Flutter Application Files

| Path/group | Purpose |
|---|---|
| `lib/main.dart` | Flutter startup, Firebase init, preferences provider override, theme/router setup |
| `lib/router.dart` | GoRouter path map and auth redirect policy |
| `lib/config/api_config.dart` | base API configuration and endpoint constants |
| `lib/models/*.dart` | manual JSON/domain models corresponding to Laravel resources |
| `lib/services/api_client.dart` | Dio setup, bearer token injection, 401 callback, request logging/redaction |
| `lib/services/api/*_api.dart` | endpoint-specific Dio adapters; no repository layer |
| `lib/services/auth_service.dart`, `cache_service.dart` | secure token and non-secret JSON cache |
| `lib/services/fcm_service.dart` | Firebase token registration and local notification behavior |
| `lib/services/realtime_service.dart`, `providers/realtime_provider.dart` | Reverb connection, private authorization, reconnect and active-thread subscriptions |
| `lib/services/download_service.dart` | protected file download and local metadata |
| `lib/providers/*.dart` | Riverpod dependency providers and feature state notifiers/futures |
| `lib/screens/auth/*` | mobile login/registration/User Agreement |
| `lib/screens/shell/home_shell.dart`, `dashboard/*` | authenticated navigation shell/dashboard |
| `lib/screens/appointments`, `schedule` | list/detail/book/calendar workflows |
| `lib/screens/assignments`, `progress`, `messages`, `notes`, `notifications`, `profile`, `chatbot`, `downloads` | feature-specific patient UI |
| `lib/widgets/password_field.dart`, `joy_avatar.dart` | reusable mobile widgets |
| `lib/utils/validators.dart`, `date_format.dart` | shared input/time contract logic |
| `lib/theme/app_theme.dart` | visual system |

## Shared Components, Helpers, and Utilities

| File | Intended reuse |
|---|---|
| `resources/views/layouts/app.blade.php` | shared staff/auth browser layout, Bootstrap/Alpine bootstrapping, sidebar/navbar shell |
| `resources/views/layouts/portal.blade.php` | shared patient portal layout/navigation shell |
| `resources/views/partials/flash.blade.php` | standard success/error/validation flash rendering |
| `resources/views/partials/navbar.blade.php`, `sidebar.blade.php`, `portal/partials/*` | role-specific navigation UI |
| `resources/views/partials/password-strength.blade.php` | shared Alpine `passwordField()` strength/confirmation UI; retain getter-based initialization |
| `resources/views/partials/availability-calendar.blade.php` | clinician availability calendar interaction |
| `public/js/file-upload.js` | browser file-upload enhancement |
| `public/js/avatar-cropper.js` | patient avatar selection, square crop, zoom/rotation, compression, and guarded form submission |
| `public/css/theraconnect.css` | shared browser visual tokens/layout behavior |
| `theraconnect_flutter/lib/widgets/password_field.dart` | mobile password entry/visibility widget |
| `theraconnect_flutter/lib/widgets/joy_avatar.dart` | chatbot persona/avatar widget |
| `theraconnect_flutter/lib/screens/profile/profile_avatar.dart` | profile avatar display/edit support |
| `theraconnect_flutter/lib/utils/validators.dart` | mobile input/password validation helpers; keep aligned with backend `StrongPassword` |
| `theraconnect_flutter/lib/utils/date_format.dart` | API date wall-clock display convention |
| `theraconnect_flutter/lib/services/api_error_handler.dart` | converts Dio/backend failures to safe mobile messages |
| `theraconnect_flutter/lib/services/cache_service.dart` | typed JSON cache serialization helpers |

## Test Files

`tests/Integration` is the main behavioral suite. File names identify the covered workflow (`AppointmentFlowTest`, `AssignmentFlowTest`, `Messaging*Test`, `Portal*Test`, `EmailNotificationDeliveryTest`, etc.). `tests/Adversarial` guards authorization, information leakage, malformed input, state machines, throttling, idempotency, and UX friction. `tests/Concerns/CreatesActors.php` and `tests/TestCase.php` provide fixtures/helpers. Treat those tests as executable architectural documentation before changing business rules.
