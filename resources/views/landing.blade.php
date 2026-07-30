<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TheraConnect brings appointments, secure care communication, assessments, and therapeutic assignments into one connected healthcare platform.">
    <title>{{ config('app.name', 'TheraConnect') }} - Connected care, made clearer</title>

    <link rel="preload" as="image" href="{{ asset('img/theraconnect-care-hero.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <link href="{{ asset('css/theraconnect.css') }}" rel="stylesheet">

    <style>
        :root {
            --lp-ink: #13242d;
            --lp-muted: #566b76;
            --lp-teal: #0d6e8a;
            --lp-teal-dark: #084e63;
            --lp-green: #17806d;
            --lp-coral: #d76555;
            --lp-line: #dce5e9;
            --lp-mist: #f3f8f9;
            --lp-white: #ffffff;
        }

        html {
            max-width: 100%;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }
        body {
            margin: 0;
            max-width: 100%;
            overflow-x: hidden;
            background: var(--lp-white);
            color: var(--lp-ink);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            letter-spacing: 0;
        }
        .lp-container { width: min(1180px, calc(100% - 40px)); margin-inline: auto; }
        .lp-kicker {
            color: var(--lp-teal);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .lp-section-title {
            max-width: 720px;
            margin: 0;
            color: var(--lp-ink);
            font-size: 3.35rem;
            line-height: 1.08;
            font-weight: 700;
        }
        .lp-section-copy {
            color: var(--lp-muted);
            font-size: 1rem;
            line-height: 1.75;
        }

        .lp-nav {
            min-height: 72px;
            background: rgba(255, 255, 255, 0.96) !important;
            border-bottom: 1px solid rgba(19, 36, 45, 0.09) !important;
            backdrop-filter: blur(12px);
        }
        .lp-nav .container-fluid { width: min(1240px, calc(100% - 32px)); }
        .lp-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            color: var(--lp-ink);
            font-size: 1.05rem;
            font-weight: 700;
            text-decoration: none;
        }
        .lp-brand:hover { color: var(--lp-ink); }
        .lp-mark {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: var(--lp-teal);
            color: var(--lp-white);
            font-size: 1rem;
        }
        .lp-nav-link {
            color: #405864;
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
        }
        .lp-nav-link:hover { color: var(--lp-teal); }
        .lp-sign-in {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1rem;
            border: 1px solid #afc2ca;
            border-radius: 7px;
            color: var(--lp-ink);
            font-size: 0.86rem;
            font-weight: 700;
            text-decoration: none;
        }
        .lp-sign-in:hover,
        .lp-sign-in:focus-visible {
            border-color: var(--lp-teal);
            color: var(--lp-teal);
        }

        .lp-hero {
            position: relative;
            min-height: max(590px, calc(100svh - 112px));
            max-height: 780px;
            display: flex;
            align-items: center;
            overflow: hidden;
            isolation: isolate;
            background-color: #cbd9da;
            background-image: url('{{ asset('img/theraconnect-care-hero.png') }}');
            background-position: center center;
            background-size: cover;
        }
        .lp-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background: rgba(7, 35, 45, 0.28);
        }
        .lp-hero::after {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 58%;
            z-index: -1;
            background: rgba(7, 43, 55, 0.84);
        }
        .lp-hero-content {
            width: min(650px, 54%);
            padding-block: 5rem;
            color: var(--lp-white);
        }
        .lp-hero .lp-kicker { color: #a8e1dd; }
        .lp-hero h1 {
            margin: 0.7rem 0 0;
            color: var(--lp-white);
            font-size: 5.8rem;
            line-height: 0.98;
            font-weight: 700;
        }
        .lp-hero-lead {
            max-width: 570px;
            margin: 1.35rem 0 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.35rem;
            line-height: 1.55;
        }
        .lp-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 2rem;
        }
        .lp-primary-action,
        .lp-secondary-action {
            min-height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 0.75rem 1.2rem;
            border-radius: 7px;
            font-size: 0.92rem;
            font-weight: 700;
            text-decoration: none;
        }
        .lp-primary-action {
            background: var(--lp-white);
            color: var(--lp-teal-dark);
            border: 1px solid var(--lp-white);
        }
        .lp-primary-action:hover { background: #eaf4f5; color: var(--lp-teal-dark); }
        .lp-secondary-action {
            background: rgba(255, 255, 255, 0.08);
            color: var(--lp-white);
            border: 1px solid rgba(255, 255, 255, 0.48);
        }
        .lp-secondary-action:hover { background: rgba(255, 255, 255, 0.15); color: var(--lp-white); }
        .lp-primary-action:focus-visible,
        .lp-secondary-action:focus-visible,
        .lp-sign-in:focus-visible,
        .lp-footer a:focus-visible {
            outline: 3px solid #f2b96b;
            outline-offset: 3px;
        }
        .lp-hero-note {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.77);
            font-size: 0.8rem;
        }

        .lp-trust {
            border-bottom: 1px solid var(--lp-line);
            background: var(--lp-white);
        }
        .lp-trust-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }
        .lp-trust-item {
            min-height: 92px;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem 1.5rem;
            border-right: 1px solid var(--lp-line);
        }
        .lp-trust-item:last-child { border-right: 0; }
        .lp-trust-item:nth-child(2) .lp-trust-icon {
            background: #faece9;
            color: var(--lp-coral);
        }
        .lp-trust-item:nth-child(3) .lp-trust-icon {
            background: #edf0fa;
            color: #4c62a5;
        }
        .lp-trust-icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 50%;
            background: #e4f3f2;
            color: var(--lp-green);
            font-size: 1rem;
        }
        .lp-trust-item strong { display: block; font-size: 0.9rem; }
        .lp-trust-item span { color: var(--lp-muted); font-size: 0.78rem; }

        .lp-journey { padding: 7rem 0; background: var(--lp-white); }
        .lp-journey-head {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 4rem;
            align-items: end;
            margin-bottom: 4rem;
        }
        .lp-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-top: 1px solid var(--lp-line);
        }
        .lp-step { padding: 2rem 2.25rem 0 0; }
        .lp-step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            margin-top: -18px;
            border: 1px solid var(--lp-teal);
            border-radius: 50%;
            background: var(--lp-white);
            color: var(--lp-teal);
            font-size: 0.76rem;
            font-weight: 700;
        }
        .lp-step h3 {
            margin: 1.5rem 0 0.6rem;
            color: var(--lp-ink);
            font-size: 1.12rem;
        }
        .lp-step p { max-width: 310px; margin: 0; color: var(--lp-muted); line-height: 1.7; }

        .lp-platform { padding: 7rem 0; background: #152832; color: var(--lp-white); }
        .lp-platform-grid {
            display: grid;
            grid-template-columns: 0.72fr 1.28fr;
            gap: 5rem;
            align-items: center;
        }
        .lp-platform .lp-kicker { color: #8fd4cf; }
        .lp-platform h2 {
            margin: 0.75rem 0 1.2rem;
            color: var(--lp-white);
            font-size: 3.45rem;
            line-height: 1.08;
        }
        .lp-platform-copy { color: #b9c8ce; line-height: 1.75; }
        .lp-capabilities {
            display: grid;
            gap: 0.8rem;
            margin-top: 2rem;
        }
        .lp-capability {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #e4edef;
            font-size: 0.9rem;
        }
        .lp-capability i { color: #7fd0c8; }

        .lp-product-window {
            overflow: hidden;
            border: 1px solid #405761;
            border-radius: 8px;
            background: #f7fafb;
            box-shadow: 0 24px 60px rgba(2, 13, 18, 0.28);
            color: var(--lp-ink);
        }
        .lp-window-bar {
            height: 42px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0 1rem;
            border-bottom: 1px solid #dce5e9;
            background: #ffffff;
        }
        .lp-window-dot { width: 8px; height: 8px; border-radius: 50%; background: #b8c8ce; }
        .lp-product-body { display: grid; grid-template-columns: 178px 1fr; min-height: 380px; }
        .lp-product-nav { padding: 1.1rem 0.8rem; background: #1a2f3a; color: #d9e4e8; }
        .lp-product-brand { display: flex; align-items: center; gap: 0.5rem; padding: 0 0.45rem 1rem; color: #fff; font-size: 0.78rem; font-weight: 700; }
        .lp-product-brand i { color: #71c8d5; }
        .lp-product-nav-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin-bottom: 0.25rem;
            padding: 0.55rem 0.65rem;
            border-radius: 6px;
            color: #afc2c9;
            font-size: 0.72rem;
        }
        .lp-product-nav-item.active { background: var(--lp-teal); color: #fff; }
        .lp-product-main { padding: 1.5rem; background: #f4f7f8; }
        .lp-product-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .lp-product-head strong { font-size: 1rem; }
        .lp-product-date { color: #6c8089; font-size: 0.7rem; }
        .lp-metric-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
        .lp-metric {
            padding: 0.95rem;
            border: 1px solid #dfe7ea;
            border-radius: 6px;
            background: #fff;
        }
        .lp-metric i { color: var(--lp-teal); }
        .lp-metric strong { display: block; margin-top: 0.7rem; font-size: 1.3rem; }
        .lp-metric span { color: #6b7f89; font-size: 0.66rem; }
        .lp-appointment-list { margin-top: 0.9rem; border: 1px solid #dfe7ea; border-radius: 6px; background: #fff; }
        .lp-list-head { padding: 0.8rem 0.9rem; border-bottom: 1px solid #e4ebee; font-size: 0.74rem; font-weight: 700; }
        .lp-list-row {
            display: grid;
            grid-template-columns: 1.4fr 0.85fr 0.65fr;
            gap: 0.6rem;
            align-items: center;
            padding: 0.72rem 0.9rem;
            border-bottom: 1px solid #edf1f3;
            font-size: 0.68rem;
        }
        .lp-list-row:last-child { border-bottom: 0; }
        .lp-person { display: flex; align-items: center; gap: 0.55rem; min-width: 0; }
        .lp-person-avatar {
            width: 25px;
            height: 25px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border-radius: 50%;
            background: #dff0f4;
            color: var(--lp-teal);
            font-size: 0.6rem;
            font-weight: 700;
        }
        .lp-status { color: var(--lp-green); font-weight: 700; }

        .lp-access { padding: 7rem 0; background: var(--lp-mist); }
        .lp-access-head { max-width: 680px; margin-bottom: 3.5rem; }
        .lp-access-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            overflow: hidden;
            border: 1px solid var(--lp-line);
            border-radius: 8px;
            background: var(--lp-line);
        }
        .lp-access-panel { padding: 3rem; background: var(--lp-white); }
        .lp-access-icon {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.6rem;
            border-radius: 8px;
            background: #e3f2f4;
            color: var(--lp-teal);
            font-size: 1.2rem;
        }
        .lp-access-panel.staff .lp-access-icon { background: #e5f1ed; color: var(--lp-green); }
        .lp-access-panel h3 { margin: 0 0 0.7rem; font-size: 1.35rem; }
        .lp-access-panel p { min-height: 76px; color: var(--lp-muted); line-height: 1.7; }
        .lp-text-action {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--lp-teal);
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
        }
        .lp-text-action:hover { color: var(--lp-teal-dark); }

        .lp-cta { padding: 5.5rem 0; background: #dceff1; }
        .lp-cta-inner {
            display: flex;
            justify-content: space-between;
            gap: 3rem;
            align-items: center;
        }
        .lp-cta h2 { max-width: 700px; margin: 0; font-size: 3.3rem; line-height: 1.1; }
        .lp-cta-action {
            min-height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            flex: 0 0 auto;
            padding: 0.75rem 1.2rem;
            border-radius: 7px;
            background: var(--lp-teal);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
        }
        .lp-cta-action:hover { background: var(--lp-teal-dark); color: #fff; }

        .lp-footer { padding: 3rem 0; background: #0f2028; color: #9eb0b8; }
        .lp-footer-inner { display: flex; justify-content: space-between; gap: 2rem; align-items: center; }
        .lp-footer .lp-brand { color: #fff; }
        .lp-footer-meta { display: flex; gap: 1.25rem; align-items: center; font-size: 0.76rem; }
        .lp-footer a { color: #d4e1e5; text-decoration: none; }
        .lp-footer a:hover { color: #fff; }

        @media (max-width: 991.98px) {
            .lp-nav-anchor { display: none; }
            .lp-hero { min-height: 650px; background-position: 62% center; }
            .lp-hero::after { width: 72%; }
            .lp-hero-content { width: min(620px, 70%); }
            .lp-hero h1 { font-size: 4.6rem; }
            .lp-section-title { font-size: 2.8rem; }
            .lp-platform-copy h2 { font-size: 2.9rem; }
            .lp-cta h2 { font-size: 2.75rem; }
            .lp-journey-head,
            .lp-platform-grid { grid-template-columns: 1fr; gap: 2rem; }
            .lp-platform-grid { gap: 3.5rem; }
            .lp-product-window { max-width: 760px; }
        }

        @media (max-width: 767.98px) {
            .lp-container { width: min(100% - 28px, 1180px); }
            .lp-nav { min-height: 64px; }
            .lp-nav .container-fluid { width: calc(100% - 16px); }
            .lp-brand { font-size: 0.95rem; }
            .lp-mark { width: 34px; height: 34px; }
            .lp-sign-in { min-height: 40px; padding-inline: 0.8rem; }
            .lp-sign-in span { display: none; }

            .lp-hero {
                min-height: calc(100svh - 96px);
                max-height: none;
                align-items: flex-end;
                background-position: 64% center;
            }
            .lp-hero::before { background: rgba(7, 35, 45, 0.42); }
            .lp-hero::after {
                inset: auto 0 0;
                width: 100%;
                height: 70%;
                background: rgba(7, 43, 55, 0.88);
            }
            .lp-hero-content {
                width: 100%;
                min-width: 0;
                padding: 4rem 0 3rem;
            }
            .lp-hero h1 {
                max-width: 100%;
                overflow-wrap: anywhere;
                font-size: 3.7rem;
            }
            .lp-hero-lead { font-size: 1.05rem; }
            .lp-hero-actions { display: grid; grid-template-columns: 1fr 1fr; }
            .lp-primary-action, .lp-secondary-action { padding-inline: 0.75rem; }
            .lp-section-title { font-size: 2.2rem; }
            .lp-platform-copy h2 { font-size: 2.35rem; }
            .lp-cta h2 { font-size: 2.2rem; }

            .lp-trust-grid { grid-template-columns: 1fr; }
            .lp-trust-item {
                min-height: 76px;
                padding-inline: 0;
                border-right: 0;
                border-bottom: 1px solid var(--lp-line);
            }
            .lp-trust-item:last-child { border-bottom: 0; }

            .lp-journey,
            .lp-platform,
            .lp-access { padding: 5rem 0; }
            .lp-journey-head { margin-bottom: 3rem; }
            .lp-steps { grid-template-columns: 1fr; border-top: 0; }
            .lp-step {
                display: grid;
                grid-template-columns: 42px 1fr;
                column-gap: 1rem;
                padding: 1.35rem 0;
                border-top: 1px solid var(--lp-line);
            }
            .lp-step-number { margin-top: -18px; }
            .lp-step h3 { margin: 0; }
            .lp-step p { grid-column: 2; margin-top: 0.5rem; }

            .lp-product-body { grid-template-columns: 1fr; }
            .lp-product-nav { display: none; }
            .lp-product-main { padding: 1rem; }
            .lp-metric-row { grid-template-columns: 1fr 1fr; }
            .lp-metric:last-child { display: none; }
            .lp-list-row { grid-template-columns: 1.2fr 0.8fr; }
            .lp-list-row > :last-child { display: none; }

            .lp-access-grid { grid-template-columns: 1fr; }
            .lp-access-panel { padding: 2rem; }
            .lp-access-panel p { min-height: 0; }

            .lp-cta-inner,
            .lp-footer-inner { align-items: flex-start; flex-direction: column; }
            .lp-footer-meta { flex-wrap: wrap; }
        }

        @media (max-width: 430px) {
            .lp-hero h1 { font-size: 3rem; }
            .lp-hero-actions { grid-template-columns: 1fr; }
            .lp-primary-action,
            .lp-secondary-action { width: 100%; }
            .lp-hero-note { align-items: flex-start; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body>
    <nav class="navbar lp-nav sticky-top" aria-label="Primary navigation">
        <div class="container-fluid px-0">
            <a class="lp-brand" href="{{ route('home') }}" aria-label="{{ config('app.name', 'TheraConnect') }} home">
                <span class="lp-mark" aria-hidden="true"><i class="bi bi-activity"></i></span>
                <span>{{ config('app.name', 'TheraConnect') }}</span>
            </a>

            <div class="d-flex align-items-center gap-4">
                <a href="#care-journey" class="lp-nav-link lp-nav-anchor">For patients</a>
                <a href="#connected-platform" class="lp-nav-link lp-nav-anchor">Platform</a>
                <a href="{{ route('login') }}" class="lp-sign-in">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    <span>Sign in</span>
                </a>
            </div>
        </div>
    </nav>

    <main>
        <section class="lp-hero" aria-labelledby="hero-title">
            <div class="lp-container">
                <div class="lp-hero-content">
                    <div class="lp-kicker">Care that stays connected</div>
                    <h1 id="hero-title">{{ config('app.name', 'TheraConnect') }}</h1>
                    <p class="lp-hero-lead">
                        A clearer way for patients and clinicians to coordinate appointments,
                        communicate securely, and keep care moving between sessions.
                    </p>

                    <div class="lp-hero-actions">
                        <a href="{{ route('register') }}" class="lp-primary-action">
                            Create patient account
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                        <a href="{{ route('login') }}" class="lp-secondary-action">
                            Sign in
                        </a>
                    </div>

                    <div class="lp-hero-note">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>Built for coordinated clinic care. Not for emergency or crisis response.</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="lp-trust" aria-label="Platform principles">
            <div class="lp-container lp-trust-grid">
                <div class="lp-trust-item">
                    <span class="lp-trust-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                    <div><strong>Private by design</strong><span>Role-based access protects care information.</span></div>
                </div>
                <div class="lp-trust-item">
                    <span class="lp-trust-icon" aria-hidden="true"><i class="bi bi-bell"></i></span>
                    <div><strong>Timely updates</strong><span>Appointments, reminders, and messages stay current.</span></div>
                </div>
                <div class="lp-trust-item">
                    <span class="lp-trust-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                    <div><strong>One care connection</strong><span>Patients and assigned clinicians share one workflow.</span></div>
                </div>
            </div>
        </section>

        <section class="lp-journey" id="care-journey">
            <div class="lp-container">
                <div class="lp-journey-head">
                    <div>
                        <div class="lp-kicker mb-3">A simpler care journey</div>
                        <h2 class="lp-section-title">Less time coordinating. More time focused on care.</h2>
                    </div>
                    <p class="lp-section-copy mb-0">
                        TheraConnect keeps the practical parts of ongoing care organized,
                        from the first appointment request through follow-up work and communication.
                    </p>
                </div>

                <div class="lp-steps">
                    <article class="lp-step">
                        <span class="lp-step-number">01</span>
                        <h3>Plan the next session</h3>
                        <p>Choose an assigned clinician, request an available time, and follow approval or rescheduling updates.</p>
                    </article>
                    <article class="lp-step">
                        <span class="lp-step-number">02</span>
                        <h3>Stay prepared</h3>
                        <p>Review appointment details, receive reminders, and join online sessions from the same account.</p>
                    </article>
                    <article class="lp-step">
                        <span class="lp-step-number">03</span>
                        <h3>Continue between visits</h3>
                        <p>Complete assessments and assignments, track progress, and communicate with the clinicians involved in your care.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="lp-platform" id="connected-platform">
            <div class="lp-container lp-platform-grid">
                <div>
                    <div class="lp-kicker">One connected platform</div>
                    <h2>Built around the rhythm of clinical work.</h2>
                    <p class="lp-platform-copy">
                        Patients get a focused care portal. Clinicians and administrators get
                        the operational visibility they need without breaking the continuity of the patient experience.
                    </p>
                    <div class="lp-capabilities">
                        <div class="lp-capability"><i class="bi bi-check2-circle" aria-hidden="true"></i> Appointment and availability management</div>
                        <div class="lp-capability"><i class="bi bi-check2-circle" aria-hidden="true"></i> Secure patient-clinician messaging</div>
                        <div class="lp-capability"><i class="bi bi-check2-circle" aria-hidden="true"></i> Assessments, assignments, and progress tracking</div>
                        <div class="lp-capability"><i class="bi bi-check2-circle" aria-hidden="true"></i> In-app, push, and real-time updates</div>
                    </div>
                </div>

                <div class="lp-product-window" aria-label="TheraConnect clinician dashboard preview">
                    <div class="lp-window-bar" aria-hidden="true">
                        <span class="lp-window-dot"></span>
                        <span class="lp-window-dot"></span>
                        <span class="lp-window-dot"></span>
                    </div>
                    <div class="lp-product-body">
                        <div class="lp-product-nav" aria-hidden="true">
                            <div class="lp-product-brand"><i class="bi bi-activity"></i> TheraConnect</div>
                            <div class="lp-product-nav-item active"><i class="bi bi-grid"></i> Dashboard</div>
                            <div class="lp-product-nav-item"><i class="bi bi-calendar3"></i> Appointments</div>
                            <div class="lp-product-nav-item"><i class="bi bi-people"></i> Patients</div>
                            <div class="lp-product-nav-item"><i class="bi bi-chat-dots"></i> Messages</div>
                            <div class="lp-product-nav-item"><i class="bi bi-journal-check"></i> Assignments</div>
                        </div>
                        <div class="lp-product-main">
                            <div class="lp-product-head">
                                <strong>Clinical overview</strong>
                                <span class="lp-product-date">Today</span>
                            </div>
                            <div class="lp-metric-row">
                                <div class="lp-metric"><i class="bi bi-calendar-check"></i><strong>6</strong><span>Today's appointments</span></div>
                                <div class="lp-metric"><i class="bi bi-clock-history"></i><strong>3</strong><span>Pending requests</span></div>
                                <div class="lp-metric"><i class="bi bi-people"></i><strong>24</strong><span>Active patients</span></div>
                            </div>
                            <div class="lp-appointment-list">
                                <div class="lp-list-head">Upcoming appointments</div>
                                <div class="lp-list-row">
                                    <div class="lp-person"><span class="lp-person-avatar">JD</span><span>Jane D.</span></div>
                                    <span>10:00 AM</span><span class="lp-status">Approved</span>
                                </div>
                                <div class="lp-list-row">
                                    <div class="lp-person"><span class="lp-person-avatar">AM</span><span>Alex M.</span></div>
                                    <span>11:30 AM</span><span class="lp-status">Approved</span>
                                </div>
                                <div class="lp-list-row">
                                    <div class="lp-person"><span class="lp-person-avatar">CR</span><span>Casey R.</span></div>
                                    <span>2:00 PM</span><span class="lp-status">Approved</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="lp-access" id="patient-access">
            <div class="lp-container">
                <div class="lp-access-head">
                    <div class="lp-kicker mb-3">Access TheraConnect</div>
                    <h2 class="lp-section-title">The right experience for every role.</h2>
                </div>

                <div class="lp-access-grid">
                    <article class="lp-access-panel">
                        <span class="lp-access-icon" aria-hidden="true"><i class="bi bi-phone"></i></span>
                        <h3>For patients</h3>
                        <p>Manage appointments and care activities from the responsive patient portal or the TheraConnect mobile application.</p>
                        @if(config('app.download_url'))
                            <a href="{{ config('app.download_url') }}" class="lp-text-action" download="TheraConnect.apk">
                                Download the Android app <i class="bi bi-download" aria-hidden="true"></i>
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="lp-text-action">
                                Create a patient account <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        @endif
                    </article>
                    <article class="lp-access-panel staff">
                        <span class="lp-access-icon" aria-hidden="true"><i class="bi bi-clipboard2-pulse"></i></span>
                        <h3>For clinicians and administrators</h3>
                        <p>Sign in to the clinical workspace to manage schedules, caseloads, communication, assessments, and follow-up work.</p>
                        <a href="{{ route('login') }}" class="lp-text-action">
                            Open the clinical workspace <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                    </article>
                </div>
            </div>
        </section>

        <section class="lp-cta">
            <div class="lp-container lp-cta-inner">
                <h2>Keep the next step in care clear and connected.</h2>
                <a href="{{ route('register') }}" class="lp-cta-action">
                    Get started
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </section>
    </main>

    <footer class="lp-footer">
        <div class="lp-container lp-footer-inner">
            <a class="lp-brand" href="{{ route('home') }}">
                <span class="lp-mark" aria-hidden="true"><i class="bi bi-activity"></i></span>
                <span>{{ config('app.name', 'TheraConnect') }}</span>
            </a>
            <div class="lp-footer-meta">
                <a href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('register') }}">Create account</a>
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'TheraConnect') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
