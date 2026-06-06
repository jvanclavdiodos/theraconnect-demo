<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Enforce role(s) on a route.
     *
     * Usage: Route::middleware('role:admin,clinician')...
     * Returns 403 on mismatch. Accepts comma-separated role list.
     *
     * [ARCHITECT DECISION] Role enforcement via middleware (§1.2).
     * Policies handle ownership checks; this handles role gating.
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! $request->user()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if (! in_array($request->user()->role, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Insufficient role.'], 403);
            }
            abort(403, 'Forbidden. Insufficient role.');
        }

        return $next($request);
    }
}
