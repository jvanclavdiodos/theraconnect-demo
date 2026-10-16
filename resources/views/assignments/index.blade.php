@extends('layouts.app')

@section('title', 'Assignments — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Assignments</li>
@endsection

@section('content')
@php
    $initials = fn($n) => collect(explode(' ', trim($n)))->filter()->take(2)
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<div class="tc-page-header">
    <div class="tc-page-header-copy">
        <h1 class="tc-page-title">Assignments</h1>
        <p class="tc-page-sub mb-0">Assign home exercises and review submissions.</p>
    </div>
    <div class="tc-page-actions">
        <a href="{{ route('assignments.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Assignment
        </a>
    </div>
</div>

<form method="GET" action="{{ route('assignments.index') }}" class="tc-filter-panel" role="search">
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" name="search" value="{{ $search }}" class="form-control"
               placeholder="Search by title, details, patient, or clinician" aria-label="Search assignments by title, details, patient, or clinician">
        <button class="btn btn-primary" type="submit">Search</button>
        @if($search !== '')
            <a href="{{ route('assignments.index') }}" class="btn btn-outline-secondary">Clear</a>
        @endif
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 tc-responsive-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Patient</th>
                    <th>Clinician</th>
                    <th>Due Date</th>
                    <th>Submissions</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    <tr>
                        <td data-label="Assignment">
                            <strong>{{ $assignment->title }}</strong>
                            @if ($assignment->hasAttachment())
                                <i class="bi bi-paperclip text-muted" title="Has worksheet attachment"></i>
                            @endif
                            @if ($assignment->description)
                                <br><small class="text-muted">{{ Str::limit($assignment->description, 60) }}</small>
                            @endif
                        </td>
                        <td data-label="Patient">
                            <div class="d-flex align-items-center gap-2">
                                <span class="tc-cell-avatar">{{ $initials($assignment->patient->user->name) }}</span>
                                <span>{{ $assignment->patient->user->name }}</span>
                            </div>
                        </td>
                        <td data-label="Clinician">{{ $assignment->clinician?->user?->name ?? '—' }}</td>
                        <td data-label="Due date">{{ $assignment->due_date ? $assignment->due_date->format('M d, Y') : '—' }}</td>
                        <td data-label="Submissions">
                            @php $count = $assignment->submissions->count(); @endphp
                            <span class="badge bg-{{ $count > 0 ? 'success' : 'secondary' }}">
                                {{ $count }} submission{{ $count !== 1 ? 's' : '' }}
                            </span>
                        </td>
                        <td data-label="Actions" class="text-end">
                            <a href="{{ route('assignments.submissions', $assignment) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="tc-table-empty">
                            <div class="tc-empty">
                                <div class="tc-empty-icon"><i class="bi bi-journal-check"></i></div>
                            <div class="mb-3">{{ $search !== '' ? 'No assignments match your search.' : 'No assignments yet.' }}</div>
                            @if($search === '')
                                <a href="{{ route('assignments.create') }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Create Your First Assignment
                                </a>
                            @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $assignments->links() }}
</div>
@endsection
