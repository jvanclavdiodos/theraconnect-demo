@extends('layouts.app')

@section('title', 'Activity Audit — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Activity Audit</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Activity Audit</h2>
    <form class="d-flex gap-2" method="get">
        <input type="text" name="event" class="form-control form-control-sm" placeholder="Event filter" value="{{ request('event') }}">
        <input type="text" name="user" class="form-control form-control-sm" placeholder="User name/email" value="{{ request('user') }}">
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
        @if(request()->hasAny(['event','user']))
            <a href="{{ route('activity-logs.index') }}" class="btn btn-sm btn-link text-secondary">Clear</a>
        @endif
    </form>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Role</th>
                    <th>Event</th>
                    <th>Target</th>
                    <th>Meta</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap text-secondary">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->user->name ?? '—' }}</td>
                        <td><span class="badge bg-secondary text-capitalize">{{ $log->user->role ?? '—' }}</span></td>
                        <td><code>{{ $log->event }}</code></td>
                        <td class="text-secondary">
                            @if($log->target_type)
                                {{ $log->target_type }} #{{ $log->target_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-secondary font-monospace" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {{ $log->meta ? json_encode($log->meta) : '' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">No activity logged yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
