@extends('layouts.portal')
@section('realtime-resources', 'notifications')

@section('title', 'Notifications — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Notifications</li>
@endsection

@section('content')
<div data-realtime-fragment="notifications">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="tc-page-title">Notifications</h1>
        <p class="tc-page-sub mb-0">Updates about your appointments, assignments, and messages.</p>
    </div>
    @if($notifications->where('read_at', null)->count())
        <form method="POST" action="{{ route('portal.notifications.readAll') }}"
              x-data="{ busy: false }" @submit.prevent="markAll($el)">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" :disabled="busy">
                <span x-show="!busy">Mark all read</span>
                <span x-show="busy" x-cloak><span class="spinner-border spinner-border-sm" role="status" aria-label="Marking all read"></span> Working…</span>
            </button>
        </form>
    @endif
</div>

{{-- Alpine-scoped toast for AJAX errors (replaces the previous blocking alert()). --}}
<div x-data="{ toast: '', shown: false, timer: null }"
     @toast-error.window="toast = $event.detail; shown = true; clearTimeout(timer); timer = setTimeout(() => shown = false, 4000)"
     class="position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div class="toast align-items-center text-bg-danger border-0" :class="{ 'show': shown }" role="alert" aria-live="assertive" aria-atomic="true" x-cloak>
        <div class="d-flex">
            <div class="toast-body" x-text="toast"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="shown = false" aria-label="Close"></button>
        </div>
    </div>
</div>

<div class="card" x-data="{ unreadCount: {{ $notifications->where('read_at', null)->count() }} }">
    <div class="list-group list-group-flush">
        @forelse($notifications as $n)
            <div class="list-group-item d-flex align-items-start gap-3"
                 x-data="{ read: {{ $n->read_at ? 'true' : 'false' }} }">
                <i class="bi mt-1" :class="read ? 'bi-bell' : 'bi-bell-fill text-primary'" aria-hidden="true"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">{{ $n->title }}</div>
                    <div class="small">{{ $n->body }}</div>
                    <div class="text-muted small mt-1">{{ $n->created_at->diffForHumans() }}</div>
                </div>
                @unless($n->read_at)
                    <form method="POST" action="{{ route('portal.notifications.read', $n->id) }}"
                          x-data="{ busy: false }" @submit.prevent="mark($el, $root)">
                        @csrf
                        <button class="btn btn-sm btn-link text-decoration-none" type="submit"
                                :disabled="busy" aria-label="Mark notification read">
                            <span :class="busy ? 'spinner-border spinner-border-sm' : ''" role="status" aria-hidden="true"></span>
                            <i x-show="!busy" class="bi bi-check2" aria-hidden="true"></i>
                        </button>
                    </form>
                @endunless
            </div>
        @empty
            <div class="list-group-item">
                <div class="tc-empty">
                    <div class="tc-empty-icon"><i class="bi bi-bell"></i></div>
                    <div>No notifications yet.</div>
                </div>
            </div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $notifications->links() }}</div>
</div>

@push('scripts')
<script>
// Inline JS that intercepts the per-notification "mark read" forms and the
// "mark all read" form. Sends via Fetch, then updates the row's CSS state in
// place — no full-page reload each time the user marks a notification read.
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;

    async function postForm(form) {
        const res = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: new FormData(form),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json().catch(() => ({}));
    }

    window.mark = async function (form, rootEl) {
        const state = form.closest('[x-data]') ? Alpine.$data(form) : null;
        if (form.busy) return;
        try {
            const button = form.querySelector('button');
            if (button) button.disabled = true;
            await postForm(form);
            // Find the outer list-group-item Alpine state and flip `read` to true.
            let el = form;
            while (el && !el.matches('.list-group-item')) el = el.parentElement;
            if (el) {
                const data = Alpine.$data(el);
                if (data) data.read = true;
            }
        } catch (e) {
            if (form.querySelector('button')) form.querySelector('button').disabled = false;
            window.dispatchEvent(new CustomEvent('toast-error', { detail: 'Could not mark notification as read. Please try again.' }));
        }
    };

    window.markAll = async function (form) {
        try {
            const button = form.querySelector('button');
            if (button) button.disabled = true;
            // Peek into the form's Alpine state to toggle the spinner.
            const data = Alpine.$data(form);
            if (data) data.busy = true;
            await postForm(form);
            // Flip every unread row on the page.
            document.querySelectorAll('.list-group-item').forEach(function (item) {
                const d = Alpine.$data(item);
                if (d) d.read = true;
            });
        } catch (e) {
            if (form.querySelector('button')) form.querySelector('button').disabled = false;
            window.dispatchEvent(new CustomEvent('toast-error', { detail: 'Could not mark all notifications as read. Please try again.' }));
        }
    };
})();
</script>
@endpush
@endsection
