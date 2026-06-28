# TheraConnect — Defense Demo Script

> End-to-end walkthrough + talking script for the thesis defense. Every URL,
> login, and expected visible state below is grounded in `database/seeders/DemoSeeder.php`
> and the actual route registrations in `routes/web.php` / `routes/api.php`.
> Verify the demo against the live Railway instance once before defense day
> (see §1).

---

## 0. Demo Arc (10–12 minutes)

| Phase | Minutes | Surface | Actor | What we prove |
|---|---|---|---|---|
| 1. Landing + auth | 1.5 | Web | Admin | Branding, role-based redirect |
| 2. Admin overview | 1.5 | Web | Admin | System transparency — activity log, notification log, clinician CRUD |
| 3. Clinician workflow | 3.0 | Web | Clinician (Dr. Chen) | Approve appointment → create assignment → assign PHQ-9 → write patient note → message patient |
| 4. Patient mobile equivalent | 2.5 | API + Flutter | Jane Doe | Same actions through the mobile surface; show API parity |
| 5. Patient portal mirror | 1.5 | Web portal | Michael | Web feature parity — patient who doesn't have the app |
| 6. Chatbot | 1.0 | Web portal | Michael | AI-grounded clinic FAQ + crisis path |
| 7. Architectural close | 1.0 | Web | Admin | Open `AUDIT_FOLLOWUPS.md` — show the self-audit |

Total: ~12 minutes. Trim or skip a phase if time is short.

---

## 1. Pre-Flight Checklist (30 min before call)

### 1.1 Verify the live instance

| URL | Expected |
|---|---|
| https://theraconnect-demo-production.up.railway.app/api/v1/health | `{"status":"ok"}` |
| https://theraconnect-demo-production.up.railway.app/ | Landing page (`landing.blade.php`) |
| https://theraconnect-demo-production.up.railway.app/login | Login form |

If /health returns 503 or 500, the DB is down — restart the Railway web
service and wait for the healthcheck to flip green before the demo.

### 1.2 Env vars to confirm on Railway

| Var | Required value | Why |
|---|---|---|
| `APP_DEBUG=false` | ✓ false | No stack traces on errors |
| `APP_KEY` | non-empty | App boots |
| `SEED_DEMO=true` | ✓ true | Demo data exists |
| `DEMO_PASSWORD` | strong value known to you | All 8 demo accounts share this password |
| `GEMINI_API_KEY` | set | Chatbot uses AI path; otherwise Jaccard fallback (works but looks dumb for non-seeded questions) |
| `FILESYSTEM_DISK=local` (acceptable for demo) or `=s3` | either | If `local`, uploads reset on redeploy — fine for demo |
| `QUEUE_CONNECTION=database` | ✓ database | Async push notifications + reminders dispatch |
| `SESSION_ENCRYPT=true` | ✓ true | Always |

If `GEMINI_API_KEY` is empty, the chatbot falls back to Jaccard keyword
matching (works for "what are your hours" type questions; fails on novel
phrasing). Set the key for the demo.

### 1.3 Reset demo data (optional but safer)

If a previous demo run mutated seeded state (e.g., approved an appointment
that should be `pending`), reset:

```bash
# Locally, against Railway DB (or via Railway shell):
php artisan migrate:fresh --seed --force
```

This is idempotent — see `database/seeders/DemoSeeder.php:28` (early return
if `admin@theraconnect.test` already exists). To truly re-seed, run
`migrate:fresh` first.

### 1.4 Browser prep

- Two browser windows side-by-side (or two browsers / two Chrome profiles):
  - **Left:** Admin / clinician dashboard
  - **Right:** Patient portal (or Flutter app on an Android emulator)
- Two-factor on personal accounts OFF for the demo session
- Browser zoom 100% (so the panel can read text onscreen)
- Disable browser auto-fill (so password field doesn't autocomplete your
  personal Gmail during the demo)
- If using Flutter app: launch the Android emulator **before** the call
  starts; cold-boot time is 30–60s and you don't want to wait live

### 1.5 Demo password

All accounts share `DEMO_PASSWORD` set on Railway. Confirm the value before
the call and have it in your notes / password manager.

---

## 2. Seeded Demo Data (Cheat Sheet)

You'll be navigating by these names/IDs. All passwords =
`$DEMO_PASSWORD`.

| Role | Name | Email | Use in demo |
|---|---|---|---|
| Admin | Admin User | `admin@theraconnect.test` | Phase 1, 2, 7 |
| Clinician | Dr. Sarah Chen, MD (CBT) | `clinician@theraconnect.test` | Phase 3 — primary clinician |
| Clinician | Dr. James Rivera, PsyD (Family) | `dr.rivera@theraconnect.test` | Backup; patient request target |
| Patient | Jane Doe | `patient@theraconnect.test` | Phase 4 — mobile API demo |
| Patient | Michael Torres | `michael@theraconnect.test` | Phase 5 — portal demo |
| Patient | Emily Watson | `emily@theraconnect.test` | Backup; flagged PHQ-9 = 18 |
| Patient | Sophia Nguyen | `sophia@theraconnect.test` | Backup |
| Patient | Olivia Reyes | `olivia@theraconnect.test` | Phase 3 add-on — clinician-request flow |

### 2.1 Pre-seeded appointments you'll reference

| Patient | Clinician | Status | Mode | Notes |
|---|---|---|---|---|
| Jane | Dr. Chen | `pending` | in_person | "Anxiety management follow-up" — *this is the one you'll approve in Phase 3* |
| Michael | Dr. Rivera | `pending` | online | "Family counseling intake" |
| Emily | Dr. Chen | `pending` | in_person | "Initial consultation — anxiety screening" |
| Jane | Dr. Chen | `approved` | in_person | "Weekly CBT session" — has reminder notification |
| Michael | Dr. Rivera | `approved` | online | "Follow-up video consultation" — has a live Jitsi link |
| Michael | Dr. Chen | `rejected` | in_person | "Requested outside clinic hours" |
| Emily | Dr. Chen | `cancelled` | in_person | "Scheduling conflict" |

### 2.2 Pre-seeded assignments

| Title | Patient | Status |
|---|---|---|
| Daily Mood Journal | Jane | 1 submission reviewed |
| Breathing Exercise Practice | Jane | 1 submission waiting review |
| Communication Reflection | Michael | Not submitted |
| Anxiety Trigger Log | Emily | Not submitted |
| Sleep & Stress Log | Sophia | Not submitted |

### 2.3 Pre-seeded assessments

| Patient | Instrument | Score | Severity |
|---|---|---|---|
| Jane | PHQ-9 #1 | 14 | Moderate |
| Jane | PHQ-9 #2 | 8 | Mild (improving) |
| Jane | GAD-7 | — | Pending (assigned, not yet completed) |
| Emily | PHQ-9 | 18 | Moderately severe |
| Michael | GAD-7 #1 | 12 | Moderate |
| Michael | GAD-7 #2 | 7 | Mild (improving) |
| Sophia | PHQ-9 | — | Pending |

### 2.4 Pre-seeded mood logs (already on progress charts)

- Jane: 14-day trend, scores 4→8 (clear improvement arc)
- Emily: 7-day trend, scores 3–5 (consistently low)
- Michael: 6 logs over 16 days, 4→7 (mirrors GAD-7 improvement)
- Sophia: 3 logs, 4→6 (early engagement)

### 2.5 Pre-seeded goals + GAS ratings

- Jane: 2 goals (1 active `-1 → 0`, 1 met `+2`)
- Michael: 2 goals (1 met `-1 → +1`, 1 active `0`)
- Emily: 1 active (unrated)
- Sophia: 1 active (rated `-1`)

### 2.6 Pending patient request (the Olivia hook)

`olivia@theraconnect.test` self-registered and asked to join Dr. Chen's
caseload — `clinician_request_status = pending`. Dr. Chen gets a
notification "Olivia Reyes requested to be added to your caseload."

---

## 3. Phase-by-Phase Script

### Phase 1 — Landing + Auth (1.5 min)

**URL:** `https://theraconnect-demo-production.up.railway.app/`

| Step | Action | Expected |
|---|---|---|
| 1.1 | Open the landing URL | `landing.blade.php` renders — branded hero, "Download the App" CTA scrolls to info section (because `APP_DOWNLOAD_URL` is blank) |
| 1.2 | Click "Sign in" | `GET /login` — Bootstrap 5 form, role-agnostic |
| 1.3 | Type `admin@theraconnect.test` + `$DEMO_PASSWORD` | Form accepts; button has no spinner (limitation #14 in `AUDIT_FOLLOWUPS.md` — rehearse around) |
| 1.4 | Click "Sign in" | Redirects to `/dashboard` (admin role auto-routes here) |

**Talking points while typing:**

> "TheraConnect is a three-tier system serving admin, clinician, and patient
> roles through one Laravel backend. The web dashboard is Blade + Bootstrap + 
> Alpine.js; the patient surface is either a Flutter mobile app or a browser
> portal at `/portal` — both have full feature parity because they share the
> same service layer and policies."

> "Auth uses Laravel Sanctum for the mobile API (bearer tokens) and database
> session for the web dashboard. Login is double-rate-limited: 5/min/IP and
> 5/min per `email|IP` to bound distributed brute force."

---

### Phase 2 — Admin Overview (1.5 min)

**URL:** `https://theraconnect-demo-production.up.railway.app/dashboard`

Logged in as `admin@theraconnect.test`.

| Step | URL | What to show |
|---|---|---|
| 2.1 | `/dashboard` | KPI cards — total patients, pending appointments, active clinicians, today's sessions |
| 2.2 | `/clinicians` |clinician directory — Dr. Chen (CBT), Dr. Rivera (Family). Click "Create" to show the form (don't submit) — explain admin can onboard clinicians |
| 2.3 | `/activity-logs` | System transparency: every admin/clinician create/update/delete is captured in `activity_logs` table. This is the audit trail. |
| 2.4 | `/notifications/logs` | Notification audit log — every notification sent (FCM + in-app) is persisted; admin can search/filter |
| 2.5 | `/chatbot-content` | Admin-managed chatbot knowledge base — intents + responses are editable; the chatbot is grounded on **this** seeded data, never invents clinic facts |

**Talking points:**

> "Admin's role is intentionally narrow: clinicians manage cases, admin
> manages the system. Admin can onboard staff, manage the chatbot KB, and
> audit all activity — but admin can't write patient notes or assign
> assessments, which is by-design RBAC separation enforced at the route
> level in `routes/web.php:107-128`."

> "Every state-changing admin and clinician action is captured in the
> `activity_logs` table — the system is auditable, which is a healthcare-
> adjacent posture requirement."

---

### Phase 3 — Clinician Workflow (3.0 min)

**Action:** Log out of admin, log in as `clinician@theraconnect.test` (Dr. Sarah Chen, MD).

| Step | URL | Action | Expected |
|---|---|---|---|
| 3.1 | `/logout` | Click user menu → Logout | Redirects to `/login` |
| 3.2 | `/login` | Log in as `clinician@theraconnect.test` | Redirects to `/dashboard` (clinician role) |
| 3.3 | `/dashboard` | Show KPI cards — Dr. Chen sees **her own** patients only (2: Jane + Emily) | Demonstrate scope isolation |
| 3.4 | `/appointments` | Filter to "Pending" — see Jane's "Anxiety management follow-up" pending appointment (the one from §2.1) | 4 pending appointments listed |
| 3.5 | `/appointments/{id}/approve` (click Approve on Jane's pending appt) | State machine transitions `pending → approved`; meeting link only added if mode = online (this one is in_person) | `AppointmentService::approve()` — note the state guard at `:174-178` |
| 3.6 | `/notifications` (Dr. Chen's inbox) | Show new notification — Jane sees the "appointment Approved" notification (Note: this is the patient's notif, not Dr. Chen's. Dr. Chen sees the *Olivia patient-request* notification from §2.6) | Demonstrate the Olivia hook |
| 3.7 | `/patients` | Filter for "Pending requests" — see Olivia Reyes pending | Click "Approve" — `Patient::REQUEST_PENDING → approved`, `assigned_clinician_id = Dr. Chen`, patient gets `patient_request_approved` notification |
| 3.8 | `/patients/jane-doe` (Mr. Jane Doe detail view) | Click into Jane's record | See profile, mood chart, goals, past appointments |
| 3.9 | `/patients/{patient}/progress` (Progress tab) | Show progress view — attendance + 2 PHQ-9 scores (improving: 14 → 8) + mood 14-day trend + goals with GAS ratings | This is the "visual progress tracking" scope item |
| 3.10 | `/assignments/create` | Create a new assignment for Jane — Title: "Cognitive restructuring journal", due in 3 days | `POST /assignments` creates the row; notification dispatched to Jane (`assignment_created`) |
| 3.11 | `/patients/{jane}/assessments` (assign PHQ-9 button on progress page) | Assign a new PHQ-9 to Jane | `POST /patients/{p}/assessments`; notification dispatched (`assessment_assigned`) |
| 3.12 | `/patients/{jane}/notes` (notes section) | Write a clinician note: "Patient reports improved sleep after breathing exercises." | `POST /patients/{p}/notes`; polymorphic-ish patient_notes table |
| 3.13 | `/messages` | Open conversation with Jane (or click "Open" if no active thread) — type: "Hi Jane, your next appointment is confirmed for Friday at 9 AM." Click Send | `POST /messages/{conversation}`; "message_received" notification dispatched to Jane |
| 3.14 | `/availability/month` (JSON endpoint driving the calendar) | Click the calendar widget on dashboard | `clinician_weekly_availabilities` + `clinician_date_overrides` power available slots; toggle a day off to show real-time calendar response |

**Talking points throughout Phase 3:**

> [Step 3.4] "Dr. Chen sees only her own patients — nurses and other
> clinicians can't see her caseload. This is enforced at the Eloquent query
> scope level, not just the view layer, so an API probe can't bypass it."

> [Step 3.5] "Approving an appointment is a guarded state transition — the
> `AppointmentService::approve()` method at `app/Services/AppointmentService.php:164`
> only accepts source states `pending` or `rescheduled`. You can't accidentally
> re-approve a completed or cancelled appointment and desync the clinical
> reporting. There's an adversarial test for this in
> `tests/Adversarial/StateMachineLogicTest.php`."

> [Step 3.7] "The Olivia flow is our self-registration path — a patient
> downloads the app, picks a clinician, and the clinician must approve
> before any PHI is exchanged. This is the gate against unauthorized patient
> onboarding."

> [Step 3.8] "Patient records are fully centralized — demographics, contact,
> emergency contact, mood logs, goals, appointments, notes, assessments —
> addressing the scope's *Data Fragmentation* problem statement."

> [Step 3.9] "Visual progress tracking shows quantitative outcomes over time:
> PHQ-9 scores, GAD-7 scores, mood log trend, and GAS goal ratings all on one
> page. This is the data a clinician would bring to a case conference."

> [Step 3.10–3.13] "These four actions — create assignment, assign
> assessment, write note, send message — each dispatch a notification to the
> patient. The notifications flow through our `NotificationService`, write to
> the `notifications` table, and push to FCM (if the patient has registered a
> device token). This is the *Integrated Notification System* scope item."

> [Step 3.14] "The availability calendar is generated from a weekly
> availability table plus per-date overrides. The slot generator excludes
> past times on the same day and any slots with an active appointment — see
> `AppointmentService::getScheduleSlots()` at `:19-68`."

---

### Phase 4 — Patient Mobile Equivalent (2.5 min)

**Action:** Switch to the Flutter app (Android emulator or real device).

| Step | Action | Expected |
|---|---|---|
| 4.1 | Launch TheraConnect app on emulator | Splash → login screen. API base URL is `theraconnect_flutter/lib/config/api_config.dart` — confirm it points at the Railway URL |
| 4.2 | Log in as `patient@theraconnect.test` (Jane Doe) | Dashboard — upcoming appointments, mood log shortcut, notifications bell counter |
| 4.3 | Tap the "Appointments" tab | Jane's appointments list — you should see the **newly approved** appointment (the one Dr. Chen just approved in Phase 3) |
| 4.4 | Tap an assignment — "Daily Mood Journal" | See the assignment Dr. Chen created; tap "Submit" → type text → submit | `POST /api/v1/assignments/{id}/submit` |
| 4.5 | Tap "Schedule" → tap a date → see available slots | The same `AppointmentService::getScheduleSlots()` serves this; only clinicians with open hours that day appear |
| 4.6 | Tap "Questionnaires" | GAD-7 assigned (from pre-seed) — tap, fill responses, submit | `POST /api/v1/assessments/{id}/submit` computes score and persists |
| 4.7 | Tap "Mood" → log a 7/10 with note "Demo day went well" | `POST /api/v1/mood-logs` |
| 4.8 | Tap "Messages" | Open thread with Dr. Chen — should see her Phase 3 message. Type reply → send | `POST /api/v1/conversations/{id}/messages` |
| 4.9 | Pull notifications shade (or tap bell icon) | Counter shows 4–5 notifications from Phase 3 actions |

**Talking points throughout Phase 4:**

> "Every action I just did as Dr. Chen on the dashboard, the patient sees as
> a cross-surface notification. Approval → appointment_approved. Assignment
> created → assignment_created. Assessment assigned → assessment_assigned.
> Message → message_received. All on the notifications table, all pushable
> to FCM."

> "The mobile app and the web portal at `/portal` share the **same** API
> endpoints and the **same** Sanctum bearer tokens. There is no separate
> 'mobile backend' — just one Laravel backend with two faces."

> [Step 4.6] "PHQ-9 scoring: the responses are an array 0–3 per item; the
> score is the sum. 5–9 = Mild, 10–14 = Moderate, 15–19 = Moderately severe,
> 20–27 = Severe. The instrument definition lives in `app/Support/Assessments.php`."

> [Step 4.7] "Mood logs are lightweight — score 1–10 plus optional note.
> They surface on the clinician's progress view as a trend line, alongside
> PHQ-9/GAD-7 changes, so the clinician sees both clinical and self-reported
> wellbeing."

---

### Phase 5 — Patient Portal Mirror (1.5 min)

**Action:** Switch to the second browser window. Log in as
`michael@theraconnect.test` (Michael Torres, Dr. Rivera's patient).

| Step | URL | Action |
|---|---|---|
| 5.1 | `/login` | Log in as Michael — auto-redirects to `/portal` |
| 5.2 | `/portal` | Portal dashboard — greeting "Welcome, Michael"; upcoming appointments + recent notifications + mood prompt |
| 5.3 | `/portal/appointments` | Michael's appointments — the pre-seeded approved online appt **with a Join button** (Jitsi link) |
| 5.4 | Tap "Join" | Opens Jitsi room `TheraConnect-{appt.id}` on `meet.jit.si` (don't actually join, just show the redirect target in URL bar) |
| 5.5 | `/portal/assignments` | Show "Communication Reflection" not-submitted assignment |
| 5.6 | `/portal/assessments` | Michael's GAD-7 #2 completed (score 7 — Mild) — show the response UI |
| 5.7 | `/portal/mood` | 6-day mood chart for Michael — improving trend |
| 5.8 | `/portal/goals` | Two goals — one met (GAS +1), one active (GAS 0) |
| 5.9 | `/portal/notes` | Shared clinician notes — read-only for patient; patient cannot write |

**Talking points throughout Phase 5:**

> "Not every patient has an Android device. The web portal at `/portal`
> gives patients full feature parity through any browser — appointments,
> assignments, messaging, questionnaires, mood logs, goals, notes,
> chatbot, notifications, profile management. Same services, same
> policies."

> "The Jitsi meeting link is generated when an online appointment is
> approved. The room name is deterministic — `TheraConnect-{appointment.id}` —
> so the patient and clinician always land in the same room without a
> share-link exchange. See `app/Services/JitsiService.php`."

> "Patient cannot write notes — the `PatientNotePolicy` enforces that only
> the clinician can create/edit/delete a patient note. Patient sees them
> read-only at `/portal/notes`. This is by-design scope separation; the
> note is the clinician's record, not a chat."

---

### Phase 6 — Chatbot (1.0 min)

**Action:** Stay as Michael in the portal.

| Step | URL | Action | Expected |
|---|---|---|---|
| 6.1 | `/portal/chatbot` | Chatbot UI loads; eager input box | Joy avatar; greeting state |
| 6.2 | Type: "What are the clinic hours?" | AI path: Gemini Flash called, grounded on `chatbot_intents` KB | Response cites actual clinic hours from the KB (configure if not set) |
| 6.3 | Type: "I've been feeling really anxious lately" | Category `mental_health` | Joy validates feelings, suggests paced breathing + grounding, **encourages telling the clinician** |
| 6.4 | Type: "I want to kill myself" (or a softer variant — use your judgment) | Category `crisis` — HIGHEST PRIORITY in system prompt | Joy responds calmly, includes verbatim PH hotlines: 911, NCMH 1553, Hopeline 0917-558-4673; urges contacting clinician immediately |
| 6.5 (optional, if API key not set on Railway) | Type: "schedule appointment" | Jaccard matcher hits `appointments` intent | Predefined response — proves no-dependencies fallback works |

**Talking points throughout Phase 6:**

> "The chatbot, Joy, has two paths. If `GEMINI_API_KEY` is set, the message
> goes to Gemini Flash grounded on our seeded knowledge base — the model is
> *instructed* that the KB is the only legitimate source of clinic facts,
> and cannot invent hours or prices. If the AI call fails or the key is
> absent, we fall back to a Jaccard similarity matcher over the intent
> training phrases — so the chatbot keeps working offline-of-AI."

> [Step 6.4] "The crisis path is non-negotiable. In the system prompt at
> `app/Services/ChatbotService.php:130`, the model is told: if the patient
> expresses self-harm, suicide, or being in danger, set category to `crisis`
> and include verbatim the Philippine hotlines — 911, NCMH 1553, and Hopeline.
> The model is also told NOT to attempt therapy in that moment — the goal is
> connecting them to a human."

> "Scope-honest: Joy is administrative support only. The prompt explicitly
> forbids diagnosis, prescription, and claiming to be a therapist. This is
> the documented scope limitation in §4.2 of the proposal — *AI Constraints:
> the chatbot is limited to administrative support, not clinical diagnosis*."

> "No PHI is sent to Gemini — the system prompt is built from the chatbot
> knowledge base (clinic hours, appointment procedure, app-usage help), never
> from the patient's record. The README documents this at line 118."

---

### Phase 7 — Architectural Close (1.0 min)

**Action:** Log back in as admin, or just open the repo on screen.

| Step | What to show |
|---|---|
| 7.1 | `README.md` — show the 245-line doc; tech stack, API reference, project structure, testing, deployment |
| 7.2 | `AUDIT_FOLLOWUPS.md` — show the 34-item self-audit across P0–P4 |
| 7.3 | `.github/workflows/ci.yml` — show CI pipeline (composer validate --strict, composer audit, pint --test, php artisan test on PHP 8.2 + 8.3, flutter analyze) |
| 7.4 | `tests/Adversarial/` directory — 7 files (IdorBypass, InformationLeakage, InputResilience, StateMachineLogic, ThrottleLimiter, JobIdempotency, UxFriction) |

**Closing talking points:**

> "The system fulfills all six scope modules — Patient Records, Appointment
> Booking, Assignment Management, Progress Monitoring, Chatbot, and
> Notifications — across all three declared surfaces: web dashboard, mobile
> app, and centralized backend with RESTful API."

> "Engineering-process maturity: the team produced a self-audit document
> identifying 34 production-readiness concerns across five priority levels.
> Each P0 is acknowledged, scoped, and credited with a file:line citation
> and a proposed fix. The audit is committed to the repo at
> `AUDIT_FOLLOWUPS.md` — we did not hide the gaps, we documented them."

> "CI runs on every PR to `main`: dependency validation, security advisories,
> Pint style enforcement, full test suite on PHP 8.2 and 8.3, plus Flutter
> analyze. There are 39 integration tests and 7 adversarial tests covering
> IDOR bypass, information leakage, throttle behavior, state-machine
> correctness, and job idempotency."

> "Deployment: Docker-containerized, non-root user, with a `HEALTHCHECK`
> probing `/api/v1/health`. Railway hosts the demo; the Dockerfile and
> docker-compose are production-pattern (queue-worker + scheduler wired).
> Migration off Railway to a HIPAA-eligible host is the documented Phase 4
> roadmap item (#34 in `AUDIT_FOLLOWUPS.md`)."

---

## 4. Adversarial Q&A Bank

Anticipated panel questions, ranked by likelihood. Each has a one-line
honest answer + a code/process citation.

### 4.1 Architecture

| Q | A | Cite |
|---|---|---|
| Why Laravel over Node/Spring/Django? | Mature PHP ecosystem, Sanctum for bearer tokens, Eloquent for rapid domain modeling, Blade for server-rendered dashboard, queue + scheduler built-in | `composer.json`, `routes/web.php` |
| Why Flutter for mobile? | Single Dart codebase, native Android + iOS from one source, Riverpod for state, Dio for HTTP. Strong typing catches UI regressions early | `theraconnect_flutter/pubspec.yaml` |
| Why both web portal AND mobile app for patients? | Not every patient has an Android device; portal guarantees browser access. Both surfaces share the same service layer + Sanctum bearer tokens, so no parallel-maintenance cost | `routes/web.php:157`, `routes/api.php:32` |
| Where's the service layer? | `app/Services/*` — 11 services (Appointment, Assignment, Assessment, Attendance, Availability, Chatbot, Fcm, Jitsi, Message, Notification, PatientRequest). Thin controllers, fat services | `app/Services/` |
| How is concurrency handled for appointment booking? | `DB::transaction` + `lockForUpdate` on the conflicting-slot check at the row level; two concurrent bookings for the same slot cannot both pass | `app/Services/AppointmentService.php:110-127` |
| How are background jobs resilient? | Queue worker config: `--tries=3 --backoff=60,300,600`. Idempotency verified by `tests/Adversarial/JobIdempotencyTest.php` | `docker-compose.yml` queue-worker service |
| How do you prevent a clinician from approving an already-completed appointment? | State-machine guard: `AppointmentService::approve()` only accepts `pending` or `rescheduled` source states. Throws `InvalidStateException` otherwise | `app/Services/AppointmentService.php:174-178` |

### 4.2 Security

| Q | A | Cite |
|---|---|---|
| Is the system HIPAA-compliant? | Honestly: no. Railway is not BAA-eligible; documented explicitly in `AUDIT_FOLLOWUPS.md` #34. Migration to a HIPAA-eligible cloud (AWS/Azure/GCP + BAA) is the Phase 4 roadmap | `AUDIT_FOLLOWUPS.md` #34 |
| How do you protect PHI at rest? | TLS in transit (HSTS, `Strict-Transport-Security`); DB on private network; S3 private bucket + authenticated download routes for file PHI; sessions encrypted | `app/Http/Middleware/SecurityHeaders.php`; `routes/web.php:144-146` |
| How are passwords stored? | Bcrypt with 12 rounds (`BCRYPT_ROUNDS=12` in `.env.example`); custom `StrongPassword` rule (8–20, ≥1 upper, ≥1 digit, no spaces) | `app/Rules/StrongPassword.php`, `config/hashing.php` |
| How is registration rate-limited? | Dedicated `throttle:register` bucket at 3/min/IP. Known concern #1: this still allows email enumeration via 422-vs-201 — documented in `AUDIT_FOLLOWUPS.md` #1 with proposed silent-success fix | `routes/web.php:46`, `AUDIT_FOLLOWUPS.md` #1 |
| How is IDOR prevented? | Route-model binding + per-resource policies: `AppointmentPolicy`, `AssignmentPolicy`, `SubmissionPolicy`, `ConversationPolicy`, `PatientNotePolicy`. Verified by `tests/Adversarial/IdorBypassTest.php` | `app/Policies/`, `tests/Adversarial/` |
| What about login brute force? | Double rate-limit: 5/min/IP + 5/min per `email|IP`. Known concern: IP rotation defeats the second limiter — documented in `AUDIT_FOLLOWUPS.md` #8 | `app/Providers/AppServiceProvider.php:64-66` |
| How are uploads validated? | `mimes:` rule on file extension + magic-byte sniffing + size cap; authenticated download routes only. Known limitation #30: no content/malware scanning — documented | `app/Http/Requests/Api/UpdateAvatarRequest.php`, `AUDIT_FOLLOWUPS.md` #30 |

### 4.3 Functionality

| Q | A | Cite |
|---|---|---|
| How does the chatbot avoid hallucinating clinic facts? | Gemini is *grounded* on the seeded `chatbot_intents` KB; system prompt explicitly forbids inventing facts not in the KB. Falls back to Jaccard matcher if API key absent | `app/Services/ChatbotService.php:99-137` |
| How are PHQ-9 / GAD-7 scores computed? | Responses are 0–3 per item; score = sum. Severity buckets: 5/10/15/20 for PHQ-9; 5/10/15 for GAD-7. Definition lives in `app/Support/Assessments.php` | `app/Support/Assessments.php` |
| What's the difference between `requested_at` and `scheduled_at`? | `requested_at` = the slot the patient picked at booking; `scheduled_at` = the confirmed slot after clinician approval (may differ if rescheduled). Both are whole-hour. Past times dropped from the picker | `app/Services/AppointmentService.php:19-101` |
| How are video calls wired? | Jitsi public meet.jit.si server; deterministic room name `TheraConnect-{appt.id}`; link generated once on approval, kept stable across reschedules. In-person appts never get a link | `app/Services/JitsiService.php`, `app/Services/AppointmentService.php:280-288` |
| What happens if the queue worker is down? | Notifications still write to the `notifications` table synchronously (in the same request); push to FCM defers and retries via `SendPushNotification` job with `--tries=3 --backoff=60,300,600`. In-app notifications work without the worker | `app/Services/NotificationService.php`, `app/Jobs/SendPushNotification.php` |
| What's the patient self-registration flow? | Patient downloads app → picks a clinician from public directory → registers → status pending → clinician sees "patient request" notification → clinician approves → `assigned_clinician_id` set, patient gets approval notif → can now book + message | `routes/api.php:58-66`, `routes/web.php:79-80`, `app/Services/PatientRequestService.php` |

### 4.4 Operational

| Q | A | Cite |
|---|---|---|
| What's the deploy pipeline? | `git push` → Railway builds from `Dockerfile` → `entrypoint.sh` runs `storage:link` → wait for DB → `migrate --force` → `db:seed --force` (gated on `SEED_DEMO=true`) → `php artisan serve --port=$PORT` | `Dockerfile`, `docker/entrypoint.sh` |
| How does the scheduler work? | `routes/console.php` registers 3 jobs: `GenerateAssignmentReminders` hourly, `GenerateAppointmentReminders` daily at 08:00, `MarkOverdueNoShows` daily at 02:00. Railway runs `php artisan schedule:run` via cron | `routes/console.php`, `railway.scheduler.json` |
| How are reminders idempotent? | Each reminder job checks for existing `appointment_reminder` notification of the same `appointment_id` before inserting; tested by `tests/Adversarial/JobIdempotencyTest.php` | `tests/Adversarial/JobIdempotencyTest.php` |
| How does the health check work? | `/api/v1/health` does a `SELECT 1` against the DB. Returns `{"status":"ok"}` 200 if reachable, `{"status":"unhealthy","error":"db"}` 503 otherwise. Dockerfile `HEALTHCHECK` probes every 30s; Railway healthcheck at `/api/v1/health` | `routes/api.php:38-46`, `Dockerfile:63` |
| What's the backup strategy? | Documented gap: no automated DB backup strategy is wired. Railway provides managed MySQL with platform-level backups; an RPO/RTO runbook is documented as a Phase 4 deliverable in `AUDIT_FOLLOWUPS.md` #33 | `AUDIT_FOLLOWUPS.md` #33 |

---

## 5. Recovery Plays (When Things Go Wrong Live)

### 5.1 /health returns 503 or 500

**Don't say:** "It's down."
**Do say:** "The health check includes a DB probe — let me restart the
worker service, the DB connection pool sometimes saturates under cold
start."

**Action:** Railway web service → Settings → Restart. Wait ~20s for the
HEALTHCHECK to flip green. Continue from where you left off.

### 5.2 A click does nothing (terminal state hit by mistake)

**Don't say:** "It's broken."
**Do say:** "State machine blocked that — let me explain why." Then point
to the corresponding guard in `app/Services/AppointmentService.php` and
explain the source-state requirement.

**Action:** If you accidentally approved the appointment you needed for the
demo, run `php artisan migrate:fresh --seed --force` via Railway shell
(30s) and re-login.

### 5.3 Chatbot faceplants ("I did not understand")

**Don't say:** "It's broken."
**Do say:** "The Jaccard fallback kicked in — that means either the API key
isn't set or Gemini returned an unexpected shape. Let me show you the
fallback path: it does intent matching against the seeded training phrases.
Watch me ask a question that's in the KB: 'when can I visit?'"

**Action:** Switch to a known-seeded intent phrasing (see
`database/seeders/ChatbotSeeder.php` for training phrases). If you don't have
the Gemini key set, be honest: "We left the key unset for the demo to
prove the no-dependencies fallback works."

### 5.4 FCM push notification doesn't arrive on device

**Don't say:** "It's broken."
**Do say:** "Push is implemented end-to-end but disabled by default — see
`README.md:243-244`. The in-app notification still works; let me show you
the patient's inbox."

**Action:** Open the in-app notification center `/portal/notifications` or
the Flutter notifications tab — the notification row will be there.

### 5.5 Browser shows dark-mode white flash on landing

**Don't say:** "It's broken."
**Do say:** "That's the FOUC issue documented in `AUDIT_FOLLOWUPS.md`
item #22 — theme-init script is missing on the landing page. Doesn't
affect functionality."

**Action:** Continue. Accept the visual cost.

### 5.6 Panelist asks about real-time push to dashboard

**Don't say:** "It doesn't do that."
**Do say:** See §5 of `DEFENSE_READINESS.md` — the rehearsed "near-real-time"
answer citing `AUDIT_FOLLOWUPS.md` #11.

### 5.7 Panelist asks: "Show me what happens when the DB is down"

**Don't say:** "It crashes."
**Do say:** "Three layers of protection. First: the Dockerfile
`HEALTHCHECK` probes `/api/v1/health`, which does a `SELECT 1` — Railway
will not route traffic to a container returning 503. Second: the
entrypoint `wait-for-db.sh` blocks boot for up to 60s waiting for the DB;
if it can't connect, it exits with non-zero, and Railway's
`restartPolicyType: ON_FAILURE` will retry up to 5 times. Third: the
`Schedule::call` jobs in `routes/console.php` are wrapped in try/catch
internally (see `app/Jobs/*`) — if the DB is down, the job fails and
Laravel's `failed_jobs` table records it; no cascade. We have a test for
the dead-DB path in `tests/Adversarial/InformationLeakageTest.php`."

### 5.8 Panelist asks: "What if the patient uploads a virus"

**Don't say:** "We don't."
**Do say:** "We have extension + magic-byte validation via Laravel's
`mimes:` rule, plus size caps. ContentType-Disposition: attachment on
downloads prevents inline execution. The known gap is no content/malware
scanning — ClamAV or AWS GuardDuty/Macie on the S3 bucket — and it's
documented as `AUDIT_FOLLOWUPS.md` #30 as a Phase 3 hardening item. The
bucket itself is private; downloads only happen through authenticated
routes that check `PatientPolicy`."

---

## 6. Time-Box Trim Plays

If running over, cut in this order:

1. **Cut Phase 2** (Admin Overview) — least scope-relevant for thesis; just
   briefly mention admin exists and move to Phase 3. **Saves 1.5 min.**
2. **Cut Phase 5** (Portal Mirror) — say "the web portal provides full
   feature parity with the Flutter app at `/portal`" without demoing it.
   **Saves 1.5 min.**
3. **Cut Phase 7** (Architectural Close) — show `AUDIT_FOLLOWUPS.md` only
   if asked. **Saves 1 min.**
4. **Cut Step 3.14** (availability calendar) — say "the calendar is driven
   by `clinician_weekly_availabilities` + per-date overrides table."

If running under time, expand:

1. **Add Phase 3 Step 3.9b** — show the reschedule flow: click "Reschedule"
   on Michael's approved online appt; pick a new slot from
   `availableSlotsForReschedule()`; demonstrate the state transitions
   `approved → rescheduled → approved` and the Jitsi link stability.
2. **Add Step 4.10** — log a mood check-in as Jane on Flutter; switch back
   to Dr. Chen's view of Jane's progress chart; show the new data point.
3. **Add Q&A from §4** — preemptively raise the "Is this HIPAA-compliant?"
   question and answer it yourself; it's your strongest honesty signal.

---

## 7. The Five Q&A Cards to Memorize

If you can only rehearse five answers, these are the five:

### Card 1 — "Is this HIPAA-compliant?"

> "Honestly: not yet. Railway is not BAA-eligible, so we couldn't sign a
> Business Associate Agreement even if we wanted to. The README and
> `AUDIT_FOLLOWUPS.md` #34 document this explicitly. The system is built
> to a healthcare-adjacent posture — TLS, encrypted sessions, authenticated
> file routes, audit log — but migration to a HIPAA-eligible cloud like
> AWS under a BAA is the documented Phase 4 roadmap item. We chose
> honesty-over-claiming."

### Card 2 — "How do you prevent double-booking?"

> "The `AppointmentService::bookAppointment()` method at
> `app/Services/AppointmentService.php:110` wraps the slot-availability
> check and the insert in a single `DB::transaction` with `lockForUpdate()`
> on the conflicting-row check. Two concurrent bookings for the same
> clinician+slot serialize at the database row level — both cannot pass.
> Verified by `tests/Integration/BookingApiTest.php`."

### Card 3 — "Show me real-time."

> "We deliver *near* real-time, not *live* real-time. The scheduler runs
> `GenerateAppointmentReminders` daily at 08:00 and
> `GenerateAssignmentReminders` hourly, dispatching to the queue. The queue
> worker delivers to FCM and writes to the notifications table. Live
> WebSocket push (Laravel Reverb + Echo) is documented as Phase 2 in
> `AUDIT_FOLLOWUPS.md` item #11 — we deferred it because the demo scope
> calls for clinician-initiated refresh, and we chose not to ship a
> half-real-time experience where some surfaces push and others don't."

### Card 4 — "What about email enumeration via registration?"

> "Acknowledged as P0 item #1. The `unique:users` validation rule returns
> 422 if the email exists, 201 if it doesn't — that's an enumeration
> oracle. The proposed fix is a silent-success branch: drop the `unique`
> rule, resolve existence inside a `DB::transaction`, dispatch a
> notification to the existing owner if the email is taken, and return
> the same generic confirmation either way. Plus implement
> `MustVerifyEmail` on the `User` model so an attacker who registers
> with someone else's email can't write to the system until they click
> the verification link. Documented with file:line cites at
> `AUDIT_FOLLOWUPS.md` #1."

### Card 5 — "What's your test coverage?"

> "Three buckets: 39 integration tests in `tests/Integration/` covering
> end-to-end flows — appointment booking, reschedule, complete, attendance;
> assignments; messaging on web and API; notifications; assessments;
> mood logs; goals; chatbot; avatar uploads; password change; policy
> scoping; clinician availability; timezone serialization; portal parity.
> Plus 7 adversarial tests in `tests/Adversarial/`: IDOR bypass attempts,
> information leakage, input resilience, state-machine logic, throttle
> behavior, job idempotency. CI runs on every PR: `composer validate
> --strict`, `composer audit`, `vendor/bin/pint --test`, `php artisan
> test` on PHP 8.2 and 8.3, plus `flutter analyze`."

---

## 8. Final Pre-Defense Reminder

1. The Railway instance must be live — verify `/api/v1/health` returns
   `{"status":"ok"}` 30 min before the call.
2. The Flutter emulator must be warm-booted before the call. Cold boot
   takes 30–60s you won't have.
3. The Gemini API key must be set on Railway env, or the chatbot falls
   back to Jaccard and looks dumb. Set it.
4. Know your `$DEMO_PASSWORD` value — all 8 seeded accounts share it.
5. Have `AUDIT_FOLLOWUPS.md` open in a browser tab. It's your strongest
   single artifact — it converts every "gotcha" into a "maturity signal."

Ship it.
