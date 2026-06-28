@extends('layouts.app')

@section('title', 'Messages — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Messages</li>
@endsection

@section('content')
@php $me = auth()->id(); @endphp
<h2>Messages</h2>

{{-- Compose: start/open a thread with a caseload patient --}}
<div class="card shadow-sm mt-3">
    <div class="card-body">
        <form action="{{ route('messages.open') }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-8">
                <label for="patient_id" class="form-label">New message to a patient</label>
                <select id="patient_id" name="patient_id" class="form-select" required>
                    <option value="" disabled selected>Choose a patient…</option>
                    @foreach($caseload as $patient)
                        <option value="{{ $patient->id }}">{{ $patient->user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Open conversation</button>
            </div>
        </form>
        @if($caseload->isEmpty())
            <p class="text-muted small mt-2 mb-0">No patients are assigned to you yet.</p>
        @endif
    </div>
</div>

{{-- Conversation list --}}
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white"><strong>Conversations</strong></div>
    <div class="list-group list-group-flush">
        @forelse($conversations as $conv)
            @php
                $unread = $conv->last_message_at && $conv->latestMessage
                    && $conv->latestMessage->sender_id !== $me
                    && (is_null($conv->clinician_last_read_at) || $conv->last_message_at->gt($conv->clinician_last_read_at));
            @endphp
            <a href="{{ route('messages.show', $conv) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
                <div class="min-w-0">
                    <div class="{{ $unread ? 'fw-bold' : '' }}">{{ $conv->patient->user->name }}</div>
                    <div class="text-muted text-truncate small" style="max-width: 32rem;">
                        {{ $conv->latestMessage?->body ?? 'No messages yet.' }}
                    </div>
                </div>
                <div class="text-end ms-2">
                    <div class="text-muted small">{{ $conv->last_message_at?->diffForHumans() }}</div>
                    @if($unread)<span class="badge bg-primary mt-1">New</span>@endif
                </div>
            </a>
        @empty
            <div class="list-group-item text-muted">No conversations yet. Start one above.</div>
        @endforelse
    </div>
</div>
@endsection
