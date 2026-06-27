<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defensive-security response headers and strips the identifying
 * `X-Powered-By` header from responses. This is the primary hardening for the
 * Railway pilot deployment, which runs on `php artisan serve` (and therefore
 * ignores the directives in `public/.htaccess`). HSTS is added only when the
 * request arrived over TLS — `request()->secure()` sees through `TRUST_PROXIES`
 * so this works behind a PaaS reverse proxy that terminated TLS upstream.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Strip the PHP signature. `header_remove()` only works for headers
        // that haven't been sent yet — the framework lets us remove it here
        // because the response is still being constructed. The SAPI-level
        // `expose_php = Off` in docker/php.ini is the primary guard; this is
        // defense-in-depth for hosts where the ini setting can't be enforced.
        header_remove('X-Powered-By');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
