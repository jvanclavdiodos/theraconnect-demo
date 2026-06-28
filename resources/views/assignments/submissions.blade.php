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
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Patient</th>
                    <th>Submission</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $submission)
                    <tr>
                        <td>{{ $submission->patient->user->name }}</td>
                        <td>
                            {{-- Simple preview: image thumbnail or content snippet; click to maximize. --}}
                            <div class="d-flex align-items-center gap-2">
                                @if ($submission->file_path && $submission->previewKind() === 'image')
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#subModal{{ $submission->id }}">
                                        <img src="{{ route('submissions.preview', $submission) }}" alt="preview"
                                             style="height:44px;width:44px;object-fit:cover;border-radius:6px;border:1px solid var(--bs-border-color);">
                                    </a>
                                @elseif ($submission->file_path)
                                    <i class="bi {{ $submission->previewKind() === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-text' }} fs-4 text-secondary"></i>
                                @endif
                                <div class="min-w-0">
                                    @if ($submission->content)
                                        <div class="text-truncate" style="max-width: 22rem;">{{ Str::limit($submission->content, 60) }}</div>
                                    @endif
                                    @if ($submission->file_path)
                                        <div class="small text-muted text-truncate" style="max-width: 22rem;">{{ $submission->original_name }}</div>
                                    @endif
                                    @if (! $submission->content && ! $submission->file_path)
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $submission->status === 'reviewed' ? 'success' : 'warning' }}">
                                {{ ucfirst($submission->status) }}
                            </span>
                        </td>
                        <td>{{ $submission->submitted_at->format('M d, Y h:i A') }}</td>
                        <td class="text-end">
                            @if ($submission->content || $submission->file_path)
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#subModal{{ $submission->id }}">
                                    <i class="bi bi-arrows-fullscreen"></i> View
                                </button>
                            @endif
                            @if ($submission->status === 'submitted')
                                <form action="{{ route('submissions.review', $submission) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i> Mark Reviewed
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No submissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Maximized submission viewers --}}
@foreach ($submissions as $submission)
    @if ($submission->content || $submission->file_path)
        <div class="modal fade" id="subModal{{ $submission->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Submission — {{ $submission->patient->user->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if ($submission->content)
                            <div class="mb-3">
                                <strong>Written response</strong>
                                <p class="mb-0 mt-1" style="white-space: pre-wrap;">{{ $submission->content }}</p>
                            </div>
                        @endif

                        @if ($submission->file_path)
                            <strong>File: {{ $submission->original_name }}</strong>
                            <div class="mt-2">
                                @if ($submission->previewKind() === 'image')
                                    <img src="{{ route('submissions.preview', $submission) }}" class="img-fluid rounded border" alt="submission">
                                @elseif (in_array($submission->previewKind(), ['pdf', 'text']))
                                    <iframe src="{{ route('submissions.preview', $submission) }}" title="submission preview" style="width:100%;height:75vh;border:1px solid #dee2e6;border-radius:6px;"></iframe>
                                @else
                                    <p class="text-muted">No inline preview for this file type — download to view.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if ($submission->file_path)
                            <a href="{{ route('submissions.file', $submission) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-download"></i> Download
                            </a>
                        @endif
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach
@endsection
