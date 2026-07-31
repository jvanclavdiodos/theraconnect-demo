<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceStaffInactivityTimeout
{
    private const SESSION_KEY = 'staff_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $lastActivity = $request->session()->get(self::SESSION_KEY);
        $timeout = (int) config('auth.staff_inactivity_timeout', 600);

        if (is_numeric($lastActivity) && now()->timestamp - (int) $lastActivity >= $timeout) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You have been logged out after 10 minutes of inactivity.',
                    'redirect' => route('login', ['inactivity' => 1]),
                ], 401);
            }

            return redirect()->route('login')->with(
                'status',
                'You have been logged out after 10 minutes of inactivity.'
            );
        }

        $request->session()->put(self::SESSION_KEY, now()->timestamp);

        return $next($request);
    }
}
