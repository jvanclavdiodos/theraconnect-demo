@extends('layouts.app')

@section('title', 'Notifications — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Notifications</li>
@endsection

@section('content')
@php
    $icon = fn($type) => match(true) {
        str_starts_with($type, 'appointment') => 'bi-calendar-check',
        $type === 'message_received' => 'bi-chat-dots',
        str_starts_with($type, 'assignment') => 'bi-clipboard-check',
        str_starts_with($type, 'assessment') => 'bi-card-checklist',
        default => 'bi-bell',
    };
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="tc-page-title">Notifications</h1>
        <p class="tc-page-sub mb-0">New bookings, messages, and schedule changes.</p>
    </div>
    @if($notifications->whereNull('read_at')->count())
        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm">Mark all read</button>
        </form>
    @endif
</div>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
            <div class="list-group-item d-flex align-items-start gap-3 {{ $n->read_at ? '' : 'tc-notification-unread' }}">
                <i class="bi {{ $icon($n->type) }} {{ $n->read_at ? 'text-secondary' : 'text-primary' }} mt-1 fs-5"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">{{ $n->title }}</div>
                    <div class="small">{{ $n->body }}</div>
                    <div class="text-muted small mt-1">{{ $n->created_at->diffForHumans() }}</div>
                </div>
                @unless($n->read_at)
                    <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-link text-decoration-none" title="Mark read"><i class="bi bi-check2"></i></button>
                    </form>
                @endunless
            </div>
        @empty
            <div class="list-group-item">
                <div class="tc-empty">
                    <div class="tc-empty-icon"><i class="bi bi-bell"></i></div>
                    <div>No notifications yet.</div>
                </div>
            </div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
