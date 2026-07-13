# API Reference: Patient Mobile API v1

Base path: `/api/v1`. Routes are defined in `routes/api.php`. The API is a patient-facing surface only; clinician/admin accounts must use browser session routes.

## Conventions

- JSON endpoints use Laravel resources, commonly wrapped as `{ "data": ... }`.
- Authenticated endpoints require `Authorization: Bearer <Sanctum token>` and `role:patient`.
- General authenticated API operations use `throttle:api` (60/min/user or IP); chatbot uses 30/min; registration 3/min/IP; login 5/min/IP plus 5/min/email+IP.
- Numeric `{id}` route parameters are constrained globally to avoid type-error 500s. Model-bound values return 404 when absent.
- API error messages are deliberately user-safe. Inspect request classes/resources/controllers for exact field shapes before adding fields.

## Public/Auth Endpoints

| Method | Route | Request | Response | Called by |
|---|---|---|---|---|
| GET | `/health` | none | `{status: ok}` or 503 DB status | Railway health check |
| POST | `/register` | name, email, password, password_confirmation, accepted_terms; optional patient profile fields | 201 user resource + token | `AuthApi.register`, Flutter register screen |
| POST | `/login` | email, password | user resource + token | `AuthApi.login` |
| POST | `/logout` | bearer token | 204; deletes user tokens | `AuthApi.logout` |
| GET | `/me` | bearer token | user + patient profile | `AuthNotifier.checkAuth/login/register` |
| PUT | `/auth/password` | current_password, password, password_confirmation | success JSON | Flutter change-password screen |

## Profile, Device, and Notifications

| Method | Route | Request/response focus | Called by |
|---|---|---|---|
| GET | `/profile` | patient profile resource | `ProfileApi.getProfile` |
| PUT | `/profile` | patient profile fields | `ProfileApi.updateProfile` |
| POST | `/profile/avatar` | multipart avatar under `UpdateAvatarRequest` | `ProfileApi.uploadAvatar` |
| GET | `/profile/avatar` | authenticated avatar stream | profile/avatar UI |
| POST | `/device-token` | FCM token and platform | Flutter `FcmService` via `NotificationApi` |
| DELETE | `/device-token` | token/platform identifier | Flutter sign-out/token cleanup |
| GET | `/notifications` | paginated notification resources | `NotificationApi.getNotifications` |
| POST | `/notifications/{id}/read` | none | read state | `NotificationApi.markRead` |

## Appointment and Availability

| Method | Route | Request/response focus | Called by |
|---|---|---|---|
| GET | `/clinicians` | available clinician directory resources | booking UI |
| GET | `/schedules` | date/slot schedule data | calendar/schedule UI |
| GET | `/schedules/availability` | clinician/date availability | booking UI |
| GET | `/appointments` | paginated appointment resources | `AppointmentApi`, list/dashboard |
| POST | `/appointments` | `StoreAppointmentRequest` (clinician, datetime, mode, optional concerns) | created appointment | booking screen |
| GET | `/appointments/{id}` | one owned appointment | detail screen |
| DELETE | `/appointments/{id}` | cancellation | detail/list UI |

## Assignments and Files

| Method | Route | Request/response focus | Called by |
|---|---|---|---|
| GET | `/assignments` | assignment resources | assignment list |
| GET | `/assignments/{id}` | owned assignment/detail | assignment detail |
| GET | `/assignments/{id}/worksheet` | protected file stream | download service |
| POST | `/assignments/{id}/submit` | `SubmissionRequest`: text and/or multipart file | submission resource | submit screen |
| GET | `/submissions/{id}/file` | protected submission file | preview/download |

## Assessments, Mood, Goals, and Notes

| Method | Route | Request/response focus | Called by |
|---|---|---|---|
| GET | `/assessments` | assigned assessments | assessment list |
| GET | `/assessments/{assessment}` | owned assessment | fill screen |
| POST | `/assessments/{assessment}/submit` | responses payload | completed assessment | fill screen |
| GET | `/mood-logs` | patient-owned mood entries | mood provider/screens |
| POST | `/mood-logs` | mood score/note/date data | created mood entry | mood UI |
| GET | `/goals` | read-only therapy goals/ratings | progress UI |
| GET | `/notes` | clinician-shared patient notes | notes UI |

## Messaging and Chatbot

| Method | Route | Request/response focus | Called by |
|---|---|---|---|
| GET | `/conversations` | participant conversation list | inbox |
| POST | `/conversations` | opens/returns patient-clinician conversation | inbox/thread flow |
| GET | `/conversations/{conversation}/messages` | participant message list | thread |
| POST | `/conversations/{conversation}/messages` | message body | thread send |
| POST | `/chatbot/message` | patient text | `{reply, intent_key, is_fallback}` | `ChatbotApi.sendMessage` |

## Backend Response Types

`app/Http/Resources` defines the serializable public contract: `UserResource`, `PatientResource`, `ClinicianResource`, `AppointmentResource`, `AssignmentResource`, `SubmissionResource`, `AssessmentResource`, `MoodLogResource`, `TherapyGoalResource`, `ConversationResource`, `MessageResource`, `NotificationResource`, `PatientNoteResource`, `DeviceTokenResource`, and `ScheduleSlotResource`.

Flutter models mirror these shapes in `theraconnect_flutter/lib/models`. This mapping is manual. Before changing resource output, find the matching Flutter model and API parser with `rg "fromJson" theraconnect_flutter/lib/models`.

## Browser Routes

Browser routes are not a second JSON API. They are named Laravel routes in `routes/web.php`, handled by Web/Portal controllers, authenticated by session, CSRF-protected by the web middleware group, and rendered by Blade. Their complete generated list can be obtained from `php artisan route:list`; use route names instead of hard-coded URLs in Blade/controllers.
