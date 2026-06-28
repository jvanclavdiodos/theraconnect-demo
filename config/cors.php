<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | from the browser. This defaults to a fail-closed policy when
    | CORS_ALLOWED_ORIGINS is unset/empty — the JSON API uses Sanctum bearer
    | tokens (not cookies), so legitimate API clients won't be blocked by an
    | empty allowlist (they call via Dio / fetch from native clients).
    |
    | For production, set CORS_ALLOWED_ORIGINS to the dashboard domain and the
    | Flutter app's HTTP origin (when running as a web app). Local dev may set
    | to `*` for convenience.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'health', 'up'],

    'allowed_methods' => ['*'],

    // Fail-closed when the env var is unset or empty. To permit any origin
    // (local dev only), explicitly set CORS_ALLOWED_ORIGINS=* in your .env.
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
