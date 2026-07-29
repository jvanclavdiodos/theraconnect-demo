@extends('layouts.app')

@section('title', 'My Account — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">My Account</li>
@endsection

@section('content')
<h2>My Account</h2>

<div class="card shadow-sm mt-3" style="max-width: 540px;">
    <div class="card-header bg-white"><strong>Profile picture</strong></div>
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            @if ($user->hasAvatar())
                <img src="{{ route('avatars.show', $user) }}" alt="avatar"
                     style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:1px solid #dee2e6;">
            @else
                <span class="rounded-circle d-inline-flex align-items-center justify-content-center"
                      style="width:88px;height:88px;background:var(--tc-teal,#0D6E8A);color:#fff;font-size:1.75rem;">
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

@include('partials.avatar-cropper')

<div class="card shadow-sm mt-3" style="max-width: 540px;" x-data="passwordField({ requireConfirm: true })">
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
@endsection
