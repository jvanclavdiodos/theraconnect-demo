<nav class="navbar navbar-expand-lg navbar-light">
    @auth
        <button class="btn btn-outline-secondary d-md-none me-2" @click="sidebarOpen = !sidebarOpen">
            <i class="bi bi-list"></i>
        </button>
    @endauth

    <span class="navbar-brand mb-0 h1 d-md-none">{{ config('app.name', 'TheraConnect') }}</span>

    <div class="ms-auto d-flex align-items-center gap-3">
        @auth
            <span class="tc-user-pill d-none d-sm-inline-flex">
                <i class="bi bi-person-circle"></i>
                {{ auth()->user()->name }}
                <span class="badge bg-secondary">{{ ucfirst(auth()->user()->role) }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Sign Out
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Sign In</a>
        @endauth
    </div>
</nav>
