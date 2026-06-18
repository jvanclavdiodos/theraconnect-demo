<?php

use Illuminate\Support\Facades\Config;

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | from the browser. This defaults to a permissive `*` policy which is safe
    | enough because the JSON API uses Sanctum bearer tokens (not cookies) and
    | browsers won't auto-send credentials cross-origin without explicit
    | `allow_credentials` headers.
    |
    | For defense-in-depth, restrict `allowed_origins` via the CORS_ALLOWED_ORIGINS
    | env var to the Railway dashboard domain and the Flutter app's HTTP origin
    | (when running as a web app). Leave blank to fall back to permissive `*`.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'health', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
