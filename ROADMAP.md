# TheraConnect — Feature & Hardening Roadmap

A consolidated architectural plan for the next work cycle. Authored 2026-06-18 after a
multi-lens code review + feature brainstorm with the project advisers. Pair with
`CLAUDE.md` (conventions), `handoff.md` (session log, esp. Session 3 hardening), and
`SYSTEM_NOTES.md` (working notes).

Status: **Phase A in progress** — later phases pending adviser sign-off on open questions
in §"Open questions" at the end of this file.

---

## Context

The 3-phase security/ops hardening pass (Session 3, see `handoff.md`) is complete:
65 tests / 229 assertions green, Dockerfile hardened, S3 + queue worker + scheduler templates
in place, policies tightened, transactional writes. The pilot is feature-complete for the
original 13-phase build but not yet **operationally useful** for a real clinic.

This roadmap closes that gap: it adds the features the advisers flagged — notes, file
preview, doctor pages, AI chatbot, metrics, reports, edge-case polish, and a documented
clinical workflow — plus a handful of QoL additions surfaced during exploration.

---

## Adviser-requested features → phase mapping

| # | Adviser feature | Phase(s) | Notes |
|---|---|---|---|
| 1 | Sample workflow with client | **H** | Operational runbook documenting how clinic staff + patients use the software end-to-end. Resolved meaning: end-to-end walkthrough when client & patient actually use the software and how it integrates with operations. |
| 2 | File Preview for assignments | **C** | Inline preview for PDF/img/txt; DOC/Office → download fallback. Web + Flutter. |
| 3 | Swap to AI API for chatbot | **G** | Minimal engine swap (stateless, keep Jaccard fallback, preserve response shape). Recommended scope per adviser. |
| 4 | New metrics | **E** | Free metrics from existing data; +1 migration (`approved_at`); charting via Chart.js CDN (matches existing SRI pattern). |
| 5 | Edge cases + userflow | **A** (foundation fixes) + woven through | Concrete list in §"Edge-case fixes". |
| 6 | Doctors Pages | **D** | Full portal + availability/blockout calendar (adviser-selected scope). |
| 7 | Notes | **B** | Structured notes table (Option C) that doubles as patient-progress data source. |
| 8 | Report generation | **F** | 5 reports: patient summary (PDF), clinician workload (PDF+CSV), appointment analytics (CSV), notification delivery (CSV), dashboard metrics snapshot (PDF). |

---

## Phase A — Foundation fixes

> **Goal:** clear the small correctness/authz gaps surfaced by the code review before building
> on top of them. No user-visible features; unblocks Phase C's S3 path and Phase D's policy work.

### Scope
- **S3 upload bug (the big one)** — `AssignmentService` hardcodes `Storage::disk('local')` at
  `app/Services/AssignmentService.php:19` (worksheet store), `:56` (submission store), `:49`
  (re-submit delete). Setting `FILESYSTEM_DISK=s3` has **no effect** on uploads despite
  `CLAUDE.md`/`README.md` claiming S3 support. Swap to `Storage::disk(config('filesystems.default'))`
  (or a named `private` disk alias) so S3 actually works. Verify the four download routes below
  also use the default disk, not hardcoded `'local'`.
- **Dashboard pending-assignments count bug** — `resources/views/clinician/dashboard.blade.php:57`
  renders `$pendingAssignments->count()` which is the **capped-5 list length**, not the true
  pending total. Compute a real `Assignment::... ->count()` in `DashboardController` and pass it
  separately.
- **API/web authz parity** — `WebAssignmentController::downloadSubmission` (`:105-116`) and
  `downloadWorksheet` (`:118-129`) rely on role middleware only; the API side uses
  `Gate::authorize('view', ...)`. Add the same Gate call to the web methods.
- **`PatientController::store` accepts `notes`** — `:41-72` doesn't accept `notes` (only
  `update()` does). Add the validation + fill so new patients can carry intake notes.
- **Flutter `_guessMime` missing `rtf`** — `theraconnect_flutter/lib/services/download_service.dart:138-163`
  falls through to `application/octet-stream` for RTF. Add `rtf` → `application/rtf`.
- **Remove dead `intentKey` field** — Flutter `ChatbotReply` parses `intent_key` but the screen
  never renders it (`chatbot_message.dart` + provider). Drop the field (will be repurposed in
  Phase G, so gate behind a `// repurposed in Phase G` comment instead if Phase G is scheduled soon).

### Verification
- `php artisan test` stays green (no new tests required for the fixes; extend if desired).
- Manual: set `FILESYSTEM_DISK=s3` locally with mocked S3 (or MinIO) → upload a submission →
  confirm it lands in the bucket, not `storage/app/`.

### Effort
Small. ~1-2 sessions.

---

## Phase B — Patient Notes + Timeline

> **Goal:** replace the lackluster single `patients.notes` text blob with a structured,
  timestamped, attributable notes feature that also doubles as a **patient-progress data
  source** (kills two birds — closes adviser feature #7 and provides metrics data for Phase E).

### Notes table design (Option C — structured + freeform)

New `patient_notes` table, mirroring the existing submission/assignment pattern (Service +
Policy + Resource + role-gated routes per `CLAUDE.md` conventions):

| Column | Type | Purpose |
|---|---|---|
| `id` | bigIncrements | PK |
| `patient_id` | foreignId | `constrained('patients')->cascadeOnDelete()` |
| `clinician_id` | foreignId, nullable | `constrained('clinicians')->nullOnDelete()` — author; nullable so admin-authored notes are allowed |
| `entry_date` | date | when observed (independent of `created_at` for backdating) |
| `category` | ENUM(`clinical_observation`, `treatment_update`, `behavioral_pattern`, `risk_flag`, `general`) | drives filtering + styling |
| `body` | text | freeform note (always required) |
| `metric_key` | varchar 50, nullable | labels a structured score, e.g. `mood`, `anxiety`, `engagement`, `sleep` |
| `rating` | tinyint nullable (1-10) | optional structured score paired with `metric_key` |
| `is_pinned` | bool, default false | surfaces important items at top of patient page |
| `created_at` / `updated_at` | timestamps | audit |
| `softDeletes` | | retention |

**Migration seeds each existing `patients.notes` value as the first `PatientNote` row**
(attributed to `clinician_id = null`, `category = general`, `entry_date = patients.created_at`),
then **drops the `patients.notes` column**.

### Deliverables
- `app/Models/PatientNote.php` — fillable, casts, `belongsTo Patient` + `belongsTo Clinician`.
- `app/Policies/PatientNotePolicy.php` — admin/clinician CRUD; patients never access (mirror
  `SubmissionPolicy::view` at `app/Policies/SubmissionPolicy.php:22-29`).
- `app/Services/PatientNoteService.php` — store/update/delete; fires `NotificationService` on
  risk-flag creation (optional).
- `app/Http/Resources/PatientNoteResource.php` — if any API exposure (initially web-only,
  but build the resource for forward-compat).
- Routes under `routes/web.php` existing `auth` + `role:admin,clinician` group:
  `POST /patients/{patient}/notes`, `PATCH /patients/notes/{note}`, `DELETE /patients/notes/{note}`.
- UI on `resources/views/patients/show.blade.php` (replaces the existing "Clinical Notes"
  card at `:49-60`): append form + chronological list + pin toggle + category filter.
- **Patient Timeline View** — one chronological feed (appointments + submissions + notes +
  notifications) per patient on `patients/show`. New partial blade pulling the union of
  the four collections, sorted by date. This is the single most useful view for a clinician
  during a session.
- **Audit log** (QoL addition bundled here) — log admin actions on patients (create/edit/delete)
  in a `patient_audit_logs` table or via Laravel events; surfaces on the patient detail page.
- **Soft-delete restore UI** (QoL addition bundled here) — Patient/Clinician rows are soft-deleted
  but there's no restore; add a "Deleted" tab on the index views with a restore action.

### Verification
- New `PatientNoteFlowTest` integration test (create / edit / delete / unauthorized patient
  access / category filter).
- Migration test: existing `patients.notes` values appear as `PatientNote` rows after migrate.
- `php artisan test` count increases.

### Effort
Medium. ~3-4 sessions.

---

## Phase C — File Preview

> **Goal:** let clinicians (web) and patients (Flutter) preview assignment attachments and
  submission files inline instead of forcing a download round-trip.

### Scope

**Backend — new inline-disposition preview routes** (parallel to existing download routes):
- Web: `GET /assignments/{assignment}/worksheet/preview` → `WebAssignmentController::previewWorksheet`
  and `GET /submissions/{submission}/file/preview` → `WebAssignmentController::previewSubmission`.
  Use `Storage::disk(config('filesystems.default'))->response($path, $name, [headers])` (Laravel's
  `response()` sets `Content-Disposition: inline`, vs `download()` which sets `attachment`).
  Reuse the same `abort_unless(... ->exists())` + same auth + new `Gate::authorize` from Phase A.
- API analogues: `GET /api/v1/assignments/{id}/worksheet/preview` and
  `GET /api/v1/submissions/{id}/file/preview` — same controllers, inline disposition.

**Web Blade rendering** in `resources/views/assignments/submissions.blade.php` + a new
`resources/views/assignments/preview.blade.php`:
- PDF → `<object data="{{ route('...preview') }}#toolbar=1" type="application/pdf" style="width:100%;height:80vh"></object>` + Download fallback link.
- Images (`jpg`/`jpeg`/`png`) → `<img src="{{ route('...preview') }}" class="img-fluid">`.
- `txt` → `<iframe>` or fetched `<pre>`.
- `doc`/`docx`/`xls`/`xlsx`/`ppt`/`pptx`/`rtf` → keep existing Download button (can't render
  natively without a 3rd-party viewer or public-URL Office viewer incompatible with private files).
- Branch on extension via `Str::afterLast($submission->original_name, '.')`.

**Flutter — new render screen + package:**
- Add `syncfusion_flutter_pdfviewer` to `pubspec.yaml` (free community license) — or `pdfx`
  (fully OSS, fewer features) as a fallback if the Syncfusion license is a blocker.
- New `theraconnect_flutter/lib/screens/assignments/preview_screen.dart` — takes `urlPath` +
  `fileName`, fetches via the existing authenticated `DownloadService.downloadAndStore()`
  (`download_service.dart:31-82`), then renders by extension:
  - PDF → `SfPdfViewer.file(File(localPath))`.
  - Images → `Image.file(File(localPath))`.
  - `txt` → `File(localPath).readAsString()` in a selectable `SingleChildScrollView`.
  - `doc`/`docx`/`rtf`/Office → fall back to the existing `OpenFilex.open(localPath)` path
    (already wired in `assignment_detail_screen.dart:43`).
- Wire from `assignment_detail_screen.dart:166-182` (worksheet button) — change "Download"
  into "Preview" / "Open"; add a **"View your submission"** action that hits the existing
  `/api/v1/submissions/{id}/file` route (`routes/api.php:62`; `SubmissionResource` already emits
  `file_url` at `app/Http/Resources/SubmissionResource.php:19` — no Flutter screen consumes it today).

### Verification
- New `FilePreviewTest`: web preview route returns inline disposition + 200; unauthorized patient
  gets 403; missing file gets 404.
- Manual: upload a PDF + DOCX + image as a submission, confirm each renders correctly on web +
  Flutter.

### Effort
Medium. ~3 sessions (Flutter part needs the SDK; see "Flutter SDK" note at the end).

### Depends on
Phase A (S3 disk fix + Gate::authorize parity).

---

## Phase D — Doctors Pages + Availability

> **Goal:** give clinicians a self-service portal (their own patients, appointments, assignments)
  instead of the global totals they see today, plus an availability/blockout calendar so patients
  can't book clinicians on leave. Adviser-selected scope: "Full portal + availability."

### Scope

**Clinician detail page (admin + clinician-self):**
- New `ClinicianController::show(Clinician $clinician)` — eager-load appointments, assignments +
  submissions, computed workload stats. Reuse the Timeline View pattern from Phase B on the
  clinician's own activity.
- New `resources/views/clinicians/show.blade.php` (model on `patients/show.blade.php`).
- **Route swap** in `routes/web.php:36-38`: change `->except(['show'])` to
  `->only(['index','create','store','edit','update','destroy'])` (keeps admin-only CRUD), then
  add `Route::get('/clinicians/{clinician}', [ClinicianController::class, 'show'])->name('clinicians.show')`
  gated with `['auth','role:admin,clinician']`. Add a "View" link in `clinicians/index.blade.php:39`.
- **Clinician self-scoping** — if the logged-in user is a clinician (not admin), filter all
  dashboard/patient/appointment/assignment queries by their `clinician_id`. This is a behavioral
  change to `DashboardController`, `PatientController`, `WebAppointmentController`,
  `WebAssignmentController`. Decide: filter at controller level (explicit) vs. a global query
  scope (magic). **Controller level recommended** — explicit + testable.

**Clinician self-service dashboard:**
- Either repurpose the existing `/dashboard` to be role-aware (clinician sees own data, admin
  sees global), or add a separate `/my-dashboard` route for clinicians. Role-aware is cleaner —
  branch in `DashboardController::index` on `auth()->user()->role`.

**Availability / blockout calendar:**
- New `clinician_blockouts` table: `id`, `clinician_id` (FK cascade), `starts_at` (datetime),
  `ends_at` (datetime), `reason` (string nullable), `created_by` (user FK), timestamps.
- New `ClinicianBlockout` model + `ClinicianBlockoutPolicy` (clinician manages own, admin manages any).
- `AppointmentService::isSlotAvailable` (`app/Services/AppointmentService.php`) — extend the
  conflict check to also reject booked-slots overlapping any blockout for that clinician.
- `AppointmentService::getScheduleSlots` — exclude slots that fall inside a blockout.
- Web form for clinicians to mark leave/working-hours (Alpine.js date/time picker, reusing the
  existing dynamic-form pattern from `chatbot-content/edit.blade.php`).
- Patient API `/schedules` should naturally return fewer slots for blocked clinicians — no
  Flutter change required.

### Verification
- New `ClinicianBlockoutTest`: booking during a blockout is rejected; schedule excludes
  blocked slots; clinician can create/view/delete own blockouts; clinician cannot edit another's.
- New `ClinicianScopedDashboardTest`: clinician sees only own patients/appointments; admin sees all.

### Effort
Large. ~5-6 sessions. The self-scoping change touches several controllers — schedule time for
the test suite updates.

### Depends on
Phase B (reuses the Timeline View pattern).

---

## Phase E — New Metrics + Charts

> **Goal:** surface real analytics on the dashboard and on patient pages, including a
  patient-progress view sourced from the Phase B notes.

### Scope

**New migration** — add `approved_at TIMESTAMP NULL` to `appointments`; set it in
`AppointmentService::approve()` (`app/Services/AppointmentService.php:133-142`). (Optional:
also add `cancelled_at`/`completed_at` for full per-state timing analytics. Defer if not
needed for the first report pass.)

**Charting library** — add **Chart.js v4 via CDN** with SRI hashes (matches the existing
Bootstrap/Alpine CDN+SRI pattern in `resources/views/layouts/app.blade.php:8-9,73-74`).
No npm/Vite change needed; charts render from server-injected JSON data attributes.

**Dashboard metrics (web)** — extend `DashboardController`:
| Metric | Source | Needs migration? |
|---|---|---|
| Appointment status distribution (pie) | `groupBy('status')` over 6-value ENUM | No |
| Appointment mode distribution (in_person vs online) (doughnut) | `groupBy('mode')` | No |
| Average time-to-approve (line) | `AVG(approved_at - requested_at)` | **Yes (new `approved_at` col)** |
| Average submission review time (bar) | `AVG(reviewed_at - submitted_at)` (both exist) | No |
| Assignment completion rate (gauge) | reviewed submissions ÷ total assignments | No |
| Notification read rate (doughnut) | `whereNotNull('read_at')` ÷ total | No |
| Clinician workload per specialization (bar) | join appts+assignments → `clinicians.specialization` | No |
| No-show rate (table) | define: `scheduled_at < now` AND status ∈ `['approved','rescheduled']` | No (define explicitly) |
| New patients this month (counter) | `whereMonth('created_at', now())` | No |

**Role-aware scoping** (depends on Phase D) — clinicians see metrics for their own patients/
appointments only; admins see global. Branch in `DashboardController` on `auth()->user()->role`.

**Patient progress chart** — on `patients/show.blade.php`, render a Chart.js line chart of
`metric_key` ratings over time from `PatientNote` rows (Phase B). Let the clinician pick which
`metric_key` to plot (dropdown). Also show pinned notes as a summary card above.

**Email notifications** (QoL addition bundled here) — Laravel Mail is already configured
(`config/mail.php` + Postmark/SES-ready). Add a `MailChannel` to `NotificationService` so
clinic staff without the mobile app still get appointment/submission alerts. Toggle via
`MAIL_NOTIFICATIONS_ENABLED` env var. Complements the engagement-rate metric.

### Verification
- New `MetricsTest` asserting the controller returns the expected aggregations keyed by role.
- Manual: log in as admin → see global charts; log in as clinician → see self-scoped charts.

### Effort
Medium-Large. ~4-5 sessions.

### Depends on
Phase B (uses Notes `metric_key` data), Phase D (uses clinician self-scoping).

---

## Phase F — Report Generation

> **Goal:** generate the 5 reports the advisers selected, role-gated, in PDF + CSV.

### Scope

**Composer dependency:**
```bash
composer require barryvdh/laravel-dompdf
```
(dompdf wrapper — most common in Laravel-land, no binary dependency. For complex layouts
`barryvdh/laravel-snappy` + wkhtmltopdf is an option but heavier + harder on Windows/Docker.)

**CSV** — native streamed `fputcsv` via `Symfony\Component\HttpFoundation\StreamedResponse`. No
library needed unless styled `.xlsx` is required (then `maatwebsite/excel` ^3.1).

**New `ReportController` + `app/Services/Reports/` namespace** (per `CLAUDE.md` "thin controllers,
services do writes" — though reports are read-only, the service pattern keeps query logic
testable + shareable between PDF and CSV renderers).

**5 reports:**

| # | Report | Format | Scope | Notes |
|---|---|---|---|---|
| 1 | Patient summary | PDF | One-patient overview: demographics, appointment history, submissions, notes, pinned observations | Useful for handoff/referral |
| 2 | Clinician workload | PDF + CSV | Per-clinician: appointments by status, assignments created, submissions reviewed, avg review time | Admin sees all; clinician sees own |
| 3 | Appointment analytics | CSV | Date-range appointment list with status/mode/clinician/patient | For spreadsheet import |
| 4 | Notification delivery | CSV | All notifications in a date range with read/sent status per patient | Audit/compliance |
| 5 | Dashboard metrics snapshot | PDF | One-page summary of all dashboard KPIs + charts for a given period | Executive view |

**Routes** under `routes/web.php` `auth` + `role:admin,clinician` group:
`GET /reports`, `GET /reports/patient/{patient}`, `GET /reports/clinician/{clinician}`,
`GET /reports/appointments`, `GET /reports/notifications`, `GET /reports/dashboard`.
Date-range filter (`?from=&to=`) where applicable.

**Notification log filters** (QoL addition bundled here) — the existing
`NotificationLogController` (`app/Http/Controllers/Web/NotificationLogController.php:11-18`)
is a flat paginated table; add date + type + patient filters. Reused by the notification
delivery report.

**Print-friendly CSS** (QoL addition) — `@media print` rules for patient overview + reports
so clinicians can browser-print without the sidebar/nav.

### Verification
- New `ReportGenerationTest`: each report route returns 200 + correct Content-Type; unauthorized
  patient access returns 403; date-range filters work; CSV opens cleanly + PDF is valid.
- Manual: generate each report against seeded demo data, confirm content + formatting.

### Effort
Medium. ~3 sessions.

### Depends on
Phase D (clinician scoping for workload report), Phase E (metrics for dashboard snapshot).

---

## Phase G — AI Chatbot Engine Swap

> **Goal:** replace the Jaccard rule engine with an AI API call as the primary path, keeping
  the existing rule engine + fallback as the catch on API errors. Adviser-selected scope:
  "Minimal engine swap."

### Scope

**Config + env:**
- New `openai` (or `anthropic`) block in `config/services.php` (currently only has postmark/ses/
  resend/slack/fcm/jitsi at `:17-47`). Keys: `api_key`, `model` (default `gpt-4o-mini`),
  `timeout` (default 8s).
- Env vars added to `.env.example` + `.env.railway.example`: `OPENAI_API_KEY`,
  `CHATBOT_MODEL`, `CHATBOT_AI_TIMEOUT`. All optional — when `OPENAI_API_KEY` is blank, the
  service skips the AI path entirely and uses the existing rule engine (graceful no-op, matching
  the pattern used by `FcmService`).

**`ChatbotService::resolve()` refactor** (`app/Services/ChatbotService.php:14-64`):
- Extract the current body into `private function jaccardResolve(string $message): array`.
- New `resolve()` body: try `aiResolve($message)` first (loads KB entries from
  `chatbot_intents`+`chatbot_responses` as system-prompt context — the existing CRUD becomes a
  "knowledge base" staff still manage via the existing `ChatbotContentController`); on any
  exception (ConnectionException, RequestException, 429 from provider, JSON parse failure) →
  fall back to `jaccardResolve()` → existing `fallbackResponse()` (`:66-84`) as last resort.
- **Keep the response shape** `{reply, intent_key, is_fallback}` to avoid breaking the 5 tests in
  `tests/Integration/ChatbotFlowTest.php` + the Flutter `ChatbotReply` parser
  (`chatbot_message.dart:12-18`). Repurpose `intent_key` as a source tag:
  `'ai'` / `'rule:clinic_hours'` / `'fallback'` / `'error'`. The Flutter screen never renders
  `intent_key` today (provider line 27 only uses `.reply`), so no Flutter rebuild needed.
- **Remains stateless** (no conversation history persistence — that's the "Full AI feature set"
  scope which was deferred). Single-message in, single-message out.

**Rate limiting** — add a new `chatbot_ai` limiter in `app/Providers/AppServiceProvider.php`
alongside the existing `chatbot` (30/min/user, `:35-37`). AI path: 8 requests/min/user +
daily cap of ~50 messages (enforced via `RateLimiter::attempt` with a `daily` decay). Wrap
inside `resolve()` (not as middleware) so the fallback rule path isn't double-limited.

**No Flutter changes** (stateless, shape preserved, screen doesn't render `intent_key`).

**Optional (time-boxed):** a "Prompt/Model Settings" page at `GET /chatbot-settings` for system
prompt template + model selection. Defer unless staff want to self-tune.

### Verification
- `ChatbotFlowTest` — existing 5 tests stay green (shape preserved, fallback path still works
  when `OPENAI_API_KEY` is blank).
- New `ChatbotAiFallbackTest` — with env blanked, AI path no-ops cleanly to rule engine; with
  a mocked HTTP failure, rule engine still answers; with both failing, `DEFAULT_FALLBACK` (
  `ChatbotService.php:10`) + `is_fallback: true` is returned.
- Manual: set a real key, ask the bot a question not in the KB, confirm a contextual AI reply.

### Effort
Small-Medium. ~2-3 sessions.

### Depends on
None (independent — can run in parallel with earlier phases if desired, but keep its tests
isolated from the Phase A-D churn).

---

## Phase H — Clinical Workflow / Operations Runbook

> **Goal:** document how clinic staff + patients actually use the software and how it integrates
  with real operations. Adviser-selected scope: "end-to-end walkthrough when the client and
  patient actually use the software and how it integrates with operations."

### Scope

**New `docs/OPERATIONS_RUNBOOK.md`** (or `OPERATIONS.md` at root for visibility) documenting
real-world end-to-end workflows across daily / weekly / monthly cycles. Examples:
- **Daily morning routine** — clinician opens dashboard, reviews today's appointments, checks
  pending submissions from overnight, glances at risk-flagged patient notes from the Timeline.
- **Same-day cancellation / no-show** — how a clinician handles a patient who cancels or doesn't
  show; how the no-show surfaces in metrics; how to reschedule.
- **New patient onboarding** — admin creates the patient, patient downloads the app, registers,
  books first appointment, clinician approves, patient gets the push.
- **Assignment review cycle** — clinician creates assignment → patient submits → clinician
  reviews + adds a progress note → patient sees the reviewed status.
- **Reminder integration** — how the scheduler fires appointment + assignment reminders, how to
  verify the queue worker + scheduler services are running on Railway.
- **Soft-delete recovery** — how to restore an accidentally-deleted patient/clinician (uses the
  restore UI from Phase B).
- **Escalation paths** — what to do when FCM is down, when S3 is full, when the DB is slow.

Seed each workflow with realistic scenarios that exercise the full stack (build on the
existing `DemoSeeder` data). Each workflow should cite the actual screens/routes by name so a
new staff member can follow along.

**In-app "Help / Workflows" page** — new web route `GET /help` rendering a Blade view that links
to the runbook sections + embeds short how-to snippets for the most common tasks. Role-gated
(admin/clinician). Alpine.js-driven **onboarding tour** (QoL addition) on first dashboard visit
— step-by-step coach-marks highlighting sidebar, stat cards, activity feed, appointment actions.

### Verification
- Review by at least one clinician + one admin for accuracy.
- Runbook walk-through follows the seeded demo data end-to-end without dead-ends.

### Effort
Medium (writing-heavy). ~2-3 sessions.

### Depends on
All prior phases (documents the integrated product). Execute last.

---

## QoL additions woven through the phases

These are **not** separate phases — they bundle into the feature that naturally owns them:

| Feature | Phase | Notes |
|---|---|---|
| Email notifications | E | Laravel Mail already configured; adds a `MailChannel` to `NotificationService` |
| Patient audit log | B | `patient_audit_logs` table; surfaces on patient detail page |
| Soft-delete restore UI | B | "Deleted" tab on Patient/Clinician index views |
| Notification log filters | F | Date + type + patient filters on `NotificationLogController` |
| Print-friendly CSS | F | `@media print` rules for patient overview + reports |
| Onboarding tour (web) | H | Alpine.js step-by-step on first dashboard visit |
| Assignment templates | (deferred) | Reusable homework templates; not bundled — note for v2 |

---

## Patient progress metrics — menu to validate with the client

The advisers flagged that metrics to measure patient progress need client discussion. Here's
the menu to take to that meeting:

**Free from existing data (no migration):**
- Appointment attendance ratio — completed vs cancelled vs no-show (define "no-show" =
  `scheduled_at < now` AND status still `approved`/`rescheduled`)
- Assignment completion rate per patient + submission timeliness (`submitted_at` vs `due_date`)
- Notification engagement (read rate) as a participation proxy
- Appointment frequency over time (sessions per month)

**Free from Phase B Notes (no extra migration):**
- Time-series of any `metric_key` rating (mood, anxiety, engagement, sleep) → line chart on
  patient page
- Risk-flag frequency over time
- Pinned observations as patient summary

**Needs new tables (defer to v2 unless client wants):**
- Standardized intake questionnaire (PHQ-9 / GAD-7 / etc.) — structured scoring forms
- Treatment goals with explicit % progress
- Session-by-session outcome measures

**Recommendation:** ship Option C notes + the free existing metrics now, defer standardized
questionnaires. The client meeting validates which `metric_key`s to track (mood, anxiety,
engagement, sleep, etc.).

---

## Edge-case fixes bundled into Phase A

Concrete list surfaced by the code review:

| # | Bug | Location | Fix |
|---|---|---|---|
| 1 | S3 uploads silently ignored (`Storage::disk('local')` hardcoded) | `app/Services/AssignmentService.php:19,49,56` + 4 download routes | Swap to `Storage::disk(config('filesystems.default'))` |
| 2 | Dashboard pending-assignments count is capped-5 not total | `resources/views/clinician/dashboard.blade.php:57` | Compute real `->count()` in `DashboardController`, pass separately |
| 3 | Web download routes skip `Gate::authorize` (API/web authz asymmetry) | `app/Http/Controllers/Web/WebAssignmentController.php:105-129` | Add `Gate::authorize('view', ...)` to both web methods |
| 4 | `PatientController::store` doesn't accept `notes` (only `update`) | `app/Http/Controllers/Web/PatientController.php:41-72` | Add `'notes' => ['nullable','string']` to store validation |
| 5 | Flutter `_guessMime` missing `rtf` | `theraconnect_flutter/lib/services/download_service.dart:138-163` | Add `rtf` → `application/rtf` |
| 6 | Flutter parses `intentKey` but never renders it | `theraconnect_flutter/lib/models/chatbot_message.dart` | Drop the field (Phase G repurposes it) |

---

## Open questions (need adviser sign-off before Phase G + B)

1. **AI provider** — OpenAI (`gpt-4o-mini`) or Anthropic (`claude-haiku`) for the chatbot swap?
   OpenAI is the default in the plan; Anthropic is a config swap. Cost is comparable at pilot
   volume.
2. **Patient self-service scope** — should the patient-side mobile app see anything new (e.g.
   their own progress chart on the dashboard), or is everything in this roadmap clinic-facing
   for now? Default assumption: clinic-facing only; patient app changes limited to File Preview
   (Phase C) + bug fixes (Phase A). Confirm before starting Phase E.
3. **No-show definition** — confirm "scheduled_at < now AND status ∈ `['approved','rescheduled']`"
   is the right operational definition. Some clinics also count late-cancellations (<24h) as
   no-shows; others only count missed-with-no-cancel. This drives a metric + a report.
4. **`metric_key`s for Notes** — take the list (mood, anxiety, engagement, sleep, ...) to the
   client meeting in Phase B; the structured-rating column is built regardless, the keys are
   seeded defaults that staff can extend.
5. **Sync vs. queue for email notifications** (Phase E) — send via `SendPushNotification`-style
   queued job (recommended, matches existing pattern) or synchronously? Default: queued.

---

## Flutter SDK constraint

No Flutter SDK on this machine. PHP-side work in every phase is verifiable (`php artisan test`).
Flutter-side work in Phases A (1 fix), C (full feature), and D (no Flutter change) will be
authored but **not** verified by `flutter analyze` until the SDK is available. Options:

- **Option 1 (default)** — author Dart changes, mark them unverified in `handoff.md`, user runs
  `flutter pub get && flutter analyze` later.
- **Option 2** — skip all Flutter-side work until the SDK is installed; backend-only phases (A
  backend, B, D backend, E backend, F, G backend, H) proceed normally, Flutter features (C, A's
  Flutter fix) queue until the SDK is ready.

Confirm preference before starting Phase C.

---

## Execution order + dependencies

```
A (foundation)
 │
 ├─► B (notes + timeline) ──► D (doctors pages + availability) ──► E (metrics) ──► F (reports) ──► H (runbook)
 │                                                            (reuses timeline)
 │
 ├─► C (file preview) [depends on A S3 fix]
 │
 └─► G (AI chatbot swap) [independent, parallelizable]
```

Optional parallelism: **G can run alongside any phase** (its tests are isolated). **C can run
once A lands.** The B→D→E→F→H chain is sequential because each depends on the prior's data
model or scoping work.

Pause for adviser review between each phase. Update `handoff.md` with a session entry at each
phase boundary.

---

## Verification at every phase

- `php artisan test` stays green; suite count grows with each phase's new integration tests.
- Run lint/typecheck if available (PHP: `php artisan lint` if Pint is installed; Flutter: `flutter
  analyze` when SDK available — see "Flutter SDK constraint" above).
- Update `CLAUDE.md` "Architecture & conventions" section if any phase introduces a new
  convention (e.g. Phase D's role-aware controller scoping, Phase B's audit log pattern).
- Update `README.md` "Build Status" table phase rows as each phase completes.
- Append a `handoff.md` "Session N" entry per phase with the test-count delta + new file paths.
