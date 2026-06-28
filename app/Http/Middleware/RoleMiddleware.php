<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * Enforce role(s) on a route.
 *
 * Usage: Route::middleware('role:admin,clinician')...
 * Accepts comma-separated role list.
 *
 * [ARCHITECT DECISION] Role enforcement via middleware (§1.2).
 * Policies handle ownership checks; this handles role gating.
 *
 * Throws AuthorizationException on a role mismatch so the response flows
 * through bootstrap/app.php's unified 403 renderable — consistent JSON
 * `{message: 'Forbidden.'}` for API requests, branded `errors.403` view
 * for browser requests. Previously the middleware called abort(403, ...)
 * which raises Symfony's HttpException (status 403) and bypassed the
 * AuthorizationException renderable, falling back to Symfony's default
 * error page (full stack trace in non-prod envs).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        if (! in_array($request->user()->role, $roles)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
