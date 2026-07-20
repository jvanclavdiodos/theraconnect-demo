@extends('layouts.portal')
@section('realtime-resources', 'appointments')

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

<form method="GET" action="{{ route('portal.appointments.index') }}" class="row g-2 align-items-end mb-4">
    <div class="col-12 col-sm-4 col-lg-3">
        <label for="appointment-status" class="form-label small fw-semibold">Status</label>
        <select id="appointment-status" name="status" class="form-select">
            <option value="">All statuses</option>
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rescheduled' => 'Rescheduled', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'no_show' => 'No-show'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-4 col-lg-3">
        <label for="appointment-mode" class="form-label small fw-semibold">Meeting type</label>
        <select id="appointment-mode" name="mode" class="form-select">
            <option value="">All meeting types</option>
            <option value="online" @selected($mode === 'online')>Online</option>
            <option value="in_person" @selected($mode === 'in_person')>In-person</option>
        </select>
    </div>
    <div class="col-12 col-sm-4 col-lg-3">
        <label for="appointment-order" class="form-label small fw-semibold">Date order</label>
        <select id="appointment-order" name="direction" class="form-select">
            <option value="desc" @selected($direction === 'desc')>Newest first</option>
            <option value="asc" @selected($direction === 'asc')>Oldest first</option>
        </select>
        <input type="hidden" name="sort" value="appointment_date">
    </div>
    <div class="col-12 col-lg-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i> Apply</button>
        @if($status || $mode || $direction !== 'desc')
            <a href="{{ route('portal.appointments.index') }}" class="btn btn-outline-secondary">Reset</a>
        @endif
    </div>
</form>

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
                            <a href="{{ route('portal.appointments.show', $appt) }}" class="btn btn-sm btn-outline-secondary"
                               aria-label="View appointment details" data-bs-toggle="tooltip" data-bs-title="View details">
                                <i class="bi bi-eye" aria-hidden="true"></i>
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
