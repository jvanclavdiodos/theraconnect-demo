# TheraConnect — Live Demo Walkthrough

This guide walks you through a complete demo of TheraConnect. Every screen and action described below works with the demo data seeded by `setup.ps1`.

---

## Before the Demo

```powershell
# 1. One-click setup (loads demo data: 6 users, 9 appointments, 4 assignments, 5 notifications)
.\setup.ps1

# 2. Start the server
php artisan serve --port=8080
```

Open **http://localhost:8080** in your browser.

---

## Part 1 — Marketing Landing Page

**URL:** `http://localhost:8080/`

| What to show | What you see |
|---|---|
| Hero section | "Connecting You to Better Care" banner |
| Feature cards | Appointment Booking, Therapeutic Assignments, Instant Support |
| Sign In button | Top-right → leads to clinician login |

**Say:** *"This is the public-facing site. Patients see this before downloading the mobile app."*

---

## Part 2 — Admin Dashboard

**Login:** `admin@theraconnect.test / password`

| What to show | What you see |
|---|---|
| Stat cards | "3 Appointments", "2 Pending" (assignments), "5 Notifications" |
| Activity feed | Recent Approved/Completed appointments + Pending assignments |
| Sidebar | Full navigation — Patients, Clinicians, Appointments, Assignments, Chatbot Content, Notification Logs |
| Breadcrumbs | Click any page — breadcrumb trail shows location |

**Say:** *"The admin sees everything — including Clinician management which regular staff can't access."*

---

## Part 3 — Patient Management

**URL:** `http://localhost:8080/patients`

Already loaded: Jane Doe, Michael Torres, Emily Watson.

| Demo action | Steps |
|---|---|
| Search a patient | Type "Jane" in search bar → list filters in real-time. Click X to clear |
| View patient detail | Click eye icon on Jane Doe → detail page shows DOB, address, emergency contact, recent appointments |
| Create a patient (live) | Click "Add Patient" → fill name, email, password, contact → "Create Patient" → appears in list |
| Edit a patient | Click pencil icon → change name → save → updated instantly |
| Delete a patient | Click trash icon → confirm dialog → patient soft-deleted |

**Say:** *"Clinicians can manage their entire patient roster from here. Everything is logged and recoverable."*

---

## Part 4 — Appointments (The Core Flow)

**URL:** `http://localhost:8080/appointments`

Already loaded: 3 pending, 2 approved, 2 completed, 1 rejected, 1 cancelled.

| Demo action | Steps |
|---|---|
| Filter by status | Click "Pending" tab → shows 3 appointments awaiting action |
| Approve appointment | Find Emily's "Initial consultation" → click green checkmark → status → "Approved" |
| Reject appointment | Find Michael's "Family counseling" → click red X → confirm → status → "Rejected" |
| Reschedule | Find Jane's approved appointment → click calendar icon → modal opens → pick date → "Reschedule" |
| Breadcrumb navigation | Shows: Home > Appointments |

**Say:** *"When a clinician approves or reschedules, the patient gets an instant push notification via Firebase. Jane's approved 9 AM CBT session is confirmed — she'd see this in her mobile app."*

---

## Part 5 — Assignments (Therapeutic Homework)

**URL:** `http://localhost:8080/assignments`

Already loaded: 4 assignments, 2 with submissions.

| Demo action | Steps |
|---|---|
| View list | See "Daily Mood Journal" (reviewed), "Breathing Exercise" (submitted), "Communication Reflection" (pending), "Anxiety Trigger Log" (pending) |
| Create assignment (live) | Click "New Assignment" → pick patient + fill title/description → "Create" → patient notified |
| Review submission | Click "View" on "Daily Mood Journal" → see Jane's 7-day mood log → click "Mark Reviewed" → badge → "Reviewed" |
| File download | If patient uploaded a file, "Download" link appears |

**Say:** *"Clinicians assign therapeutic homework here. Patients complete it in the mobile app. Submissions appear for review with a single click."*

---

## Part 6 — Clinician Management (Admin Only)

**URL:** `http://localhost:8080/clinicians`

**Must login as admin** — switch to clinician and the sidebar link disappears.

Already loaded: Dr. Sarah Chen (CBT), Dr. James Rivera (Family Therapy).

| Demo action | Steps |
|---|---|
| Admin view | See both clinicians with license numbers and specializations |
| Clinician view | Logout → login as `clinician@theraconnect.test` → "Clinicians" link NOT in sidebar |
| Create clinician (live) | Login as admin → "Add Clinician" → fill form → "Create" |

**Say:** *"Only administrators can manage clinician accounts — this is enforced server-side, not just hidden in the UI."*

---

## Part 7 — Chatbot Content Management

**URL:** `http://localhost:8080/chatbot-content`

Already loaded: 9 intents across FAQ, Scheduling, Small Talk, and Fallback categories.

| Demo action | Steps |
|---|---|
| View intents | Table with category badges, phrase counts, response counts |
| Create intent (live) | "New Intent" → key: `demo_test`, name: "Demo Test", Category: FAQ → add phrase "does this work" → add response "Yes! Changes are live instantly." → "Create" |
| Test it | Run this PowerShell: |
|  | `$token = (Invoke-RestMethod http://localhost:8080/api/v1/login -Method POST -Body '{"email":"patient@theraconnect.test","password":"password"}' -ContentType "application/json").data.token` |
|  | `Invoke-RestMethod http://localhost:8080/api/v1/chatbot/message -Method POST -Body '{"message":"does this work"}' -Headers @{Authorization="Bearer $token";"Content-Type"="application/json"}` |
| Result | Bot replies "Yes! Changes are live instantly." — no deploy, no restart |

**Say:** *"Staff can add, edit, or disable chatbot topics without a developer. Every change is live immediately."*

---

## Part 8 — Notification Audit Log

**URL:** `http://localhost:8080/notifications/logs`

Already loaded: 5 notifications with various types and read/unread states.

| What to show |
|---|
| Paginated table of all notifications ever sent |
| Type badges: appointment_approved, appointment_rejected, assignment_deadline, appointment_reminder |
| Which patient received each notification |
| Sent timestamp and Read timestamp |
| "Unread" vs "Read" status visible at a glance |

**Say:** *"Every notification sent to patients is logged. You can audit delivery, see read rates, and track communication history."*

---

## Part 9 — Patient Experience (API / Mobile App)

The patient mobile app (Flutter, Phase 10) is built. Here's how to demo the API directly:

### Option A: Import Postman Collection

Import `postman/TheraConnect_API_v1.postman_collection.json` into Postman. Run the **"End-to-End Flow"** folder — it chains Register → Login → Get Me → Check Schedules → Book → View → Update Profile → Chatbot → Logout. The token auto-saves between steps.

### Option B: PowerShell

```powershell
$token = (Invoke-RestMethod http://localhost:8080/api/v1/login -Method POST -Body '{"email":"patient@theraconnect.test","password":"password"}' -ContentType "application/json").data.token

# View schedules for next week
Invoke-RestMethod "http://localhost:8080/api/v1/schedules?date=2026-06-10" -Headers @{Authorization="Bearer $token"}

# Chat with the bot
Invoke-RestMethod http://localhost:8080/api/v1/chatbot/message -Method POST -Body '{"message":"how do I book an appointment?"}' -Headers @{Authorization="Bearer $token";"Content-Type"="application/json"}

# View your notifications
Invoke-RestMethod http://localhost:8080/api/v1/notifications -Headers @{Authorization="Bearer $token"}
```

**Say:** *"The Flutter mobile app consumes these exact same API endpoints. All 20 endpoints are built, tested, and documented. The mobile app provides the native UI — the backend is the same."*

---

## Part 10 — Security Demo

| What to show | How to demo |
|---|---|
| Rate limiting | Try 6 rapid login attempts → 5th returns 429 "Too Many Requests" |
| Role enforcement | Login as clinician → "Clinicians" sidebar link hidden AND route blocked (403) |
| Patient data privacy | API calls as patient show own data only — cannot view other patients' appointments (403) |
| Password security | bcrypt with cost factor 12 — no plaintext storage |

---

## Part 11 — Running Tests (for technical audience)

```powershell
php artisan test
# 38 tests, 148 assertions — all green
```

Walk through a test file: `tests\Integration\EndToEndFlowTest.php` — demonstrates full appointment lifecycle, assignment submission flow, and notification creation all in automated tests.

---

## Demo Accounts

| Role | Email | Password | Dashboard Access |
|---|---|---|---|
| Admin | `admin@theraconnect.test` | `password` | Full access |
| Clinician | `clinician@theraconnect.test` | `password` | All except Clinicians page |
| Clinician | `dr.rivera@theraconnect.test` | `password` | Family Therapy specialist |
| Patient | `patient@theraconnect.test` | `password` | Jane Doe — mobile app only |
| Patient | `michael@theraconnect.test` | `password` | Michael Torres — mobile app only |
| Patient | `emily@theraconnect.test` | `password` | Emily Watson — mobile app only |

---

## Demo Data Summary

| Entity | Count | Details |
|---|---|---|
| Users | 6 | 1 admin, 2 clinicians, 3 patients |
| Appointments | 9 | 3 pending, 2 approved, 2 completed, 1 rejected, 1 cancelled |
| Assignments | 4 | 2 with submissions (1 submitted, 1 reviewed) |
| Notifications | 5 | appointment_approved, appointment_rejected, appointment_reminder, assignment_created, assignment_deadline |
| Chatbot intents | 9 | Hours, Location, Booking steps, Reminders, Assignments, Greeting, Thanks, Goodbye, Fallback |
