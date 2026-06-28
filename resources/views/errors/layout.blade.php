<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'TheraConnect'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <link href="{{ asset('css/theraconnect.css') }}" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="min-vh-100 d-flex flex-column justify-content-center align-items-center px-3">
        <div class="card shadow-sm" style="max-width: 480px; width: 100%;">
            <div class="card-body text-center py-5 px-4">
                <div class="mb-3">
                    <i class="@yield('icon', 'bi bi-exclamation-triangle-fill') display-1 text-primary"></i>
                </div>
                <h1 class="h3 mb-2">@yield('code', 'Error')</h1>
                <p class="text-muted mb-4">@yield('message', 'Something went wrong.')</p>
                <div class="d-flex gap-2 justify-content-center">
                    @auth
                        @if(auth()->user()->role === 'patient')
                            <a href="{{ route('portal.dashboard') }}" class="btn btn-primary">
                                <i class="bi bi-house me-1"></i> Back to dashboard
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                                <i class="bi bi-house me-1"></i> Back to dashboard
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
                        </a>
                    @endauth
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Go back
                    </a>
                </div>
            </div>
        </div>
        <p class="text-muted small mt-4 mb-0">&copy; {{ date('Y') }} {{ config('app.name', 'TheraConnect') }}</p>
    </div>
</body>
</html>
