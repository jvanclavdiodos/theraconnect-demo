<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'TheraConnect'))</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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
                top: 0;
                left: 0;
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
        {{-- Sidebar overlay (mobile) --}}
        <div id="sidebar-overlay" :class="{ 'show': sidebarOpen }" @click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        @include('partials.sidebar')

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

    @stack('scripts')
</body>
</html>
