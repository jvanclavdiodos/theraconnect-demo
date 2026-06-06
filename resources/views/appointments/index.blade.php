@extends('layouts.app')

@section('title', 'Appointments — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Appointments</li>
@endsection

@section('content')
<h2>Appointments</h2>

{{-- Filter tabs --}}
<div class="mb-3">
    <div class="btn-group btn-group-sm">
        <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary {{ !request('status') ? 'active' : '' }}">All</a>
        <a href="{{ route('appointments.index', ['status' => 'pending']) }}" class="btn btn-outline-warning {{ request('status') === 'pending' ? 'active' : '' }}">Pending</a>
        <a href="{{ route('appointments.index', ['status' => 'approved']) }}" class="btn btn-outline-success {{ request('status') === 'approved' ? 'active' : '' }}">Approved</a>
        <a href="{{ route('appointments.index', ['status' => 'rejected']) }}" class="btn btn-outline-danger {{ request('status') === 'rejected' ? 'active' : '' }}">Rejected</a>
        <a href="{{ route('appointments.index', ['status' => 'completed']) }}" class="btn btn-outline-info {{ request('status') === 'completed' ? 'active' : '' }}">Completed</a>
        <a href="{{ route('appointments.index', ['status' => 'cancelled']) }}" class="btn btn-outline-dark {{ request('status') === 'cancelled' ? 'active' : '' }}">Cancelled</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
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
                        <td>{{ $appt->patient->user->name }}</td>
                        <td>{{ $appt->clinician?->user?->name ?? '—' }}</td>
                        <td>{{ $appt->requested_at->format('M d, Y h:i A') }}</td>
                        <td>{{ ucfirst($appt->mode) }}</td>
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
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-calendar-check text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-3">No appointments found.</p>
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
