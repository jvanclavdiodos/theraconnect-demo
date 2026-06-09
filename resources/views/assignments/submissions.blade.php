@extends('layouts.app')

@section('title', 'Submissions — ' . $assignment->title . ' — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('assignments.index') }}">Assignments</a></li>
    <li class="breadcrumb-item active">Submissions: {{ $assignment->title }}</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>{{ $assignment->title }}</h2>
        <p class="text-muted mb-0">
            Assigned to: <strong>{{ $assignment->patient->user->name }}</strong>
            @if ($assignment->due_date)
                &middot; Due: {{ $assignment->due_date->format('M d, Y') }}
            @endif
        </p>
    </div>
    <a href="{{ route('assignments.index') }}" class="btn btn-outline-secondary btn-sm">Back to Assignments</a>
</div>

@if ($assignment->description || $assignment->hasAttachment())
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            @if ($assignment->description)
                <strong>Description:</strong>
                <p class="mb-0 mt-1">{{ $assignment->description }}</p>
            @endif
            @if ($assignment->hasAttachment())
                <div class="@if ($assignment->description) mt-3 @endif">
                    <strong>Worksheet:</strong>
                    <a href="{{ route('assignments.worksheet', $assignment) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="bi bi-download"></i> {{ $assignment->attachment_name ?? 'Download' }}
                    </a>
                </div>
            @endif
        </div>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header"><strong>Submissions ({{ $submissions->count() }})</strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Patient</th>
                    <th>Content</th>
                    <th>File</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $submission)
                    <tr>
                        <td>{{ $submission->patient->user->name }}</td>
                        <td>{{ $submission->content ? Str::limit($submission->content, 80) : '—' }}</td>
                        <td>
                            @if ($submission->file_path)
                                <a href="{{ route('submissions.file', $submission) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $submission->status === 'reviewed' ? 'success' : 'warning' }}">
                                {{ ucfirst($submission->status) }}
                            </span>
                        </td>
                        <td>{{ $submission->submitted_at->format('M d, Y h:i A') }}</td>
                        <td class="text-end">
                            @if ($submission->status === 'submitted')
                                <form action="{{ route('submissions.review', $submission) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i> Mark Reviewed
                                    </button>
                                </form>
                            @else
                                <small class="text-muted">Reviewed {{ $submission->reviewed_at?->format('M d, Y') ?? '—' }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No submissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
