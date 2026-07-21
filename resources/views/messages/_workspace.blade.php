@php $activeConversation = $conversation ?? null; $me = auth()->id(); @endphp
<div class="d-flex align-items-end justify-content-between gap-3 mb-3">
    <div><h1 class="tc-page-title mb-1">Messages</h1><p class="tc-page-sub mb-0">Private conversations with your patients.</p></div>
</div>

<div class="tc-messaging-shell">
    <aside class="tc-conversation-sidebar" aria-label="Patient conversations">
        <div class="tc-conversation-sidebar-header"><span>Patients</span><span class="badge text-bg-light">{{ $conversations->count() }}</span></div>
        <form action="{{ route('messages.open') }}" method="POST" class="tc-new-conversation">
            @csrf
            <label for="patient_id" class="form-label small fw-semibold">Start a conversation</label>
            <div class="d-flex gap-2">
                <select id="patient_id" name="patient_id" class="form-select form-select-sm" required>
                    <option value="" disabled selected>Choose a patient...</option>
                    @foreach($caseload as $patient)<option value="{{ $patient->id }}">{{ $patient->user->name }}</option>@endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm" aria-label="Open conversation"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
            </div>
            @if($caseload->isEmpty())<p class="text-muted small mt-2 mb-0">No patients are assigned to you yet.</p>@endif
        </form>
        <nav class="tc-conversation-list" data-realtime-fragment="messages-sidebar">
            @forelse($conversations as $thread)
                @php
                    $patientName = $thread->patient?->user?->name ?? 'Patient';
                    $initials = collect(explode(' ', trim($patientName)))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                    $unread = $thread->last_message_at && $thread->latestMessage && $thread->latestMessage->sender_id !== $me
                        && (is_null($thread->clinician_last_read_at) || $thread->last_message_at->gt($thread->clinician_last_read_at));
                    $isActive = $activeConversation?->id === $thread->id;
                @endphp
                <a href="{{ route('messages.show', $thread) }}" class="tc-conversation-item {{ $isActive ? 'active' : '' }}" @if($isActive) aria-current="page" @endif>
                    @if($thread->patient?->user?->hasAvatar())
                        <img class="tc-chat-avatar tc-chat-avatar-image" src="{{ route('avatars.show', $thread->patient->user) }}?v={{ $thread->patient->user->updated_at->timestamp }}" alt="{{ $patientName }} profile photo">
                    @else
                        <span class="tc-chat-avatar" aria-hidden="true">{{ $initials ?: 'P' }}</span>
                    @endif
                    <span class="min-w-0 flex-grow-1"><span class="tc-conversation-name {{ $unread ? 'fw-bold' : '' }}">{{ $patientName }}</span><span class="tc-conversation-specialty">{{ $thread->latestMessage?->body ?? 'No messages yet.' }}</span></span>
                    @if($unread)<span class="badge rounded-pill text-bg-primary" aria-label="New message">New</span>@endif
                </a>
            @empty
                <div class="tc-conversation-empty">No conversations yet</div>
            @endforelse
        </nav>
    </aside>

    <section class="tc-active-chat" aria-label="Active conversation">
        @if(!$activeConversation)
            <div class="tc-chat-empty"><i class="bi bi-chat-square-text" aria-hidden="true"></i><span>Select a patient conversation or start a new one.</span></div>
        @else
            @php
                $patientName = $activeConversation->patient->user->name;
                $patientInitials = collect(explode(' ', trim($patientName)))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
            @endphp
            <header class="tc-chat-header">
                <span class="tc-chat-avatar tc-chat-avatar-lg" aria-hidden="true">{{ $patientInitials ?: 'P' }}</span>
                <span class="min-w-0"><strong class="tc-chat-clinician-name">{{ $patientName }}</strong><span class="tc-chat-clinician-meta">Patient</span></span>
                <span class="tc-chat-status ms-auto"><i class="bi bi-shield-check" aria-hidden="true"></i> Secure conversation</span>
            </header>
            <div class="tc-message-thread" id="thread">
                @forelse($activeConversation->messages->sortBy('created_at') as $message)
                    @php $mine = $message->sender_id === $me; @endphp
                    <div class="tc-message-row {{ $mine ? 'outgoing' : 'incoming' }}" data-message-id="{{ $message->id }}"><div class="tc-message-bubble"><div class="tc-message-body">{{ $message->body }}</div><time class="tc-message-time" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, g:i A') }}</time></div></div>
                @empty
                    <div class="tc-chat-empty"><i class="bi bi-chat-square-text" aria-hidden="true"></i><span>No messages yet. Say hello below.</span></div>
                @endforelse
            </div>
            @can('send', $activeConversation)
                <footer class="tc-message-composer">
                    <form action="{{ route('messages.store', $activeConversation) }}" method="POST" class="tc-message-form">
                        @csrf
                        <textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="1" placeholder="Write a message..." aria-label="Message to {{ $patientName }}" required>{{ old('body') }}</textarea>
                        <button type="submit" class="btn btn-primary tc-message-send" aria-label="Send message"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
                    </form>
                    @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </footer>
            @else
                <div class="tc-message-readonly"><i class="bi bi-lock" aria-hidden="true"></i> This patient is no longer assigned to you. This conversation is read-only.</div>
            @endcan
            <script>const t=document.getElementById('thread');if(t)t.scrollTop=t.scrollHeight;</script>
        @endif
    </section>
</div>
