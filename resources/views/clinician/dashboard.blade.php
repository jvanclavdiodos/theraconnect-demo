@extends('layouts.app')

@section('title', 'Dashboard — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<h2>Dashboard</h2>
<p class="text-muted">Welcome back, {{ auth()->user()->name }}.</p>

<div class="row g-4 mt-2">
    <div class="col-md-4">
        <div class="card border-primary shadow-sm h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h1 class="display-4 text-primary">{{ $totalPatients }}</h1>
                <p class="text-muted mb-0">Total Patients</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning shadow-sm h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h1 class="display-4 text-warning">{{ $pendingAppointments }}</h1>
                <p class="text-muted mb-0">Pending Appointments</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info shadow-sm h-100">
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <h1 class="display-4 text-info">{{ $todayAppointments }}</h1>
                <p class="text-muted mb-0">Today's Appointments</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-3">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><strong><i class="bi bi-clock-history me-1"></i> Recent Appointments</strong></div>
            <div class="list-group list-group-flush">
                @forelse ($recentAppointments as $appt)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $appt->patient->user->name }}</strong>
                            <br><small class="text-muted">{{ $appt->requested_at->format('M d, h:i A') }} &middot; {{ ucfirst($appt->mode) }}</small>
                        </div>
                        <span class="badge bg-{{ match($appt->status) { 'approved' => 'success', 'pending','rescheduled' => 'warning', 'rejected','cancelled' => 'danger', 'completed' => 'info', default => 'secondary' } }}">{{ ucfirst($appt->status) }}</span>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-4">No recent appointments.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><strong><i class="bi bi-journal-check me-1"></i> Pending Assignments</strong></div>
            <div class="list-group list-group-flush">
                @forelse ($pendingAssignments as $ass)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $ass->patient->user->name }}</strong> — {{ $ass->title }}
                            @if ($ass->due_date)
                                <br><small class="text-muted">Due: {{ $ass->due_date->format('M d, Y') }}</small>
                            @endif
                        </div>
                        <span class="badge bg-secondary">{{ $ass->submissions_count }} submission(s)</span>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-4">No pending assignments.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
