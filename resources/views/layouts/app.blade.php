<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'TheraConnect'))</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <link href="{{ asset('css/theraconnect.css') }}" rel="stylesheet">

    <style>
        #sidebar-wrapper {
            min-width: 240px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            z-index: 1020;
        }
        @media (max-width: 767.98px) {
            #sidebar-wrapper {
                position: fixed;
                /* Pin top AND bottom so the panel always fills the visible
                   viewport — more reliable than min-height:100vh, which mobile
                   browsers mis-measure as the address bar shows/hides. */
                top: 0;
                bottom: 0;
                left: 0;
                /* Let top/bottom govern height; don't let the base 100vh
                   override it (100vh can exceed the visible area on mobile). */
                min-height: 0;
                margin-left: -240px;
            }
            #sidebar-wrapper.open {
                margin-left: 0;
            }
            #sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1019;
            }
            #sidebar-overlay.show {
                display: block;
            }
        }
    </style>

    @stack('styles')
</head>
<body x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    <div class="d-flex" id="wrapper">
        @auth
            {{-- Sidebar overlay (mobile) --}}
            <div id="sidebar-overlay" :class="{ 'show': sidebarOpen }" @click="sidebarOpen = false"></div>

            {{-- Sidebar --}}
            @include('partials.sidebar')
        @endauth

        <div id="page-content-wrapper" class="flex-grow-1">
            {{-- Navbar --}}
            @include('partials.navbar')

            @include('partials.flash')

            {{-- Breadcrumbs --}}
            @hasSection('breadcrumbs')
                <nav aria-label="breadcrumb" class="px-4 pt-3 mb-0">
                    <ol class="breadcrumb mb-0">
                        @yield('breadcrumbs')
                    </ol>
                </nav>
            @endif

            <main class="container-fluid px-4 py-3">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" integrity="sha384-l8f0VcPi/M1iHPv8egOnY/15TDwqgbOR1anMIJWvU6nLRgZVLTLSaNqi/TOoT5Fh" crossorigin="anonymous"></script>

    @stack('scripts')
</body>
</html>
