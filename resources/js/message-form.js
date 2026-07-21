const clearError = (form, input) => {
    input.classList.remove('is-invalid');
    const error = form.closest('.tc-message-composer')?.querySelector('[data-message-error]');
    if (!error) return;

    error.textContent = '';
    error.hidden = true;
};

const showError = (form, input, message) => {
    input.classList.add('is-invalid');
    const error = form.closest('.tc-message-composer')?.querySelector('[data-message-error]');
    if (!error) return;

    error.textContent = message;
    error.hidden = false;
};

const appendMessage = (message) => {
    const thread = document.getElementById('thread');
    if (!thread || document.querySelector(`[data-message-id="${message.id}"]`)) return;

    const row = document.createElement('div');
    row.className = 'tc-message-row outgoing';
    row.dataset.messageId = String(message.id);

    const bubble = document.createElement('div');
    bubble.className = 'tc-message-bubble';

    const body = document.createElement('div');
    body.className = 'tc-message-body';
    body.textContent = message.body;

    const time = document.createElement('time');
    time.className = 'tc-message-time';
    time.dateTime = message.created_at;
    time.textContent = message.created_at_label;

    bubble.append(body, time);
    row.appendChild(bubble);
    thread.querySelector('.tc-chat-empty')?.remove();
    thread.appendChild(row);

    window.requestAnimationFrame(() => {
        thread.scrollTop = thread.scrollHeight;
    });
};

const updateSidebar = (message) => {
    const sidebar = document.querySelector('[data-realtime-fragment="messages-sidebar"]');
    const activeConversation = sidebar?.querySelector('.tc-conversation-item.active');
    if (!sidebar || !activeConversation) return;

    const preview = activeConversation.querySelector('[data-message-preview]');
    if (preview) preview.textContent = message.body;
    sidebar.prepend(activeConversation);
};

const responseError = async (response) => {
    if (response.status === 401 || response.status === 419) {
        return 'Your session expired. Refresh the page and sign in again.';
    }
    if (response.status === 403) return 'You can no longer send messages in this conversation.';

    try {
        const payload = await response.json();
        return payload.errors?.body?.[0]
            ?? (response.status < 500 ? payload.message : null)
            ?? 'The message could not be sent. Please try again.';
    } catch (_) {
        return 'The message could not be sent. Please try again.';
    }
};

document.querySelectorAll('.tc-message-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;

        const input = form.elements.namedItem('body');
        const button = form.querySelector('[type="submit"]');
        if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) return;

        clearError(form, input);
        form.dataset.submitting = 'true';
        button?.setAttribute('disabled', '');
        button?.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });

            if (!response.ok) {
                showError(form, input, await responseError(response));
                return;
            }

            const payload = await response.json();
            appendMessage(payload.data);
            updateSidebar(payload.data);
            input.value = '';
            input.focus();
        } catch (_) {
            showError(form, input, 'The message could not be sent. Check your connection and try again.');
        } finally {
            delete form.dataset.submitting;
            button?.removeAttribute('disabled');
            button?.removeAttribute('aria-busy');
        }
    });
});
