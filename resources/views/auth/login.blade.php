@extends('layouts.app')

@section('title', 'Sign In — ' . config('app.name', 'TheraConnect'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title text-center mb-4">Sign In</h4>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Sign In</button>
                </form>

                <p class="text-center text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Forgot your password? Contact your clinic administrator to have it reset.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
