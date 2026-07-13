@extends('layouts.app')
@section('realtime-resources', 'messages')
@section('realtime-conversation', $conversation->id)

@section('title', 'Conversation — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('messages.index') }}">Messages</a></li>
    <li class="breadcrumb-item active">{{ $conversation->patient->user->name }}</li>
@endsection

@section('content')
@php $me = auth()->id(); @endphp
<h2 class="h4">{{ $conversation->patient->user->name }}</h2>

<div class="card shadow-sm mt-3">
    <div class="card-body" style="max-height: 60vh; overflow-y: auto;">
        @forelse($conversation->messages->sortBy('created_at') as $message)
            @php $mine = $message->sender_id === $me; @endphp
            <div class="d-flex mb-2 {{ $mine ? 'justify-content-end' : 'justify-content-start' }}">
                <div class="p-2 rounded-3 {{ $mine ? 'bg-primary text-white' : 'tc-message-other' }}" style="max-width: 75%;">
                    <div class="small {{ $mine ? 'text-white-50' : 'text-muted' }}">
                        {{ $message->sender->name }} · {{ $message->created_at->format('M d, h:i A') }}
                    </div>
                    <div style="white-space: pre-wrap;">{{ $message->body }}</div>
                </div>
            </div>
        @empty
            <p class="text-muted text-center mb-0">No messages yet. Say hello below.</p>
        @endforelse
    </div>
    <div class="card-footer">
        <form action="{{ route('messages.store', $conversation) }}" method="POST">
            @csrf
            <div class="input-group">
                <textarea name="body" class="form-control @error('body') is-invalid @enderror"
                    rows="2" placeholder="Type a message…" required>{{ old('body') }}</textarea>
                <button type="submit" class="btn btn-primary">Send</button>
            </div>
            @error('body') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </form>
    </div>
</div>
@endsection
