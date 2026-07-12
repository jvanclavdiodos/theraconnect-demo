@extends('layouts.app')

@section('title', 'Create Account - ' . config('app.name', 'TheraConnect'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title text-center mb-1">Create your account</h4>
                <p class="text-center text-muted small mb-4">For patients of the clinic.</p>

                <form id="registration-form" method="POST" action="{{ route('register') }}" x-data="Object.assign(passwordField({ requireConfirm: true }), { termsAccepted: {{ old('accepted_terms') ? 'true' : 'false' }} })" @terms-accepted="termsAccepted = true" @terms-revoked="termsAccepted = false">
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

                    <hr>
                    <p class="text-muted small mb-2">About you <span>(optional - you can fill these in later)</span></p>

                    <div class="row g-2">
                        <div class="col-sm-6 mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                                <option value=""></option>
                                @foreach(\App\Models\Patient::GENDERS as $g)
                                    <option value="{{ $g }}" {{ old('gender') === $g ? 'selected' : '' }}>{{ $g }}</option>
                                @endforeach
                            </select>
                            @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label for="employment_status" class="form-label">Employment status</label>
                            <select id="employment_status" name="employment_status" class="form-select @error('employment_status') is-invalid @enderror">
                                <option value=""></option>
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
                            <option value=""></option>
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

                    <div class="form-check mb-3">
                        <input type="hidden" name="accepted_terms" :value="termsAccepted ? '1' : '0'">
                        <input class="form-check-input @error('accepted_terms') is-invalid @enderror" type="checkbox" id="accepted_terms" :checked="termsAccepted" aria-label="Agree to the TheraConnect User Agreement" aria-haspopup="dialog">
                        <label class="form-check-label small">
                            By creating an account, I agree to the
                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-bs-toggle="modal" data-bs-target="#user-agreement-modal">TheraConnect User Agreement</button>.
                        </label>
                        @error('accepted_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100" :disabled="!canSubmit || !termsAccepted">Create account</button>

                    <div class="modal fade" id="user-agreement-modal" tabindex="-1" aria-labelledby="terms-title" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="terms-title">TheraConnect User Agreement</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body small">
                                    @include('partials.terms-and-conditions')
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="accept-user-agreement">I Agree</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('registration-form');
        const checkbox = document.getElementById('accepted_terms');
        const acceptedTerms = form?.querySelector('input[name="accepted_terms"]');
        const modalElement = document.getElementById('user-agreement-modal');
        const agreeButton = document.getElementById('accept-user-agreement');

        if (!form || !checkbox || !acceptedTerms || !modalElement || !agreeButton) return;

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
                // Consent is granted only by the modal's explicit I Agree action.
                checkbox.checked = false;
                modal.show();
                return;
            }

            acceptedTerms.value = '0';
            form.dispatchEvent(new CustomEvent('terms-revoked', { bubbles: true }));
        });

        agreeButton.addEventListener('click', function () {
            checkbox.checked = true;
            acceptedTerms.value = '1';
            form.dispatchEvent(new CustomEvent('terms-accepted', { bubbles: true }));
            modal.hide();
        });

        form.addEventListener('submit', function (event) {
            if (acceptedTerms.value !== '1') {
                event.preventDefault();
                modal.show();
            }
        });
    })();
</script>
@endpush
