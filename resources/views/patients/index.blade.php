@extends('layouts.app')

@section('title', 'Patients — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Patients</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Patients</h2>
    <a href="{{ route('patients.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Patient
    </a>
</div>

{{-- Search --}}
<form method="GET" action="{{ route('patients.index') }}" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search by name, email, or contact..." value="{{ request('search') }}">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        @if(request('search'))
            <a href="{{ route('patients.index') }}" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
        @endif
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Date of Birth</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    <tr>
                        <td><a href="{{ route('patients.show', $patient) }}" class="text-decoration-none">{{ $patient->user->name }}</a></td>
                        <td>{{ $patient->user->email }}</td>
                        <td>{{ $patient->contact_no ?? '—' }}</td>
                        <td>{{ $patient->date_of_birth ? $patient->date_of_birth->format('M d, Y') : '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('patients.edit', $patient) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('patients.destroy', $patient) }}" method="POST" class="d-inline"
                                  x-data @submit.prevent="if (confirm('Delete this patient?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-3">No patients found.</p>
                            <a href="{{ route('patients.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg"></i> Add Your First Patient
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $patients->links() }}
</div>
@endsection
