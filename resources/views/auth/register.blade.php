@extends('layouts.app')

@section('title', 'Create Account — ' . config('app.name', 'TheraConnect'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title text-center mb-1">Create your account</h4>
                <p class="text-center text-muted small mb-4">For patients of the clinic.</p>

                <form method="POST" action="{{ route('register') }}" x-data="passwordField({ requireConfirm: true })">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Full name</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required autofocus>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contact_no" class="form-label">Contact number <span class="text-muted">(optional)</span></label>
                        <input type="text" id="contact_no" name="contact_no" class="form-control @error('contact_no') is-invalid @enderror"
                               value="{{ old('contact_no') }}">
                        @error('contact_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="requested_clinician_id" class="form-label">Preferred clinician <span class="text-muted">(optional)</span></label>
                        <select id="requested_clinician_id" name="requested_clinician_id"
                                class="form-select @error('requested_clinician_id') is-invalid @enderror">
                            <option value="">— No preference for now —</option>
                            @foreach($clinicians as $clinician)
                                <option value="{{ $clinician->id }}" {{ (string) old('requested_clinician_id') === (string) $clinician->id ? 'selected' : '' }}>
                                    {{ $clinician->user->name }}@if($clinician->specialization) — {{ $clinician->specialization }}@endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Your request is sent to the clinician for approval before you're connected.</div>
                        @error('requested_clinician_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <hr>
                    <p class="text-muted small mb-2">About you <span>(optional — you can fill these in later)</span></p>

                    <div class="row g-2">
                        <div class="col-sm-6 mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach(\App\Models\Patient::GENDERS as $g)
                                    <option value="{{ $g }}" {{ old('gender') === $g ? 'selected' : '' }}>{{ $g }}</option>
                                @endforeach
                            </select>
                            @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label for="employment_status" class="form-label">Employment status</label>
                            <select id="employment_status" name="employment_status" class="form-select @error('employment_status') is-invalid @enderror">
                                <option value="">—</option>
                                @foreach(\App\Models\Patient::EMPLOYMENT_STATUSES as $s)
                                    <option value="{{ $s }}" {{ old('employment_status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                            @error('employment_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="educational_attainment" class="form-label">Educational attainment</label>
                        <select id="educational_attainment" name="educational_attainment" class="form-select @error('educational_attainment') is-invalid @enderror">
                            <option value="">—</option>
                            @foreach(\App\Models\Patient::EDUCATION_LEVELS as $e)
                                <option value="{{ $e }}" {{ old('educational_attainment') === $e ? 'selected' : '' }}>{{ $e }}</option>
                            @endforeach
                        </select>
                        @error('educational_attainment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="personal_issues" class="form-label">What brings you here? <span class="text-muted">(optional)</span></label>
                        <textarea id="personal_issues" name="personal_issues" rows="3" maxlength="2000"
                                  class="form-control @error('personal_issues') is-invalid @enderror">{{ old('personal_issues') }}</textarea>
                        @error('personal_issues') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <hr>

                    @include('partials.password-strength', ['confirm' => true])

                    <button type="submit" class="btn btn-primary w-100" :disabled="!canSubmit">Create account</button>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
