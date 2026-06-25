# TheraConnect — Demo Script

~15 min. Two screens: **laptop browser** (clinician/admin dashboard) + **phone** (patient app).
Everything runs against Railway: `https://theraconnect-demo-production.up.railway.app`.

**Framing:** *One Laravel backend serving two clients — a clinician/admin web dashboard and a
patient mobile app — for the full care loop: scheduling, sessions, messaging, assignments, notes,
and an AI assistant.*

## Accounts (all password `password`)
- **Clinician (dashboard):** `dr.rivera@theraconnect.test`
- **Admin (dashboard):** `admin@theraconnect.test`
- **Patient (phone app):** a patient **assigned to the demo clinician** (verify/set this first)

## Pre-flight (5 min before, off-screen)
1. **Railway deploy green + migrated:** log into the dashboard and open **Messages**, a patient's
   **Notes**, and the **availability calendar** on the dashboard. If any error, the new
   tables/enums didn't migrate — redeploy.
2. **Reinstall the latest APK** on the phone (so it has every mobile feature).
3. **Pair one clinician + one patient:** the phone's patient must be **assigned to** the demo
   clinician — messaging, notes, assignments, and booking notifications all key off that. Assign via
   the dashboard if needed.
4. **Gemini key** set on Railway for the smart chatbot (otherwise it falls back gracefully).
5. **Safety net:** have one already-**approved online** appointment ready so "Join" works instantly.
6. Phone on internet; dashboard in one browser; optionally admin in a second/incognito window.

---

## 0. One-liner (20s)
> "TheraConnect is a clinic system with three parts: a **patient mobile app**, a **clinician/admin web
> dashboard**, and **one Laravel backend** they both talk to. The phone uses a JSON API with a bearer
> token; the dashboard uses web sessions; both call the **same business logic**, so the rules are
> identical no matter who's acting."

## 1. The two surfaces (1 min)
- Show the dashboard (laptop) and the app (phone) side by side. Note the **app matches the web's
  look** (teal/slate, Inter) — same brand, two front-ends.

## 2. Clinician sets availability (1 min) — *dashboard*
- Scroll to the **availability calendar** on the dashboard. Block a **day** and an **hour**.
> "Clinicians are available by default; here they carve out time off. This directly controls what
> patients can book."

## 3. Patient books an online appointment (2 min) — *phone*
- **Book** → pick the clinician → the month calendar **greys out the blocked day** → pick an open
  **time slot** → mode **Online** → confirm.
> "Clinician-first booking. The calendar only offers the clinician's open days and hours, and the
> backend re-checks availability on submit — no off-hours or double bookings."

## 4. Approve → video room (2 min) — *dashboard → phone*
- **Appointments** → the new **pending** request (clinician was notified) → **Approve**.
> "Approving an **online** appointment auto-generates a private **Jitsi room** and notifies the patient."
- Phone: open the appointment → **Join Video Call**. Dashboard: **Join** too → same room. Wave.
> "The link is an unguessable `meet.jit.si/TheraConnect-<id>-<uuid>`. These links **expire 5h after the
> appointment** — after that the app and dashboard stop offering them."

## 5. Post-meeting wrap-up (30s) — *dashboard*
- After clicking Join, the **"close the case?"** prompt appears → **Yes, close case**.
> "When the session's done, the clinician confirms and the appointment is marked **completed** —
> closing the case."

## 6. Messaging (2 min) — *phone → dashboard → phone*
- Phone: **Messages** tab → message the clinician.
- Dashboard: the **unread badge** on Messages → open the inbox → reply.
- Phone: pull to refresh → see the reply.
> "Direct messaging between a patient and their assigned clinician — a single ongoing thread, unread
> badges on both sides, and a push notification on each new message."

## 7. Assignments + submission preview (2.5 min) — *dashboard → phone → dashboard*
- Dashboard: **Assignments → New** → pick the patient → title/description → optional worksheet → save.
- Phone: **Assignments** → open it → **Submit** a photo/file (or text).
- Dashboard: open **Submissions** → click **View** → the file opens in a **maximized preview**
  (image/PDF/text inline). Mark **Reviewed**.
> "Uploads stay on a **private disk**, served only through authenticated routes. The clinician previews
> the work inline instead of blind-downloading; the patient can preview their own submission too."

## 8. Clinician notes / prescription (1.5 min) — *dashboard → phone*
- Dashboard: open the patient → **Clinician Notes** → add a note (e.g. a prescription) → tick
  **Share with patient** → save.
- Phone: **Profile → Notes from your clinician** → the shared note appears.
> "Clinicians keep notes about a patient — private by default, or shared to the patient's app. Private
> notes never leave the dashboard."

## 9. AI chatbot (1 min) — *phone*
- **Chatbot** → ask "What are your clinic hours?" or "I've been feeling anxious."
> "A **Gemini-powered** assistant grounded on the clinic's knowledge base, with a rule-based fallback if
> the AI is unavailable, and built-in crisis handling — it never invents clinic facts."

## 10. Admin & wrap (1 min) — *dashboard, optional*
- Quick peek: admin manages **clinicians, patients, chatbot content, notification logs**.
> "Two front-ends, one brain. Phone → `/api/v1` (bearer token) → thin controller → a **Service** →
> MySQL → JSON. Dashboard → web session → the **same Services** → HTML. Side channels: **Jitsi** for
> video, **private storage** for files, **FCM + in-app** notifications on every key event, **Gemini**
> for the assistant. Laravel 11 + Flutter, ~136 passing tests, deployed on Railway."

---

## Tips
- **Lead with the cross-surface moments** (book on phone → appears on dashboard; the message
  round-trip). They're the most convincing.
- Keep the **same patient+clinician pair** throughout so every feature connects.
- If a push is slow, just refresh — the in-app lists always update.
- Time-box the chatbot/admin; the scheduling → session → messaging arc is the strongest narrative.

## Quick fallback answers
- **"Is the video secure / HIPAA?"** — Public Jitsi for the demo; production swaps one env var for a
  JWT-secured server. No patient data flows through the link itself; links also expire after 5h.
- **"Where does the data live?"** — MySQL on Railway; uploaded files + avatars on a private disk,
  authenticated routes only.
- **"Double-booking?"** — Booking re-checks clinician availability + existing appointments and rejects
  conflicts (DB lock on the slot).
- **"Two apps to maintain?"** — One backend + shared service layer; the apps are thin clients, so a
  rule lives in exactly one place.

## If something fails live
- App won't load data → network; it shows an error, not a hang. Re-open / pull to refresh.
- No **Join** button on an online appointment → it wasn't **approved** yet, or the link **expired** (5h).
- **Messages/Notes/availability error on the dashboard** → the new migrations didn't run; redeploy.
- Dashboard 500 right after deploy → give Railway a minute to finish, refresh.
