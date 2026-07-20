<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipSelector = '[data-bs-toggle="tooltip"]';
        var popoverSelector = '[data-bs-toggle="popover"]';

        document.querySelectorAll(tooltipSelector).forEach(function (element) {
            bootstrap.Tooltip.getOrCreateInstance(element, {
                container: 'body',
                trigger: 'hover focus',
            });
        });

        document.querySelectorAll(popoverSelector).forEach(function (element) {
            bootstrap.Popover.getOrCreateInstance(element, {
                container: 'body',
                html: false,
                trigger: 'click',
            });

            element.addEventListener('show.bs.popover', function () {
                document.querySelectorAll(popoverSelector).forEach(function (other) {
                    if (other !== element) bootstrap.Popover.getInstance(other)?.hide();
                });
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll(popoverSelector).forEach(function (element) {
                bootstrap.Popover.getInstance(element)?.hide();
            });
        });

        document.addEventListener('show.bs.modal', function () {
            document.querySelectorAll(tooltipSelector + ', ' + popoverSelector).forEach(function (element) {
                bootstrap.Tooltip.getInstance(element)?.hide();
                bootstrap.Popover.getInstance(element)?.hide();
            });
        });
    });
</script>
