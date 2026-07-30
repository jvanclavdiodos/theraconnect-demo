@extends('layouts.portal')

@section('title', 'Mood Check-ins - ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Mood check-ins</li>
@endsection

@section('content')
<div class="mb-4">
    <h1 class="tc-page-title">Mood Check-ins</h1>
    <p class="tc-page-sub mb-0">A quick daily reflection on how you have felt overall (1 = very low, 10 = great).</p>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Today's check-in</strong></div>
            <div class="card-body">
                @if($todayLog)
                    <div class="text-center py-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success mb-3"
                              style="width:3rem;height:3rem;" aria-hidden="true">
                            <i class="bi bi-check-lg fs-4"></i>
                        </span>
                        <h2 class="h5">Today's check-in is complete</h2>
                        <p class="text-muted mb-3">{{ $todayLog->logged_on->format('F j, Y') }}</p>
                        <span class="badge rounded-pill bg-{{ $todayLog->score >= 7 ? 'success' : ($todayLog->score >= 4 ? 'warning' : 'danger') }} fs-6">
                            {{ $todayLog->score }}/10
                        </span>
                        @if($todayLog->note)
                            <p class="mb-0 mt-3">{{ $todayLog->note }}</p>
                        @endif
                    </div>
                @else
                    <form method="POST" action="{{ route('portal.mood.store') }}" x-data="{ score: {{ old('score', 5) }} }">
                        @csrf
                        <label for="score" class="form-label d-flex justify-content-between gap-3">
                            <span>How have you felt overall today?</span>
                            <span class="fw-bold" x-text="score"></span>
                        </label>
                        <input type="range" class="form-range" min="1" max="10" id="score" name="score"
                               x-model="score" value="{{ old('score', 5) }}">
                        <div class="d-flex justify-content-between text-muted small mb-3">
                            <span>1 - Very low</span><span>10 - Great</span>
                        </div>
                        @error('score')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                        <div class="mb-3">
                            <label for="note" class="form-label">Note <span class="text-muted">(optional)</span></label>
                            <textarea name="note" id="note" rows="2" class="form-control" maxlength="255">{{ old('note') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Save today's check-in</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Recent check-ins</strong></div>
            <div class="list-group list-group-flush">
                @forelse($logs as $log)
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <span class="badge rounded-pill bg-{{ $log->score >= 7 ? 'success' : ($log->score >= 4 ? 'warning' : 'danger') }}" style="min-width:2.2rem;">{{ $log->score }}</span>
                        <div class="flex-grow-1 min-w-0">
                            @if($log->note)<div>{{ $log->note }}</div>@endif
                            <div class="text-muted small">{{ $log->logged_on->format('D, M j, Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="list-group-item text-muted">No check-ins yet. Log your first one!</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
