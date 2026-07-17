# Operations, Configuration, External Services, and Testing

## Configuration Sources

Use `.env.example` and `.env.railway.example` as key inventories only. Do not copy actual `.env` values, credentials, tokens, database passwords, FCM service accounts, or cloud secrets into documentation, tests, or commits.

| Concern | Config/env keys | Code locations |
|---|---|---|
| Application | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE` | `config/app.php` |
| Privacy notice | `PRIVACY_CONTROLLER_NAME`, `PRIVACY_CONTACT_EMAIL` | `config/app.php`, registration agreement/privacy notice |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `config/database.php`, migrations |
| Browser session | `SESSION_DRIVER`, `SESSION_CONNECTION`, `SESSION_TABLE`, `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `SESSION_LIFETIME` | `config/session.php` |
| Queue/cache/logging | `QUEUE_CONNECTION`, `DB_QUEUE_*`, `CACHE_STORE`, `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | `config/queue.php`, `config/cache.php`, `config/logging.php` |
| Storage | `FILESYSTEM_DISK`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT` | `config/filesystems.php` |
| Mail | `MAIL_*` values | `config/mail.php`, `SendEmailNotification` |
| Realtime | `BROADCAST_CONNECTION`, `REVERB_APP_*`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_SERVER_*`, `REVERB_ALLOWED_ORIGINS`, `REVERB_SCALING_ENABLED` | `config/broadcasting.php`, `config/reverb.php` |
| CORS/Sanctum | `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_TOKEN_PREFIX` | `config/cors.php`, `config/sanctum.php` |
| FCM | `FCM_PROJECT_ID`, `FCM_CREDENTIALS`, `FCM_CREDENTIALS_B64` | `config/services.php`, `FcmService`, entrypoint |
| Jitsi | `JITSI_BASE_URL`, `JITSI_ROOM_PREFIX` | `config/services.php`, `JitsiService` |
| Gemini | `GEMINI_API_KEY`, `GEMINI_CHATBOT_MODEL` | `config/services.php`, `ChatbotService` |
| Demo seed | `SEED_DEMO`, `DEMO_PASSWORD` | `docker/entrypoint.sh`, seeders |

## Deployment Shape

### Docker and local development

- `Dockerfile` builds Vite browser assets in a Node stage, then PHP 8.2 Alpine with MySQL/PHP extensions, Composer dependencies, and a non-root `www-data` runtime.
- `docker/entrypoint.sh` performs storage link, waits for database, migrates, optionally seeds, decodes FCM credentials when configured, and serves the app.
- `docker-compose.yml` defines app, MySQL, database queue worker, Reverb, and scheduler containers. Reverb is exposed at `localhost:8081`; the image-built Vite bundle is preserved despite the source bind mount. `docker-compose.db.yml` provides a database-focused option.
- The Laravel Composer `dev` script starts PHP server, queue listener, Pail, Vite, and Reverb concurrently.

### Railway

- `railway.json`: web app, Dockerfile build, `/api/v1/health` health check, one replica.
- `railway.worker.json`: separate `queue:work` process with retry/backoff. Required for queued push/email/reminders under `QUEUE_CONNECTION=database`.
- `railway.scheduler.json`: Railway cron invokes `php artisan schedule:run` every minute.
- `railway.reverb.json`: separate long-running Reverb WebSocket service. Give it a public Railway domain and do not attach the web app HTTP health check to it.
- The web service migration process is in the entrypoint. Confirm the deployed service is the Laravel app service, then run `php artisan migrate --force` in its Railway console only when required by deployment procedure.

For Railway realtime deployment:

1. Create a Reverb service from the same repository and select `railway.reverb.json` as its config file.
2. Generate a public domain for that service. Set `REVERB_HOST` to its hostname, `REVERB_PORT=443`, and `REVERB_SCHEME=https` on the app, worker, and Reverb services.
3. Generate one random `REVERB_APP_ID`, public `REVERB_APP_KEY`, and secret `REVERB_APP_SECRET`; use the same values on all three services. Never expose the secret in browser/mobile output.
4. Set `BROADCAST_CONNECTION=reverb`; set `REVERB_SERVER_HOST=0.0.0.0`, `REVERB_SERVER_PORT=8080`, and `REVERB_ALLOWED_ORIGINS` to the web app hostname on the Reverb service.
5. Keep the database queue worker running. Broadcast events are queued, so Reverb alone is insufficient.
6. Redeploy the app, worker, and Reverb services after variable changes. No database migration is introduced by realtime itself.

One Reverb replica with scaling disabled is the efficient starting topology for this deployment. If multiple Reverb replicas are later required, enable Reverb scaling and provide shared Redis; do not add replicas without the shared pub/sub layer.

## External Services

| Service | Where used | Failure behavior |
|---|---|---|
| Firebase Cloud Messaging | Flutter `fcm_service.dart`; Laravel `FcmService`, `SendPushNotification` | Missing config/no token no-ops; invalid tokens are handled/removed; delivery dispatch failures are logged without rolling back workflow. |
| SMTP/provider via Laravel Mail | `SendEmailNotification`, `NotificationEmail` | Job tracks email failure state and rethrows for queue retry; not all notification types are email eligible. |
| Laravel Reverb | private browser/mobile notification, message, and appointment invalidation | Broadcast enqueue/send failures are logged without rolling back domain work; reconnect causes clients to refetch current state. |
| AWS S3-compatible storage | Laravel filesystem disk for sensitive uploads | Local private disk is fallback. Production needs a private persistent bucket; container storage is ephemeral. |
| Jitsi | `JitsiService` meeting links in appointments | Generates unguessable room URL; no observed server-side Jitsi authentication/token integration. |
| Google Gemini API | `ChatbotService` | Optional; 5s connect / 15s total timeout; logs warning and uses database/Jaccard fallback. |
| MySQL | all durable backend data, queued jobs/cache/sessions when configured | `/api/v1/health` reports 503 if a simple DB query fails. |

## Logging and Error Handling

- `SecurityHeaders` sets defensive headers and HSTS only for secure requests.
- `bootstrap/app.php` renders branded 403/404/419/429 pages and production 500 responses, while preserving server logs.
- Expected business exceptions (`InvalidStateException`, `SlotUnavailableException`) should produce validation/user-safe errors in their controllers; do not let them become generic 500s.
- Notification dispatch is best-effort after commit and logs channel/notification context if enqueueing fails.
- Realtime dispatch is best-effort after commit. Private broadcast payloads contain identifiers/state only, not message or clinical content.
- Registration protects the post-commit automatic-login boundary: an account may be created even if session infrastructure fails, and the controller redirects the user to login with a success message while logging context.
- Railway should capture logs from stderr (`LOG_STACK=stderr` in the Railway example). Local container file logs are ephemeral in production.

## Background Work

| Schedule | Job | Effect |
|---|---|---|
| hourly | `GenerateAssignmentReminders` | creates/dispatches assignment deadline reminders |
| daily 08:00 | `GenerateAppointmentReminders` | creates/dispatches appointment reminders |
| daily 02:00 | `MarkOverdueNoShows` | marks eligible appointments no-show |
| after transaction commit | `SendPushNotification`, `SendEmailNotification` | delivery channels for created notifications |
| after transaction commit | queued `BroadcastEvent` | sends notification/message/appointment invalidation through Reverb |

Queue workers must be running for asynchronous delivery. With `sync`, jobs execute during request processing; with `database` and no worker, business data persists but jobs remain queued.

## Testing Strategy

| Suite | Intent |
|---|---|
| `tests/Integration` | end-to-end request/domain behavior across API, web, portal, services, policies, notifications and files |
| `tests/Adversarial` | IDOR, auth/role bypass, state-transition abuse, malformed input, throttling, information disclosure, idempotency |
| `tests/Concerns/CreatesActors.php` | reusable patient/clinician/admin fixture setup |
| `tests/TestCase.php` | database migration setup and base actor/token helpers |

Typical commands:

```powershell
php artisan test
php artisan test tests\Integration\AppointmentFlowTest.php
php artisan test tests\Adversarial\IdorBypassTest.php
php artisan view:cache
```

Known local environment caveats observed in this repository: tests that generate image files need PHP GD; some full-suite failures can originate from local extension availability or stale expectations, not the changed feature. Report such failures distinctly from feature-suite results.
