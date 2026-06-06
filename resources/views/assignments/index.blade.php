@extends('layouts.app')

@section('title', 'Assignments — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Assignments</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Assignments</h2>
    <a href="{{ route('assignments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Assignment
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Patient</th>
                    <th>Clinician</th>
                    <th>Due Date</th>
                    <th>Submissions</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    <tr>
                        <td>
                            <strong>{{ $assignment->title }}</strong>
                            @if ($assignment->description)
                                <br><small class="text-muted">{{ Str::limit($assignment->description, 60) }}</small>
                            @endif
                        </td>
                        <td>{{ $assignment->patient->user->name }}</td>
                        <td>{{ $assignment->clinician?->user?->name ?? '—' }}</td>
                        <td>{{ $assignment->due_date ? $assignment->due_date->format('M d, Y') : '—' }}</td>
                        <td>
                            @php $count = $assignment->submissions->count(); @endphp
                            <span class="badge bg-{{ $count > 0 ? 'success' : 'secondary' }}">
                                {{ $count }} submission{{ $count !== 1 ? 's' : '' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('assignments.submissions', $assignment) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-journal-check text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-3">No assignments yet.</p>
                            <a href="{{ route('assignments.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg"></i> Create Your First Assignment
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $assignments->links() }}
</div>
@endsection
