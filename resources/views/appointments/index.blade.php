@extends('layouts.app')

@section('title', 'Appointments — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Appointments</li>
@endsection

@section('content')
@php
    $initials = fn($n) => collect(explode(' ', trim($n)))->filter()->take(2)
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $filters = [
        '' => 'All', 'pending' => 'Pending', 'approved' => 'Approved',
        'rejected' => 'Rejected', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
    ];
@endphp

<div class="mb-4">
    <h1 class="tc-page-title">Appointments</h1>
    <p class="tc-page-sub">Review, approve, and manage appointment requests.</p>
</div>

{{-- Filter pills --}}
<div class="tc-filters mb-4">
    @foreach ($filters as $key => $label)
        <a href="{{ $key ? route('appointments.index', ['status' => $key]) : route('appointments.index') }}"
           class="tc-filter {{ (request('status') ?? '') === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Clinician</th>
                    <th>Requested</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appointments as $appt)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="tc-cell-avatar">{{ $initials($appt->patient->user->name) }}</span>
                                <span class="fw-semibold">{{ $appt->patient->user->name }}</span>
                            </div>
                        </td>
                        <td>{{ $appt->clinician?->user?->name ?? '—' }}</td>
                        <td>{{ $appt->requested_at->format('M d, Y h:i A') }}</td>
                        <td>
                            <span class="tc-mode {{ $appt->mode === 'online' ? 'online' : 'in-person' }}">
                                {{ $appt->mode === 'online' ? 'Online' : 'In-Person' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-{{ match($appt->status) {
                                'approved' => 'success',
                                'pending', 'rescheduled' => 'warning',
                                'rejected', 'cancelled' => 'danger',
                                'completed' => 'info',
                                default => 'secondary'
                            } }}">{{ ucfirst($appt->status) }}</span>
                        </td>
                        <td class="text-end">
                            @if ($appt->meetingLinkActive())
                                <a href="{{ $appt->meeting_link }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-primary" title="Join video call">
                                    <i class="bi bi-camera-video"></i>
                                </a>
                            @elseif ($appt->mode === 'online' && $appt->meeting_link && optional($appt->meetingLinkExpiresAt())->isPast())
                                <span class="badge bg-secondary" title="Link expired {{ $appt->meetingLinkExpiresAt()->diffForHumans() }}">
                                    <i class="bi bi-camera-video-off"></i> Link expired
                                </span>
                            @endif

                            @if ($appt->status === 'pending')
                                <form action="{{ route('appointments.approve', $appt) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-success" title="Approve">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <form action="{{ route('appointments.reject', $appt) }}" method="POST" class="d-inline"
                                      x-data @submit.prevent="if (confirm('Reject this appointment?')) $el.submit()">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-danger" title="Reject">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            @endif

                            @if (in_array($appt->status, ['approved', 'rescheduled']))
                                <button class="btn btn-sm btn-outline-secondary" title="Reschedule"
                                        x-data
                                        @click="$dispatch('open-reschedule', { id: {{ $appt->id }} })">
                                    <i class="bi bi-calendar2-week"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="tc-empty">
                                <div class="tc-empty-icon"><i class="bi bi-calendar-check"></i></div>
                                <div>No appointments match this filter.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $appointments->links() }}
</div>

{{-- Reschedule modal (hidden by default) --}}
<div x-data="{ open: false, apptId: null, date: '' }"
     x-on:open-reschedule.window="open = true; apptId = $event.detail.id"
     x-show="open"
     x-cloak
     style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050;">
    <div class="modal d-block" tabindex="-1" style="display: block !important;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form :action="'/appointments/' + apptId + '/reschedule'" method="POST">
                    @csrf @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title">Reschedule Appointment</h5>
                        <button type="button" class="btn-close" @click="open = false"></button>
                    </div>
                    <div class="modal-body">
                        <label for="scheduled_at" class="form-label">New Date &amp; Time</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>[x-cloak] { display: none !important; }</style>
@endpush
@endsection
