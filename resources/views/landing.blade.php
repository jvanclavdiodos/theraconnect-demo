<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'TheraConnect') }} — Connecting You to Better Care</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .hero-section {
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            color: white;
            padding: 6rem 0;
        }
        .feature-icon {
            font-size: 2.5rem;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">{{ config('app.name', 'TheraConnect') }}</a>
            <div class="ms-auto">
                <a href="{{ url('/login') }}" class="btn btn-outline-primary btn-sm">Sign In</a>
            </div>
        </div>
    </nav>

    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Connecting You to Better Care</h1>
            <p class="lead mb-4">
                Book appointments, complete therapeutic assignments, and stay connected with your clinic — all from one place.
            </p>
            <a href="#" class="btn btn-light btn-lg px-4 fw-semibold">Download the App</a>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <h5>Easy Appointment Booking</h5>
                    <p class="text-muted">Request and manage your therapy appointments with real-time status updates and reminders.</p>
                </div>
                <div class="col-md-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <h5>Therapeutic Assignments</h5>
                    <p class="text-muted">Receive, complete, and submit assignments from your clinician directly through the app.</p>
                </div>
                <div class="col-md-4">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-chat-dots"></i>
                    </div>
                    <h5>Instant Support</h5>
                    <p class="text-muted">Get quick answers to common questions through our intelligent chatbot, available 24/7.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-light py-4 text-center text-muted">
        <div class="container">
            <small>&copy; {{ date('Y') }} {{ config('app.name', 'TheraConnect') }}. All rights reserved.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
