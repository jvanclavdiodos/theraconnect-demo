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
        'rejected', 'cancelled', 'no_show', 'expired' => 'danger', 'completed' => 'info', default => 'secondary',
    };
@endphp

<div class="tc-page-header">
    <div class="tc-page-header-copy">
        <h1 class="tc-page-title">My Appointments</h1>
        <p class="tc-page-sub mb-0">Request, review, and manage your visits.</p>
    </div>
    <div class="tc-page-actions">
        <a href="{{ route('portal.appointments.book') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Book Appointment
        </a>
    </div>
</div>

<form method="GET" action="{{ route('portal.appointments.index') }}" class="row g-2 align-items-end tc-filter-panel" role="search">
    <div class="col-12 col-lg-4">
        <label for="appointment-search" class="form-label small fw-semibold">Search</label>
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input id="appointment-search" type="search" name="search" value="{{ $search }}" class="form-control"
                   placeholder="Clinician or specialization">
        </div>
    </div>
    <div class="col-12 col-sm-4 col-lg-2">
        <label for="appointment-requested-date" class="form-label small fw-semibold">Requested date</label>
        <input id="appointment-requested-date" type="date" name="requested_date" value="{{ $requestedDate }}" class="form-control">
    </div>
    <div class="col-12 col-sm-4 col-lg-2">
        <label for="appointment-status" class="form-label small fw-semibold">Status</label>
        <select id="appointment-status" name="status" class="form-select">
            <option value="">All statuses</option>
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rescheduled' => 'Rescheduled', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'no_show' => 'No-show', 'expired' => 'Expired'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-4 col-lg-2">
        <label for="appointment-mode" class="form-label small fw-semibold">Meeting type</label>
        <select id="appointment-mode" name="mode" class="form-select">
            <option value="">All meeting types</option>
            <option value="online" @selected($mode === 'online')>Online</option>
            <option value="in_person" @selected($mode === 'in_person')>In-person</option>
        </select>
    </div>
    <div class="col-12 col-sm-4 col-lg-2">
        <label for="appointment-order" class="form-label small fw-semibold">Date order</label>
        <select id="appointment-order" name="direction" class="form-select">
            <option value="desc" @selected($direction === 'desc')>Newest first</option>
            <option value="asc" @selected($direction === 'asc')>Oldest first</option>
        </select>
        <input type="hidden" name="sort" value="appointment_date">
    </div>
    <div class="col-12 tc-filter-actions mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply filters</button>
        @if($search !== '' || $requestedDate || $status || $mode || $direction !== 'desc')
            <a href="{{ route('portal.appointments.index') }}" class="btn btn-outline-secondary">Reset</a>
        @endif
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 tc-responsive-table">
            <thead class="table-light">
                <tr><th>When</th><th>Clinician</th><th>Mode</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($appointments as $appt)
                    <tr>
                        <td data-label="When">
                            <a href="{{ route('portal.appointments.show', $appt) }}" class="text-decoration-none fw-semibold">
                                {{ ($appt->scheduled_at ?? $appt->requested_at)->format('D, M j, Y · g:i A') }}
                            </a>
                        </td>
                        <td data-label="Clinician">{{ $appt->clinician?->user?->name ?? '—' }}</td>
                        <td data-label="Mode">{{ $appt->mode === 'online' ? 'Online' : 'In-person' }}</td>
                        <td data-label="Status"><span class="badge bg-{{ $statusColor($appt->status) }}">{{ ucfirst(str_replace('_',' ',$appt->status)) }}</span></td>
                        <td data-label="Actions" class="text-end">
                            <a href="{{ route('portal.appointments.show', $appt) }}" class="btn btn-sm btn-outline-secondary"
                               aria-label="View appointment details" data-bs-toggle="tooltip" data-bs-title="View details">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tc-table-empty">
                        <div class="tc-empty">
                            <div class="tc-empty-icon"><i class="bi bi-calendar-x"></i></div>
                            <div class="mb-3">{{ $search !== '' || $requestedDate || $status || $mode ? 'No appointments match your search or filters.' : 'You have no appointments yet.' }}</div>
                            @if($search === '' && ! $requestedDate && ! $status && ! $mode)
                                <a href="{{ route('portal.appointments.book') }}" class="btn btn-primary btn-sm">Book your first appointment</a>
                            @endif
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $appointments->links() }}</div>
@endsection
