<?php

return [
    // Provider selection.
    // Supported: 'jitsi' (embedded iframe via /visio/{room}), 'link' (simple external URL).
    'provider' => env('VISIO_PROVIDER', 'link'),

    // Default room used when linking from UI (e.g. Chat button).
    'default_room' => env('VISIO_DEFAULT_ROOM', 'famille'),

    // Jitsi domain (without protocol).
    // Example: meet.jit.si
    'jitsi_domain' => env('VISIO_JITSI_DOMAIN', 'meet.jit.si'),

    // External meeting room URL (Jitsi / Google Meet / Zoom).
    // Example: https://meet.jit.si/ma-famille
    'url' => env('VISIO_URL'),

    // Optional label displayed on the page.
    'label' => env('VISIO_LABEL', 'Rejoindre la visio'),
];
