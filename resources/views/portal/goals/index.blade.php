@extends('layouts.portal')

@section('title', 'My Goals — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Goals</li>
@endsection

@section('content')
<div class="mb-4">
    <h1 class="tc-page-title">My Therapy Goals</h1>
    <p class="tc-page-sub mb-0">Goals you and your clinician are working toward, with their latest ratings.</p>
</div>

<div class="row g-3">
    @forelse($goals as $goal)
        @php $rating = $goal->latestRating; @endphp
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h6 class="fw-semibold mb-1" style="white-space:pre-wrap;">{{ $goal->description }}</h6>
                        <span class="badge bg-{{ $goal->status === 'met' ? 'success' : 'secondary' }}">{{ ucfirst($goal->status) }}</span>
                    </div>
                    @if($goal->target_date)
                        <p class="text-muted small mb-2">Target: {{ $goal->target_date->format('M j, Y') }}</p>
                    @endif
                    @if($rating)
                        <div class="mt-2">
                            <span class="text-muted small">Latest rating:</span>
                            <span class="fw-semibold">{{ $rating->label() }}</span>
                            <span class="text-muted small">· {{ $rating->created_at->format('M j, Y') }}</span>
                            @if($rating->note)
                                <div class="small mt-1" style="white-space:pre-wrap;">{{ $rating->note }}</div>
                            @endif
                        </div>
                    @else
                        <div class="text-muted small mt-2">Not yet rated.</div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card"><div class="card-body tc-empty">
                <div class="tc-empty-icon"><i class="bi bi-bullseye"></i></div>
                <div>No goals yet. Your clinician will set these with you.</div>
            </div></div>
        </div>
    @endforelse
</div>
@endsection
