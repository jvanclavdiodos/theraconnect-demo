(function () {
    // Auto-wire client-side validation for file inputs marked with
    // `data-validate-file`. Each marked input may carry:
    //   data-max-bytes="4194304"
    //   data-allowed-extensions="jpg,jpeg,png,webp"
    //
    // On change (and again on form submit) the file's size and extension are
    // checked. Invalid files set `is-invalid` on the input and populate any
    // sibling `.invalid-feedback` div. The form submit is blocked until the
    // user picks a valid file — avoiding a wasted full upload round-trip.

    function validateInput(input) {
        if (!input.hasAttribute('data-validate-file')) return true;
        var file = input.files && input.files[0];
        if (!file) return true; // `required` handles empty selection.

        var maxBytes = parseInt(input.getAttribute('data-max-bytes') || '0', 10);
        if (maxBytes > 0 && file.size > maxBytes) {
            var mbMax = (maxBytes / (1024 * 1024)).toFixed(0);
            setError(input, 'The file must not be greater than ' + mbMax + ' MB.');
            return false;
        }

        var allowedRaw = (input.getAttribute('data-allowed-extensions') || '').toLowerCase();
        var allowed = allowedRaw.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s.length > 0; });
        if (allowed.length > 0) {
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            if (allowed.indexOf(ext) === -1) {
                setError(input, 'This file type is not allowed. Accepted: ' + allowed.join(', ') + '.');
                return false;
            }
        }

        clearError(input);
        return true;
    }

    function setError(input, message) {
        input.classList.add('is-invalid');
        var fb = input.parentElement.querySelector('.invalid-feedback');
        if (fb) {
            fb.textContent = message;
            fb.style.display = 'block';
        } else {
            input.setCustomValidity(message);
        }
    }

    function clearError(input) {
        input.classList.remove('is-invalid');
        input.setCustomValidity('');
        // Don't touch the @error-rendered feedback — let Blade keep its initial
        // hidden state. Only short-circuit errors we just set above.
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[type="file"][data-validate-file]')) {
            validateInput(e.target);
        }
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches('form')) return;
        var inputs = form.querySelectorAll('input[type="file"][data-validate-file]');
        var allValid = true;
        for (var i = 0; i < inputs.length; i++) {
            if (!validateInput(inputs[i])) allValid = false;
        }
        if (!allValid) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
})();
