@extends('layouts.portal')
@section('realtime-resources', 'appointments')

@section('title', 'Appointment — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('portal.appointments.index') }}">Appointments</a></li>
    <li class="breadcrumb-item active">Details</li>
@endsection

@section('content')
@php
    $statusColor = match($appointment->status) {
        'approved' => 'success', 'pending', 'rescheduled' => 'warning',
        'rejected', 'cancelled', 'no_show' => 'danger', 'completed' => 'info', default => 'secondary',
    };
    $canCancel = in_array($appointment->status, ['pending', 'approved', 'rescheduled']);
@endphp

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body text-center p-4">
                <span class="badge bg-{{ $statusColor }} mb-3">{{ ucfirst(str_replace('_',' ',$appointment->status)) }}</span>
                <h3 class="mb-1">{{ ($appointment->scheduled_at ?? $appointment->requested_at)->format('l, F j, Y') }}</h3>
                <div class="fs-5 text-muted">{{ ($appointment->scheduled_at ?? $appointment->requested_at)->format('g:i A') }}</div>
            </div>

            @if($appointment->meetingLinkActive())
                <div class="px-4 pb-3">
                    <a href="{{ $appointment->meeting_link }}" target="_blank" rel="noopener" class="btn btn-primary w-100">
                        <i class="bi bi-camera-video me-1"></i> Join Video Call
                    </a>
                </div>
            @elseif($appointment->mode === 'online' && $appointment->meeting_link && optional($appointment->meetingLinkExpiresAt())->isPast())
                <div class="px-4 pb-3">
                    <div class="alert alert-secondary mb-0"><i class="bi bi-camera-video-off me-1"></i> This meeting link has expired.</div>
                </div>
            @endif

            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Clinician</span><span>{{ $appointment->clinician?->user?->name ?? 'Not assigned' }}</span>
                </li>
                @if($appointment->clinician)
                    <li class="list-group-item">
                        <div class="text-muted mb-2">Clinician contact</div>
                        <div class="d-grid gap-2">
                            @if($appointment->clinician->specialization)
                                <div><i class="bi bi-person-badge me-2 text-muted" aria-hidden="true"></i>{{ $appointment->clinician->specialization }}</div>
                            @endif
                            @if($appointment->clinician->user?->email)
                                <a href="mailto:{{ $appointment->clinician->user->email }}" class="text-decoration-none">
                                    <i class="bi bi-envelope me-2" aria-hidden="true"></i>{{ $appointment->clinician->user->email }}
                                </a>
                            @endif
                            @if($appointment->clinician->contact_no)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $appointment->clinician->contact_no) }}" class="text-decoration-none">
                                    <i class="bi bi-telephone me-2" aria-hidden="true"></i>{{ $appointment->clinician->contact_no }}
                                </a>
                            @endif
                            @if(!$appointment->clinician->user?->email && !$appointment->clinician->contact_no)
                                <span class="text-body-secondary">No contact details available.</span>
                            @endif
                        </div>
                    </li>
                @endif
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Mode</span><span>{{ $appointment->mode === 'online' ? 'Online' : 'In-person' }}</span>
                </li>
                @if($appointment->reason)
                    <li class="list-group-item">
                        <div class="text-muted mb-1">Reason for visit</div>
                        <div style="white-space:pre-wrap;">{{ $appointment->reason }}</div>
                    </li>
                @endif
                @if($appointment->clinic_notes)
                    <li class="list-group-item">
                        <div class="text-muted mb-1">Notes from your clinician</div>
                        <div style="white-space:pre-wrap;">{{ $appointment->clinic_notes }}</div>
                    </li>
                @endif
            </ul>

            <div class="card-body d-flex justify-content-between">
                <a href="{{ route('portal.appointments.index') }}" class="btn btn-outline-secondary">Back</a>
                @if($canCancel)
                    <form method="POST" action="{{ route('portal.appointments.destroy', $appointment) }}"
                          x-data @submit.prevent="if (confirm('Cancel this appointment?')) $el.submit()">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i> Cancel appointment</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
