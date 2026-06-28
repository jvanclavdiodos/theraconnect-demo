# Push Notifications — Firebase (FCM) Setup Guide

The code for push notifications is **fully implemented** (backend + Flutter
foreground/background/tap-to-deeplink). To turn it on you only need to create a
Firebase project and drop in two credential files. This guide walks you through
it end to end. Budget ~15 minutes.

> **Android package name (you'll need it):** `com.theraconnect.app`

---

## Part 1 — Create the Firebase project (Firebase console)

1. Go to <https://console.firebase.google.com> and sign in with your Google account.
2. Click **Add project** → name it (e.g. `TheraConnect`) → continue. Google
   Analytics is optional; you can disable it. Click **Create project**.

## Part 2 — Register the Android app → `google-services.json`

3. In the project, click the **Android** icon ("Add app").
4. **Android package name:** enter exactly `com.theraconnect.app`.
   App nickname / debug signing SHA-1 are optional — leave blank. Click **Register app**.
5. Click **Download `google-services.json`**.
6. Put that file here (this exact path):
   ```
   theraconnect_flutter/android/app/google-services.json
   ```
   That's the only place it goes. (It is gitignored / should NOT be committed.)
7. Skip the remaining "add SDK" steps in the console — the app is already wired
   for them. Click **Continue to console**.

> The Android build is already set up for FCM (the `com.google.gms.google-services`
> Gradle plugin is applied). **The APK will not build until this file is present** —
> that's expected; it's the activation step.

## Part 3 — Service-account key → backend credential

8. In the console: **gear icon → Project settings → Service accounts** tab.
9. Click **Generate new private key** → **Generate key**. A `.json` file downloads.
   Keep it secret — never commit it.
10. Note your **Project ID** (Project settings → General → *Project ID*, e.g.
    `theraconnect-12345`).

---

## Part 4 — Backend configuration

The backend authenticates to FCM with the service-account key. Set two env vars
(names already wired in `config/services.php`):

| Variable | Value |
|---|---|
| `FCM_PROJECT_ID` | your Firebase **Project ID** (Part 3, step 10) |
| `FCM_CREDENTIALS` | absolute path to the service-account `.json` on the server |

**Local dev:**
```
# .env
FCM_PROJECT_ID=theraconnect-12345
FCM_CREDENTIALS=/absolute/path/to/service-account.json
QUEUE_CONNECTION=sync     # so the push job runs immediately (see note below)
```

**Railway (production)** — your installed app talks to the Railway backend, so
this is where the creds must live. Railway has no file uploads, so the boot
script (`docker/entrypoint.sh`) decodes a base64 env var into the credential
file at startup. In the service **Variables**, set:

| Variable | Value |
|---|---|
| `FCM_PROJECT_ID` | your Firebase Project ID |
| `FCM_CREDENTIALS_B64` | the service-account JSON, base64-encoded (see command below) |
| `FCM_CREDENTIALS` | `/var/www/storage/app/private/firebase-credentials.json` |
| `QUEUE_CONNECTION` | `sync` (simplest — runs the push inline; avoids needing a worker) |

Generate the base64 of your service-account key locally (don't paste the raw JSON):
```powershell
# Windows PowerShell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\service-account.json"))
```
```bash
# macOS/Linux/Git-Bash
base64 -w0 /path/to/service-account.json
```
Copy the single-line output into `FCM_CREDENTIALS_B64`. Redeploy.

### ⚠️ Queue worker (required, easy to miss)
The push send (`SendPushNotification`) is a **queued job**, and
`QUEUE_CONNECTION=database` by default. Queued jobs do nothing unless a worker
drains the queue:
- **Local:** either set `QUEUE_CONNECTION=sync` (runs inline — simplest for
  testing), or run `php artisan queue:work` in a second terminal.
- **Railway:** deploy the queue-worker service (`railway.worker.json`, runs
  `php artisan queue:work`). Without it, the DB notification row is still written
  (in-app notifications work) but **no push is sent**.

---

## Part 5 — Build, install, test

1. With `google-services.json` in place:
   ```
   cd theraconnect_flutter
   flutter build apk --release
   flutter install
   ```
   (On first launch Android 13+ will prompt for notification permission — allow it.)
2. **Quick test via Firebase console:** Cloud Messaging → *Send test message* →
   paste the device's FCM token (printed in logs on app start, or capture it from
   the `device_tokens` table) → send. You should see a banner.
3. **End-to-end test:** as a patient, book an appointment; as the clinician,
   approve it. The patient device should receive a push. Tapping it deep-links to
   the appointment.

### What each app state does
| App state | Behavior |
|---|---|
| Foreground | In-app banner (local notification) + the notifications list refreshes |
| Background / killed | OS tray notification (channel **General**) |
| Tap (any state) | Deep-links: appointments / assignments / messages / questionnaires, else the Notifications screen |

---

## Troubleshooting
- **APK build fails after wiring:** `google-services.json` is missing or in the
  wrong folder (must be `theraconnect_flutter/android/app/`).
- **No push received, but in-app notification appears:** the queue worker isn't
  running (Part 4) or `FCM_PROJECT_ID`/`FCM_CREDENTIALS` are unset — check the
  Laravel log for `FCM: skipped (no project ID configured)`.
- **`FCM: push failed` 404 UNREGISTERED in logs:** stale device token; it's
  auto-deleted. Reopen the app to re-register.
- **No permission prompt on Android 13+:** ensure the app was reinstalled after
  this change (the `POST_NOTIFICATIONS` permission was added to the manifest).
