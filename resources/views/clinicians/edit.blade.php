@extends('layouts.app')

@section('title', 'Edit Clinician — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('clinicians.index') }}">Clinicians</a></li>
    <li class="breadcrumb-item active">Edit: {{ $clinician->user->name }}</li>
@endsection

@section('content')
<h2>Edit Clinician: {{ $clinician->user->name }}</h2>

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <form action="{{ route('clinicians.update', $clinician) }}" method="POST">
            @csrf @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $clinician->user->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $clinician->user->email) }}" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="license_no" class="form-label">License No.</label>
                    <input type="text" id="license_no" name="license_no" class="form-control" value="{{ old('license_no', $clinician->license_no) }}">
                </div>
                <div class="col-md-6">
                    <label for="specialization" class="form-label">Specialization</label>
                    <input type="text" id="specialization" name="specialization" class="form-control" value="{{ old('specialization', $clinician->specialization) }}">
                </div>
                <div class="col-md-6">
                    <label for="contact_no" class="form-label">Contact No.</label>
                    <input type="text" id="contact_no" name="contact_no" class="form-control" value="{{ old('contact_no', $clinician->contact_no) }}">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('clinicians.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
