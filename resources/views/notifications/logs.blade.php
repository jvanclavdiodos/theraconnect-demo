@extends('layouts.app')

@section('title', 'Notification Logs — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Notification Logs</li>
@endsection

@section('content')
<h2>Notification Logs</h2>

<div class="card shadow-sm mt-3">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Body</th>
                    <th>Sent</th>
                    <th>Read</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td>{{ $notification->user?->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ str_replace('_', ' ', $notification->type) }}</span>
                        </td>
                        <td>{{ $notification->title }}</td>
                        <td>{{ Str::limit($notification->body, 60) }}</td>
                        <td>{{ $notification->sent_at ? $notification->sent_at->format('M d, h:i A') : '—' }}</td>
                        <td>{{ $notification->read_at ? $notification->read_at->format('M d, h:i A') : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-bell text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-3">No notifications sent yet.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $notifications->links() }}
</div>
@endsection
