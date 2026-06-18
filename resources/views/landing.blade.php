<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'TheraConnect') }} — Connecting You to Better Care</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/theraconnect.css') }}" rel="stylesheet">

    <style>
        .lp-nav {
            background: #fff;
            border-bottom: 1px solid var(--tc-neutral-200);
        }
        .lp-brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; }
        .lp-brand .lp-logo {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--tc-teal); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem;
        }
        .lp-brand .lp-name { font-weight: 700; color: var(--tc-slate); font-size: 1.1rem; letter-spacing: -0.02em; }

        .hero {
            background: linear-gradient(135deg, var(--tc-teal) 0%, var(--tc-teal-dark) 100%);
            color: #fff;
            padding: 6rem 0 7rem;
            position: relative;
            overflow: hidden;
        }
        .hero::after {
            content: "";
            position: absolute;
            top: -40%; right: -10%;
            width: 60%; height: 180%;
            background: radial-gradient(closest-side, rgba(255,255,255,0.12), transparent);
            pointer-events: none;
        }
        .hero .hero-badge {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.22);
            color: #fff; font-size: 0.8rem; font-weight: 600;
            padding: 0.35rem 0.9rem; border-radius: 999px;
            margin-bottom: 1.5rem;
        }
        .hero h1 { color: #fff; font-size: 2.9rem; font-weight: 700; letter-spacing: -0.03em; }
        .hero .lead { color: rgba(255,255,255,0.85); font-size: 1.15rem; max-width: 620px; margin: 1rem auto 2rem; }
        .btn-hero {
            background: #fff; color: var(--tc-teal-dark);
            font-weight: 600; border-radius: 10px; padding: 0.7rem 1.5rem;
            border: none;
        }
        .btn-hero:hover { background: var(--tc-neutral-100); color: var(--tc-teal-dark); }
        .btn-hero-outline {
            background: transparent; color: #fff;
            border: 1px solid rgba(255,255,255,0.5);
            font-weight: 600; border-radius: 10px; padding: 0.7rem 1.5rem;
        }
        .btn-hero-outline:hover { background: rgba(255,255,255,0.12); color: #fff; }

        .features { padding: 5rem 0; }
        .features-head h2 { font-size: 1.9rem; }
        .feature-card {
            background: #fff;
            border: 1px solid var(--tc-neutral-200);
            border-radius: var(--tc-radius);
            box-shadow: var(--tc-shadow);
            padding: 1.75rem;
            height: 100%;
            transition: box-shadow 0.18s ease, transform 0.18s ease;
        }
        .feature-card:hover { box-shadow: var(--tc-shadow-hover); transform: translateY(-2px); }
        .feature-chip {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.35rem; margin-bottom: 1.1rem;
        }
        .feature-chip.teal  { background: var(--tc-teal-light); color: var(--tc-teal); }
        .feature-chip.green { background: var(--tc-green-bg);  color: var(--tc-green); }
        .feature-chip.blue  { background: var(--tc-blue-bg);   color: var(--tc-blue); }
        .feature-card h5 { color: var(--tc-slate); font-weight: 700; }
        .feature-card p { color: var(--tc-slate-light); font-size: 0.92rem; margin-bottom: 0; }

        .audience { background: #fff; border-top: 1px solid var(--tc-neutral-200); padding: 4rem 0; }
        .audience-card {
            display: flex; gap: 1rem; align-items: flex-start;
            padding: 1.5rem;
            border: 1px solid var(--tc-neutral-200);
            border-radius: var(--tc-radius);
            background: var(--tc-neutral-50);
        }
        .audience-card .ac-icon {
            width: 44px; height: 44px; border-radius: 11px; flex-shrink: 0;
            background: var(--tc-teal); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }
        .audience-card h6 { color: var(--tc-slate); font-weight: 700; margin-bottom: 0.25rem; }
        .audience-card p { color: var(--tc-slate-light); font-size: 0.88rem; margin-bottom: 0; }

        .lp-footer { background: var(--tc-slate); color: rgba(255,255,255,0.65); padding: 2.5rem 0; }
        .lp-footer .lp-logo {
            width: 28px; height: 28px; border-radius: 8px; background: var(--tc-teal);
            display: inline-flex; align-items: center; justify-content: center; color: #fff;
        }
    </style>
</head>
<body style="background: var(--tc-neutral-50);">
    <nav class="navbar navbar-expand-lg lp-nav sticky-top">
        <div class="container">
            <a class="lp-brand" href="/">
                <span class="lp-logo"><i class="bi bi-activity"></i></span>
                <span class="lp-name">{{ config('app.name', 'TheraConnect') }}</span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="{{ url('/login') }}" class="btn btn-outline-primary btn-sm">Sign In</a>
            </div>
        </div>
    </nav>

    <section class="hero text-center">
        <div class="container position-relative">
            <span class="hero-badge"><i class="bi bi-heart-pulse"></i> Clinic care, simplified</span>
            <h1 class="mb-3">Connecting You to Better Care</h1>
            <p class="lead">
                Book appointments, complete therapeutic assignments, join video consultations,
                and stay connected with your clinic — all from one place.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="#" class="btn btn-hero btn-lg"><i class="bi bi-download me-2"></i>Download the App</a>
                <a href="{{ url('/login') }}" class="btn btn-hero-outline btn-lg">Clinician Sign In</a>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <div class="text-center features-head mb-5">
                <h2 class="fw-bold">Everything your care journey needs</h2>
                <p class="text-muted">One platform for patients and clinicians alike.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-chip teal"><i class="bi bi-calendar-check"></i></div>
                        <h5>Easy Appointment Booking</h5>
                        <p>Request and manage therapy appointments with real-time status updates, reminders, and one-tap video consultations.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-chip green"><i class="bi bi-journal-check"></i></div>
                        <h5>Therapeutic Assignments</h5>
                        <p>Receive, complete, and submit assignments from your clinician — with worksheets saved right to your device.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-chip blue"><i class="bi bi-chat-dots"></i></div>
                        <h5>Instant Support</h5>
                        <p>Get quick answers to common questions through our intelligent chatbot, available around the clock.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="audience">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="audience-card">
                        <span class="ac-icon"><i class="bi bi-phone"></i></span>
                        <div>
                            <h6>For Patients</h6>
                            <p>A simple mobile app to book visits, do assigned exercises, join video calls, and message your clinic.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="audience-card">
                        <span class="ac-icon"><i class="bi bi-clipboard2-pulse"></i></span>
                        <div>
                            <h6>For Clinicians &amp; Admins</h6>
                            <p>A web dashboard to manage appointments, assign work, review submissions, and oversee the clinic.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="lp-footer">
        <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="lp-logo"><i class="bi bi-activity"></i></span>
                <span class="text-white fw-semibold">{{ config('app.name', 'TheraConnect') }}</span>
            </div>
            <small>&copy; {{ date('Y') }} {{ config('app.name', 'TheraConnect') }}. All rights reserved.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
