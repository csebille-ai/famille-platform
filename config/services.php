<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
    ],

    'webpush' => [
        'subject' => env('WEBPUSH_SUBJECT', env('APP_URL')),
        'public_key' => env('WEBPUSH_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_PRIVATE_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // Images
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        // Audio TTS
        'tts_model' => env('OPENAI_TTS_MODEL', 'tts-1'),
        'tts_voice' => env('OPENAI_TTS_VOICE', 'alloy'),
        'tts_format' => env('OPENAI_TTS_FORMAT', 'mp3'),
        'tts_speed' => env('OPENAI_TTS_SPEED'),
    ],

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        // Workers AI
        'ai_base_url' => env('CLOUDFLARE_AI_BASE_URL', 'https://api.cloudflare.com/client/v4'),
        // Example: @cf/stabilityai/stable-diffusion-xl-base-1.0
        'ai_image_model' => env('CLOUDFLARE_AI_IMAGE_MODEL', '@cf/stabilityai/stable-diffusion-xl-base-1.0'),
    ],

    'geo' => [
        // OpenStreetMap Nominatim (place -> lat/lon)
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        // Nominatim requires an identifying User-Agent.
        'nominatim_user_agent' => env('NOMINATIM_USER_AGENT', 'FamillePlatform/1.0'),

        // Timezone API (lat/lon -> IANA timezone)
        // Default uses timeapi.io coordinate endpoint.
        'timezone_url' => env('TIMEZONE_API_URL', 'https://timeapi.io/api/TimeZone/coordinate'),

        // Cache TTL for geo/timezone lookups.
        'cache_days' => (int) env('GEO_CACHE_DAYS', 365),
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];
