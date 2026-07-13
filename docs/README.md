# TheraConnect Engineering Documentation

This documentation describes the repository as inspected on 2026-07-13. It is intended for engineers and AI agents who need to change the system safely. It documents observable behavior from source, routes, migrations, configuration, and tests; it does not treat deployment settings or credentials as source of truth.

## Contents

| Document | Scope |
|---|---|
| [Architecture and Data Flow](architecture.md) | System structure, request paths, state, navigation, authentication |
| [Feature Index and Modification Map](features.md) | User-visible capabilities, dependencies, and blast radius |
| [API Reference](api.md) | Mobile API v1 endpoint contract and callers |
| [Data Model](data-model.md) | Tables, Eloquent entities, relationships, migrations |
| [File Index](file-index.md) | Important files and their responsibilities |
| [Operations and Configuration](operations.md) | Environment, queues, scheduler, storage, integrations, testing |
| [Guidelines for Future AI Agents](ai-guide.md) | Local conventions, technical debt, safe implementation rules |

## Project Overview

TheraConnect is a clinic-management and patient-engagement system for a mental-health workflow. It has three application surfaces that share a Laravel/MySQL domain model:

1. **Staff web dashboard** for administrators and clinicians: patients/caseloads, appointments, availability, assignments, clinical notes, assessments, therapy goals, messaging, notification logs, chatbot knowledge management, and audit logs.
2. **Patient web portal**: self-registration, appointment booking, assignments/submissions, assessments, mood logs, goals, notes shared by clinicians, messages, notifications, profile, and chatbot.
3. **Flutter patient mobile app**: an API-backed patient experience with closely overlapping portal capabilities. It does not expose staff administration.

The platform uses role-based access (`admin`, `clinician`, `patient`), model policies for ownership/caseload checks, session authentication for browser surfaces, and Laravel Sanctum bearer tokens for Flutter. It supports private Laravel Reverb updates, scheduled notifications, FCM push, optional transactional email, optional Gemini-assisted chatbot answers, Jitsi meeting links, and private/S3 file storage.

## Tech Stack

| Area | Technology |
|---|---|
| Backend | PHP 8.2, Laravel 11, Eloquent ORM, Blade, Laravel queues/scheduler |
| Web UI | Blade templates, Bootstrap 5, Bootstrap Icons, Alpine.js + Alpine Focus, small vanilla JS |
| Mobile | Flutter/Dart, Material UI, Riverpod `StateNotifier`/`FutureProvider`, GoRouter, Dio |
| Authentication | Laravel session guard for web; Laravel Sanctum personal access tokens for mobile |
| Database | MySQL 8 in deployment/local Docker; tests use migrations and can use SQLite configuration |
| Storage | Laravel private local disk by default; optional AWS S3-compatible disk |
| Notifications/realtime | Database notifications, Laravel Reverb/Echo, Firebase Cloud Messaging, queued email via Laravel Mail |
| Video | Jitsi room URLs generated server-side |
| Chatbot | Database intent/response knowledge base; optional Google Gemini API with Jaccard fallback |
| Deployment | Docker, Docker Compose, Railway app/worker/scheduler definitions |
| Tests | PHPUnit/Laravel integration and adversarial test suites; Flutter tests are not present in this repository |

## Architectural Shape

```text
Browser staff UI / Browser patient portal        Flutter patient app
             |                                         |
             | session-authenticated web routes         | Dio + Sanctum bearer token
             v                                         v
        Web / Portal controllers                   /api/v1 controllers
             |                                         |
             +----------- services, policies, requests/resources -----------+
                                      |
                                Eloquent models
                                      |
                           MySQL + private/S3 storage
                                      |
               queued jobs: push, email, reminders, no-show processing
```

Laravel controllers are deliberately split by surface:

- `app/Http/Controllers/Web`: staff pages plus browser login/registration.
- `app/Http/Controllers/Portal`: patient browser pages.
- `app/Http/Controllers/Api/V1`: Flutter/mobile JSON API.

Business operations that must behave consistently across surfaces normally live in `app/Services`. Authorization belongs in `app/Policies` plus `RoleMiddleware`; input validation is primarily in `app/Http/Requests/Api`, reused by some web and portal controllers.

## Important Folder Structure

```text
.
├── app/
│   ├── Exceptions/             Expected appointment/state failures
│   ├── Http/
│   │   ├── Controllers/        Web staff, patient portal, and API v1 adapters
│   │   ├── Middleware/         Role and defensive-header middleware
│   │   ├── Requests/           Reusable request validation
│   │   └── Resources/          JSON API serializers
│   ├── Jobs/                   Queue/scheduled work
│   ├── Mail/                   Transactional email composition
│   ├── Models/                 Eloquent entities and relationships
│   ├── Policies/               Model-level authorization
│   ├── Providers/              rate limits, gates, serialization conventions
│   ├── Rules/                  custom validation rules
│   ├── Services/               shared domain/external integration logic
│   └── Support/                domain constants (assessments, terms)
├── bootstrap/                  Laravel boot and exception/middleware registration
├── config/                     framework, auth, queue, storage, external-service config
├── database/
│   ├── factories/              test/model factories
│   ├── migrations/             schema evolution
│   └── seeders/                demo and chatbot seed data
├── docker/                     runtime entrypoint, PHP settings, DB wait script
├── docs/                       this engineering knowledge base
├── postman/                    API v1 Postman collection
├── public/                     web entry point, static CSS/JS/assets, APK download
├── resources/
│   ├── css, js/                Vite source assets
│   └── views/                  Blade web/staff/portal/email/error templates
├── routes/                     web, API, console schedule route declarations
├── tests/                      integration, adversarial, fixture helpers
└── theraconnect_flutter/
    ├── lib/                    Flutter source: config, models, providers, services, screens
    ├── android/                Android runner/build configuration
    ├── assets/                 Flutter assets
    └── pubspec.yaml            Flutter dependency/build manifest
```

## Important Caveats

- The repository contains local/untracked and deleted artifacts outside application source. This documentation intentionally describes tracked application architecture, not the current worktree state.
- Root-level historical documents have copies under `markdown/`; their accuracy relative to code is not guaranteed. Prefer source, migrations, and tests.
- No conventional repository/DAO layer exists. Controllers and services use Eloquent models directly.
- Mobile endpoints are patient-only by design. Staff users use the browser dashboard.
