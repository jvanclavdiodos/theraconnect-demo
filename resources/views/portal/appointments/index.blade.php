@extends('layouts.portal')

@section('title', 'My Appointments — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Appointments</li>
@endsection

@section('content')
@php
    $statusColor = fn($s) => match($s) {
        'approved' => 'success', 'pending', 'rescheduled' => 'warning',
        'rejected', 'cancelled', 'no_show' => 'danger', 'completed' => 'info', default => 'secondary',
    };
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="tc-page-title">My Appointments</h1>
        <p class="tc-page-sub mb-0">Request, review, and manage your visits.</p>
    </div>
    <a href="{{ route('portal.appointments.book') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Book Appointment
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>When</th><th>Clinician</th><th>Mode</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($appointments as $appt)
                    <tr>
                        <td>
                            <a href="{{ route('portal.appointments.show', $appt) }}" class="text-decoration-none fw-semibold">
                                {{ ($appt->scheduled_at ?? $appt->requested_at)->format('D, M j, Y · g:i A') }}
                            </a>
                        </td>
                        <td>{{ $appt->clinician?->user?->name ?? '—' }}</td>
                        <td>{{ $appt->mode === 'online' ? 'Online' : 'In-person' }}</td>
                        <td><span class="badge bg-{{ $statusColor($appt->status) }}">{{ ucfirst(str_replace('_',' ',$appt->status)) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('portal.appointments.show', $appt) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">
                        <div class="tc-empty">
                            <div class="tc-empty-icon"><i class="bi bi-calendar-x"></i></div>
                            <div class="mb-3">You have no appointments yet.</div>
                            <a href="{{ route('portal.appointments.book') }}" class="btn btn-primary btn-sm">Book your first appointment</a>
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $appointments->links() }}</div>
@endsection
