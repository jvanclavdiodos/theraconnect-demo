@php
    $isActive = fn(string $route) => request()->routeIs($route) ? 'active' : '';
    $name = auth()->check() ? auth()->user()->name : '';
    $initials = collect(explode(' ', trim($name)))
        ->filter()->take(2)
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('');

    $patient = auth()->user()->patient ?? null;
    $unreadMessages = 0;
    if ($patient) {
        $unreadMessages = app(\App\Services\MessageService::class)
            ->patientUnreadCount($patient->id, auth()->id());
    }
@endphp

<aside class="text-white" id="sidebar-wrapper" :class="{ 'open': sidebarOpen }">
    <div class="sidebar-heading d-flex justify-content-between align-items-center">
        <div class="tc-brand">
            <span class="tc-logo"><i class="bi bi-activity"></i></span>
            <div>
                <div class="tc-brand-name">{{ config('app.name', 'TheraConnect') }}</div>
                <div class="tc-brand-sub">Patient Portal</div>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-light d-md-none" @click="sidebarOpen = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav>
        <div class="tc-nav-group">
            <div class="tc-nav-group-label">Overview</div>
            <a href="{{ route('portal.dashboard') }}" class="tc-nav-item {{ $isActive('portal.dashboard') }}">
                <i class="bi bi-grid-1x2"></i> <span>Dashboard</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
        </div>

        <div class="tc-nav-group">
            <div class="tc-nav-group-label">Care</div>
            <a href="{{ route('portal.appointments.index') }}" class="tc-nav-item {{ $isActive('portal.appointments.*') }}">
                <i class="bi bi-calendar-check"></i> <span>Appointments</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
            <a href="{{ route('portal.assignments.index') }}" class="tc-nav-item {{ $isActive('portal.assignments.*') }}">
                <i class="bi bi-clipboard-check"></i> <span>Assignments</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
            <a href="{{ route('portal.messages.index') }}" class="tc-nav-item {{ $isActive('portal.messages.*') }}">
                <i class="bi bi-chat-dots"></i> <span>Messages</span>
                <span data-realtime-message-count data-count="{{ $unreadMessages }}"
                      class="badge bg-primary rounded-pill ms-auto {{ $unreadMessages > 0 ? '' : 'd-none' }}">
                    {{ $unreadMessages > 9 ? '9+' : $unreadMessages }}
                </span>
                <i class="bi bi-chevron-right tc-nav-chevron {{ $unreadMessages > 0 ? 'd-none' : '' }}"></i>
            </a>
            <a href="{{ route('portal.guide.show') }}" class="tc-nav-item {{ $isActive('portal.guide.*') }}">
                <i class="bi bi-question-circle"></i> <span>User Guide</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
        </div>

        <div class="tc-nav-group">
            <div class="tc-nav-group-label">My Progress</div>
            <a href="{{ route('portal.assessments.index') }}" class="tc-nav-item {{ $isActive('portal.assessments.*') }}">
                <i class="bi bi-card-checklist"></i> <span>Questionnaires</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
            <a href="{{ route('portal.mood.index') }}" class="tc-nav-item {{ $isActive('portal.mood.*') }}">
                <i class="bi bi-emoji-smile"></i> <span>Mood check-ins</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
            <a href="{{ route('portal.goals.index') }}" class="tc-nav-item {{ $isActive('portal.goals.*') }}">
                <i class="bi bi-bullseye"></i> <span>Goals</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
            <a href="{{ route('portal.notes.index') }}" class="tc-nav-item {{ $isActive('portal.notes.*') }}">
                <i class="bi bi-journal-text"></i> <span>Notes from clinician</span>
                <i class="bi bi-chevron-right tc-nav-chevron"></i>
            </a>
        </div>
    </nav>

    @auth
        <div class="tc-sidebar-footer">
            <a href="{{ route('portal.profile.show') }}" class="tc-user-chip text-decoration-none text-reset" title="My account">
                @if(auth()->user()->hasAvatar())
                    <img src="{{ route('portal.profile.avatar') }}" alt="avatar" class="tc-avatar" style="object-fit:cover;padding:0;">
                @else
                    <span class="tc-avatar">{{ $initials ?: 'U' }}</span>
                @endif
                <div class="overflow-hidden">
                    <div class="tc-user-name text-truncate">{{ $name }}</div>
                    <div class="tc-user-role">Patient</div>
                </div>
            </a>
        </div>
    @endauth
</aside>
