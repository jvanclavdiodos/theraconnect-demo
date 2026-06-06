@php
    $isActive = fn(string $route) => request()->routeIs($route) ? 'active' : '';
@endphp

<aside class="bg-dark text-white" id="sidebar-wrapper"
       :class="{ 'open': sidebarOpen }">
    <div class="sidebar-heading text-center py-3 border-bottom border-secondary d-flex justify-content-between align-items-center px-3">
        <strong>{{ config('app.name', 'TheraConnect') }}</strong>
        <button class="btn btn-sm btn-outline-light d-md-none" @click="sidebarOpen = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <nav class="list-group list-group-flush">
        @auth
            <a href="{{ route('dashboard') }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary {{ $isActive('dashboard') }}">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'clinician']))
                <a href="{{ route('appointments.index') }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary {{ $isActive('appointments.*') }}">
                    <i class="bi bi-calendar-check me-2"></i> Appointments
                </a>
                <a href="{{ route('patients.index') }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary {{ $isActive('patients.*') }}">
                    <i class="bi bi-people me-2"></i> Patients
                </a>
                <a href="{{ route('assignments.index') }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary {{ $isActive('assignments.*') }}">
                    <i class="bi bi-journal-check me-2"></i> Assignments
                </a>
                <a href="{{ route('chatbot-content.index') }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary {{ $isActive('chatbot-content.*') }}">
                    <i class="bi bi-robot me-2"></i> Chatbot Content
                </a>
                <a href="{{ route('notifications.logs') }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary {{ $isActive('notifications.*') }}">
                    <i class="bi bi-bell me-2"></i> Notification Logs
                </a>
            @endif
            @if(auth()->user()->role === 'admin')
                <a href="{{ route('clinicians.index') }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary {{ $isActive('clinicians.*') }}">
                    <i class="bi bi-person-badge me-2"></i> Clinicians
                </a>
            @endif
        @endauth
    </nav>
</aside>
