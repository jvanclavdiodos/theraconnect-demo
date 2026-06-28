@php
    $unreadNotifications = auth()->check()
        ? \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count()
        : 0;
@endphp
<nav class="navbar navbar-expand-lg navbar-light">
    @auth
        <button class="btn btn-outline-secondary d-md-none me-2" type="button"
                aria-label="Toggle navigation sidebar" aria-expanded="false" aria-controls="sidebar-wrapper"
                @click="sidebarOpen = !sidebarOpen" :aria-expanded="sidebarOpen ? 'true' : 'false'">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
    @endauth

    <span class="navbar-brand mb-0 h1 d-md-none">{{ config('app.name', 'TheraConnect') }}</span>

    <div class="ms-auto d-flex align-items-center gap-3">
        @auth
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    @click="$store.theme.toggle()"
                    :aria-label="$store.theme.current === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                    title="Toggle dark mode">
                <i class="bi" :class="$store.theme.current === 'dark' ? 'bi-sun' : 'bi-moon-stars'" aria-hidden="true"></i>
            </button>
            <a href="{{ route('portal.notifications.index') }}" class="btn btn-outline-secondary btn-sm position-relative" title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                @if($unreadNotifications > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        {{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}
                    </span>
                @endif
            </a>
            <span class="tc-user-pill d-none d-sm-inline-flex">
                <i class="bi bi-person-circle"></i>
                {{ auth()->user()->name }}
            </span>
            <form method="POST" action="{{ route('logout') }}" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Sign Out
                </button>
            </form>
        @endauth
    </div>
</nav>
