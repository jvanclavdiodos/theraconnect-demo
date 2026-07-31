(function () {
    'use strict';

    var root = document.body;
    var timeoutSeconds = Number(root.dataset.staffInactivityTimeout || 600);
    var activityUrl = root.dataset.staffActivityUrl;
    var logoutUrl = root.dataset.staffLogoutUrl;
    var loginUrl = root.dataset.staffLoginUrl;
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!activityUrl || !logoutUrl || !loginUrl || !csrf || timeoutSeconds <= 0) return;

    var timeoutMs = timeoutSeconds * 1000;
    var heartbeatIntervalMs = Math.min(60000, Math.max(10000, timeoutMs / 4));
    var lastActivityAt = Date.now();
    var lastHeartbeatAt = 0;
    var timer = null;
    var loggingOut = false;

    function logoutForInactivity() {
        if (loggingOut) return;
        loggingOut = true;

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = logoutUrl;
        form.hidden = true;
        form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="inactivity" value="1">';
        document.body.appendChild(form);
        form.submit();
    }

    function checkTimeout() {
        var remaining = timeoutMs - (Date.now() - lastActivityAt);
        if (remaining <= 0) {
            logoutForInactivity();
            return;
        }
        clearTimeout(timer);
        timer = setTimeout(checkTimeout, remaining);
    }

    function heartbeat() {
        if (Date.now() - lastHeartbeatAt < heartbeatIntervalMs) return;
        lastHeartbeatAt = Date.now();

        fetch(activityUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        }).then(function (response) {
            if (response.status === 401 || response.status === 419 || response.redirected) {
                window.location.assign(loginUrl);
            }
        }).catch(function () {
            // A temporary network failure must not extend the local timeout.
        });
    }

    function recordActivity() {
        if (loggingOut) return;
        lastActivityAt = Date.now();
        checkTimeout();
        heartbeat();
    }

    ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (eventName) {
        window.addEventListener(eventName, recordActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) checkTimeout();
    });

    checkTimeout();
}());
