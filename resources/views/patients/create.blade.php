@extends('layouts.app')

@section('title', 'Add Patient — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('patients.index') }}">Patients</a></li>
    <li class="breadcrumb-item active">Add</li>
@endsection

@section('content')
<h2>Add Patient</h2>

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <form action="{{ route('patients.store') }}" method="POST" x-data="passwordField()">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    @include('partials.password-strength')
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}">
                </div>
                <div class="col-md-6">
                    <label for="contact_no" class="form-label">Contact No.</label>
                    <input type="text" id="contact_no" name="contact_no" class="form-control" value="{{ old('contact_no') }}">
                </div>
                <div class="col-md-6">
                    <label for="emergency_contact" class="form-label">Emergency Contact</label>
                    <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" value="{{ old('emergency_contact') }}">
                </div>
                @role('admin')
                <div class="col-md-6">
                    <label for="assigned_clinician_id" class="form-label">Assigned Clinician</label>
                    <select id="assigned_clinician_id" name="assigned_clinician_id" class="form-select">
                        <option value="">Unassigned</option>
                        @foreach ($clinicians as $clinician)
                            <option value="{{ $clinician->id }}" {{ old('assigned_clinician_id') == $clinician->id ? 'selected' : '' }}>
                                {{ $clinician->user->name }}@if ($clinician->specialization) ({{ $clinician->specialization }})@endif
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">The clinician who owns this patient's caseload.</small>
                </div>
                @else
                <div class="col-md-6 d-flex align-items-end">
                    <p class="text-muted mb-2"><i class="bi bi-person-check me-1"></i> This patient will be added to your caseload.</p>
                </div>
                @endrole
                @include('patients._profile_fields', ['patient' => null])

                <div class="col-12">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary" :disabled="!canSubmit">Create Patient</button>
                <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
