@extends('layouts.portal')

@section('title', 'Mood Check-ins — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Mood check-ins</li>
@endsection

@section('content')
<div class="mb-4">
    <h1 class="tc-page-title">Mood Check-ins</h1>
    <p class="tc-page-sub mb-0">A quick daily pulse on how you're feeling (1 = very low, 10 = great).</p>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Log how you feel</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('portal.mood.store') }}" x-data="{ score: {{ old('score', 5) }} }">
                    @csrf
                    <label for="score" class="form-label d-flex justify-content-between">
                        <span>Today's mood</span>
                        <span class="fw-bold" x-text="score"></span>
                    </label>
                    <input type="range" class="form-range" min="1" max="10" id="score" name="score"
                           x-model="score" value="{{ old('score', 5) }}">
                    <div class="d-flex justify-content-between text-muted small mb-3">
                        <span>1 · Very low</span><span>10 · Great</span>
                    </div>
                    @error('score')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    <div class="mb-3">
                        <label for="note" class="form-label">Note <span class="text-muted">(optional)</span></label>
                        <textarea name="note" id="note" rows="2" class="form-control" maxlength="255">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Save check-in</button>
                </form>
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
                            <div class="text-muted small">{{ $log->created_at->format('D, M j, Y · g:i A') }}</div>
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
