const config = window.theraRealtime;

if (config?.enabled && config.userId && window.Echo) {
    const resources = new Set(
        (document.body.dataset.realtimeResources ?? '').split(' ').filter(Boolean),
    );
    let reloadTimer;

    const hasActiveEditor = () => {
        const active = document.activeElement;
        const editing = active?.matches?.('input, textarea, select')
            && active.value?.trim?.().length > 0;

        return editing || document.querySelector('.modal.show');
    };

    const showRefreshNotice = () => {
        if (document.getElementById('realtime-refresh-notice')) return;

        const notice = document.createElement('div');
        notice.id = 'realtime-refresh-notice';
        notice.className = 'alert alert-info position-fixed end-0 bottom-0 m-3 shadow d-flex align-items-center gap-2';
        notice.style.zIndex = '1090';
        notice.innerHTML = '<span>New updates are available.</span><button type="button" class="btn btn-sm btn-info">Refresh</button>';
        notice.querySelector('button').addEventListener('click', () => window.location.reload());
        document.body.appendChild(notice);
    };

    const refreshResource = (resource) => {
        if (!resources.has(resource)) return;

        window.clearTimeout(reloadTimer);
        reloadTimer = window.setTimeout(() => {
            if (hasActiveEditor()) {
                showRefreshNotice();
                return;
            }

            window.location.reload();
        }, 400);
    };

    const incrementCounters = (selector) => {
        document.querySelectorAll(selector).forEach((badge) => {
            const count = Number.parseInt(badge.dataset.count ?? '0', 10) + 1;
            badge.dataset.count = String(count);
            badge.textContent = count > 9 ? '9+' : String(count);
            badge.classList.remove('d-none');
            if (badge.nextElementSibling?.classList.contains('tc-nav-chevron')) {
                badge.nextElementSibling.classList.add('d-none');
            }
        });
    };

    window.Echo.private(`users.${config.userId}`)
        .listen('.notification.created', (event) => {
            incrementCounters('[data-realtime-notification-count]');
            if (event.type === 'message_received') {
                incrementCounters('[data-realtime-message-count]');
                refreshResource('messages');
            }
            refreshResource('notifications');
        })
        .listen('.appointment.updated', () => refreshResource('appointments'));

    if (config.role === 'admin') {
        window.Echo.private('admin.appointments')
            .listen('.appointment.updated', () => refreshResource('appointments'));
    }

    const conversationId = Number.parseInt(document.body.dataset.realtimeConversation ?? '', 10);
    if (conversationId) {
        window.Echo.private(`conversations.${conversationId}`)
            .listen('.message.created', () => refreshResource('messages'));
    }
}
