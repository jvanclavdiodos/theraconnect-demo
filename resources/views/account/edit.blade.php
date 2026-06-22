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

        <form action="{{ route('account.avatar.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-2">
                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                       class="form-control @error('avatar') is-invalid @enderror" required>
                @error('avatar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">JPG, PNG, or WEBP, up to 4&nbsp;MB.</div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Upload picture</button>
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
@endsection
