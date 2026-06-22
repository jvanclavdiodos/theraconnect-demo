@extends('layouts.app')

@section('title', 'Patients — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Patients</li>
@endsection

@section('content')
@php
    $initials = fn($n) => collect(explode(' ', trim($n)))->filter()->take(2)
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="tc-page-title">Patients</h1>
        <p class="tc-page-sub mb-0">Manage patient records and profiles.</p>
    </div>
    <a href="{{ route('patients.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Patient
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

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
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
                        <td>
                            <a href="{{ route('patients.show', $patient) }}" class="text-decoration-none d-flex align-items-center gap-2">
                                <span class="tc-cell-avatar">{{ $initials($patient->user->name) }}</span>
                                <span class="fw-semibold">{{ $patient->user->name }}</span>
                                @if (!empty($atRisk[$patient->id]))
                                    <span class="badge bg-danger" title="Consecutive no-shows — at risk of disengaging">
                                        <i class="bi bi-exclamation-triangle"></i> At risk
                                    </span>
                                @endif
                            </a>
                        </td>
                        <td>{{ $patient->user->email }}</td>
                        <td>{{ $patient->contact_no ?? '—' }}</td>
                        <td>{{ $patient->date_of_birth ? $patient->date_of_birth->format('M d, Y') : '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            @role('admin')
                            <a href="{{ route('patients.edit', $patient) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('patients.destroy', $patient) }}" method="POST" class="d-inline"
                                  x-data @submit.prevent="if (confirm('Delete this patient?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            @endrole
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="tc-empty">
                                <div class="tc-empty-icon"><i class="bi bi-people"></i></div>
                                <div class="mb-3">No patients found.</div>
                                <a href="{{ route('patients.create') }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Add Your First Patient
                                </a>
                            </div>
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
