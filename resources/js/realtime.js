const config = window.theraRealtime;

if (config?.enabled && config.userId && window.Echo) {
    const resources = new Set(
        (document.body.dataset.realtimeResources ?? '').split(' ').filter(Boolean),
    );
    let reloadTimer;
    let syncPromise;

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

    const fetchCurrentDocument = () => {
        if (syncPromise) return syncPromise;

        syncPromise = fetch(window.location.href, {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then((response) => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.text();
            })
            .then((html) => new DOMParser().parseFromString(html, 'text/html'))
            .finally(() => { syncPromise = null; });

        return syncPromise;
    };

    const syncCounterGroup = (sourceDocument, selector) => {
        const source = sourceDocument.querySelector(selector);
        if (!source) return;

        document.querySelectorAll(selector).forEach((badge) => {
            badge.dataset.count = source.dataset.count ?? '0';
            badge.textContent = source.textContent;
            badge.classList.toggle('d-none', source.classList.contains('d-none'));
            if (badge.nextElementSibling?.classList.contains('tc-nav-chevron')) {
                badge.nextElementSibling.classList.toggle('d-none', !source.classList.contains('d-none'));
            }
        });
    };

    const syncFragment = (sourceDocument, name) => {
        const current = document.querySelector(`[data-realtime-fragment="${name}"]`);
        const source = sourceDocument.querySelector(`[data-realtime-fragment="${name}"]`);
        if (!current || !source) return;

        current.innerHTML = source.innerHTML;
        window.Alpine?.initTree?.(current);
    };

    const syncMessageThread = (sourceDocument) => {
        const current = document.getElementById('thread');
        const source = sourceDocument.getElementById('thread');
        if (!current || !source) return;

        const distanceFromBottom = current.scrollHeight - current.scrollTop - current.clientHeight;
        const wasAtBottom = distanceFromBottom <= 80;
        const previousTop = current.scrollTop;
        const existingIds = new Set(
            [...current.querySelectorAll('[data-message-id]')].map((row) => row.dataset.messageId),
        );
        const incomingRows = [...source.querySelectorAll('[data-message-id]')]
            .filter((row) => !existingIds.has(row.dataset.messageId));

        if (incomingRows.length === 0) return;

        current.querySelector('.tc-chat-empty')?.remove();
        incomingRows.forEach((row) => current.appendChild(row.cloneNode(true)));

        window.requestAnimationFrame(() => {
            current.scrollTop = wasAtBottom ? current.scrollHeight : previousTop;
        });
    };

    const syncPage = async ({ messages = false, notifications = false } = {}) => {
        try {
            const sourceDocument = await fetchCurrentDocument();
            syncCounterGroup(sourceDocument, '[data-realtime-notification-count]');
            syncCounterGroup(sourceDocument, '[data-realtime-message-count]');

            if (messages) {
                syncFragment(sourceDocument, 'messages-sidebar');
                syncMessageThread(sourceDocument);
            }
            if (notifications) syncFragment(sourceDocument, 'notifications');
        } catch (error) {
            console.warn('Realtime synchronization failed; waiting for reconnect or next event.', error);
        }
    };

    const refreshResource = (resource) => {
        if (!resources.has(resource)) return;

        if (resource === 'messages') {
            syncPage({ messages: true });
            return;
        }
        if (resource === 'notifications') {
            syncPage({ notifications: true });
            return;
        }

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
            .listen('.message.created', (event) => {
                if (document.querySelector(`[data-message-id="${event.message_id}"]`)) return;
                syncPage({ messages: true });
            });
    }

    const connection = window.Echo.connector?.pusher?.connection;
    let connectedOnce = connection?.state === 'connected';
    connection?.bind('connected', () => {
        if (connectedOnce) {
            syncPage({
                messages: resources.has('messages'),
                notifications: resources.has('notifications'),
            });
            if (resources.has('appointments') && !hasActiveEditor()) window.location.reload();
        }
        connectedOnce = true;
    });
}
