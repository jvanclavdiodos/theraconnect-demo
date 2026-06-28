@extends('layouts.portal')

@section('title', $assignment->title . ' — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('portal.assignments.index') }}">Assignments</a></li>
    <li class="breadcrumb-item active">{{ $assignment->title }}</li>
@endsection

@section('content')
@php $submission = $assignment->submissions->first(); @endphp

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header"><strong>{{ $assignment->title }}</strong></div>
            <div class="card-body">
                <div class="text-muted small mb-3">
                    From {{ $assignment->clinician?->user?->name ?? 'your clinician' }}
                    @if($assignment->due_date) · Due {{ $assignment->due_date->format('l, M j, Y') }}@endif
                </div>
                @if($assignment->description)
                    <div style="white-space:pre-wrap;">{{ $assignment->description }}</div>
                @else
                    <p class="text-muted mb-0">No description provided.</p>
                @endif

                @if($assignment->hasAttachment())
                    <hr>
                    <a href="{{ route('portal.assignments.worksheet', $assignment) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1"></i> Download worksheet ({{ $assignment->attachment_name }})
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Your Submission</strong></div>
            <div class="card-body">
                @if($submission)
                    <div class="mb-3">
                        <span class="badge bg-{{ $submission->status === 'reviewed' ? 'info' : 'success' }}">
                            {{ ucfirst($submission->status) }}
                        </span>
                        <span class="text-muted small ms-1">{{ $submission->submitted_at?->format('M j, Y g:i A') }}</span>
                    </div>
                    @if($submission->content)
                        <div class="mb-3" style="white-space:pre-wrap;">{{ $submission->content }}</div>
                    @endif
                    @if($submission->file_path)
                        <a href="{{ route('portal.submissions.file', $submission) }}" class="btn btn-outline-secondary btn-sm mb-3">
                            <i class="bi bi-paperclip me-1"></i> {{ $submission->original_name }}
                        </a>
                    @endif
                @endif

                @if($submission && $submission->status === 'reviewed')
                    <p class="text-muted small mb-0">This submission has been reviewed and can no longer be changed.</p>
                @else
                    <hr class="@if(!$submission) d-none @endif">
                    <p class="small text-muted">{{ $submission ? 'Update your submission' : 'Submit your work' }} — add a note, attach a file, or both.</p>
                    <form method="POST" action="{{ route('portal.assignments.submit', $assignment) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <textarea name="content" rows="4" class="form-control @error('content') is-invalid @enderror"
                                      placeholder="Write your response…">{{ old('content', $submission->content ?? '') }}</textarea>
                            @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <input type="file" name="file" class="form-control @error('file') is-invalid @enderror"
                                   accept=".pdf,.doc,.docx,.txt,.rtf,.jpg,.jpeg,.png"
                                   data-validate-file data-max-bytes="10485760" data-allowed-extensions="pdf,doc,docx,txt,rtf,jpg,jpeg,png">
                            <div class="form-text">PDF, DOC, DOCX, TXT, RTF, JPG, or PNG. Max 10 MB.</div>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ $submission ? 'Update submission' : 'Submit' }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/file-upload.js') }}" defer></script>
@endpush
@endsection
