@extends('layouts.app')
@section('realtime-resources', 'notifications')

@section('title', 'Notifications — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Notifications</li>
@endsection

@section('content')
@php
    $icon = fn($type) => match(true) {
        str_starts_with($type, 'appointment') => 'bi-calendar-check',
        str_starts_with($type, 'assignment') => 'bi-clipboard-check',
        str_starts_with($type, 'assessment') => 'bi-card-checklist',
        default => 'bi-bell',
    };
@endphp

<div data-realtime-fragment="notifications">
<div class="tc-page-header">
    <div class="tc-page-header-copy">
        <h1 class="tc-page-title">Notifications</h1>
        <p class="tc-page-sub mb-0">Appointments, assignments, assessments, and account updates.</p>
    </div>
    <div class="tc-page-actions">
        @if($notifications->whereNull('read_at')->count())
            <form method="POST" action="{{ route('notifications.readAll') }}">
                @csrf
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-all"></i> Mark all read</button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
            <div class="list-group-item d-flex align-items-start gap-3 tc-notification-item {{ $n->read_at ? '' : 'is-unread' }}">
                <span class="tc-notification-icon" aria-hidden="true"><i class="bi {{ $icon($n->type) }}"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="tc-notification-title">{{ $n->title }}</div>
                    <div class="tc-notification-body">{{ $n->body }}</div>
                    <div class="tc-notification-time"><i class="bi bi-clock"></i>{{ $n->created_at->diffForHumans() }}</div>
                </div>
                @unless($n->read_at)
                    <form method="POST" action="{{ route('notifications.read', $n) }}">
                        @csrf
                        <button class="btn btn-sm btn-link text-decoration-none" aria-label="Mark notification read"
                                data-bs-toggle="tooltip" data-bs-title="Mark read">
                            <i class="bi bi-check2" aria-hidden="true"></i>
                        </button>
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
</div>
@endsection
