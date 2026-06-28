@extends('layouts.portal')

@section('title', 'Edit Profile — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('portal.profile.show') }}">Profile</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="tc-page-title mb-3">Edit Profile</h1>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('portal.profile.update') }}">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror"
                                   value="{{ old('date_of_birth', optional($patient->date_of_birth)->format('Y-m-d')) }}">
                            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach(\App\Models\Patient::GENDERS as $g)
                                    <option value="{{ $g }}" {{ old('gender', $patient->gender) === $g ? 'selected' : '' }}>{{ $g }}</option>
                                @endforeach
                            </select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="educational_attainment" class="form-label">Educational Attainment</label>
                            <select id="educational_attainment" name="educational_attainment" class="form-select @error('educational_attainment') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach(\App\Models\Patient::EDUCATION_LEVELS as $e)
                                    <option value="{{ $e }}" {{ old('educational_attainment', $patient->educational_attainment) === $e ? 'selected' : '' }}>{{ $e }}</option>
                                @endforeach
                            </select>
                            @error('educational_attainment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="employment_status" class="form-label">Employment Status</label>
                            <select id="employment_status" name="employment_status" class="form-select @error('employment_status') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach(\App\Models\Patient::EMPLOYMENT_STATUSES as $s)
                                    <option value="{{ $s }}" {{ old('employment_status', $patient->employment_status) === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                            @error('employment_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="contact_no" class="form-label">Contact No.</label>
                            <input type="text" id="contact_no" name="contact_no" class="form-control @error('contact_no') is-invalid @enderror"
                                   value="{{ old('contact_no', $patient->contact_no) }}" maxlength="20">
                            @error('contact_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="emergency_contact" class="form-label">Emergency Contact</label>
                            <input type="text" id="emergency_contact" name="emergency_contact" class="form-control @error('emergency_contact') is-invalid @enderror"
                                   value="{{ old('emergency_contact', $patient->emergency_contact) }}" maxlength="255">
                            @error('emergency_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror"
                                   value="{{ old('address', $patient->address) }}" maxlength="255">
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="personal_issues" class="form-label">Personal Issues / what brings you here</label>
                            <textarea id="personal_issues" name="personal_issues" rows="3" class="form-control @error('personal_issues') is-invalid @enderror"
                                      maxlength="2000">{{ old('personal_issues', $patient->personal_issues) }}</textarea>
                            @error('personal_issues')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('portal.profile.show') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mt-4" x-data="passwordField({ requireConfirm: true })">
            <div class="card-header bg-white"><strong>Change password</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('portal.profile.password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input type="password" id="current_password" name="current_password"
                               class="form-control @error('current_password') is-invalid @enderror"
                               autocomplete="current-password" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @include('partials.password-strength', [
                        'label' => 'New password',
                        'confirm' => true,
                        'confirmLabel' => 'Confirm new password',
                    ])

                    <button type="submit" class="btn btn-primary" :disabled="!canSubmit">Update password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
