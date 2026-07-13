# Guidelines for Future AI Agents

## Rules of Engagement

1. Read `routes`, the relevant controller, service, policy, model, resource, view/screen, and tests before editing. Search for an existing analogous workflow first.
2. Reuse existing services. Do not duplicate appointment transition, assignment submission, notification, availability, file-download, or authorization logic in a new controller/screen.
3. Keep business logic out of Blade and Flutter widgets. Blade/Flutter should orchestrate display and call controller/provider methods; Laravel services own cross-surface domain behavior.
4. Do not invent a repository layer for a one-off change. The established pattern is direct Eloquent in controllers/services; add an abstraction only when it removes real repeated complexity.
5. Never bypass policies, caseload scoping, participant checks, or private-file streaming routes. Authorization is a safety boundary, not display logic.
6. Treat patient content as sensitive. Avoid exposing note/message/assessment data in notification email, logs, public URLs, client cache, or error messages.
7. Preserve post-commit semantics. Create notifications inside the relevant transaction and dispatch their delivery after commit through `NotificationService`.
8. Update all contract partners for a changed API field: request validation, controller/service, resource, Flutter model/API/provider/UI/cache, tests, and often Postman documentation.
9. Use named Laravel routes and route helpers; do not introduce hard-coded browser paths where a route name exists.
10. Add forward migrations only. Never edit deployed migration history. Mention `php artisan migrate --force` in deployment handoff when schema changed.
11. Preserve Manila wall-clock date serialization until backend and Flutter are migrated together. Time handling has explicit regression tests.
12. Keep edits narrow. Existing unrelated worktree changes are user-owned; never reset/revert/stage them without explicit instruction.
13. Run focused tests proportional to affected layers, plus `git diff --check`. State test/environment limitations clearly.
14. Update `/docs` when adding a feature, endpoint, table, external service, cross-surface contract, or critical change rule.

## Coding Conventions

- PHP: PSR-4 under `App\`; Laravel type declarations and return types are used consistently. Prefer constructor injection for services.
- Naming: web controllers use `Web*` where a similarly named API/portal controller exists; portal controllers prefix `Portal`; Flutter files use snake_case and classes PascalCase.
- Validation: backend rule classes/FormRequests are authoritative. Client validation improves UX but is never sufficient for security.
- Errors: API/mobile code converts unknown exceptions to safe user messages. Web code redirects with validation/flash messages for expected input/state errors.
- Logs: include identifiers/type/channel context, not raw sensitive payloads.
- Data access: load relations intentionally (`with`, `load`) to avoid N+1 queries in dashboards/lists.
- UI: follow existing Bootstrap/Alpine patterns in Blade and Material/Riverpod patterns in Flutter. Keep responsive staff screens dense and operational.

## Dependency Graph

```text
User/Patient/Clinician
├── Authentication and authorization
├── Appointments
│   ├── AvailabilityService
│   ├── AppointmentPolicy
│   ├── JitsiService
│   ├── AttendanceService / no-show job
│   └── NotificationService
├── Caseload / patient requests
│   ├── PatientPolicy
│   ├── Messaging
│   ├── Assignments
│   ├── Assessments / goals / notes
│   └── NotificationService
└── Notifications
    ├── Device tokens / FCM
    ├── Email jobs/mail config
    ├── Portal/staff inboxes
    └── Flutter notifications/cache
```

High-impact files: `routes/web.php`, `routes/api.php`, `AppServiceProvider.php`, `AppointmentService.php`, `NotificationService.php`, `AvailabilityService.php`, `User.php`, `Patient.php`, `RoleMiddleware.php`, `api_client.dart`, `auth_provider.dart`, and `router.dart`.

## Known Technical Debt and Risk Areas

| Area | Evidence / risk | Safe direction |
|---|---|---|
| Cross-surface duplication | Web, portal, API, and Flutter each have adapters/UI for the same domain actions | Keep domain changes in Laravel services/resources and update adapters together; avoid new divergent rules. |
| Flutter contract is manual | No generated OpenAPI client; models and endpoint strings are hand-maintained | Update backend resources and Flutter parsers in one change; add API integration tests. |
| Browser JS mixes Alpine and Bootstrap | Registration agreement uses Bootstrap modal plus Alpine password state; other templates use both libraries | Avoid object-spreading state objects with computed getters; use established component initialization and test interactive paths. |
| Large controllers/services | Appointment, progress, assignment and message workflows have substantial orchestration | Refactor only behind tested service boundaries; do not split state transitions across controllers. |
| Notification fan-out | Many callers create notification types; queued delivery has independent state/retry behavior | Centralize through `NotificationService`; preserve idempotency and message-email privacy exclusion. |
| Database-backed infra | Queue/cache/session behavior changes with Railway env/worker topology | Verify migrations, worker, scheduler, session table and stderr logging in production. |
| External service resilience | Gemini, FCM, SMTP, S3 can fail independently | Keep failure isolated from core bookings/care writes; log contextual non-sensitive errors. |
| Test environment variance | GD and database/network environment can affect broad suites | Run targeted suites first; distinguish infrastructure failures from regressions. |
| Source encoding/comment noise | Some historical comments display malformed Unicode characters | Avoid mechanical encoding rewrites; keep new edited text ASCII unless a file clearly requires Unicode. |

## Feature Implementation Checklist

Before implementing a new feature:

1. Locate analogous route/controller/service/policy/model/resource/provider/screen with `rg`.
2. Define which role(s) and surface(s) own the feature.
3. Identify data migration, privacy implications, state transitions, notifications, audit need, and file handling.
4. Implement the backend service/policy/data contract before UI adapters when behavior is shared.
5. Add or update focused integration/adversarial tests.
6. For API changes, update Flutter models/services/providers/screens and any cache key serialization.
7. Verify web/portal redirects, error messaging, and mobile 401 behavior.
8. Check `git diff --check`, preserve unrelated changes, and update these docs.

## Glossary

| Term | Meaning in this project |
|---|---|
| Patient portal | Session-authenticated browser experience under `/portal`. |
| Staff dashboard | Session-authenticated web experience for clinicians/admins. |
| Caseload | Many-to-many patient-clinician relationships in `clinician_patient`; key authorization scope. The singular patient field is compatibility-only. |
| Clinician request | A pending/approved/denied request relationship distinct from caseload until approval. |
| Assignment | Clinician-created task with optional worksheet/attachment and patient submission. |
| Assessment | Assigned standardized questionnaire, currently PHQ-9/GAD-7 support. |
| Goal rating | Rating linked to a therapy goal, optionally associated with an appointment. |
| In-app notification | Row in `notifications`, source of truth even when push/email fails. |
| Push delivery | FCM delivery, tracked with existing notification push fields. |
| Transactional email | Allow-listed notification email, tracked separately from push. |
| Wall-clock contract | Laravel serializes Manila local time with a `Z`; Flutter intentionally treats it as local wall clock. |
| Joy | TheraConnect’s clinic chatbot persona. |
| PHI-adjacent | Sensitive patient/clinical information needing strict access and minimal disclosure. |
