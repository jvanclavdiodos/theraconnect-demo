<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // TRUST_PROXIES must be '*' on PaaS (Railway, Heroku, Render, Fly.io) where
        // TLS terminates at a reverse proxy. Leave unset for direct-server deployments
        // so X-Forwarded-* headers cannot be spoofed by clients.
        $middleware->trustProxies(at: env('TRUST_PROXIES'));

        // Strip the PHP `X-Powered-By` header and add defensive headers
        // (X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
        // Permissions-Policy, HSTS on HTTPS). These apply to BOTH the web /
        // portal (session) and the API (Sanctum) surfaces.
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Over-limit uploads that exceed PHP's post_max_size trigger this
        // exception BEFORE any FormRequest fires (the request body is dropped
        // by PHP). Without this handler the user sees an opaque 413 / debug
        // page instead of the friendly validation message defined per field.
        $exceptions->renderable(function (PostTooLargeException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Uploaded file is too large.',
                    'errors' => [
                        'file' => ['The file exceeds the server size limit.'],
                    ],
                ], 422);
            }

            return redirect()->back()->with('error', 'File is too large. The server size limit was exceeded.');
        });

        // Render branded error pages instead of the default Symfony traces for
        // these specific exceptions. The handlers are environment-agnostic —
        // they neither expose internals nor hide debugging info (the underlying
        // exception still appears in Laravel's logs). In local dev with
        // APP_DEBUG=true these are a small UX nicety; in production they
        // prevent an ugly framework page from reaching the end user.
        $exceptions->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            return response()->view('errors.404', [], 404);
        });

        $exceptions->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            return response()->view('errors.404', [], 404);
        });

        $exceptions->renderable(function (AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return response()->view('errors.403', [], 403);
        });

        // Catch-all only in PRODUCTION (not test/dev env). Less-specific
        // renderables than the ones above; the more-specific ones always win
        // regardless of registration order. This catches genuine 500s and
        // presents a branded page rather than a Symfony trace. The production
        // environment check (rather than `! config('app.debug')`) ensures
        // tests can pin APP_DEBUG without triggering the catch-all.
        if (app()->environment('production')) {
            $exceptions->renderable(function (Throwable $e, $request) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Server error.'], 500);
                }

                return response()->view('errors.500', [], 500);
            });
        }
    })->create();
