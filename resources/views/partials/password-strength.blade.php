{{--
    Reusable password field(s) with a live strength meter + requirements checklist.
    Drive it from the enclosing <form> with:  x-data="passwordField({ requireConfirm: true|false })"
    and gate the submit button with             :disabled="!canSubmit"

    Params (via @include):
      $label        - password label            (default "Password")
      $confirm      - render a confirm field?    (default false)
      $confirmLabel - confirm label              (default "Confirm password")
      $autofocus    - autofocus the password?    (default false)

    Field names are fixed: `password` + `password_confirmation` (matches the
    backend validation everywhere it's used).
--}}
@php
    $label = $label ?? 'Password';
    $confirm = $confirm ?? false;
    $confirmLabel = $confirmLabel ?? 'Confirm password';
    $autofocus = $autofocus ?? false;
@endphp

<div class="mb-3">
    <label for="password" class="form-label">{{ $label }}</label>
    <div class="input-group has-validation">
        <input :type="show ? 'text' : 'password'" id="password" name="password"
               class="form-control @error('password') is-invalid @enderror"
               x-model="password" autocomplete="new-password" required @if($autofocus) autofocus @endif>
        <button class="btn btn-outline-secondary" type="button" tabindex="-1"
                @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'">
            <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
        </button>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Live strength meter + requirements checklist (hidden until typing) --}}
    <div class="pw-feedback mt-2" x-show="password.length > 0" x-cloak>
        <div class="progress" style="height: 6px;" role="progressbar"
             :aria-valuenow="strength.pct" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" :class="strength.cls" :style="`width: ${strength.pct}%`"></div>
        </div>
        <div class="d-flex justify-content-between mt-1">
            <small class="text-muted">Password strength</small>
            <small class="fw-semibold"
                   :class="{'text-danger': strength.label==='Weak','text-warning': strength.label==='Medium','text-success': strength.label==='Strong'}"
                   x-text="strength.label"></small>
        </div>
        <ul class="pw-reqs list-unstyled small mt-2 mb-0">
            <li :class="reqLen ? 'pw-req ok' : 'pw-req bad'">
                <i class="bi" :class="reqLen ? 'bi-check-circle-fill' : 'bi-x-circle'"></i> 8–20 characters
            </li>
            <li :class="reqUpper ? 'pw-req ok' : 'pw-req bad'">
                <i class="bi" :class="reqUpper ? 'bi-check-circle-fill' : 'bi-x-circle'"></i> At least one uppercase letter
            </li>
            <li :class="reqDigit ? 'pw-req ok' : 'pw-req bad'">
                <i class="bi" :class="reqDigit ? 'bi-check-circle-fill' : 'bi-x-circle'"></i> At least one number
            </li>
            <li :class="reqNoSpace ? 'pw-req ok' : 'pw-req bad'">
                <i class="bi" :class="reqNoSpace ? 'bi-check-circle-fill' : 'bi-x-circle'"></i> No spaces
            </li>
        </ul>
    </div>
</div>

@if($confirm)
<div class="mb-3">
    <label for="password_confirmation" class="form-label">{{ $confirmLabel }}</label>
    <div class="input-group">
        <input :type="showConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation"
               class="form-control" x-model="confirmation" autocomplete="new-password" required>
        <button class="btn btn-outline-secondary" type="button" tabindex="-1"
                @click="showConfirm = !showConfirm" :aria-label="showConfirm ? 'Hide password' : 'Show password'">
            <i class="bi" :class="showConfirm ? 'bi-eye-slash' : 'bi-eye'"></i>
        </button>
    </div>
    <div class="small mt-1 pw-req" x-show="confirmation.length > 0" x-cloak
         :class="matches ? 'ok' : 'bad'">
        <i class="bi" :class="matches ? 'bi-check-circle-fill' : 'bi-x-circle'"></i>
        <span x-text="matches ? 'Passwords match' : 'Passwords do not match'"></span>
    </div>
</div>
@endif

@once
@push('scripts')
<script>
    // Shared password component — strength algorithm MUST stay in sync with the
    // Flutter app (lib/utils/validators.dart) and the backend StrongPassword rule.
    function passwordField(opts = {}) {
        return {
            password: '',
            confirmation: '',
            requireConfirm: opts.requireConfirm ?? false,
            show: false,
            showConfirm: false,
            get reqLen() { return this.password.length >= 8 && this.password.length <= 20; },
            get reqUpper() { return /[A-Z]/.test(this.password); },
            get reqDigit() { return /[0-9]/.test(this.password); },
            get reqNoSpace() { return this.password.length > 0 && !/\s/.test(this.password); },
            get allOk() { return this.reqLen && this.reqUpper && this.reqDigit && this.reqNoSpace; },
            get matches() { return this.password.length > 0 && this.password === this.confirmation; },
            get canSubmit() { return this.allOk && (this.requireConfirm ? this.matches : true); },
            get strength() {
                const p = this.password;
                if (!p) return { pct: 0, label: '', cls: '' };
                let s = 0;
                if (p.length >= 8) s++;
                if (p.length >= 12) s++;
                if (/[a-z]/.test(p)) s++;
                if (/[A-Z]/.test(p)) s++;
                if (/[0-9]/.test(p)) s++;
                if (/[^A-Za-z0-9]/.test(p)) s++;
                if (s <= 2) return { pct: 33, label: 'Weak', cls: 'bg-danger' };
                if (s <= 4) return { pct: 66, label: 'Medium', cls: 'bg-warning' };
                return { pct: 100, label: 'Strong', cls: 'bg-success' };
            },
        };
    }
</script>
@endpush
@endonce
