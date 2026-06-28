@extends('layouts.portal')

@section('title', 'Notes from your clinician — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Notes from clinician</li>
@endsection

@section('content')
<div class="mb-4">
    <h1 class="tc-page-title">Notes from your clinician</h1>
    <p class="tc-page-sub mb-0">Information your clinician has chosen to share with you.</p>
</div>

@forelse($notes as $note)
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            @if($note->title)<div class="fw-bold mb-1">{{ $note->title }}</div>@endif
            <div style="white-space:pre-wrap;">{{ $note->body }}</div>
            <div class="text-muted small mt-2">
                {{ $note->clinician?->user?->name ?? 'Clinician' }} · {{ $note->created_at->format('M j, Y g:i A') }}
            </div>
        </div>
    </div>
@empty
    <div class="card"><div class="card-body tc-empty">
        <div class="tc-empty-icon"><i class="bi bi-journal-text"></i></div>
        <div>No shared notes yet.</div>
    </div></div>
@endforelse
@endsection
