@extends('layouts.app')

@section('title', $patient->user->name . ' — Progress — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('patients.index') }}">Patients</a></li>
    <li class="breadcrumb-item"><a href="{{ route('patients.show', $patient) }}">{{ $patient->user->name }}</a></li>
    <li class="breadcrumb-item active">Progress</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="tc-page-title">{{ $patient->user->name }} — Progress</h1>
        <p class="tc-page-sub mb-0">Attendance, symptom scales, mood and therapy goals over time.</p>
    </div>
    <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to profile
    </a>
</div>

{{-- ── Attendance / engagement ───────────────────────────────────────────── --}}
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Attendance &amp; engagement</strong>
        @if ($attendance['at_risk'])
            <span class="badge bg-danger">
                <i class="bi bi-exclamation-triangle"></i>
                {{ $attendance['consecutive_no_shows'] }} consecutive no-shows
            </span>
        @endif
    </div>
    <div class="card-body">
        <div class="row text-center g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="fs-3 fw-bold">
                    {{ $attendance['attendance_rate'] !== null ? $attendance['attendance_rate'] . '%' : '—' }}
                </div>
                <div class="text-muted small">Attendance rate</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-3 fw-bold text-success">{{ $attendance['attended'] }}</div>
                <div class="text-muted small">Attended</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-3 fw-bold text-danger">{{ $attendance['no_shows'] }}</div>
                <div class="text-muted small">No-shows</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="fs-3 fw-bold text-secondary">{{ $attendance['cancelled'] }}</div>
                <div class="text-muted small">Cancelled</div>
            </div>
        </div>

        @if ($attendance['at_risk'])
            <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div>This patient has missed {{ $attendance['consecutive_no_shows'] }} sessions in a row.
                    Consider reaching out — repeated no-shows are an early sign of disengagement.</div>
            </div>
        @endif

        @if ($sessions->isNotEmpty())
            <div class="d-flex flex-wrap gap-1 align-items-center">
                <span class="text-muted small me-2">Recent sessions:</span>
                @foreach ($sessions->reverse() as $s)
                    @php
                        $dot = match ($s->status) {
                            'completed' => ['bg-success', 'Attended'],
                            'no_show'   => ['bg-danger', 'No-show'],
                            default     => ['bg-secondary', 'Cancelled'],
                        };
                    @endphp
                    <span class="badge rounded-pill {{ $dot[0] }}"
                          title="{{ $dot[1] }} · {{ $s->scheduled_at->format('M d, Y') }}">
                        {{ $s->scheduled_at->format('M d') }}
                    </span>
                @endforeach
            </div>
        @else
            <p class="text-muted mb-0">No concluded sessions yet.</p>
        @endif
    </div>
</div>

{{-- Symptom scales, mood and goals are added in later slices. --}}
@endsection
