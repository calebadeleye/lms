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
    // straight to S3, not here). Verified tenant *custom* domains (outside
    // the neutral platform domain pattern below) are comma-separated here;
    // Phase 1 requires re-deploying config on domain verification. Making
    // this fully dynamic (DB-backed origin check) is documented remaining
    // work in the domains module.
    'allowed_origins' => array_filter(explode(',', env('CORS_ADDITIONAL_ORIGINS', ''))),

    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)?'.preg_quote(env('NEUTRAL_PLATFORM_DOMAIN', 'localhost'), '#').'(:\d+)?$#i',
        '#^'.preg_quote(rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'), '#').'$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    'supports_credentials' => true,

];
