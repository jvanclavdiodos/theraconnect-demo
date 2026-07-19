@extends('layouts.app')
@section('realtime-resources', 'appointments')

@section('title', 'Dashboard — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

    // Build a natural greeting name. Clinician names are stored with a title and
    // credentials (e.g. "Dr. James Rivera, PsyD"), so the old "first token"
    // approach produced just "Dr.". Drop the trailing ", credentials" suffix,
    // then greet titled names as "Dr. {surname}" and others by first name.
    $base = trim(explode(',', trim(auth()->user()->name))[0]);
    $parts = preg_split('/\s+/', $base, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $honorifics = ['dr', 'dr.', 'mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.', 'mx', 'mx.', 'prof', 'prof.'];
    $greetingName = count($parts) > 1 && in_array(mb_strtolower($parts[0]), $honorifics, true)
        ? $parts[0] . ' ' . end($parts)   // "Dr. Rivera"
        : ($parts[0] ?? '');               // "James" / "Admin"

    $initials = fn($n) => collect(explode(' ', trim($n)))->filter()->take(2)
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<div class="mb-4">
    <h1 class="tc-page-title">{{ $greeting }}, {{ $greetingName }}</h1>
    <p class="tc-page-sub">{{ now()->format('l, F j, Y') }} — Here's your clinical overview for today.</p>
</div>

{{-- KPI cards --}}
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="tc-kpi">
            <div class="tc-kpi-head">
                <span class="tc-kpi-icon teal"><i class="bi bi-calendar-check"></i></span>
            </div>
            <div class="tc-kpi-value">{{ $todayAppointments }}</div>
            <div class="tc-kpi-label">Today's Appointments</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="tc-kpi">
            <div class="tc-kpi-head">
                <span class="tc-kpi-icon amber"><i class="bi bi-clock-history"></i></span>
            </div>
            <div class="tc-kpi-value">{{ $pendingAppointments }}</div>
            <div class="tc-kpi-label">Pending Requests</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="tc-kpi">
            <div class="tc-kpi-head">
                <span class="tc-kpi-icon green"><i class="bi bi-people"></i></span>
            </div>
            <div class="tc-kpi-value">{{ $totalPatients }}</div>
            <div class="tc-kpi-label">Active Patients</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="tc-kpi">
            <div class="tc-kpi-head">
                <span class="tc-kpi-icon blue"><i class="bi bi-clipboard-check"></i></span>
            </div>
            <div class="tc-kpi-value">{{ $pendingAssignments->count() }}</div>
            <div class="tc-kpi-label">Pending Assignments</div>
        </div>
    </div>
</div>

{{-- Two-column lower section --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-clock-history me-2"></i> Upcoming Appointments</span>
                <a href="{{ route('appointments.index') }}" class="tc-viewall">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div>
                @forelse ($upcomingAppointments as $appt)
                    <div class="tc-list-row">
                        @if($appt->patient->user->hasAvatar() && auth()->user()->can('viewAvatar', $appt->patient->user))
                            <img src="{{ route('avatars.show', $appt->patient->user) }}?v={{ $appt->patient->user->updated_at?->timestamp }}"
                                 alt="{{ $appt->patient->user->name }} profile photo"
                                 class="tc-row-avatar tc-row-avatar-image" data-dashboard-patient-avatar>
                        @else
                            <span class="tc-row-avatar" data-dashboard-patient-initials>{{ $initials($appt->patient->user->name) }}</span>
                        @endif
                        <div class="flex-grow-1 min-w-0">
                            <div class="tc-row-title text-truncate">{{ $appt->patient->user->name }}</div>
                            <div class="tc-row-sub">{{ $appt->requested_at->format('M d, h:i A') }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="tc-mode {{ $appt->mode === 'online' ? 'online' : 'in-person' }}">
                                {{ $appt->mode === 'online' ? 'Online' : 'In-Person' }}
                            </span>
                            <span class="badge bg-{{ match($appt->status) { 'approved' => 'success', 'pending','rescheduled' => 'warning', 'rejected','cancelled' => 'danger', 'completed' => 'info', default => 'secondary' } }}">{{ ucfirst($appt->status) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="tc-empty">
                        <div class="tc-empty-icon"><i class="bi bi-calendar-x"></i></div>
                        <div>No upcoming appointments.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-journal-check me-2"></i> Pending Assignments</span>
                <a href="{{ route('assignments.index') }}" class="tc-viewall">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div>
                @forelse ($pendingAssignments as $ass)
                    <div class="tc-list-row">
                        <span class="tc-row-avatar neutral">{{ $initials($ass->patient->user->name) }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="tc-row-title text-truncate">{{ $ass->title }}</div>
                            <div class="tc-row-sub">
                                {{ $ass->patient->user->name }}@if ($ass->due_date) · Due {{ $ass->due_date->format('M d, Y') }}@endif
                            </div>
                        </div>
                        <span class="badge bg-secondary">{{ $ass->submissions_count }} submission(s)</span>
                    </div>
                @empty
                    <div class="tc-empty">
                        <div class="tc-empty-icon"><i class="bi bi-clipboard"></i></div>
                        <div>No pending assignments.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Availability calendar — clinicians manage their own schedule here. --}}
@if(auth()->user()->role === 'clinician' && auth()->user()->clinician)
    @include('partials.availability-calendar')
@endif
@endsection
