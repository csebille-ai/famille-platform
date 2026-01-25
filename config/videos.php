<?php

return [
    // Max upload size enforced by Laravel validation for video uploads.
    // Note: PHP's upload_max_filesize and post_max_size must be >= this value.
    'max_upload_kb' => (int) env('VIDEOS_MAX_UPLOAD_KB', 2097152), // 2 GB

    // When true and a real queue worker is configured, MP4 files stored on public/local disks
    // are re-muxed with `-movflags +faststart` so they start instantly on mobile/iOS.
    'optimize_on_upload' => (bool) env('VIDEOS_OPTIMIZE_ON_UPLOAD', false),
];
