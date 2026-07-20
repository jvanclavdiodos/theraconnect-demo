@extends('layouts.app')
@section('realtime-resources', 'appointments')

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
        'no_show' => 'No-show',
    ];
    $modeFilters = [
        '' => 'All modes',
        'online' => 'Online',
        'in_person' => 'Offline',
    ];
    $nextDirection = ($sortDirection ?? 'desc') === 'desc' ? 'asc' : 'desc';
    $sortIcon = ($sortDirection ?? 'desc') === 'desc' ? 'bi-arrow-down' : 'bi-arrow-up';
    $showReasons = auth()->user()->role === 'clinician';
@endphp

<div class="mb-4">
    <h1 class="tc-page-title">Appointments</h1>
    <p class="tc-page-sub">Review, approve, and manage appointment requests.</p>
</div>

{{-- Filter pills --}}
<div class="tc-filters mb-4">
    @foreach ($filters as $key => $label)
        <a href="{{ route('appointments.index', array_filter([
                'status' => $key ?: null,
                'mode' => $validMode ?? null,
                'sort' => request('sort'),
                'direction' => request('direction'),
            ])) }}"
           class="tc-filter {{ (request('status') ?? '') === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="tc-filters mb-4">
    @foreach ($modeFilters as $key => $label)
        <a href="{{ route('appointments.index', array_filter([
                'status' => $validStatus ?? null,
                'mode' => $key ?: null,
                'sort' => request('sort'),
                'direction' => request('direction'),
            ])) }}"
           class="tc-filter {{ (request('mode') ?? '') === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Clinician</th>
                    <th>
                        <a class="d-inline-flex align-items-center gap-1 text-reset text-decoration-none"
                           href="{{ route('appointments.index', array_filter([
                               'status' => $validStatus ?? null,
                               'mode' => $validMode ?? null,
                               'sort' => 'requested_at',
                               'direction' => $nextDirection,
                           ])) }}">
                            Requested
                            <i class="bi {{ $sortIcon }}" aria-hidden="true"></i>
                        </a>
                    </th>
                    <th>Mode</th>
                    <th>Status</th>
                    @if ($showReasons)
                        <th class="text-center">Reason</th>
                    @endif
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
                                'rejected', 'cancelled', 'no_show' => 'danger',
                                'completed' => 'info',
                                default => 'secondary'
                            } }}">{{ $appt->status === 'no_show' ? 'No-show' : ucfirst($appt->status) }}</span>
                        </td>
                        @if ($showReasons)
                            <td class="text-center">
                                @can('viewReason', $appt)
                                    @if (filled(trim((string) $appt->reason)))
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="popover" data-bs-placement="left"
                                                data-bs-title="Booking reason" data-bs-content="{{ $appt->reason }}"
                                                aria-label="View booking reason">
                                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                                        </button>
                                    @else
                                        <span class="text-muted" aria-label="No booking reason provided">&mdash;</span>
                                    @endif
                                @endcan
                            </td>
                        @endif
                        <td class="text-end">
                            @if ($appt->meetingLinkActive())
                                <a href="{{ $appt->meeting_link }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-primary" aria-label="Join video call"
                                   data-bs-toggle="tooltip" data-bs-title="Join video call"
                                   x-data @click="$dispatch('open-conclude', { id: {{ $appt->id }} })">
                                    <i class="bi bi-camera-video" aria-hidden="true"></i>
                                </a>
                            @elseif ($appt->mode === 'online' && $appt->meeting_link && optional($appt->meetingLinkExpiresAt())->isPast())
                                <span class="badge bg-secondary" title="Link expired {{ $appt->meetingLinkExpiresAt()->diffForHumans() }}">
                                    <i class="bi bi-camera-video-off" aria-hidden="true"></i> Link expired
                                </span>
                            @endif

                            @if ($appt->status === 'pending')
                                <form action="{{ route('appointments.approve', $appt) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-success" type="submit" aria-label="Approve appointment"
                                            data-bs-toggle="tooltip" data-bs-title="Approve appointment">
                                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <form action="{{ route('appointments.reject', $appt) }}" method="POST" class="d-inline"
                                      x-data @submit.prevent="if (confirm('Reject this appointment?')) $el.submit()">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-danger" type="submit" aria-label="Reject appointment"
                                            data-bs-toggle="tooltip" data-bs-title="Reject appointment">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif

                            @if (in_array($appt->status, ['approved', 'rescheduled']))
                                <button class="btn btn-sm btn-outline-secondary" type="button" aria-label="Reschedule appointment"
                                        data-bs-toggle="tooltip" data-bs-title="Reschedule appointment"
                                        x-data
                                        @click="$dispatch('open-reschedule', { id: {{ $appt->id }} })">
                                    <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" type="button" aria-label="Conclude or close case"
                                        data-bs-toggle="tooltip" data-bs-title="Conclude appointment"
                                        x-data
                                        @click="$dispatch('open-conclude', { id: {{ $appt->id }} })">
                                    <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showReasons ? 7 : 6 }}">
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

{{-- Reschedule modal (hidden by default) — pick a date, then an OPEN slot. --}}
<div x-data="{
        open: false, apptId: null, date: '', slots: [], slot: '', loading: false,
        reset() { this.date = ''; this.slots = []; this.slot = ''; this.loading = false; },
        fetchSlots() {
            this.slot = '';
            if (!this.date) { this.slots = []; return; }
            this.loading = true;
            fetch('/appointments/' + this.apptId + '/reschedule-slots?date=' + this.date, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => { this.slots = d.slots || []; })
                .catch(() => { this.slots = []; })
                .finally(() => { this.loading = false; });
        },
        fmt(s) { const [h, m] = s.split(':'); const hh = +h; const ap = hh < 12 ? 'AM' : 'PM'; const h12 = (hh % 12) || 12; return h12 + ':' + m + ' ' + ap; }
     }"
     x-on:open-reschedule.window="open = true; apptId = $event.detail.id; reset()"
     x-show="open"
     x-cloak
     style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050;">
    <div class="modal d-block" tabindex="-1" style="display: block !important;" role="dialog" aria-modal="true" aria-labelledby="reschedule-title">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" x-trap="open">
                <form :action="'/appointments/' + apptId + '/reschedule'" method="POST">
                    @csrf @method('PATCH')
                    <input type="hidden" name="scheduled_at" :value="date && slot ? date + ' ' + slot + ':00' : ''">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reschedule-title">Reschedule Appointment</h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="open = false"></button>
                    </div>
                    <div class="modal-body">
                        <label for="reschedule_date" class="form-label">New date</label>
                        <input type="date" id="reschedule_date" class="form-control mb-3"
                               min="{{ now()->format('Y-m-d') }}" x-model="date" @change="fetchSlots()">

                        <label class="form-label">Available time</label>
                        <div x-show="loading" class="text-muted small py-2">Loading times…</div>
                        <div x-show="!loading && date && slots.length === 0" class="text-muted small py-2">
                            No open times for this date. Try another day.
                        </div>
                        <div x-show="!date" class="text-muted small py-2">Pick a date to see open times.</div>
                        <div class="d-flex flex-wrap gap-2" x-show="!loading && slots.length">
                            <template x-for="s in slots" :key="s">
                                <button type="button" class="btn btn-sm"
                                        :class="slot === s ? 'btn-primary' : 'btn-outline-secondary'"
                                        @click="slot = s" x-text="fmt(s)"></button>
                            </template>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                        <button type="submit" class="btn btn-primary" :disabled="!date || !slot">Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Post-meeting wrap-up / session outcome (hidden by default) --}}
<div x-data="{ open: false, apptId: null }"
     x-on:open-conclude.window="open = true; apptId = $event.detail.id"
     x-show="open"
     x-cloak
     style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050;">
    <div class="modal d-block" tabindex="-1" style="display: block !important;" role="dialog" aria-modal="true" aria-labelledby="conclude-title">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" x-trap="open">
                <form :action="'/appointments/' + apptId + '/complete'" method="POST">
                    @csrf @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title" id="conclude-title">Conclude appointment</h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="open = false"></button>
                    </div>
                    <div class="modal-body">
                        <p>How did this session go? This closes the case and records attendance for progress tracking.</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" id="outcome-attended" value="attended" checked>
                            <label class="form-check-label" for="outcome-attended">
                                <strong>Patient attended</strong> — mark completed
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="outcome" id="outcome-no-show" value="no_show">
                            <label class="form-check-label" for="outcome-no-show">
                                <strong>Patient no-showed</strong> — record as missed
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="open = false">Not yet</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> Save &amp; close case
                        </button>
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
