@extends('layouts.app')

@section('title', 'Clinicians — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Clinicians</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Clinicians</h2>
    <a href="{{ route('clinicians.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Clinician
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Specialization</th>
                    <th>License No.</th>
                    <th>Contact</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clinicians as $clinician)
                    <tr>
                        <td>{{ $clinician->user->name }}</td>
                        <td>{{ $clinician->user->email }}</td>
                        <td>{{ $clinician->specialization ?? '—' }}</td>
                        <td>{{ $clinician->license_no ?? '—' }}</td>
                        <td>{{ $clinician->contact_no ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('clinicians.edit', $clinician) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('clinicians.destroy', $clinician) }}" method="POST" class="d-inline"
                                  x-data @submit.prevent="if (confirm('Delete this clinician?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-person-badge text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-3">No clinicians found.</p>
                            <a href="{{ route('clinicians.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg"></i> Add Your First Clinician
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $clinicians->links() }}
</div>
@endsection
