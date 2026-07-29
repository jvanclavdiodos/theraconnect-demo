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
