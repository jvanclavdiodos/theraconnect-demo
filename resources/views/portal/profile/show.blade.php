@extends('layouts.portal')

@section('title', 'My Profile — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Profile</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="tc-page-title mb-0">My Profile</h1>
    <a href="{{ route('portal.profile.edit') }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                @if(auth()->user()->hasAvatar())
                    <img src="{{ route('portal.profile.avatar') }}" alt="avatar" class="rounded-circle mb-3"
                         style="width:96px;height:96px;object-fit:cover;border:1px solid #dee2e6;">
                @else
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:96px;height:96px;"><i class="bi bi-person fs-1 text-secondary"></i></div>
                @endif
                <h5 class="mb-0">{{ $patient->user->name }}</h5>
                <div class="text-muted small">{{ $patient->user->email }}</div>
                @if($patient->assignedClinicians->isNotEmpty())
                    <hr>
                    <div class="text-muted small mb-1">Your clinicians</div>
                    @foreach($patient->assignedClinicians as $clinician)
                        <div class="fw-semibold">{{ $clinician->user->name }}</div>
                    @endforeach
                @endif

                <hr>
                <form id="avatar-upload-form" method="POST" action="{{ route('portal.profile.avatar.update') }}"
                      enctype="multipart/form-data" data-avatar-crop-form>
                    @csrf
                    <label for="avatar-input" class="form-label visually-hidden">Choose profile photo</label>
                    <input id="avatar-input" type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                           data-avatar-input data-max-source-bytes="10485760"
                           class="form-control form-control-sm mb-2 @error('avatar') is-invalid @enderror" required>
                    <div class="invalid-feedback d-block text-start" data-avatar-error @unless($errors->has('avatar')) hidden @endunless>
                        @error('avatar'){{ $message }}@enderror
                    </div>
                    <div class="form-text text-start mb-2">JPG, PNG, or WebP. The adjusted photo must be 2 MB or smaller.</div>
                    <button class="btn btn-sm btn-outline-primary w-100" data-avatar-submit>
                        <i class="bi bi-camera me-1"></i> Adjust and upload
                    </button>
                </form>
                @if(auth()->user()->hasAvatar())
                    <form method="POST" action="{{ route('portal.profile.avatar.destroy') }}" class="mt-2"
                          x-data @submit.prevent="if(confirm('Remove photo?')) $el.submit()">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-link text-danger text-decoration-none">Remove photo</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header"><strong>Personal Information</strong></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Date of Birth</dt>
                    <dd class="col-sm-8">{{ $patient->date_of_birth ? $patient->date_of_birth->format('M j, Y') : '—' }}</dd>
                    <dt class="col-sm-4">Gender</dt><dd class="col-sm-8">{{ $patient->gender ?? '—' }}</dd>
                    <dt class="col-sm-4">Educational Attainment</dt><dd class="col-sm-8">{{ $patient->educational_attainment ?? '—' }}</dd>
                    <dt class="col-sm-4">Employment Status</dt><dd class="col-sm-8">{{ $patient->employment_status ?? '—' }}</dd>
                    <dt class="col-sm-4">Contact No.</dt><dd class="col-sm-8">{{ $patient->contact_no ?? '—' }}</dd>
                    <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $patient->address ?? '—' }}</dd>
                    <dt class="col-sm-4">Emergency Contact</dt><dd class="col-sm-8">{{ $patient->emergency_contact ?? '—' }}</dd>
                    <dt class="col-sm-4">Personal Issues</dt>
                    <dd class="col-sm-8" style="white-space:pre-wrap;">{{ $patient->personal_issues ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="avatar-crop-modal" tabindex="-1" aria-labelledby="avatar-crop-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="avatar-crop-title">Adjust profile photo</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="avatar-crop-stage">
                    <img id="avatar-crop-image" alt="Selected profile photo preview">
                </div>
                <div class="d-flex align-items-center gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-avatar-rotate="-90"
                            title="Rotate left" aria-label="Rotate left">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <i class="bi bi-zoom-out text-muted" aria-hidden="true"></i>
                    <input type="range" class="form-range flex-grow-1" min="0" max="1" step="0.01" value="0"
                           data-avatar-zoom aria-label="Photo zoom">
                    <i class="bi bi-zoom-in text-muted" aria-hidden="true"></i>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-avatar-rotate="90"
                            title="Rotate right" aria-label="Rotate right">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-avatar-apply>
                    <i class="bi bi-check-lg me-1"></i> Save photo
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous">
    <style>
        .avatar-crop-stage {
            height: min(58vh, 420px);
            min-height: 280px;
            background: #111;
            overflow: hidden;
        }
        .avatar-crop-stage img {
            display: block;
            max-width: 100%;
        }
        .avatar-crop-stage .cropper-view-box,
        .avatar-crop-stage .cropper-face {
            border-radius: 50%;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/avatar-cropper.js') }}" defer></script>
@endpush
@endsection
