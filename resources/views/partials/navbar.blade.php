<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-3">
    <button class="btn btn-outline-secondary d-md-none me-2" @click="sidebarOpen = !sidebarOpen">
        <i class="bi bi-list"></i>
    </button>

    <span class="navbar-brand mb-0 h1">{{ config('app.name', 'TheraConnect') }}</span>

    <div class="ms-auto d-flex align-items-center gap-2">
        @auth
            <span class="text-muted small d-none d-sm-inline me-2">
                {{ auth()->user()->name }}
                <span class="badge bg-secondary">{{ ucfirst(auth()->user()->role) }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">Sign Out</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Sign In</a>
        @endauth
    </div>
</nav>
