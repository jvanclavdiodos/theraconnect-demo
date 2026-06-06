@extends('layouts.app')

@section('title', 'Add Clinician — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('clinicians.index') }}">Clinicians</a></li>
    <li class="breadcrumb-item active">Add</li>
@endsection

@section('content')
<h2>Add Clinician</h2>

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <form action="{{ route('clinicians.store') }}" method="POST">
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
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="license_no" class="form-label">License No.</label>
                    <input type="text" id="license_no" name="license_no" class="form-control" value="{{ old('license_no') }}">
                </div>
                <div class="col-md-6">
                    <label for="specialization" class="form-label">Specialization</label>
                    <input type="text" id="specialization" name="specialization" class="form-control" value="{{ old('specialization') }}">
                </div>
                <div class="col-md-6">
                    <label for="contact_no" class="form-label">Contact No.</label>
                    <input type="text" id="contact_no" name="contact_no" class="form-control" value="{{ old('contact_no') }}">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Create Clinician</button>
                <a href="{{ route('clinicians.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
