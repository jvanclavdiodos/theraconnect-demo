@extends('layouts.portal')

@section('title', 'My Assignments — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Assignments</li>
@endsection

@section('content')
<div class="mb-4">
    <h1 class="tc-page-title">My Assignments</h1>
    <p class="tc-page-sub mb-0">Therapeutic work from your clinician.</p>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse($assignments as $assignment)
            @php $submission = $assignment->submissions->first(); @endphp
            <a href="{{ route('portal.assignments.show', $assignment) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div class="min-w-0">
                    <div class="fw-semibold">{{ $assignment->title }}</div>
                    <div class="text-muted small">
                        {{ $assignment->clinician?->user?->name ?? 'Clinician' }}
                        @if($assignment->due_date) · Due {{ $assignment->due_date->format('M j, Y') }}@endif
                    </div>
                </div>
                @if(! $submission)
                    <span class="badge bg-warning">To do</span>
                @elseif($submission->status === 'reviewed')
                    <span class="badge bg-info">Reviewed</span>
                @else
                    <span class="badge bg-success">Submitted</span>
                @endif
            </a>
        @empty
            <div class="list-group-item">
                <div class="tc-empty">
                    <div class="tc-empty-icon"><i class="bi bi-clipboard"></i></div>
                    <div>No assignments yet.</div>
                </div>
            </div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $assignments->links() }}</div>
@endsection
