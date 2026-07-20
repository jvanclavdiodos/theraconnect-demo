# TheraConnect Feature Implementation Roadmap

Status: planning only. This document does not authorize or contain application-code changes.

## 1. Engineering Specification

### Goal

Deliver seven focused improvements without weakening TheraConnect's patient isolation, clinician-caseload authorization, encrypted clinical-data handling, or shared web/API/Flutter behavior:

1. clinician-only PDF patient-record export;
2. accessible help for unclear buttons;
3. a floating Joy chat experience backed by the existing chatbot;
4. a role-aware quick user guide;
5. clinically cautious questionnaire explanations;
6. clinician access to an appointment's booking reason; and
7. patient appointment sorting and filtering.

### Current context

- Backend: PHP 8.2, Laravel 11, Eloquent, session-authenticated Blade staff/portal surfaces, Sanctum patient API, queues, Reverb, and private filesystem storage.
- Browser UI: Bootstrap 5.3.3, Bootstrap Icons, Alpine.js, shared `layouts.app` and `layouts.portal`, and `public/css/theraconnect.css`.
- Mobile: Flutter, Riverpod, GoRouter, Dio, and hand-maintained API models.
- Authorization: route role middleware provides the coarse boundary; policies and caseload checks provide model-level isolation.
- Caseload: `clinician_patient` is the many-to-many source of truth, with `patients.assigned_clinician_id` retained for compatibility. Use `Patient::isAssignedTo()` rather than checking only the legacy column.
- Clinical fields already present include encrypted `appointments.reason` and encrypted `patients.personal_issues`; patient notes have an existing `is_shared` boundary.
- There is no PDF library in `composer.json` or `composer.lock`.
- Joy already has one backend service and two adapters: portal routes/controller plus patient API/Flutter provider. Its transcript is in-memory and has no unread model.
- Staff appointment sorting/filtering already provides a safe allowlist pattern and preserves query strings. Portal/API patient appointment lists do not yet expose equivalent controls.
- Questionnaire definitions are centralized in `App\Support\Assessments`; PHQ-9 and GAD-7 scoring must remain unchanged.
- PHPUnit integration and adversarial suites exist. There is currently no Flutter `test/` directory and no browser end-to-end test runner in the repository.

### Constraints

- Do not redesign unrelated screens or refactor domain services opportunistically.
- Keep business rules in services/policies/support classes, not Blade or Flutter widgets.
- Preserve the four application surfaces as separate adapters: staff web, patient portal, patient API, and Flutter.
- Reuse current services, policies, resources, Bootstrap components, Riverpod providers, and route naming conventions.
- Never expose clinical data through public storage, public URLs, broadcast payloads, logs, filenames, or client-side responses for another patient/clinician.
- Keep query sort columns and directions server-allowlisted; never pass request values directly into SQL ordering.
- Preserve current appointment state transitions, assessment scoring, chatbot matching, notification behavior, and realtime invalidation.
- Maintain current Manila wall-clock serialization behavior.
- Keep all new user-facing copy in English for this pass, matching the current product. Flutter strings should use the existing localization files rather than new hard-coded copies.

### Coding standards

- Use typed Laravel controllers/services and named routes.
- Authorize before loading or rendering sensitive detail.
- Eager-load only required relations and select only required columns for large exports.
- Return API data through resources or explicit response DTO arrays; update Flutter parsers in the same vertical slice.
- Escape all patient-authored text. Bootstrap popovers must keep HTML rendering disabled.
- Keep downloadable files private and send `Cache-Control: no-store, private` for generated clinical documents.
- Add focused PHPUnit integration/adversarial coverage and Flutter widget/unit tests where Flutter changes are made.
- Run Laravel Pint and `dart format` only on files touched by the implementation.

### Architecture considerations

```text
Staff web UI
  -> session/role middleware
  -> controller
  -> policy
  -> domain/export service
  -> Eloquent

Patient portal
  -> session/role middleware
  -> portal controller
  -> shared service/support definition
  -> Eloquent

Flutter
  -> Riverpod provider
  -> API adapter/Dio
  -> Sanctum patient API controller
  -> policy/service/resource
  -> Eloquent
```

Cross-feature reuse:

- Feature 2's Bootstrap initializer is a prerequisite for Feature 6's popover and should be implemented once in both browser layouts.
- Features 3 and 4 both modify portal navigation/layout and Flutter shell/router files; implement and merge them sequentially.
- Features 5 and 7 change API payload/query contracts and must update Laravel and Flutter together.
- Feature 1 should reuse `PatientPolicy`, `Patient::isAssignedTo()`, and `ActivityLogService`, but needs a stricter export ability than the current admin-inclusive `view` ability.

## 2. Safest Delivery Order

| Order | Batch | Vertical slice | Risk | Why this order |
|---|---|---|---|---|
| 0 | Baseline | Run targeted existing tests and record current UI/API behavior | Low | Distinguishes pre-existing failures from regressions. |
| 1 | Shared UI | Bootstrap tooltip/popover initializer plus selected icon-button annotations | Low | Establishes one accessible interaction convention before appointment reasons use it. |
| 2 | Appointment privacy | Clinician booking-reason icon/popover and authorization tests | Medium | Small data-path change that reuses Batch 1. |
| 3 | Assessment copy | Central explanation metadata, portal/API/Flutter rendering | Medium | No schema or scoring change; validates the cross-surface contract pattern. |
| 4A | Patient appointments | Portal sorting/filtering/pagination | Medium | Reuses staff conventions with a narrow session-authenticated query. |
| 4B | Patient appointments | API query contract and Flutter controls/pagination state | Medium | Builds on the verified server behavior from 4A. |
| 5A | User guide | Central role-aware guide definition and browser routes/views | Low | Independent content feature; land before changing the same navigation for Joy. |
| 5B | User guide | Patient API adapter and Flutter guide screen | Medium | Keeps one source of content across both required patient surfaces. |
| 6A | Joy | Portal floating widget using the existing controller/service | Medium | Layout and focus behavior need isolated responsive validation. |
| 6B | Joy | Flutter floating launcher/panel using the existing provider | Medium | Router/shell work follows the settled portal interaction. |
| 7 | Patient PDF | Strict export policy, query service, template, Dompdf, audit, download | High | New dependency plus the broadest clinical-data and memory footprint. |
| 8 | Regression | Full Laravel suite, Flutter analysis/tests, builds, responsive/privacy review | Medium | Final cross-feature verification. |

Each batch should be independently reviewable and deployable. Do not combine the PDF dependency with navigation or appointment-query changes.

## 3. Feature Plans

## Feature 1: Clinician PDF Patient Record

### Current implementation summary

- Staff patient profile: `GET /patients/{patient}` -> `PatientController::show()` -> `patients/show.blade.php`.
- Progress page: `GET /patients/{patient}/progress` -> `ProgressController::show()` -> `patients/progress.blade.php`.
- `PatientPolicy::view()` allows admins to view every patient and assigned clinicians to view their caseload.
- `PatientController::show()` logs `patient.viewed` through `ActivityLogService`.
- Existing clinical data spans `patients`, `appointments`, `assessments`, `therapy_goals`, `goal_ratings`, `assignments`, `assignment_submissions`, `mood_logs`, and `patient_notes`.
- `patient_notes.is_shared` already distinguishes patient-visible notes from private clinician notes.
- Assignment files and submission files are private and served only through policy-checked download routes.
- No PDF renderer is installed.

### Exact scope

Add a clinician-only, synchronous, direct-download complete longitudinal PDF for one assigned patient. Include every active historical row in the selected clinical domains; do not impose arbitrary row limits or silently truncate sections. Keep the report-data mapping and template sectioned so clinic-approved content and formatting can be revised later without rewriting authorization/query logic. The generated PDF remains a read-only export, not an editable document.

Confirmed v1 contents:

| Section | Included data | Visibility label |
|---|---|---|
| Document header | Patient name/record id, generated timestamp in app timezone, generating clinician name/license/specialization | Administrative |
| Patient profile | DOB, gender, education, employment, contact/address/emergency contact, record-created date | Patient-provided profile |
| Presenting information | `personal_issues` and legacy intake `patients.notes` when present | Private clinical information |
| Care team | Currently assigned clinician names | Care-team information |
| Appointments | Requested/effective date, clinician, mode, status, patient booking reason; exclude meeting URL | Care history |
| Assessments | Instrument, assigned/completed dates, status, score/max, severity, and every item-level question/response using the definition labels | Clinical results |
| Goals | Description, status, target date, ratings and rating notes | Shared care planning |
| Assignments | Title, description, clinician, due date, submission status/date and text response; list safe original filename only, never embed file bytes | Shared care activity |
| Mood records | Date, score, patient note | Patient-provided progress data |
| Shared notes | Notes where `is_shared = true`, author and date | Explicitly shared with patient |
| Private notes | Notes where `is_shared = false`, author and date; appointment `clinic_notes` if used | Private clinical information |

Exclude passwords, tokens, notifications, activity logs, conversations/messages, device tokens, meeting links, avatar bytes, deleted records, worksheet/submission file bytes, internal storage paths, and unrelated clinician/patient data.

### Non-goals

- Patient self-service export.
- Admin export unless separately approved later.
- Editing, signing, emailing, scheduling, or bulk-generating records.
- Persisting generated PDFs or creating a report-history table.
- Merging uploaded assignment/submission files into the PDF.
- Claiming the PDF is a legally complete medical record without clinic/legal review.

### Affected application surfaces

| Surface | Change |
|---|---|
| Staff web | Add a download action on the patient profile and a protected export route. |
| Patient portal | None. |
| Patient API | None. |
| Flutter | None. |

### Expected files or components

- `composer.json`, `composer.lock`: add a Laravel-compatible Dompdf wrapper.
- `routes/web.php`: named clinician-only route, registered before the generic patient show route if its URI shape could conflict.
- `app/Http/Controllers/Web/PatientRecordExportController.php`: invokable orchestration and download response.
- `app/Services/PatientRecordPdfService.php`: authorize-independent query mapping/render input; no request/session access.
- `app/Policies/PatientPolicy.php`: add `exportRecord(User $user, Patient $patient)` requiring role `clinician`, a clinician profile, and `Patient::isAssignedTo()`.
- `resources/views/patients/record-pdf.blade.php`: print-safe A4 template with section boundaries, wrapping, repeated headers, and page breaks.
- `resources/views/patients/show.blade.php`: render the action only with `@can('exportRecord', $patient)`.
- `tests/Integration/PatientRecordPdfTest.php`: authorization, isolation, output, empty-state, audit, and formatting assertions.
- Optional `config/dompdf.php`: only if explicit hardening cannot be set per render without publishing the package configuration.

### Data-model or migration impact

No migration. Generate in memory and stream as an attachment. Do not store the PDF.

Recommended renderer: `barryvdh/laravel-dompdf` 3.x because it integrates Blade views directly and supports Laravel 11. Keep remote access disabled and avoid remote CSS/fonts/images. The package's official documentation states that remote access is disabled by default in 3.x; preserve that setting and use local, controlled assets only: [official Laravel Dompdf repository](https://github.com/barryvdh/laravel-dompdf).

### Authorization and privacy considerations

- Call `Gate::authorize('exportRecord', $patient)` before invoking the export service.
- Do not reuse `PatientPolicy::view()` as the sole check because it intentionally includes admins.
- The service must start from the already-authorized patient id and constrain every relation by that patient/foreign key.
- Keep private and shared notes in separate titled sections; `is_shared` means shared with the patient, not public.
- Default assumption: an assigned clinician can export all clinical notes currently visible on the patient profile, including notes authored by other assigned clinicians. This mirrors existing staff visibility.
- Escape all free-form content in Blade. Do not enable Dompdf remote resources or arbitrary local paths; Dompdf documents `chroot` and remote-resource controls specifically to limit file access: [official Dompdf project](https://github.com/dompdf/dompdf).
- Set attachment disposition, a conservative filename such as `patient-record-{patient-id}-{date}.pdf`, and `Cache-Control: no-store, private`.
- Log only `patient.record_exported`, actor, patient id, generation timestamp, and non-sensitive section counts. Never log content.
- Add `X-Robots-Tag: noindex, noarchive` defensively to the response.

### Implementation steps

1. Add the PDF package in an isolated dependency commit and verify package discovery on PHP 8.2/Laravel 11.
2. Add `PatientPolicy::exportRecord()` and policy tests before adding the route or button.
3. Add the clinician-only route and invokable controller.
4. Build `PatientRecordPdfService` to eager-load the recommended data graph with stable chronological ordering and selected columns.
5. Map Eloquent models into a plain view-data structure so the PDF template contains formatting only.
6. Split shared/private clinical sections in the mapped data, preserving missing-data states.
7. Create the A4 Blade template with simple Dompdf-supported CSS, safe text wrapping, page-break rules, and no remote assets.
8. Render synchronously, verify non-empty PDF bytes, record the audit event after successful rendering, and return the no-store attachment response.
9. Add the guarded patient-profile action.
10. Add focused integration/adversarial tests and a representative manual export with long Unicode-free/Unicode patient text, many rows, and empty sections.

### Synchronous versus queued recommendation

Use synchronous generation in v1. The interaction is an immediate download, the current records are relational/text-heavy rather than media-heavy, and synchronous generation avoids report-status UI, persistent sensitive files, retention cleanup, and dependence on a Railway queue worker. Use eager loading, selected columns, simple CSS, and memory/latency monitoring. Move to queued generation only if measured p95 generation exceeds about 5 seconds or realistic large records breach the deployed memory limit; that later design must add encrypted/private storage, expiry, ownership checks at download time, and cleanup.

### Edge cases

- Missing patient fields, no appointments, no assessments, or no notes render `Not recorded`/`None` rather than failing.
- Soft-deleted records remain excluded unless a separate legal-record requirement is approved.
- Very long notes wrap and can continue across pages; table rows must not force an entire large note onto one page.
- Unsupported characters/fonts must not produce a blank PDF; use a Unicode-capable local font only if representative Filipino names require it.
- Concurrent record changes may produce a point-in-time mix unless a read transaction is used. Prefer one short consistent read transaction if supported without holding locks during rendering; render after data is materialized.
- A clinician removed from the caseload between page render and button click receives 403.
- Failure to render must not write a success audit event or leave a partial file.
- The complete active history must be included even when a section spans many pages; no first-N query caps are permitted.

### Acceptance criteria

- An assigned clinician downloads a valid `application/pdf` attachment for the selected patient.
- An unassigned clinician, patient, guest, and admin cannot generate the PDF.
- The document includes patient name, generation date/time, and generating clinician identity.
- Shared and private notes are visibly separated and accurately labeled.
- Every active questionnaire response, mood note, assignment text submission, appointment reason, and listed longitudinal record is included without silent truncation.
- No unrelated patient's marker data appears in the bytes or rendered document.
- Missing sections render cleanly and long content remains readable.
- The successful export creates one `patient.record_exported` activity log without clinical content.
- No generated PDF is written to public or persistent storage.

### Automated and manual validation

Automated:

- Policy unit/integration matrix for assigned clinician, second assigned clinician, unassigned clinician, admin, patient, and guest.
- Response status, MIME type, disposition, no-store headers, `%PDF` signature, and non-empty body.
- Fixture patient plus unrelated patient with unique marker strings; assert only the authorized marker is rendered.
- Shared/private note placement, empty dataset, long text, filename sanitization, and audit-on-success/no-audit-on-failure.
- Query-count guard or explicit eager-loading assertion for a representative record.

Manual:

- Open the PDF in at least two readers and print preview A4.
- Inspect multi-page wrapping, section labels, dates, clinician identity, missing values, and page breaks.
- Verify browser history does not display a cached document after logout.
- Observe Railway memory and response time using a realistically large seeded record.

### Performance considerations

- Use one fixed relation graph and avoid model access from inside nested Blade loops that could trigger N+1 queries; row counts remain complete rather than capped.
- Do not load file contents, avatars, messages, notifications, or audit history.
- Materialize query data before rendering so database connections are not held throughout Dompdf layout.
- Add operational logging for duration and byte size without patient text.

### Dependencies

- Existing `PatientPolicy`, `Patient::isAssignedTo()`, `ActivityLogService`, clinical models/relations, and private response conventions.
- New Composer PDF package.
- No dependency on Features 2-7.

### Risk level

High: broad clinical-data aggregation, a new renderer dependency, and potentially large in-memory output.

### Recommended implementation batch

Batch 7, isolated from all other features.

## Feature 2: Accessible Button Tooltips

### Current implementation summary

- Both Blade layouts load Bootstrap 5.3.3 bundle, but neither initializes tooltips/popovers globally.
- Many icon-only controls already have `aria-label` or `title`, including appointment actions, notification actions, theme toggles, navigation toggles, profile crop controls, and chatbot send.
- Some icon-only controls have neither a Bootstrap tooltip annotation nor a complete accessible name.
- Flutter uses native `IconButton`/Material controls; some controls define `tooltip`, while others, such as the dashboard notification action, do not.

### Exact scope

Create one opt-in Bootstrap tooltip initializer and annotate only unclear icon-only, abbreviated, destructive, or uncommon controls. Preserve visible text for ordinary commands. Audit the staff layout, portal layout, appointment actions, notification actions, profile crop actions, availability controls, messaging send actions, and mobile-native icon buttons.

### Non-goals

- Adding tooltips to every text button, link, badge, form field, or navigation item.
- Replacing clear visible labels with icons.
- Putting interactive links/buttons inside tooltips.
- Introducing a separate tooltip library.
- Reworking the visual design of buttons.

### Affected application surfaces

| Surface | Change |
|---|---|
| Staff web | Shared initializer and selected annotations. |
| Patient portal | Shared initializer and selected annotations. |
| Patient API | None. |
| Flutter | Add Material `tooltip`/semantics only where an icon action lacks a clear accessible name. |

### Expected files or components

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/portal.blade.php`
- Recommended shared script partial: `resources/views/partials/bootstrap-overlays.blade.php`
- Selected views found by the implementation audit, initially:
  - `resources/views/appointments/index.blade.php`
  - `resources/views/notifications/index.blade.php`
  - `resources/views/portal/notifications/index.blade.php`
  - `resources/views/portal/appointments/index.blade.php`
  - `resources/views/portal/profile/show.blade.php`
  - `resources/views/messages/show.blade.php`
  - `resources/views/portal/messages/index.blade.php`
  - `resources/views/partials/navbar.blade.php`
  - `resources/views/portal/partials/navbar.blade.php`
- Flutter icon-button files discovered by `rg "IconButton"`, including `dashboard_screen.dart` and `chatbot_screen.dart`.
- Focused Blade render tests in an existing relevant integration test or `tests/Integration/AccessibleControlsTest.php`.

### Data-model or migration impact

None.

### Authorization and privacy considerations

- Tooltip text must describe the action only; never put patient names, booking reasons, clinical notes, or hidden state in generic tooltips.
- Preserve `aria-label` even when `data-bs-title` is added.
- Do not expose controls a user cannot execute; continue using policy/role-based rendering.
- Do not use raw HTML tooltip content from model/request data.

### Implementation steps

1. Inventory icon-only and uncommon controls on both Blade surfaces and Flutter; classify each as needs tooltip, already clear, or should gain visible text instead.
2. Add an idempotent initializer for `[data-bs-toggle="tooltip"]` after Bootstrap loads in both layouts. Use `trigger: 'hover focus'`, an appropriate container, and no HTML.
3. Add `data-bs-toggle="tooltip"`, `data-bs-title`, and any missing `aria-label` to selected controls.
4. For disabled controls that need an explanation, place the trigger on a focusable wrapper because disabled elements cannot receive hover/focus events. This follows Bootstrap's documented limitation: [Bootstrap 5.3 tooltip documentation](https://getbootstrap.com/docs/5.3/components/tooltips/).
5. Ensure modal/open-panel teardown disposes overlays where necessary so stale tooltip nodes do not remain above dialogs.
6. Add native Flutter `tooltip` values and localization keys to unclear `IconButton`s; do not mimic Bootstrap in Flutter.
7. Add markup tests and perform keyboard, mouse, screen-reader, touch, responsive, and dark-mode checks.

### Edge cases

- A tooltip must not remain visible after its source modal closes or the page navigates.
- A disabled source cannot be the focus target.
- Long tooltip copy should be shortened rather than widened over content.
- Touch users may not hover; action controls still need understandable icons, accessible names, and visible labels where ambiguity would block use.
- Dynamically inserted controls need explicit initialization if future code adds them; current realtime code mainly refreshes/reloads and does not inject action buttons.

### Acceptance criteria

- Every selected icon-only/uncommon control has a stable accessible name.
- Bootstrap tooltips appear on mouse hover and keyboard focus, and disappear on blur.
- Existing visible labels and `aria-label`s are preserved.
- Obvious text buttons do not receive redundant tooltips.
- Tooltips do not block the action, overflow narrow screens, or appear above an active modal incorrectly.
- Flutter icon actions expose native tooltips/semantics where needed.

### Automated and manual validation

Automated:

- Blade response assertions for `aria-label`, `data-bs-toggle`, and non-duplication on representative controls.
- Guest/role render tests continue proving protected controls are absent.
- Flutter widget tests verify `Tooltip`/semantics for changed icon actions.

Manual:

- Tab through controls without a mouse and confirm focus tooltips.
- Hover with a mouse, use touch emulation, switch themes, open/close modals, and test 320px, 768px, and desktop widths.
- Check accessible names with a browser accessibility tree.

### Performance considerations

- Initialize only opted-in elements once; do not observe or scan the full DOM continuously.
- Keep copy short and avoid HTML templates.

### Dependencies

- Existing Bootstrap bundle and shared layouts.
- Feature 6 depends on the shared overlay initialization convention.

### Risk level

Low, with moderate interaction-testing needs.

### Recommended implementation batch

Batch 1.

## Feature 3: Floating Joy Chatbot Bubble

### Current implementation summary

- Portal Joy page: `PortalChatbotController`, `portal/chatbot/index.blade.php`, `GET|POST /portal/chatbot`.
- Portal UI uses one Alpine `chatSession`, in-memory messages, a 15-second request timeout, CSRF, JSON responses, and a non-JS redirect fallback.
- Backend logic is centralized in `ChatbotService`, with Gemini fallback to the database/rule matcher.
- Flutter has `ChatbotScreen`, `ChatbotNotifier`, `ChatbotApi`, and a fifth `HomeShell` navigation destination.
- There is no conversation persistence, server-side chat history, chatbot unread state, or active-state event.

### Exact scope

Replace Joy's persistent navigation placement with a lower-right floating launcher that opens a compact panel. Reuse the existing portal controller/service and Flutter provider/API. Keep the existing route as an accessible deep-link/full-page fallback, but render the same shared chat component rather than a second implementation.

Portal behavior:

- 56px fixed launcher, lower right, safe-area aware.
- Compact desktop panel; near-full-width bottom sheet style on narrow screens.
- Toggle, explicit close, click-outside close where safe, and Escape close.
- Move focus to the input on open and return focus to the launcher on close.
- Preserve transcript while the current page remains mounted; no new persistence in v1.
- Show sending/typing state only. Do not invent unread counts.

Flutter behavior:

- Remove Joy as a fifth bottom-navigation destination.
- Add an accessible floating launcher above the navigation bar.
- Open a modal bottom sheet/panel backed by the existing `chatbotProvider`.
- Extract reusable chat content from `ChatbotScreen`; keep `/chatbot` as a full-screen route/deep-link using the same content widget.

### Non-goals

- New chatbot backend, duplicated transcript store, websocket chat, unread database state, proactive messages, file attachments, clinician messaging, or cross-device transcript history.
- Changing intent matching, Gemini prompts, throttles, response schema, or fallback copy beyond layout needs.
- Displaying Joy to staff/admin users.

### Affected application surfaces

| Surface | Change |
|---|---|
| Staff web | None. |
| Patient portal | Portal layout, navigation, reusable widget, existing Joy page. |
| Patient API | No endpoint/schema change. |
| Flutter | Shell/router and reusable presentation only; existing provider/API unchanged. |

### Expected files or components

Portal:

- `resources/views/layouts/portal.blade.php`
- `resources/views/portal/partials/nav.blade.php`
- New `resources/views/portal/partials/joy-widget.blade.php` or a Blade component
- `resources/views/portal/chatbot/index.blade.php`
- `public/css/theraconnect.css`
- `tests/Integration/ChatbotFlowTest.php`
- Optional `tests/Integration/PortalAccessTest.php` navigation assertions

Flutter:

- `theraconnect_flutter/lib/screens/shell/home_shell.dart`
- `theraconnect_flutter/lib/router.dart`
- `theraconnect_flutter/lib/screens/chatbot/chatbot_screen.dart`
- New reusable `theraconnect_flutter/lib/widgets/joy_chat_panel.dart`
- `theraconnect_flutter/lib/l10n/app_en.arb` and generated localization files
- New Flutter widget tests for launcher/panel/focus/send state

No changes expected in `ChatbotService`, portal/API chatbot controllers, `ChatbotApi`, or `ChatbotNotifier` unless extraction reveals a presentation-only interface need.

### Data-model or migration impact

None. No unread state is added because existing behavior cannot support it truthfully.

### Authorization and privacy considerations

- Include the launcher only inside the authenticated patient portal layout; API calls retain `auth:sanctum`/patient role or portal session/role middleware.
- Keep CSRF, 1,000-character validation, timeout, and 30/minute throttle.
- Do not persist transcript in `localStorage`/`sessionStorage` without a separate privacy decision; current in-memory behavior is safer on shared devices.
- Do not label typing state as unread activity.
- Keep chatbot guidance clearly separate from clinician messaging and clinical diagnosis.

### Implementation steps

1. Extract current chat list/input/submit behavior into one reusable portal component without changing the endpoint contract.
2. Add launcher/panel state at the portal-layout level and remove the Joy sidebar item.
3. Implement focus return, `x-trap`, Escape, accessible dialog name/description, loading announcement, and close control.
4. Add responsive CSS, safe-area offsets, constrained dimensions, message wrapping, and z-index rules below Bootstrap modals.
5. Hide or disable the launcher while a Bootstrap modal is open; add page bottom padding so the closed bubble does not cover the last form action.
6. Make `/portal/chatbot` render the same component in expanded mode or redirect to a stable open-widget state with a non-JS fallback.
7. Add portal interaction/markup tests and manual responsive validation.
8. For Flutter, extract `ChatbotScreen` body into one reusable panel widget, add the shell launcher/bottom sheet, remove the fifth navigation destination/branch, and retain a full-screen `/chatbot` route using that widget.
9. Add Flutter widget tests for open/close, semantics, focus, send disabled/loading state, provider transcript reuse, and small-screen keyboard insets.

### Edge cases

- Software keyboard reduces viewport height; panel must remain usable without hiding the input.
- Long responses and long unbroken words must wrap without widening the panel.
- Repeated clicks while a request is pending must not send duplicates.
- Route changes currently recreate portal pages; transcript reset is accepted v1 behavior unless persistence is separately requested.
- Bootstrap modals and mobile sidebar overlays must visually cover Joy, not sit below it.
- `prefers-reduced-motion` should remove nonessential launcher/panel animation.
- The existing non-JS form fallback remains reachable.

### Acceptance criteria

- Authenticated patients see one Joy launcher and no duplicate persistent Joy navigation item.
- Activating it opens/closes one compact chat panel using the existing endpoint/provider.
- Existing chatbot answers, fallback handling, throttle, timeout, and typing state still work.
- No unread badge appears without a real unread source.
- Keyboard users can open, operate, close, and return focus predictably.
- The widget does not cover active Bootstrap modals, the mobile sidebar, or final-page actions.
- Portal and Flutter layouts remain usable on narrow and desktop/tablet screens.

### Automated and manual validation

Automated:

- Existing `ChatbotFlowTest` API/service tests remain green.
- Portal response contains one launcher, one panel, correct endpoint, accessible labels, and no duplicate sidebar Joy link.
- Empty/invalid/failed message behavior remains covered.
- Flutter widget tests cover panel state, provider reuse, loading lockout, Escape/back dismissal, and semantics.

Manual:

- Test portal at 320px, 768px, and desktop; open sidebar/modal; tab through; press Escape; use touch emulation; switch themes.
- Test Flutter on small Android screen, iOS safe area, software keyboard, back button, rotation, and screen reader.
- Confirm network failure gives the existing safe message and leaves the panel usable.

### Performance considerations

- Do not mount duplicate chat histories or create a provider per open/close.
- Load the small Joy asset once and avoid animation loops.
- Keep panel hidden with lightweight state; no polling or background API call when closed.

### Dependencies

- Existing Joy controller/service/API/provider and Alpine Focus plugin.
- Feature 4 touches the same navigation/router files and should land first.

### Risk level

Medium: global portal layout and Flutter shell/router interaction.

### Recommended implementation batch

Batches 6A and 6B.

## Feature 4: Quick User Guide

### Current implementation summary

- No guide route, content source, view, API endpoint, or Flutter screen exists.
- Portal and staff each have a sidebar; Flutter uses a bottom shell plus profile/secondary routes.
- Relevant destinations already exist for appointments, assignments, messages, patients, and clinician-request workflows.
- Clinician assignment is now many-to-many and becomes established when a clinician approves an appointment/request; booking alone must not be described as permanent assignment.

### Exact scope

Create a local, code-defined, role-aware guide with concise numbered sections and links/action keys to existing screens.

Patient sections:

1. choose a clinician and book an available appointment;
2. understand pending/approved/rescheduled/cancelled states and manage appointments;
3. find, complete, attach, and submit assignments, then see review state;
4. understand that approved care relationships can include multiple clinicians;
5. if no clinician is assigned, book with an available clinician or contact the clinic/privacy contact only where appropriate.

Clinician sections:

1. review and approve appointment requests;
2. understand that approval adds the patient to the caseload without replacing other clinicians;
3. create assignments and review submissions;
4. find assigned patients and open their messaging threads.

Recommended content architecture: `App\Support\UserGuide` returns versioned, role-keyed sections with stable action keys. Blade maps action keys to named web routes. A patient-only API endpoint returns the same safe content for Flutter, whose router maps those action keys to app routes. This keeps one copy source without adding a CMS or database table.

### Non-goals

- CMS/admin editor, analytics, searchable knowledge base, onboarding tour, contextual coach marks, videos, legal/clinical policy manual, or public unauthenticated help center.
- Documenting every administrator feature.
- Duplicating instructions on each workflow page.
- Treating Joy's dynamic answers as the guide source of truth.

### Affected application surfaces

| Surface | Change |
|---|---|
| Staff web | Clinician guide route/view and Help navigation item. |
| Patient portal | Patient guide route/view and Help navigation item. |
| Patient API | Read-only authenticated guide endpoint for Flutter. |
| Flutter | Secondary guide route/screen and a discoverable Help action. |

### Expected files or components

- New `app/Support/UserGuide.php`
- New `app/Http/Controllers/Web/UserGuideController.php` or thin role-specific controllers
- New `app/Http/Controllers/Api/V1/UserGuideController.php`
- `routes/web.php`, `routes/api.php`
- New `resources/views/guide/show.blade.php` with role-aware sections
- `resources/views/partials/sidebar.blade.php`
- `resources/views/portal/partials/nav.blade.php`
- Optional reusable `resources/views/guide/_section.blade.php`
- `theraconnect_flutter/lib/models/user_guide.dart`
- `theraconnect_flutter/lib/services/api/user_guide_api.dart`
- `theraconnect_flutter/lib/providers/user_guide_provider.dart`
- `theraconnect_flutter/lib/screens/guide/user_guide_screen.dart`
- `theraconnect_flutter/lib/router.dart`
- A discoverability point in `dashboard_screen.dart` or `profile_screen.dart`
- Localization files for native labels
- `tests/Integration/UserGuideTest.php` plus Flutter model/widget tests

### Data-model or migration impact

None. Content stays in a support definition under source control.

### Authorization and privacy considerations

- Browser routes require authentication and role `patient` or `clinician`; do not show patient-only details to clinicians or clinician operations to patients.
- Patient API endpoint remains inside `auth:sanctum`, `role:patient`, and `throttle:api`.
- Guide content must contain no patient-specific data, clinician-specific caseload data, internal IDs, protected file URLs, or promises about clinical outcomes.
- Use stable action keys rather than accepting arbitrary destination URLs from the API.
- Clarify that urgent/emergency concerns should use appropriate local emergency resources; do not position Joy or ordinary messages as emergency channels. Final crisis wording should receive clinic review.

### Implementation steps

1. Draft and clinically/product-review the patient and clinician copy against actual current workflows.
2. Add `UserGuide` with stable section/action schemas and a content version constant.
3. Add authenticated browser controller/routes and one responsive view that selects only the current role's sections.
4. Add Help/User Guide links near the bottom of each applicable sidebar, outside primary care workflows.
5. Verify every guide CTA uses an existing named route and handles prerequisites, especially no assigned clinician and multiple clinicians.
6. Expose the patient sections through one read-only API response and add the Flutter model/API/provider/screen adapters.
7. Add a Flutter Help action in a secondary location such as profile or dashboard app bar, not a crowded bottom-navigation destination.
8. Add role/content/link/schema tests and responsive/manual copy review.

### Edge cases

- Patient has no clinician, one clinician, or multiple clinicians.
- Patient has no appointments or assignments yet.
- Assignment has no worksheet, requires text only, file only, or has already been submitted/reviewed.
- A deep link targets a workflow whose prerequisite no longer exists; the target screen remains responsible for its normal empty state.
- API guide content version changes while an older Flutter build is installed; stable action keys must remain backward compatible or unknown keys must render without a CTA.
- Offline Flutter users should receive a retry state; optional caching can follow the existing non-secret `CacheService` pattern.

### Acceptance criteria

- Patients and clinicians can find the guide in no more than one navigation action from their normal shell/sidebar.
- Each role sees only relevant sections.
- The patient guide accurately explains booking, appointment management, assignments, clinician assignment, and the no-clinician path.
- Guide links open existing screens and do not bypass normal authorization/prerequisite checks.
- Content is defined once on the backend and not duplicated across multiple Blade pages.
- Mobile and desktop layouts remain readable with large text and long section titles.

### Automated and manual validation

Automated:

- Guest rejection and patient/clinician success.
- Admin receives no guide route/content or navigation item in v1.
- Role-specific marker text is absent for the other role.
- Every configured web action key resolves to a named route.
- API auth/schema/version/action-key tests.
- Flutter JSON parsing, unknown action-key fallback, loading/error, and CTA navigation widget tests.

Manual:

- Follow each guide step using an empty patient, patient with one clinician, patient with multiple clinicians, and clinician account.
- Review copy with clinic operations/clinical owner.
- Test small screens, text zoom, dark mode, and browser/app back navigation.

### Performance considerations

- Static arrays are negligible; optionally cache the serialized API response by guide version.
- Do not add database queries to shared navigation just to show the guide link.

### Dependencies

- Existing named routes and current many-to-many clinician assignment behavior.
- Should land before Feature 3 because both edit portal navigation and Flutter router/shell.

### Risk level

Low for browser-only; medium when adding the API/Flutter adapter because it creates a maintained cross-platform content contract.

### Recommended implementation batch

Batches 5A and 5B.

## Feature 5: Questionnaire Explanations

### Current implementation summary

- `App\Support\Assessments::INSTRUMENTS` contains PHQ-9/GAD-7 names, prompts, max scores, and fixed items.
- Portal index and detail pages render assignment state and the instrument prompt.
- API detail merges prompt/options/items into `AssessmentResource`; the list resource includes title/name/status/score/max/severity.
- Flutter `Assessment`/`AssessmentDetail` models and assessment list/fill screens mirror that response.
- Current copy explains the two-week answer period but not purpose, clinician use, or diagnostic limits.

### Exact scope

Add structured, instrument-specific explanatory metadata to the central definitions and render it before a patient starts/submits on portal and Flutter. Keep list cards concise and detail screens complete.

Recommended copy intent:

- PHQ-9: screens the frequency of depressive symptoms over the last two weeks; helps the clinician discuss concerns, plan care, and track changes; does not independently diagnose depression.
- GAD-7: screens the frequency of anxiety symptoms over the last two weeks; helps the clinician discuss concerns, plan care, and track changes; does not independently diagnose an anxiety disorder.

Store this as structured fields such as `purpose`, `clinician_use`, and `disclaimer`, not a hard-coded paragraph in each UI.

### Non-goals

- Changing questions, answer labels, scoring, severity bands, completion rules, assignment flow, or stored responses.
- Adding a diagnosis, treatment recommendation, automated triage, or new crisis workflow.
- Adding new instruments.
- Replacing clinician review or informed clinical judgment.

### Affected application surfaces

| Surface | Change |
|---|---|
| Staff web | Optional display in assessment assignment/progress UI only if useful; no workflow change. |
| Patient portal | Explanation on assessment list/detail, before form/submit. |
| Patient API | Add explanation metadata to list/detail resource contract. |
| Flutter | Parse and render the same explanation before questions/submission. |

### Expected files or components

- `app/Support/Assessments.php`
- `app/Http/Resources/AssessmentResource.php`
- `app/Http/Controllers/Api/V1/AssessmentController.php` only if detail assembly needs adjustment
- `resources/views/portal/assessments/index.blade.php`
- `resources/views/portal/assessments/show.blade.php`
- Optional `resources/views/patients/progress.blade.php` for clinician context
- `theraconnect_flutter/lib/models/assessment.dart`
- `theraconnect_flutter/lib/screens/progress/assessments_screen.dart`
- `theraconnect_flutter/lib/screens/progress/assessment_fill_screen.dart`
- Flutter localization files
- `tests/Integration/AssessmentTest.php`
- New Flutter model/widget tests

### Data-model or migration impact

None. Definitions remain code-based; assessment rows and scoring are unchanged.

### Authorization and privacy considerations

- Explanations contain no patient data and may be returned only through the existing authenticated assessment routes.
- Continue using `AssessmentPolicy` for detail/completion isolation.
- Avoid absolute clinical claims. Use `may help`/`screening tool` and explicitly state that the result is not a diagnosis by itself.
- The PHQ-9 includes a self-harm item. Do not imply that the informational disclaimer is a safety response; existing clinical escalation behavior is outside this slice and should be separately reviewed.

### Implementation steps

1. Add typed/documented explanation fields to each `Assessments::INSTRUMENTS` definition.
2. Add a helper that returns a stable explanation structure and gracefully handles unsupported keys.
3. Include that structure in `AssessmentResource`; keep existing response fields unchanged for backward compatibility.
4. Render a concise purpose on list cards and the full explanation in a semantic callout before the portal questionnaire form. Keep it visible on completed detail where appropriate.
5. Update Flutter models with nullable fields first so older/cached payloads remain parseable.
6. Add localized native copy labels and render the explanation before question cards and the submit control.
7. Add API/portal assertions and Flutter parsing/widget tests; rerun all existing scoring and isolation tests.

### Edge cases

- Older cached Flutter assessment JSON lacks explanation fields.
- Unknown/retired instrument definition must not cause null-index errors.
- Completed assessments should not misleadingly invite the patient to begin again.
- Large text/accessibility scaling must not push submit controls over content.
- Copy changes must not alter `prompt`, `items`, `OPTIONS`, `score()`, or `severity()`.

### Acceptance criteria

- PHQ-9 and GAD-7 show distinct, accurate purpose text before completion.
- Both explain clinician use for discussion/care planning/progress and that the score is not a standalone diagnosis.
- Portal and Flutter use the central API/definition content rather than divergent clinical claims.
- Existing questionnaire submissions and all score/severity results remain byte-for-byte behaviorally equivalent.
- Another patient's assessment remains inaccessible.

### Automated and manual validation

Automated:

- Definition helper returns all required fields for both instruments.
- API list/detail schema includes explanations without removing existing keys.
- Portal detail contains the correct instrument-specific text before the form.
- Existing PHQ-9/GAD-7 scoring, invalid-length/value, duplicate-submit, and IDOR tests remain green.
- Flutter model handles present/missing explanation and widgets place it before questions/submit.

Manual:

- Clinical/product review of wording.
- Portal/Flutter review on pending and completed states, small screens, dark mode, and large text.
- Confirm score, severity, response selection, validation, and submit confirmation are unchanged.

### Performance considerations

Negligible static response growth. Keep explanation strings concise and cache-safe.

### Dependencies

- Existing `Assessments`, resource, portal controller/views, API, and Flutter assessment model/provider.
- Independent of other features.

### Risk level

Medium because the copy concerns mental-health screening, despite low technical risk.

### Recommended implementation batch

Batch 3.

## Feature 6: Appointment Reason Visible to Clinicians

### Current implementation summary

- `appointments.reason` already exists as nullable `varchar(500)` and is cast as `encrypted` by `Appointment`.
- Patient portal detail already displays the patient's own reason.
- `AppointmentResource` already returns the reason to the owning patient API.
- Staff appointment index eager-loads patient/clinician, scopes clinicians to `clinician_id`, allows admins all appointments, and does not render the reason.
- `AppointmentPolicy::manage()` allows the assigned clinician and any admin; `view()` is too broad because it allows any clinician.

### Exact scope

Add a compact Reason column or adjacent info action to the staff appointment table. For rows the current user may inspect, an info button opens a plain-text Bootstrap popover containing the complete reason. Render an em dash or `No reason provided` accessible state when null/blank. Keep the existing encrypted field and 500-character validation.

Authorization must use a dedicated `viewReason` policy ability rather than the broad `view` ability. It must require role `clinician`, a clinician profile, and `appointment.clinician_id === user.clinician.id`. Administrators must not receive or render the reason.

### Non-goals

- New reason column/migration, reason editing, reason search, reason in notifications/email/realtime, appointment detail redesign, or exposing reason to unrelated clinicians.
- Rendering reason as HTML or placing it in a generic tooltip title.

### Affected application surfaces

| Surface | Change |
|---|---|
| Staff web | Appointment table and policy ability. |
| Patient portal | None; already displays owned reason. |
| Patient API | None; already returns owned reason. |
| Flutter | None; patient detail already receives owned reason. |

### Expected files or components

- `app/Policies/AppointmentPolicy.php`
- `resources/views/appointments/index.blade.php`
- Shared Bootstrap overlay initializer from Feature 2
- `tests/Integration/AppointmentIndexTest.php`
- `tests/Adversarial/IdorBypassTest.php` or focused `AppointmentReasonVisibilityTest.php`

A separate reason endpoint/controller is not recommended for a 500-character field already present on an authorized server-rendered row. If product later requires audit-on-open rather than audit-on-page-view, add a dedicated policy-checked endpoint instead of embedding the value.

### Data-model or migration impact

None.

### Authorization and privacy considerations

- Use `@can('viewReason', $appointment)` around the reason control/content.
- Do not use `AppointmentPolicy::view()`; it currently permits any clinician.
- Keep Bootstrap `html: false`; Blade escaping plus text-only popover prevents patient-authored markup execution.
- Do not add the reason to realtime events, notifications, logs, `aria-label`, or `title`; the accessible label should say `View booking reason` without repeating content.
- The clinician index query remains constrained to that clinician's appointment rows. Test with unique marker reasons across clinicians.
- If reasons become longer later, continue enforcing a server maximum and use a scrollable modal rather than truncating clinical text.

### Implementation steps

1. Add `AppointmentPolicy::viewReason()` with explicit assigned-clinician ownership and an explicit denial for administrators.
2. Extend the shared Bootstrap overlay partial to initialize opt-in popovers idempotently, with click/focus behavior, HTML disabled, and safe placement/container.
3. Add a Reason header and icon button for nonblank authorized reasons; add an accessible empty state for missing reasons.
4. Store the escaped complete text in `data-bs-content`; use a button so mouse, keyboard, and touch all activate the same control.
5. Ensure only one popover remains open and Escape/outside click can dismiss it without interfering with row forms/modals.
6. Add authorization/data-isolation, missing, long, special-character/XSS, and responsive markup tests.

### Edge cases

- Null, empty, and whitespace-only reason.
- Maximum-length reason, newlines, quotes, `<script>` text, and long unbroken words.
- Appointment whose clinician was removed/deleted or user lacks a clinician profile.
- Multiple popovers opened in sequence.
- Popover present when reschedule/conclude modal opens; dispose/hide it first.
- Realtime appointment refresh while a popover is open.

### Acceptance criteria

- The assigned clinician can activate an information icon and read the complete plain-text reason.
- An unrelated clinician never receives another appointment/reason in the rendered response.
- Missing reasons render a graceful noninteractive state.
- Keyboard and touch activation work; the control has an accessible name and dismiss behavior.
- Special characters render as text, not HTML.
- No migration or duplicate field is added.

### Automated and manual validation

Automated:

- Assigned clinician succeeds; unassigned clinician and administrator are denied/receive no rendered reason.
- Unique marker reason for another clinician is absent from HTML.
- Null reason, 500-character reason, newline, and script-like input assertions.
- Existing status/mode/sort/pagination tests remain green.

Manual:

- Mouse click, keyboard tab/Enter/Space/Escape, touch emulation, dark mode, narrow table, modal interaction.
- Confirm full text wraps and can be dismissed without triggering approve/reject/reschedule actions.

### Performance considerations

- At most one 500-character value per rendered row; current pagination is 10.
- Avoid an extra query per row. Access the existing model attribute only inside authorized rendering.

### Dependencies

- Feature 2 shared overlay initializer.
- Existing encrypted cast and `AppointmentPolicy`.

### Risk level

Medium because it exposes sensitive patient-authored text on a broader staff page.

### Recommended implementation batch

Batch 2.

## Feature 7: Patient Appointment Sorting

### Current implementation summary

- Staff web validates `status`, `mode`, `sort=requested_at`, and `direction`, scopes clinicians, orders with an id tie-breaker, paginates 10, and preserves query strings.
- Portal patient index scopes by `patient_id`, orders by `requested_at desc`, paginates 15, and has no controls.
- Patient API scopes by the authenticated patient's id, orders by `requested_at desc`, paginates 20, and accepts no sort/filter query.
- Flutter loads only one API page into `AppointmentNotifier`; it currently discards pagination metadata and has no sorting/filter state or load-more behavior.
- The displayed appointment date is `scheduled_at ?? requested_at`, so sorting only by `requested_at` can disagree with what users see after rescheduling.

### Exact scope

Add patient-owned filters and ordering on both patient clients:

- Sort date: newest first and oldest first, using the displayed/effective date `COALESCE(scheduled_at, requested_at)`.
- Status: all, pending, approved, rescheduled, completed, rejected, cancelled, and no-show.
- Mode: all, online, in-person, for parity with staff controls.
- Default: effective date newest first, preserving the current newest-first intent.
- Stable tie-breaker: appointment id in the same direction.
- Portal pagination remains 15 unless product asks to standardize it; preserve all active query parameters.
- API pagination remains 20 and returns current/last/total metadata.
- Flutter implements server-backed filter/sort state and correct pagination/load-more rather than filtering only the first page.

### Non-goals

- Changing appointment states, booking, cancellation, staff defaults, dashboard upcoming logic, database schema, full-text search, arbitrary date ranges, or cross-patient access.
- Returning every appointment in one unpaginated API response.

### Affected application surfaces

| Surface | Change |
|---|---|
| Staff web | None except shared query helper only if reuse is justified and tested. |
| Patient portal | Controller validation/query and responsive controls. |
| Patient API | Query parameters and stable pagination metadata. |
| Flutter | API adapter, provider state/cache, list controls, load-more/pagination. |

### Expected files or components

- `app/Http/Controllers/Portal/PortalAppointmentController.php`
- `app/Http/Controllers/Api/V1/AppointmentController.php`
- Optional small shared query object/scope such as `app/Support/AppointmentListFilters.php` only if it removes real duplicate allowlist/order logic across portal/API; do not abstract the staff query prematurely.
- `resources/views/portal/appointments/index.blade.php`
- `tests/Integration/PortalAppointmentIndexTest.php` or additions to `PortalFeatureTest.php`
- `tests/Integration/AppointmentFlowTest.php` for API query contract/isolation
- `theraconnect_flutter/lib/services/api/appointment_api.dart`
- `theraconnect_flutter/lib/providers/appointment_provider.dart`
- `theraconnect_flutter/lib/screens/appointments/appointment_list_screen.dart`
- Optional new `theraconnect_flutter/lib/models/appointment_list_state.dart`
- `theraconnect_flutter/lib/l10n/app_en.arb` and generated localization files
- New Flutter API/provider/widget tests

### Data-model or migration impact

No required migration. Before implementation, inspect production query plans. If realistic appointment volume shows a regression, consider a follow-up composite index on `(patient_id, status, requested_at)`; do not add an index speculatively because effective-date `COALESCE` may not use it.

### Authorization and privacy considerations

- Always begin from `where('patient_id', $request->user()->patient->id)` before applying filters/order.
- Do not accept `patient_id`, clinician scope, raw column name, SQL expression, or arbitrary direction from the request.
- Validate/allowlist every status/mode/sort/direction. Invalid values should produce normal 422 JSON or validation redirect, not 500.
- API pagination URLs/metadata must not leak another patient's totals.
- Flutter cache keys must include filter/sort state or cache only the default list; never show one filter's cached rows under another label.

### Implementation steps

1. Define the exact allowlists and one safe effective-date order expression compatible with MySQL production and SQLite tests.
2. Update portal `index(Request)` validation, patient-owned base query, status/mode filters, stable ordering, `paginate(15)->withQueryString()`.
3. Add portal controls that reuse staff filter styling but use a compact select/menu where pills would overflow. Show current ordering and a clear reset.
4. Add portal tests for every ordering direction, representative statuses/modes, query-string pagination, invalid values, and another-patient marker isolation.
5. Update API `index(Request)` with the same contract and retain existing response keys/meta.
6. Add API tests for filtering, effective-date ordering after reschedule, pagination, invalid input, and isolation.
7. Introduce Flutter appointment-list state containing rows, selected sort/status/mode, page, lastPage, total, loadingMore, and error.
8. Update `AppointmentApi.getAppointments()` to send only active allowlisted values and parse pagination metadata.
9. Update the notifier to reset on filter change, append unique rows on load-more, invalidate/refetch after create/cancel/realtime updates, and prevent concurrent page loads.
10. Update the Flutter list with a sort menu, filter controls, active-state summary/reset, loading-more indicator/retry, and empty state that distinguishes `no appointments` from `no matches`.
11. Add Flutter model/provider/widget tests and verify current dashboard consumers still derive counts safely from the default list or move counts to a dedicated source if pagination makes them incomplete.

### Edge cases

- Rescheduled appointment where `scheduled_at` and `requested_at` have different order.
- Identical effective timestamps require deterministic id order.
- Invalid/tampered status, mode, sort, or direction.
- Filter change while load-more is running.
- Realtime invalidation while a non-default filter is active.
- Create/cancel then return to a filtered list where the changed item no longer belongs.
- Last page, empty page after deletion, duplicate append, offline cached default list, and API error during load-more.
- Dashboard counts currently use the loaded Flutter appointment list; paginated/filter-specific state must not silently make those counts misleading.

### Acceptance criteria

- Portal and Flutter patients can choose newest/oldest effective date and filter by status; mode filtering is also available.
- Default ordering is clearly newest effective date first.
- Rescheduled appointments sort by the date shown to the patient.
- Pagination/load-more retains active controls and does not duplicate/drop rows.
- Only the authenticated patient's appointments and totals are returned/rendered.
- Invalid query values are handled as validation errors.
- Existing booking, detail, cancellation, realtime refresh, and dashboard behavior remains correct.

### Automated and manual validation

Automated:

- Portal: ascending/descending effective date, status/mode filter, combined filter, tie-break, 15-per-page, page-2 query preservation, invalid input, isolation.
- API: same behavior at 20-per-page plus response metadata and another-patient total isolation.
- Regression: existing staff `AppointmentIndexTest`, booking, cancellation, reschedule, timezone serialization, and realtime tests.
- Flutter: API query serialization, state reset/append/dedup/concurrency, cache separation, empty/error/load-more widgets, filter semantics, and realtime invalidation.

Manual:

- Portal at narrow/desktop widths with combined filters and pagination.
- Flutter with 25+ appointments, slow/offline network, filter changes, pull-to-refresh, create/cancel, and realtime update.
- Verify displayed dates and order around rescheduled rows and Manila-time boundaries.

### Performance considerations

- Keep patient id as the first selectivity boundary and paginate on the server.
- Request only one page in Flutter and append on demand.
- Debouncing is unnecessary for fixed option controls; prevent duplicate in-flight loads.
- Inspect `EXPLAIN` against a production-like dataset before adding an index.

### Dependencies

- Existing appointment controllers/resource/model, staff filter CSS convention, Flutter appointment provider/API/cache, and realtime invalidation.
- Independent of other features, but API and Flutter must land together.

### Risk level

Medium: cross-database ordering semantics and a necessary Flutter pagination-state correction.

### Recommended implementation batch

Batches 4A and 4B.

## 4. Cross-Feature Testing Strategy

### Baseline commands

Run before implementation and after each relevant batch:

```powershell
php artisan test --testsuite=Integration
php artisan test --testsuite=Adversarial
vendor\bin\pint --test
npm run build
```

For Flutter-bearing batches:

```powershell
flutter analyze
flutter test
flutter build apk --debug
```

Create Flutter tests before relying on `flutter test`; the repository currently has no `theraconnect_flutter/test` directory.

### Focused regression matrix

| Area | Existing suites to retain |
|---|---|
| Patient/caseload isolation | `PolicyTest`, `ClinicianScopingTest`, `ManyToManyClinicianRelationshipTest`, `IdorBypassTest` |
| Appointments | `AppointmentIndexTest`, `AppointmentFlowTest`, `AppointmentRescheduleTest`, `PortalFeatureTest`, `RealtimeUpdatesTest`, `TimezoneSerializationTest` |
| Assessments | `AssessmentTest`, `InformationLeakageTest` |
| Joy | `ChatbotFlowTest`, `WebChatbotContentTest`, throttle tests |
| Audit | `ActivityLogTest` plus new PDF audit cases |
| Browser shell/auth | `PortalAccessTest`, `UxFrictionTest` |

### Manual accessibility/responsive matrix

- Chrome/Edge desktop with mouse and keyboard only.
- Browser responsive widths: 320px, 375px, 768px, 1280px.
- Touch emulation and a physical mobile browser where possible.
- Light/dark themes and 200% browser text zoom.
- Flutter small Android/iOS layouts, software keyboard, rotation, TalkBack/VoiceOver semantics.
- Modal/sidebar/chat-panel combinations to catch z-index and focus collisions.

## 5. Security and Privacy Review Checklist

- [ ] Every sensitive route has role middleware plus a model/caseload policy.
- [ ] PDF export uses clinician-only `exportRecord`, not admin-inclusive `view`.
- [ ] Appointment reason uses clinician ownership, not broad `AppointmentPolicy::view`.
- [ ] Patient list queries begin with authenticated patient ownership.
- [ ] No clinical free text enters logs, filenames, tooltip titles, realtime events, emails, or public storage.
- [ ] Patient-authored popover/PDF content is escaped and remote PDF resources remain disabled.
- [ ] API additions retain Sanctum patient role and throttle middleware.
- [ ] Flutter action keys and sort values are allowlisted; no server-provided arbitrary navigation URLs are followed.
- [ ] Error responses preserve 403/404/422 semantics and do not become branded 500 pages.
- [ ] Generated clinical downloads are no-store and not persisted.

## 6. Definition of Done

The initiative is complete only when:

1. Each feature's acceptance criteria are met on every approved surface.
2. All new routes are named, authenticated, authorized, and covered by success plus denial/isolation tests.
3. API changes are backward compatible and their Flutter models/providers/screens ship in the same batch.
4. Questionnaire scoring, appointment state transitions, chatbot backend behavior, notification/realtime behavior, and private file serving are unchanged.
5. Targeted and full Laravel suites pass; Flutter analysis/tests/build pass for Flutter changes; frontend production build passes.
6. Keyboard, touch, screen-reader semantics, text zoom, dark mode, narrow layouts, and modal/sidebar interactions are manually verified.
7. PDF output is readable, isolated, non-persistent, audited, and measured under a production-like large record.
8. `git diff --check` passes and no unrelated source/docs changes are included.
9. Relevant architecture/API/feature documentation is updated alongside implementation, including new routes/contracts/dependencies.

## 7. Resolved Product Decisions

1. The PDF is a complete longitudinal export, including every active item-level questionnaire response, mood note, assignment text submission, appointment reason, and other record category listed in Feature 1. Its content/template is expected to evolve later.
2. Appointment booking reasons are visible only to the appointment's assigned clinician. Administrators cannot view them.
3. The floating Joy bubble and Quick User Guide ship on both the responsive patient portal and the native Flutter app.

No unresolved product decisions remain for this roadmap.
