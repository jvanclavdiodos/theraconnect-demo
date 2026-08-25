@extends('layouts.app')

@section('title', 'My Account — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">My Account</li>
@endsection

@section('content')
<div class="mb-4">
    <h1 class="tc-page-title mb-1">My Account</h1>
    <p class="tc-page-sub mb-0">Manage your profile picture and account security.</p>
</div>

<div class="tc-account-grid">
<div class="card">
    <div class="card-header bg-white"><strong>Profile picture</strong></div>
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            @if ($user->hasAvatar())
                <img src="{{ route('avatars.show', $user) }}" alt="avatar" class="tc-profile-avatar">
            @else
                <span class="tc-profile-avatar tc-profile-avatar-fallback">
                    {{ collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn($p) => mb_strtoupper(mb_substr($p,0,1)))->implode('') ?: 'U' }}
                </span>
            @endif
            <div>
                <div class="fw-bold">{{ $user->name }}</div>
                <div class="text-muted small">{{ ucfirst($user->role) }}</div>
            </div>
        </div>

        <form id="avatar-upload-form" action="{{ route('account.avatar.update') }}" method="POST"
              enctype="multipart/form-data" data-avatar-crop-form>
            @csrf
            <div class="mb-2">
                <label for="avatar-input" class="form-label visually-hidden">Choose profile photo</label>
                <input id="avatar-input" type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                       data-avatar-input data-max-source-bytes="10485760"
                       class="form-control @error('avatar') is-invalid @enderror" required>
                <div class="invalid-feedback d-block" data-avatar-error @unless($errors->has('avatar')) hidden @endunless>
                    @error('avatar'){{ $message }}@enderror
                </div>
                <div class="form-text">JPG, PNG, or WebP. The adjusted photo must be 2 MB or smaller.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" data-avatar-submit>
                <i class="bi bi-camera me-1"></i> Adjust and upload
            </button>
            @if ($user->hasAvatar())
                <button type="submit" form="remove-avatar" class="btn btn-outline-danger btn-sm">Remove</button>
            @endif
        </form>
        @if ($user->hasAvatar())
            <form id="remove-avatar" action="{{ route('account.avatar.destroy') }}" method="POST" class="d-none">
                @csrf @method('DELETE')
            </form>
        @endif
    </div>
</div>

<div class="card" x-data="passwordField({ requireConfirm: true })">
    <div class="card-header bg-white"><strong>Change password</strong></div>
    <div class="card-body">
        <form action="{{ route('account.password.update') }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label for="current_password" class="form-label">Current password</label>
                <input type="password" id="current_password" name="current_password"
                       class="form-control @error('current_password') is-invalid @enderror"
                       autocomplete="current-password" required>
                @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            @include('partials.password-strength', [
                'label' => 'New password',
                'confirm' => true,
                'confirmLabel' => 'Confirm new password',
            ])

            <button type="submit" class="btn btn-primary btn-sm" :disabled="!canSubmit">Update password</button>
        </form>
    </div>
</div>

@if($user->role === 'admin')
<div class="card tc-account-wide">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>User agreement and privacy notice</strong>
        <span class="badge bg-light text-dark border">
            Version {{ $termsDocument?->version ?? \App\Support\TermsOfService::CURRENT_VERSION }}
        </span>
    </div>
    <div class="card-body">
        <form action="{{ route('account.terms.update') }}" method="POST">
            @csrf @method('PUT')

            <label for="terms-content" class="form-label fw-semibold">Agreement content</label>
            <textarea id="terms-content" name="terms_content" rows="24"
                      class="form-control font-monospace @error('terms_content') is-invalid @enderror"
                      maxlength="50000" required>{{ old('terms_content', $termsContent) }}</textarea>
            @error('terms_content') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text mb-3">
                Markdown formatting is supported. HTML is removed for security. Keep
                <code>@{{privacy_controller}}</code> and <code>@{{privacy_contact}}</code>
                where the configured clinic details should appear.
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-7 col-lg-5">
                    <label for="terms-current-password" class="form-label">Current password</label>
                    <input id="terms-current-password" type="password" name="current_password"
                           class="form-control @error('current_password') is-invalid @enderror"
                           autocomplete="current-password" required>
                    @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Publish agreement
                    </button>
                </div>
            </div>
        </form>

        <details class="mt-4">
            <summary class="fw-semibold text-primary">Preview current published agreement</summary>
            <div class="border rounded p-3 mt-3 tc-legal-preview">
                {!! \App\Support\TermsOfService::renderHtml() !!}
            </div>
        </details>
    </div>
</div>
@endif
</div>

@include('partials.avatar-cropper')
@endsection
