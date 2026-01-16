<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Astro Engine
    |--------------------------------------------------------------------------
    |
    | Internal ephemerides service used for Moon computations.
    |
    */

    'engine_url' => env('ASTRO_ENGINE_URL', 'http://astro-engine:3000'),

    'timeout_seconds' => (int) env('ASTRO_ENGINE_TIMEOUT', 3),

    // Some hosting environments use self-signed certificates.
    // Prefer fixing TLS properly, but allow opting out of verification if needed.
    'verify_ssl' => filter_var(env('ASTRO_ENGINE_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
];
