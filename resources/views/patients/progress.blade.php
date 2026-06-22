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

@php
    $isAssignedClinician = auth()->user()->role === 'clinician'
        && auth()->user()->clinician
        && $patient->assigned_clinician_id === auth()->user()->clinician->id;

    // Tiny inline SVG line chart for a score series (dependency-free, prints fine).
    $sparkline = function ($scores, $max, $color) {
        $n = count($scores);
        if ($n === 0) return '';
        $w = 320; $h = 70; $pad = 6;
        $stepX = $n > 1 ? ($w - 2 * $pad) / ($n - 1) : 0;
        $pts = [];
        foreach (array_values($scores) as $i => $s) {
            $x = $pad + $i * $stepX;
            $y = $h - $pad - ($max > 0 ? ($s / $max) * ($h - 2 * $pad) : 0);
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        $poly = implode(' ', $pts);
        $dots = '';
        foreach ($pts as $p) {
            [$x, $y] = explode(',', $p);
            $dots .= "<circle cx='{$x}' cy='{$y}' r='3' fill='{$color}' />";
        }
        return "<svg viewBox='0 0 {$w} {$h}' width='100%' height='{$h}' preserveAspectRatio='none' style='max-width:360px'>"
            . ($n > 1 ? "<polyline points='{$poly}' fill='none' stroke='{$color}' stroke-width='2' />" : '')
            . $dots . "</svg>";
    };
@endphp

{{-- ── Symptom scales (PHQ-9 / GAD-7) ────────────────────────────────────── --}}
<div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Symptom scales</strong></div>
    <div class="card-body">
        @if ($isAssignedClinician)
            <form action="{{ route('progress.assessments.assign', $patient) }}" method="POST" class="mb-4">
                @csrf
                <label class="form-label">Assign a questionnaire for the patient to complete in their app</label>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select name="instrument" class="form-select" style="max-width:320px">
                        @foreach ($instruments as $key => $def)
                            <option value="{{ $key }}">{{ $def['title'] }} — {{ $def['name'] }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Assign
                    </button>
                </div>
                @error('instrument') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </form>
        @endif

        @if ($pendingAssessments->isNotEmpty())
            <div class="alert alert-info py-2">
                <i class="bi bi-hourglass-split"></i>
                Awaiting patient:
                {{ $pendingAssessments->map(fn($a) => $a->title())->implode(', ') }}
            </div>
        @endif

        @php $hasTrend = false; @endphp
        @foreach ($instruments as $key => $def)
            @php $series = $scoreTrends[$key] ?? collect(); @endphp
            @if ($series->isNotEmpty())
                @php
                    $hasTrend = true;
                    $latest = $series->last();
                    $scores = $series->pluck('score')->all();
                    $color = $key === 'phq9' ? '#0d9488' : '#6366f1';
                @endphp
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <div>
                            <strong>{{ $def['title'] }}</strong>
                            <span class="text-muted small">{{ $def['name'] }}</span>
                        </div>
                        <div>
                            <span class="fs-5 fw-bold">{{ $latest->score }}</span>
                            <span class="text-muted small">/ {{ $def['max'] }}</span>
                            <span class="badge bg-secondary ms-1">{{ $latest->severity() }}</span>
                        </div>
                    </div>
                    {!! $sparkline($scores, $def['max'], $color) !!}
                    <div class="table-responsive mt-1">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr class="text-muted small">
                                    @foreach ($series as $a)
                                        <td class="text-center">{{ $a->completed_at?->format('M d') }}</td>
                                    @endforeach
                                </tr>
                                <tr class="fw-semibold">
                                    @foreach ($series as $a)
                                        <td class="text-center">{{ $a->score }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach

        @if (! $hasTrend && $pendingAssessments->isEmpty())
            <p class="text-muted mb-0">No questionnaires completed yet.
                @if ($isAssignedClinician) Assign one above to start tracking. @endif
            </p>
        @endif
    </div>
</div>

{{-- Mood and goals are added in later slices. --}}
@endsection
