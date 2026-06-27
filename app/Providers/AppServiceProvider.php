<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\SubmissionPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

        // The app runs on Asia/Manila (see config/app.php), so dates are stored
        // and formatted as PH-local wall-clock. Serialize Carbon to JSON as that
        // wall-clock with a trailing 'Z' (rather than converting to true UTC) so
        // the mobile app — which shows the wall-clock and deliberately ignores
        // the offset (see date_format.dart) — displays the same time the clinic
        // entered, on any device, without needing a rebuild. This keeps the
        // long-standing API contract intact after the UTC -> Manila switch.
        $serializeAsWallClock = fn ($date) => $date->format('Y-m-d\TH:i:s.u\Z');
        Carbon::serializeUsing($serializeAsWallClock);
        CarbonImmutable::serializeUsing($serializeAsWallClock);

        // `{id}` route params are always numeric DB keys. Without this, a
        // non-numeric id (e.g. /api/v1/appointments/abc) reaches a controller
        // method type-hinted `int $id` and throws a 500 TypeError instead of a
        // clean 404. (Route-model-bound params like {appointment} 404 already.)
        Route::pattern('id', '[0-9]+');

        // Rate limiter for the staff web login. Separate buckets for login vs
        // registration so a registration flood can't lock out legitimate logins
        // (and vice versa). The `account-login` bucket is also keyed by the
        // submitted email so distributed brute-force attempts against a single
        // account from rotating IPs trip the limit before they can grind.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('account-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email', '').'|'.$request->ip());
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
