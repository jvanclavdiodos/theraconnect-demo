@php
    $isActive = fn(string $route) => request()->routeIs($route) ? 'active' : '';
    $role = auth()->check() ? auth()->user()->role : null;
    $name = auth()->check() ? auth()->user()->name : '';
    $initials = collect(explode(' ', trim($name)))
        ->filter()
        ->take(2)
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('');
    $roleLabel = $role ? ucfirst($role) : '';
@endphp

<aside class="text-white" id="sidebar-wrapper" :class="{ 'open': sidebarOpen }">
    <div class="sidebar-heading d-flex justify-content-between align-items-center">
        <div class="tc-brand">
            <span class="tc-logo"><i class="bi bi-activity"></i></span>
            <div>
                <div class="tc-brand-name">{{ config('app.name', 'TheraConnect') }}</div>
                <div class="tc-brand-sub">Clinic Management</div>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-light d-md-none" @click="sidebarOpen = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav>
        @auth
            {{-- Overview --}}
            <div class="tc-nav-group">
                <div class="tc-nav-group-label">Overview</div>
                <a href="{{ route('dashboard') }}" class="tc-nav-item {{ $isActive('dashboard') }}">
                    <i class="bi bi-grid-1x2"></i> <span>Dashboard</span>
                    <i class="bi bi-chevron-right tc-nav-chevron"></i>
                </a>
            </div>

            @if(in_array($role, ['admin', 'clinician']))
                {{-- Clinical --}}
                <div class="tc-nav-group">
                    <div class="tc-nav-group-label">Clinical</div>
                    <a href="{{ route('appointments.index') }}" class="tc-nav-item {{ $isActive('appointments.*') }}">
                        <i class="bi bi-calendar-check"></i> <span>Appointments</span>
                        <i class="bi bi-chevron-right tc-nav-chevron"></i>
                    </a>
                    <a href="{{ route('assignments.index') }}" class="tc-nav-item {{ $isActive('assignments.*') }}">
                        <i class="bi bi-clipboard-check"></i> <span>Assignments</span>
                        <i class="bi bi-chevron-right tc-nav-chevron"></i>
                    </a>
                    <a href="{{ route('patients.index') }}" class="tc-nav-item {{ $isActive('patients.*') }}">
                        <i class="bi bi-people"></i> <span>Patients</span>
                        <i class="bi bi-chevron-right tc-nav-chevron"></i>
                    </a>
                </div>

                {{-- Tools — clinic administration (admin only) --}}
                @if($role === 'admin')
                    <div class="tc-nav-group">
                        <div class="tc-nav-group-label">Administration</div>
                        <a href="{{ route('clinicians.index') }}" class="tc-nav-item {{ $isActive('clinicians.*') }}">
                            <i class="bi bi-person-badge"></i> <span>Clinicians</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                        <a href="{{ route('chatbot-content.index') }}" class="tc-nav-item {{ $isActive('chatbot-content.*') }}">
                            <i class="bi bi-robot"></i> <span>Chatbot Content</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                        <a href="{{ route('notifications.logs') }}" class="tc-nav-item {{ $isActive('notifications.*') }}">
                            <i class="bi bi-bell"></i> <span>Notification Logs</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                    </div>
                @endif
            @endif
        @endauth
    </nav>

    @auth
        <div class="tc-sidebar-footer">
            <div class="tc-user-chip">
                <span class="tc-avatar">{{ $initials ?: 'U' }}</span>
                <div class="overflow-hidden">
                    <div class="tc-user-name text-truncate">{{ $name }}</div>
                    <div class="tc-user-role">{{ $roleLabel }}</div>
                </div>
            </div>
        </div>
    @endauth
</aside>
