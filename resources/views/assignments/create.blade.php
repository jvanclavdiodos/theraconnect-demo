@extends('layouts.app')

@section('title', 'New Assignment — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('assignments.index') }}">Assignments</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')
<h2>New Assignment</h2>

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <form action="{{ route('assignments.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-12">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}" required>
                </div>
                <div class="col-md-6">
                    <label for="patient_id" class="form-label">Patient</label>
                    <select id="patient_id" name="patient_id" class="form-select" required>
                        <option value="">Select a patient...</option>
                        @foreach ($patients as $patient)
                            <option value="{{ $patient->id }}" {{ old('patient_id') == $patient->id ? 'selected' : '' }}>
                                {{ $patient->user->name }} ({{ $patient->user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="due_date" class="form-label">Due Date</label>
                    <input type="date" id="due_date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                </div>
                @role('admin')
                <div class="col-md-6">
                    <label for="clinician_id" class="form-label">Clinician</label>
                    <select id="clinician_id" name="clinician_id" class="form-select @error('clinician_id') is-invalid @enderror" required>
                        <option value="">Select a clinician...</option>
                        @foreach ($clinicians as $clinician)
                            <option value="{{ $clinician->id }}" {{ old('clinician_id') == $clinician->id ? 'selected' : '' }}>
                                {{ $clinician->user->name }}@if ($clinician->specialization) ({{ $clinician->specialization }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('clinician_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">As an admin, choose which clinician this assignment is from.</small>
                </div>
                @endrole
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <label for="attachment" class="form-label">Worksheet / Attachment <span class="text-muted">(optional)</span></label>
                    <input type="file" id="attachment" name="attachment" class="form-control @error('attachment') is-invalid @enderror"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png">
                    @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">PDF, Office documents, text, or images. Max 10 MB. The patient can download this from the mobile app.</small>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Create Assignment</button>
                <a href="{{ route('assignments.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
