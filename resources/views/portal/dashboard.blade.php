@extends('layouts.portal')
@section('realtime-resources', 'appointments notifications')

@section('title', 'My Dashboard — ' . config('app.name'))

@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim(auth()->user()->name))[0] ?? '';
    $statusColor = fn($s) => match($s) {
        'approved' => 'success', 'pending', 'rescheduled' => 'warning',
        'rejected', 'cancelled', 'no_show' => 'danger', 'completed' => 'info', default => 'secondary',
    };
@endphp

<div class="mb-4">
    <h1 class="tc-page-title">{{ $greeting }}, {{ $firstName }}</h1>
    <p class="tc-page-sub">{{ now()->format('l, F j, Y') }} — here's your care overview.</p>
</div>

@if($pendingAssessments->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4" style="background: var(--tc-teal-light);">
        <div class="card-body d-flex align-items-center gap-3">
            <i class="bi bi-card-checklist fs-3 text-teal" style="color: var(--tc-teal);"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">
                    You have {{ $pendingAssessments->count() }}
                    questionnaire{{ $pendingAssessments->count() === 1 ? '' : 's' }} to complete
                </div>
                <div class="small text-muted">{{ $pendingAssessments->map->title()->implode(', ') }}</div>
            </div>
            <a href="{{ route('portal.assessments.index') }}" class="btn btn-sm btn-primary">Open</a>
        </div>
    </div>
@endif

{{-- Quick stats --}}
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <a href="{{ route('portal.appointments.index') }}" class="text-decoration-none">
            <div class="tc-kpi">
                <div class="tc-kpi-head"><span class="tc-kpi-icon teal"><i class="bi bi-calendar-check"></i></span></div>
                <div class="tc-kpi-value">{{ $upcoming->count() }}</div>
                <div class="tc-kpi-label">Upcoming appointments</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('portal.assignments.index') }}" class="text-decoration-none">
            <div class="tc-kpi">
                <div class="tc-kpi-head"><span class="tc-kpi-icon amber"><i class="bi bi-clipboard-check"></i></span></div>
                <div class="tc-kpi-value">{{ $pendingAssignments }}</div>
                <div class="tc-kpi-label">Assignments to do</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('portal.assessments.index') }}" class="text-decoration-none">
            <div class="tc-kpi">
                <div class="tc-kpi-head"><span class="tc-kpi-icon green"><i class="bi bi-card-checklist"></i></span></div>
                <div class="tc-kpi-value">{{ $pendingAssessments->count() }}</div>
                <div class="tc-kpi-label">Questionnaires</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-xl-3">
        <a href="{{ route('portal.notifications.index') }}" class="text-decoration-none">
            <div class="tc-kpi">
                <div class="tc-kpi-head"><span class="tc-kpi-icon blue"><i class="bi bi-bell"></i></span></div>
                <div class="tc-kpi-value">{{ $unreadNotifications }}</div>
                <div class="tc-kpi-label">Unread alerts</div>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2"></i> Upcoming Appointments</span>
        <a href="{{ route('portal.appointments.book') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Book
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>When</th><th>Clinician</th><th>Mode</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($upcoming as $appt)
                    <tr style="position: relative; cursor: pointer;">
                        <td>
                            <a href="{{ route('portal.appointments.show', $appt) }}" class="stretched-link text-decoration-none text-reset"
                               aria-label="View appointment for {{ $appt->clinician?->user?->name ?? 'your clinician' }} on {{ ($appt->scheduled_at ?? $appt->requested_at)->format('M j, g:i A') }}">
                                {{ ($appt->scheduled_at ?? $appt->requested_at)->format('D, M j · g:i A') }}
                            </a>
                        </td>
                        <td>{{ $appt->clinician?->user?->name ?? '—' }}</td>
                        <td>{{ $appt->mode === 'online' ? 'Online' : 'In-person' }}</td>
                        <td><span class="badge bg-{{ $statusColor($appt->status) }}">{{ ucfirst($appt->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4">
                        <div class="tc-empty">
                            <div class="tc-empty-icon"><i class="bi bi-calendar-x"></i></div>
                            <div class="mb-3">No upcoming appointments.</div>
                            <a href="{{ route('portal.appointments.book') }}" class="btn btn-primary btn-sm">Book an appointment</a>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
