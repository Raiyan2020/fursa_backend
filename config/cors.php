<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    | Keep "*" for API clients (Postman / ApiDog). Browsers calling from a
    | frontend origin also work as long as the client does NOT send cookies
    | with credentials: 'include' / withCredentials: true (that combination
    | is rejected by browsers when Allow-Origin is "*").
    | Override with CORS_ALLOWED_ORIGINS=http://localhost:3000,https://...
    */
    'allowed_origins' => array_values(array_filter(array_unique(
        trim((string) env('CORS_ALLOWED_ORIGINS', '*')) === '*'
            ? ['*']
            : array_merge(
                [env('FRONTEND_HOST', 'http://localhost:3000')],
                array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
            )
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 60 * 60 * 24,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
