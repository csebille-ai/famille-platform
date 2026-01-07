<?php

return [
    // Max upload size enforced by Laravel validation for video uploads.
    // Note: PHP's upload_max_filesize and post_max_size must be >= this value.
    'max_upload_kb' => (int) env('VIDEOS_MAX_UPLOAD_KB', 2097152), // 2 GB
];
