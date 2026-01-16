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
];
