@extends('layouts.app')

@section('title', $patient->user->name . ' — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('patients.index') }}">Patients</a></li>
    <li class="breadcrumb-item active">{{ $patient->user->name }}</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>{{ $patient->user->name }}</h2>
    <div>
        <a href="{{ route('patients.edit', $patient) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary btn-sm">Back to List</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Patient Details</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Email</dt>
                    <dd class="col-sm-7">{{ $patient->user->email }}</dd>

                    <dt class="col-sm-5">Date of Birth</dt>
                    <dd class="col-sm-7">{{ $patient->date_of_birth ? $patient->date_of_birth->format('M d, Y') : '—' }}</dd>

                    <dt class="col-sm-5">Contact No.</dt>
                    <dd class="col-sm-7">{{ $patient->contact_no ?? '—' }}</dd>

                    <dt class="col-sm-5">Address</dt>
                    <dd class="col-sm-7">{{ $patient->address ?? '—' }}</dd>

                    <dt class="col-sm-5">Emergency Contact</dt>
                    <dd class="col-sm-7">{{ $patient->emergency_contact ?? '—' }}</dd>

                    <dt class="col-sm-5">Registered</dt>
                    <dd class="col-sm-7">{{ $patient->created_at->format('M d, Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Clinical Notes</strong></div>
            <div class="card-body">
                @if ($patient->notes)
                    <p class="mb-0">{{ $patient->notes }}</p>
                @else
                    <p class="text-muted mb-0">No clinical notes recorded.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header"><strong>Recent Appointments</strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Clinician</th>
                    <th>Mode</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appointments as $appt)
                    <tr>
                        <td>{{ $appt->requested_at->format('M d, Y h:i A') }}</td>
                        <td>{{ $appt->clinician?->user?->name ?? '—' }}</td>
                        <td>{{ ucfirst($appt->mode) }}</td>
                        <td>
                            <span class="badge bg-{{ $appt->status === 'approved' ? 'success' : ($appt->status === 'cancelled' || $appt->status === 'rejected' ? 'danger' : 'warning') }}">
                                {{ ucfirst($appt->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No appointments yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
