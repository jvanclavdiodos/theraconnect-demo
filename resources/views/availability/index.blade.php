@extends('layouts.app')

@section('title', 'My Availability — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">My Availability</li>
@endsection

@php
    // Whole-hour options for the working-hours windows (matches the hourly slot model).
    $hours = [];
    for ($h = 0; $h < 24; $h++) {
        $hours[] = sprintf('%02d:00', $h);
    }
    $dayLabels = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];
@endphp

@section('content')
<h2>My Availability</h2>
<p class="text-muted">Set the days and hours you accept appointments. You are available by default; turn a day off or block specific dates to stop patients booking then.</p>

{{-- Weekly recurring schedule --}}
<div class="card shadow-sm mt-3">
    <div class="card-header bg-white"><strong>Weekly schedule</strong></div>
    <div class="card-body">
        <form action="{{ route('availability.update') }}" method="POST">
            @csrf @method('PUT')

            @foreach($dayLabels as $day => $label)
                <div class="row g-2 align-items-center mb-2">
                    <div class="col-md-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="avail_{{ $day }}"
                                name="weekly[{{ $day }}][is_available]" value="1" @checked($weekly[$day]['is_available'])>
                            <label class="form-check-label" for="avail_{{ $day }}">{{ $label }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">From</span>
                            <select name="weekly[{{ $day }}][start_time]" class="form-select">
                                @foreach($hours as $h)
                                    <option value="{{ $h }}" @selected($weekly[$day]['start_time'] === $h)>{{ $h }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">To</span>
                            <select name="weekly[{{ $day }}][end_time]" class="form-select">
                                @foreach($hours as $h)
                                    <option value="{{ $h }}" @selected($weekly[$day]['end_time'] === $h)>{{ $h }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endforeach

            @error('weekly.*.end_time')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save weekly schedule</button>
            </div>
        </form>
    </div>
</div>

{{-- One-off date blocks --}}
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white"><strong>Blocked dates (time off)</strong></div>
    <div class="card-body">
        <form action="{{ route('availability.overrides.store') }}" method="POST" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-4">
                <label for="date" class="form-label">Date to block</label>
                <input type="date" id="date" name="date" min="{{ now()->toDateString() }}"
                    class="form-control @error('date') is-invalid @enderror" required>
                @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5">
                <label for="reason" class="form-label">Reason (optional)</label>
                <input type="text" id="reason" name="reason" maxlength="255"
                    class="form-control" placeholder="e.g. Vacation">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-danger w-100">Block date</button>
            </div>
        </form>

        @if($overrides->isEmpty())
            <p class="text-muted mb-0">No upcoming blocked dates.</p>
        @else
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Reason</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                    @foreach($overrides as $override)
                        <tr>
                            <td>{{ $override->date->format('D, M d, Y') }}</td>
                            <td>{{ $override->reason ?: '—' }}</td>
                            <td class="text-end">
                                <form action="{{ route('availability.overrides.destroy', $override) }}" method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
