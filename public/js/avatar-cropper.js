(function () {
    var form = document.querySelector('[data-avatar-crop-form]');
    if (!form) return;

    var input = form.querySelector('[data-avatar-input]');
    var error = form.querySelector('[data-avatar-error]');
    var submit = form.querySelector('[data-avatar-submit]');
    var modalElement = document.getElementById('avatar-crop-modal');
    var image = document.getElementById('avatar-crop-image');
    var apply = modalElement.querySelector('[data-avatar-apply]');
    var zoom = modalElement.querySelector('[data-avatar-zoom]');
    var modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    var cropper = null;
    var objectUrl = null;
    var cropReady = false;
    var submitting = false;

    function setError(message) {
        input.classList.add('is-invalid');
        input.setCustomValidity(message);
        error.textContent = message;
        error.hidden = false;
    }

    function clearError() {
        input.classList.remove('is-invalid');
        input.setCustomValidity('');
        error.textContent = '';
        error.hidden = true;
    }

    function selectedFile() {
        return input.files && input.files[0] ? input.files[0] : null;
    }

    function validateSource(file) {
        if (!file) {
            setError('Choose a photo before uploading.');
            return false;
        }

        var allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (allowedTypes.indexOf(file.type) === -1) {
            setError('Choose a JPG, PNG, or WebP image.');
            return false;
        }

        var maxSourceBytes = parseInt(input.dataset.maxSourceBytes || '0', 10);
        if (maxSourceBytes > 0 && file.size > maxSourceBytes) {
            setError('Choose a source photo smaller than 10 MB.');
            return false;
        }

        clearError();
        return true;
    }

    function releasePreview() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        image.removeAttribute('src');
    }

    function openCropper() {
        var file = selectedFile();
        if (!validateSource(file)) {
            input.reportValidity();
            return;
        }
        if (!window.Cropper) return;

        releasePreview();
        objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        cropReady = false;
        zoom.value = '0';
        modal.show();
    }

    input.addEventListener('change', function () {
        cropReady = false;
        if (selectedFile() && window.Cropper) openCropper();
        else clearError();
    });

    modalElement.addEventListener('shown.bs.modal', function () {
        if (!window.Cropper) return;
        cropper = new window.Cropper(image, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            background: false,
            guides: false,
            center: false,
            cropBoxMovable: false,
            cropBoxResizable: false,
            toggleDragModeOnDblclick: false,
            ready: function () {
                cropper.setAspectRatio(1);
                var data = cropper.getImageData();
                var fittedRatio = data.naturalWidth > 0 ? data.width / data.naturalWidth : 1;
                zoom.min = String(fittedRatio);
                zoom.max = String(fittedRatio * 3);
                zoom.step = String(Math.max(fittedRatio / 100, 0.001));
                zoom.value = String(fittedRatio);
            }
        });
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        if (!submitting) releasePreview();
    });

    modalElement.querySelectorAll('[data-avatar-rotate]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (cropper) cropper.rotate(Number(button.dataset.avatarRotate));
        });
    });

    zoom.addEventListener('input', function () {
        if (cropper) cropper.zoomTo(Number(zoom.value));
    });

    apply.addEventListener('click', function () {
        if (!cropper) return;

        apply.disabled = true;
        cropper.getCroppedCanvas({
            width: 512,
            height: 512,
            fillColor: '#fff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        }).toBlob(function (blob) {
            apply.disabled = false;
            if (!blob) {
                setError('The photo could not be processed. Choose another image.');
                modal.hide();
                return;
            }
            if (blob.size > 2 * 1024 * 1024) {
                setError('The adjusted photo must not be greater than 2 MB.');
                modal.hide();
                return;
            }

            var transfer = new DataTransfer();
            transfer.items.add(new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' }));
            input.files = transfer.files;
            cropReady = true;
            submitting = true;
            submit.disabled = true;
            modal.hide();
            form.requestSubmit(submit);
        }, 'image/jpeg', 0.9);
    });

    form.addEventListener('submit', function (event) {
        if (!selectedFile()) {
            event.preventDefault();
            setError('Choose a photo before uploading.');
            input.reportValidity();
            return;
        }

        if (window.Cropper && !cropReady) {
            event.preventDefault();
            openCropper();
        }
    });
})();
