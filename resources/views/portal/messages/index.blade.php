@extends('layouts.portal')
@section('realtime-resources', 'messages')
@section('realtime-conversation', $conversation?->id)

@section('title', 'Messages - ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Messages</li>
@endsection

@section('content')
<div class="d-flex align-items-end justify-content-between gap-3 mb-3">
    <div>
        <h1 class="tc-page-title mb-1">Messages</h1>
        <p class="tc-page-sub mb-0">Private conversations with your care team.</p>
    </div>
</div>

<div class="tc-messaging-shell">
    <aside class="tc-conversation-sidebar" aria-label="Conversations">
        <div class="tc-conversation-sidebar-header">
            <span>Care team</span>
            <span class="badge text-bg-light">{{ $conversations->count() }}</span>
        </div>
        <nav class="tc-conversation-list" data-realtime-fragment="messages-sidebar">
            @forelse($conversations as $thread)
                @php
                    $threadName = $thread->clinician?->user?->name ?? 'Clinician';
                    $threadInitials = collect(explode(' ', trim($threadName)))->filter()->take(2)
                        ->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                    $unread = $thread->unreadCountFor(auth()->user());
                    $isActive = $conversation?->id === $thread->id;
                @endphp
                <a href="{{ route('portal.messages.index', ['conversation' => $thread->id]) }}"
                   class="tc-conversation-item {{ $isActive ? 'active' : '' }}"
                   @if($isActive) aria-current="page" @endif>
                    @if($thread->clinician?->user?->hasAvatar())
                        <img class="tc-chat-avatar tc-chat-avatar-image" src="{{ route('portal.clinicians.avatar', $thread->clinician) }}?v={{ $thread->clinician->user->updated_at->timestamp }}" alt="{{ $threadName }} profile photo">
                    @else
                        <span class="tc-chat-avatar" aria-hidden="true">{{ $threadInitials ?: 'C' }}</span>
                    @endif
                    <span class="min-w-0 flex-grow-1">
                        <span class="tc-conversation-name">{{ $threadName }}</span>
                        <span class="tc-conversation-specialty">{{ $thread->clinician?->specialization ?? 'Care team clinician' }}</span>
                    </span>
                    @if($unread > 0)
                        <span class="badge rounded-pill {{ $isActive ? 'text-bg-light' : 'text-bg-primary' }}" aria-label="{{ $unread }} unread messages">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </a>
            @empty
                <div class="tc-conversation-empty">No conversations yet</div>
            @endforelse
        </nav>
    </aside>

    <section class="tc-active-chat" aria-label="Active conversation">
        @if(! $conversation)
            <div class="tc-empty h-100 d-flex flex-column justify-content-center">
                <div class="tc-empty-icon"><i class="bi bi-chat-dots"></i></div>
                <div>You don't have an approved clinician to message yet.</div>
                <p class="text-muted small mt-1 mb-0">A conversation becomes available after a clinician approves your appointment.</p>
            </div>
        @else
            @php
                $clinicianName = $conversation->clinician?->user?->name ?? 'Your clinician';
                $clinicianInitials = collect(explode(' ', trim($clinicianName)))->filter()->take(2)
                    ->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
            @endphp
            <header class="tc-chat-header">
                <span class="tc-chat-avatar tc-chat-avatar-lg" aria-hidden="true">{{ $clinicianInitials ?: 'C' }}</span>
                <span class="min-w-0">
                    <strong class="tc-chat-clinician-name">{{ $clinicianName }}</strong>
                    <span class="tc-chat-clinician-meta">{{ $conversation->clinician?->specialization ?? 'Clinician' }}</span>
                </span>
                <span class="tc-chat-status ms-auto"><i class="bi bi-shield-check" aria-hidden="true"></i> Secure conversation</span>
            </header>

            <div class="tc-message-thread" id="thread">
                @forelse($conversation->messages as $message)
                    @php $mine = $message->sender_id === auth()->id(); @endphp
                    <div class="tc-message-row {{ $mine ? 'outgoing' : 'incoming' }}" data-message-id="{{ $message->id }}">
                        <div class="tc-message-bubble">
                            <div class="tc-message-body">{{ $message->body }}</div>
                            <time class="tc-message-time" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, g:i A') }}</time>
                        </div>
                    </div>
                @empty
                    <div class="tc-chat-empty"><i class="bi bi-chat-square-text" aria-hidden="true"></i><span>No messages yet. Say hello to your clinician.</span></div>
                @endforelse
            </div>

            <footer class="tc-message-composer">
                <form method="POST" action="{{ route('portal.messages.send', $conversation) }}" class="tc-message-form">
                    @csrf
                    <input type="text" name="body" class="form-control @error('body') is-invalid @enderror"
                           placeholder="Write a message..." aria-label="Message to {{ $clinicianName }}" maxlength="5000" required autofocus>
                    <button class="btn btn-primary tc-message-send" type="submit" aria-label="Send message"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
                </form>
                @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </footer>
        @endif
    </section>
</div>

@if($conversation)
    <script>
        // Jump to the latest message.
        const t = document.getElementById('thread');
        if (t) t.scrollTop = t.scrollHeight;
    </script>
@endif
@endsection
