<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies: Railway (and most PaaS like Heroku, Render, Fly.io)
        // terminate TLS at a reverse proxy and forward to the container over HTTP.
        // Without this, secure-cookie session/CSRF logic would break and
        // rate limiters would rate-limit on the proxy IP instead of real
        // client IPs.
        //
        // SECURITY NOTE: trusting '*' is safe ONLY behind a PaaS proxy. If this
        // app is ever directly exposed (no reverse proxy), restrict to the
        // proxy's CIDR here (e.g. trustProxies(at: '10.0.0.0/8')) so a client
        // cannot spoof X-Forwarded-* headers.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
