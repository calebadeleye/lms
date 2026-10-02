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

    // The BFF pattern means almost all frontend->backend traffic is
    // server-to-server (no browser CORS involved). These origins only matter
    // for the rare direct-from-browser calls (e.g. presigned upload PUTs go
    // straight to S3, not here).
    'allowed_origins' => array_filter(explode(',', env('CORS_ADDITIONAL_ORIGINS', ''))),

    'allowed_origins_patterns' => [
        '#^'.preg_quote(rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'), '#').'$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    'supports_credentials' => true,

];
