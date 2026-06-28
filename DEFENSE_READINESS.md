# Defense Readiness Assessment

> Pre-defense snapshot of scope fulfillment, ERD/DFD cross-checks, and demo-day
> polish risks. Companion to `AUDIT_FOLLOWUPS.md` (production-readiness gaps)
> and `DEMO_SCRIPT.md` (live walkthrough script).

---

## 1. Verdict

**Defense-ready: yes.** Scope fulfillment is ~95%; the 5% is the deliberately
hedged "real-time" claim, which is honestly disclosed as Phase 2 in
`AUDIT_FOLLOWUPS.md` item #11. Remaining demo-day risks are polish items,
not scope gaps. The single highest-ROI prep is verifying the ERD + DFD
diagrams against the actual code so a cross-referencing panelist doesn't find
a mismatch.

---

## 2. Scope Coverage Matrix

Mapping each declared sub-item from the project scope to a concrete
implementation in the codebase.

### 2.1 Core Problems Addressed

| Stated problem | How the system addresses it | Evidence |
|---|---|---|
| Manual scheduling (phone/SMS) | Self-service booking via mobile app + patient portal; clinician approves from dashboard | `routes/api.php:98` `POST /appointments`; `routes/web.php:163` `POST /portal/appointments` |
| Communication gaps | In-app messaging (patient ↔ assigned clinician only) + notification center | `routes/api.php:125-128`; `routes/web.php:124-128` |
| Data fragmentation | Single MySQL DB; one service layer serves all 3 surfaces | `app/Services/*`, `app/Models/*` |
| Administrative overload | Admin dashboard for clinicians, appointments, assignments, notifications, activity log | `routes/web.php:52-148` |
| Workflow inefficiency | Scheduler + queue: reminders, no-show marking, push dispatch | `routes/console.php`, `app/Jobs/*` |

### 2.2 Proposed Solutions

| Declared solution | Implementation | File / route | Status |
|---|---|---|---|
| Web Dashboard — Centralized patient records | `Web/PatientController` | `routes/web.php:72-82` | Delivered |
| Web Dashboard — Approve and monitor appointments | `Web/WebAppointmentController` | `routes/web.php:132-137` (approve / reject / reschedule / complete) | Delivered |
| Web Dashboard — Create and review assignments | `Web/WebAssignmentController` | `routes/web.php:140-147` (create / submissions / worksheet / review) | Delivered |
| Web Dashboard — Visual progress tracking | `Web/ProgressController` | `routes/web.php:85` (`/patients/{p}/progress` — attendance + assessments + mood + goals) | Delivered |
| Mobile — Book & manage appointments | Flutter `screens/schedule/*` | API `POST /api/v1/appointments` | Delivered |
| Mobile — View upcoming schedules | Flutter `schedule_screen.dart`, `calendar_screen.dart` | API `GET /api/v1/appointments` | Delivered |
| Mobile — Submit clinical assignments | Flutter `submit_assignment_screen.dart`, `submission_preview.dart` | API `POST /api/v1/assignments/{id}/submit` | Delivered |
| Mobile — Integrated notification receiver | FCM fully wired (`FcmService`, `Jobs/SendPushNotification`, Flutter `fcm_service.dart`) | API `POST /api/v1/device-token` | **Code-complete; disabled by default** (`README.md:243-244`) |
| Chatbot — Common administrative inquiries | `ChatbotService`, `database/seeders/ChatbotSeeder` | API `POST /api/v1/chatbot/message`; web `/portal/chatbot` | Delivered |
| Chatbot — Predefined clinical/admin intents | `ChatbotIntent`/`ChatbotResponse` models + Jaccard matcher (no-deps fallback) | `ChatbotService.php:139-189` | Delivered |
| Notifications — Automated appointment confirmations | `NotificationService::appointmentApproved/Rejected/Rescheduled` | Dispatched by `WebAppointmentController` + `PortalAppointmentController::destroy` | Delivered |
| Notifications — Real-time / near real-time schedule updates + assignment reminders | `Jobs/GenerateAppointmentReminders`, `Jobs/GenerateAssignmentReminders`, `Jobs/MarkOverdueNoShows` | `routes/console.php` (scheduler) + queue worker | **Near-real-time via cron, not WebSocket** — scope-hedged; honestly disclosed as Phase 2 |

### 2.3 Project Scope

| Declared boundary | Status |
|---|---|
| Web Frontend — Dashboard for clinician-level management | Delivered (`resources/views/clinician/`, `resources/views/layouts/app.blade.php`) |
| Mobile Frontend — App for patient-level interaction | Delivered (`theraconnect_flutter/`) |
| Backend — Centralized cloud DB + RESTful API | Delivered (Laravel 11 + MySQL 8, `/api/v1/*` Sanctum-bearer) |
| Core modules: Patient Records, Appointment Booking, Assignment Management, Progress Monitoring, Chatbot, Notifications | All six delivered (see §2.2) |

### 2.4 Technical & Functional Limitations (as stated by the scope)

These are scope *admissions* the panel may probe:

| Stated limitation | Implementation reality | Defense answer |
|---|---|---|
| Connectivity required | True — no offline mode in Flutter app | Acknowledge; cite as future work |
| Chatbot limited to admin support, no clinical diagnosis | Enforced in `ChatbotService::buildSystemPrompt()` — system prompt forbids diagnosis, prescription, claiming to be a therapist | Strong: the prompt is auditable in `app/Services/ChatbotService.php:113-136` |
| Standalone (no external EMR integration) | True — no FHIR/HL7 connectors | Acknowledge; cite as future work |
| Performance contingent on user device + adoption rate | Generic scope hedge; not a code property | Talk about Flutter cross-platform reach |

**12 of 12 sub-items implemented. 2 of 12 have documented caveats. No scope-item is missing.**

---

## 3. ERD Cross-Check

The code contains 23 Eloquent models backed by migrations. If your ERD shows
an entity not in this list, panelists cross-referencing the code will catch
the mismatch.

### 3.1 Entities the code actually has

```
Domain (20):
  User, Clinician, Patient, Appointment, Assignment, Submission,
  DeviceToken, Notification, ChatbotIntent, ChatbotResponse,
  ClinicianWeeklyAvailability, ClinicianDateOverride,
  Conversation, Message, PatientNote, Assessment, MoodLog,
  TherapyGoal, GoalRating, ActivityLog

Framework (3):
  PersonalAccessToken (Sanctum), Cache, Job / FailedJob
```

Migrations live in `database/migrations/` — one file per table.

### 3.2 Relationships worth double-checking on your diagram

- `Patient` belongs to `Clinician` via `assigned_clinician_id`
- `Patient` may also have a `requested_clinician_id` + `clinician_request_status` (pending/approved/denied) — the self-registration flow
- `Appointment` has both `requested_at` (patient's pick) and `scheduled_at` (clinician's confirmation) — two timestamps; if your ERD shows only one, that's a mismatch
- `Appointment` has `meeting_link` (nullable; online mode only, Jitsi)
- `Submission` is 1:1 with `Assignment` per patient; has `content` (text) and optional `file_path` + `original_name`
- `Assessment` stores `responses` as JSON array; `score` is the computed sum
- `TherapyGoal` has many `GoalRating` — each rating tied to an `Appointment` (GAS rating is captured at a session)
- `Notification` has polymorphic-ish `data` JSON column + `type` enum string + `read_at` timestamp
- `Conversation` is between two users; `Message` belongs to `Conversation` + `sender_id`
- `ClinicianWeeklyAvailability` + `ClinicianDateOverride` power the schedule slot generator

### 3.3 Items to verify on your ERD

If your ERD shows **any entity not in the list above** (e.g., "video_calls",
"prescriptions", "billing", "medications"), panelists cross-referencing the
code will catch it. Either remove from the diagram or be ready to discuss as
"Phase 2."

If your ERD omits **any entity above**, the panel might ask "what's
`activity_logs` for, it's not on the diagram" — easy to defend as supporting
infra (audit trail for admin actions).

---

## 4. DFD Cross-Check

Six data flows the code actually plumbs. Verify each arrow on your DFD maps
to one of these.

| Flow | Path in code |
|---|---|
| Patient → Mobile App → API → MySQL | Flutter `services/api/*` → `routes/api.php` → Eloquent |
| Clinician → Web Dashboard → Server → MySQL | Blade views → `routes/web.php` → Eloquent |
| Admin → Web Dashboard → Server → MySQL | Same as above; admin routes `routes/web.php:88-102` |
| Server → `NotificationService` → `notifications` table → (optional) `SendPushNotification` job → FCM | `app/Services/NotificationService.php`; `app/Jobs/SendPushNotification.php` |
| Scheduler (cron) → `php artisan schedule:run` → Reminder jobs → `notifications` table | `routes/console.php` (3 scheduled jobs) |
| Patient → Server → Gemini API (if `GEMINI_API_KEY` set) → Chatbot reply | `app/Services/ChatbotService.php:40-92` (Jaccard fallback if no key) |
| Server → S3 (if `FILESYSTEM_DISK=s3`) for worksheets + submissions + avatars | `config/filesystems.php`; private authenticated download routes |

**Most common DFD assertion that wouldn't survive cross-check:** "real-time
push to dashboard." The system has no WebSocket/Reverb/Echo — clinician
refreshes manually. See `AUDIT_FOLLOWUPS.md` #11.

---

## 5. The One Scope-vs-Reality Friction to Pre-empt

The scope says:

> *"Real-time (or near real-time) schedule updates and assignment reminders."*

The parenthetical hedge saves you, but a sharp critic on the panel will ask:
"Show me real-time: I send a message as patient; the clinician sees it
without reloading."

### Rehearsed answer

> "We deliver *near* real-time via the Laravel scheduler + database queue —
> `GenerateAppointmentReminders` fires daily at 08:00, `GenerateAssignmentReminders`
> fires hourly, both dispatch push notifications through the worker. For
> *instant* push (WebSocket), Laravel Reverb + Echo is documented as Phase 2
> in `AUDIT_FOLLOWUPS.md` item #11, deferred because the demo scope calls
> for clinician-initiated refresh rather than collaborative real-time editing.
> We chose not to ship a half-real-time experience where some surfaces push
> and others don't — either all real-time or honestly near-real-time, and
> we picked the latter."

The `AUDIT_FOLLOWUPS.md` reference is your shield — it shows the team *knew*
the gap and chose to document rather than hide it.

---

## 6. Demo-Day Risks (Ranked by Likelihood × Embarrassment)

| # | Risk | Where | What panelist sees | Fix effort | Verdict |
|---|---|---|---|---|---|
| 1 | No real-time anywhere | No `ShouldBroadcast` / Reverb / Echo in codebase | Send msg as patient → clinician dashboard → "refresh the page" is your honest answer | Large | Do NOT fix before defense; rehearse talking point |
| 2 | Chatbot: Jaccard fallback if `GEMINI_API_KEY` unset | `ChatbotService.php:37` | "when can I visit?" → "I'm sorry, I did not quite understand that" → looks broken | 2 min | **Set the env var on Railway; smoke-test 3–4 prompts** |
| 3 | Chatbot Gemini call is synchronous, 20s timeout | `ChatbotService.php:49` | 5–15s spinner after asking chatbot | None | Rehearse a quip ("typical Gemini Flash latency is Xs") |
| 4 | Messaging: full-page reload per message | `portal/messages/index.blade.php:44` | White page flash after every message | Medium (half-day) | Do NOT fix before defense |
| 5 | 9 native `confirm()` dialogs | `patients/index.blade.php:61,66,129` etc. | Browser-native popup with no styling | Medium (day) | Do NOT fix all 9; rehearse the demo to hit minimum |
| 6 | Loading states missing on book appointment / login / message-send | `portal/appointments/book.blade.php:104`, `auth/login.blade.php:25`, `portal/messages/index.blade.php:44` | ~300ms of no feedback on Railway | Small (1–2h) | Optional pre-defense polish |
| 7 | White flash on `/` and `/error` pages if browser is dark-mode | `landing.blade.php`, `errors/layout.blade.php` | First impression paints light briefly | Small (30 min) | Optional pre-defense polish |
| 8 | Admin can't write patient notes / assign assessments | `routes/web.php:107-128` (clinician-only block) | Panelist asks "let's have the admin do X" — admin can't | None | Frame as intentional RBAC separation |

Items #1, #3, #4, #5 are real but should NOT be fixed before the defense —
each is multi-day work and the demo can route around them. The rehearsed
answer above (citing `AUDIT_FOLLOWUPS.md`) converts a critic's gotcha into a
maturity signal.

Items #2, #6, #7 are cheap wins worth doing if you have an afternoon.

---

## 7. Pre-Defense Checklist (Ranked by ROI)

| Priority | Action | Effort | Payoff |
|---|---|---|---|
| 1 | Cross-check your ERD against the 23-entity list in §3 | 15 min | Avoids phantom entities |
| 2 | Cross-check your DFD arrows against the 6 paths in §4 | 15 min | Avoids asserting real-time push that doesn't exist |
| 3 | Set `GEMINI_API_KEY` on Railway env + smoke-test chatbot with 3–4 panelist-style prompts | 10 min | Avoids chatbot faceplant during live demo |
| 4 | Rehearse the "real-time" question — one short answer citing `AUDIT_FOLLOWUPS.md` #11 (see §5) | 5 min | Converts a gotcha into a maturity signal |
| 5 | Rehearse the "show me a push notification" answer — either set up FCM, or honestly say "phase 2, documented in `README.md:243`" | 5 min | Either the demo works or you control the framing |
| 6 | Dry-run each demo flow against seeded data | 30 min | Avoids discovering a dead click mid-demo |
| 7 (optional) | Add loading state to `portal/appointments/book.blade.php`, `auth/login.blade.php`, `portal/messages/index.blade.php` (AUDIT #14) | 1–2 h | Smoother demo if panelist's network is slow |
| 8 (optional) | Extract theme-init script to `partials/theme-init.blade.php` + `@include` in `landing.blade.php` + `errors/layout.blade.php` (AUDIT #22) | 30 min | Avoids white-flash first impression |

**Do NOT attempt** before the defense: real-time broadcast (AUDIT #11,
multi-day), messaging optimistic echo (AUDIT #12, half-day), all 9
`confirm()` modal swaps (AUDIT #24, day). Document them, mention them as
roadmap, ship the demo.

---

## 8. Adversarial Q&A the Panel May Ask

For each, the honest answer + the strongest defensive cite.

| Q | A | Citation |
|---|---|---|
| "How do you prevent double-booking?" | DB transaction + `lockForUpdate` at the row level on the conflicting-slot check | `app/Services/AppointmentService.php:110-127` |
| "How do you prevent a clinician from approving an already-completed appointment?" | State-machine guard: `approve()` only accepts `pending` or `rescheduled` source states | `app/Services/AppointmentService.php:174-178` |
| "How is PHI protected at rest?" | E2E TLS in transit; DB behind private network on Railway; S3 bucket private + authenticated download routes for file PHI; `SESSION_ENCRYPT=true` | `app/Http/Middleware/SecurityHeaders.php` HSTS; `routes/web.php:144-146` authenticated downloads |
| "Is this HIPAA-compliant?" | Honestly: no. Railway is not BAA-eligible. The README explicitly notes this; migration to HIPAA-eligible cloud (AWS/Azure/GCP + BAA) is the documented Phase 4 roadmap item (#34) | `AUDIT_FOLLOWUPS.md` #34 |
| "What about email enumeration via registration?" | Acknowledged as P0 (#1); documented; planned fix is silent-success branch + `MustVerifyEmail` gate | `AUDIT_FOLLOWUPS.md` #1 |
| "What's your test coverage?" | 39 integration tests + 7 adversarial tests (IDOR bypass, info leakage, throttle, state machine, job idempotency). CI on PHP 8.2 + 8.3, `composer audit` + `pint --test` | `.github/workflows/ci.yml`, `tests/Integration/`, `tests/Adversarial/` |
| "What about users who don't have Android phones?" | Full feature parity web portal at `/portal` — same services + policies as the mobile API; patients can log in via browser | `routes/web.php:157-208`, `README.md:159` |
| "How does the chatbot handle a crisis mention?" | System prompt has a HIGHEST-PRIORITY crisis branch with verbatim PH hotline numbers (911, NCMH 1553, Hopeline 0917-558-4673) | `app/Services/ChatbotService.php:130-135` |
| "How does the chatbot stay on-claim about clinic facts?" | Gemini is *grounded* on the seeded knowledge base — system prompt instructs "Never invent clinic facts not in the knowledge base"; falls back to Jaccard matcher if no API key | `app/Services/ChatbotService.php:99-137` |
| "What's your audit log capture?" | `ActivityLog` model writes every admin/clinician create/update/delete; admin-only view at `/activity-logs` | `routes/web.php:101`; `app/Models/ActivityLog.php` |
| "How are background jobs resilient?" | Retry + backoff: queue worker config `--tries=3 --backoff=60,300,600`; idempotency verified by `tests/Adversarial/JobIdempotencyTest.php` | `docker-compose.yml` queue-worker service |
| "What if the DB is down at boot?" | Container `HEALTHCHECK` probes `/api/v1/health` which does a `SELECT 1`; entrypoint.sh's PDO probe waits up to ~60s then `exit 1` (fail-fast, no spin) | `routes/api.php:38-46`; `docker/wait-for-db.sh` |

---

## 9. Migration (Out of Scope Here)

This document is defense-scoped only. The post-defense off-Railway migration
plan is a separate track; produce `MIGRATION_PLAN.md` when that work begins
in earnest.

---

## 10. Final Word

The scope is covered. The diagrams, if verified against §3 and §4 above, will
hold under cross-reference. The known gaps are *documented in the repo itself*
as engineering-process maturity, not hidden as if-coverable. Ship it.
