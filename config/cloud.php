<?php

return [
    // Max upload size enforced by Laravel validation.
    // Note: PHP's upload_max_filesize and post_max_size must be >= this value.
    'max_upload_kb' => (int) env('CLOUD_MAX_UPLOAD_KB', 51200), // 50 MB

    // Global quota for all stored Cloud files.
    // 0 disables quota enforcement.
    'quota_global_gb' => (float) env('CLOUD_QUOTA_GLOBAL_GB', 10),
];
