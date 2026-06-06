<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\SubmissionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('chatbot', function (Request $request) {
            return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Assignment::class, AssignmentPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);

        Gate::define('role', fn (User $user, string ...$roles) => in_array($user->role, $roles));

        Blade::if('role', function (string ...$roles) {
            return auth()->check() && in_array(auth()->user()->role, $roles);
        });
    }
}
