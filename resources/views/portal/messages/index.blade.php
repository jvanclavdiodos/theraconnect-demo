@extends('layouts.portal')

@section('title', 'Messages — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Messages</li>
@endsection

@section('content')
<h1 class="tc-page-title mb-3">Messages</h1>

@if(! $conversation)
    @php
        $pendingClinician = $patient?->clinician_request_status === \App\Models\Patient::REQUEST_PENDING
            ? $patient->requestedClinician
            : null;
    @endphp
    <div class="card">
        <div class="card-body tc-empty">
            <div class="tc-empty-icon"><i class="bi bi-chat-dots"></i></div>
            @if($pendingClinician)
                <div>Your request to {{ $pendingClinician->user?->name ?? 'your selected clinician' }} is pending approval.</div>
                <p class="text-muted small mt-1 mb-0">Once the request is approved, you can start a conversation here.</p>
            @else
                <div>You don't have an assigned clinician to message yet.</div>
                <p class="text-muted small mt-1 mb-0">Once a clinician is assigned to you, you can start a conversation here.</p>
            @endif
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-person-circle"></i>
            <strong>{{ $conversation->clinician?->user?->name ?? 'Your clinician' }}</strong>
        </div>

        <div class="card-body" style="max-height: 60vh; overflow-y: auto;" id="thread">
            @forelse($conversation->messages as $message)
                @php $mine = $message->sender_id === auth()->id(); @endphp
                <div class="d-flex mb-2 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="p-2 px-3 rounded-3 {{ $mine ? 'bg-primary text-white' : 'bg-light' }}" style="max-width: 75%;">
                        <div style="white-space:pre-wrap;">{{ $message->body }}</div>
                        <div class="small {{ $mine ? 'text-white-50' : 'text-muted' }} text-end mt-1">
                            {{ $message->created_at->format('M j, g:i A') }}
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center my-4">No messages yet. Say hello to your clinician.</p>
            @endforelse
        </div>

        <div class="card-footer">
            <form method="POST" action="{{ route('portal.messages.send', $conversation) }}" class="d-flex gap-2">
                @csrf
                <input type="text" name="body" class="form-control @error('body') is-invalid @enderror"
                       placeholder="Write a message…" maxlength="5000" required autofocus>
                <button class="btn btn-primary"><i class="bi bi-send"></i></button>
            </form>
            @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    <script>
        // Jump to the latest message.
        const t = document.getElementById('thread');
        if (t) t.scrollTop = t.scrollHeight;
    </script>
@endif
@endsection
