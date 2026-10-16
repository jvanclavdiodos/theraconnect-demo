@extends('layouts.portal')

@section('title', 'My Assignments — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Assignments</li>
@endsection

@section('content')
<div class="tc-page-header">
    <div class="tc-page-header-copy">
    <h1 class="tc-page-title">My Assignments</h1>
    <p class="tc-page-sub mb-0">Therapeutic work from your clinician.</p>
    </div>
</div>

<form method="GET" action="{{ route('portal.assignments.index') }}" class="tc-filter-panel" role="search">
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" name="search" value="{{ $search }}" class="form-control"
               placeholder="Search by title, details, or clinician" aria-label="Search assignments by title, details, or clinician">
        <button class="btn btn-primary" type="submit">Search</button>
        @if($search !== '')
            <a href="{{ route('portal.assignments.index') }}" class="btn btn-outline-secondary">Clear</a>
        @endif
    </div>
</form>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse($assignments as $assignment)
            @php $submission = $assignment->submissions->first(); @endphp
            <a href="{{ route('portal.assignments.show', $assignment) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center tc-assignment-item">
                <span class="tc-assignment-icon" aria-hidden="true"><i class="bi bi-journal-check"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <div class="tc-record-primary">{{ $assignment->title }}</div>
                    <div class="tc-record-secondary">
                        {{ $assignment->clinician?->user?->name ?? 'Clinician' }}
                        @if($assignment->due_date) · Due {{ $assignment->due_date->format('M j, Y') }}@endif
                    </div>
                </div>
                <span class="tc-assignment-status">
                    @if(! $submission)
                        <span class="badge bg-warning">To do</span>
                    @elseif($submission->status === 'reviewed')
                        <span class="badge bg-info">Reviewed</span>
                    @else
                        <span class="badge bg-success">Submitted</span>
                    @endif
                    <i class="bi bi-chevron-right tc-assignment-chevron" aria-hidden="true"></i>
                </span>
            </a>
        @empty
            <div class="list-group-item">
                <div class="tc-empty">
                    <div class="tc-empty-icon"><i class="bi bi-clipboard"></i></div>
                    <div>{{ $search !== '' ? 'No assignments match your search.' : 'No assignments yet.' }}</div>
                </div>
            </div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $assignments->links() }}</div>
@endsection
