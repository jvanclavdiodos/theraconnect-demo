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

                    <dt class="col-sm-5">Gender</dt>
                    <dd class="col-sm-7">{{ $patient->gender ?? '—' }}</dd>

                    <dt class="col-sm-5">Educational Attainment</dt>
                    <dd class="col-sm-7">{{ $patient->educational_attainment ?? '—' }}</dd>

                    <dt class="col-sm-5">Employment Status</dt>
                    <dd class="col-sm-7">{{ $patient->employment_status ?? '—' }}</dd>

                    <dt class="col-sm-5">Personal Issues</dt>
                    <dd class="col-sm-7" style="white-space: pre-wrap;">{{ $patient->personal_issues ?? '—' }}</dd>

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
            <div class="card-header"><strong>Intake Notes</strong></div>
            <div class="card-body">
                @if ($patient->notes)
                    <p class="mb-0">{{ $patient->notes }}</p>
                @else
                    <p class="text-muted mb-0">No intake notes recorded.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@php
    $isAssignedClinician = auth()->user()->role === 'clinician'
        && auth()->user()->clinician
        && $patient->assigned_clinician_id === auth()->user()->clinician->id;
    $myClinicianId = auth()->user()->clinician?->id;
@endphp

{{-- Clinician notes (prescriptions / general info), private or shared with patient --}}
<div class="card shadow-sm mt-4">
    <div class="card-header"><strong>Clinician Notes</strong></div>
    <div class="card-body">
        @if ($isAssignedClinician)
            <form action="{{ route('patient-notes.store', $patient) }}" method="POST" class="mb-4">
                @csrf
                <div class="mb-2">
                    <input type="text" name="title" class="form-control" placeholder="Title (optional) — e.g. Prescription" value="{{ old('title') }}">
                </div>
                <div class="mb-2">
                    <textarea name="body" rows="3" class="form-control @error('body') is-invalid @enderror" placeholder="Write a note…" required>{{ old('body') }}</textarea>
                    @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="form-check">
                        <input type="hidden" name="is_shared" value="0">
                        <input type="checkbox" id="is_shared" name="is_shared" value="1" class="form-check-input">
                        <label for="is_shared" class="form-check-label">Share with patient (visible in their app)</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add note</button>
                </div>
            </form>
        @endif

        @forelse ($patient->clinicianNotes as $note)
            <div class="border rounded p-3 mb-2" x-data="{ editing: false }">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="min-w-0" x-show="!editing">
                        @if ($note->title)<div class="fw-bold">{{ $note->title }}</div>@endif
                        <div style="white-space: pre-wrap;">{{ $note->body }}</div>
                        <div class="text-muted small mt-1">
                            {{ $note->clinician?->user?->name ?? 'Clinician' }} · {{ $note->created_at->format('M d, Y h:i A') }}
                            @if ($note->is_shared)
                                <span class="badge bg-success ms-1">Shared</span>
                            @else
                                <span class="badge bg-secondary ms-1">Private</span>
                            @endif
                        </div>
                    </div>
                    @if ($note->clinician_id === $myClinicianId)
                        <div class="ms-2 d-flex gap-1" x-show="!editing">
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="editing = true">Edit</button>
                            <form action="{{ route('patient-notes.destroy', $note) }}" method="POST" onsubmit="return confirm('Delete this note?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($note->clinician_id === $myClinicianId)
                    <form action="{{ route('patient-notes.update', $note) }}" method="POST" x-show="editing" x-cloak>
                        @csrf @method('PUT')
                        <input type="text" name="title" class="form-control mb-2" placeholder="Title (optional)" value="{{ $note->title }}">
                        <textarea name="body" rows="3" class="form-control mb-2" required>{{ $note->body }}</textarea>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="form-check">
                                <input type="hidden" name="is_shared" value="0">
                                <input type="checkbox" name="is_shared" value="1" class="form-check-input" {{ $note->is_shared ? 'checked' : '' }}>
                                <label class="form-check-label">Share with patient</label>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm" @click="editing = false">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-muted mb-0">No clinician notes yet.</p>
        @endforelse
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

@push('styles')
<style>[x-cloak]{ display: none !important; }</style>
@endpush
