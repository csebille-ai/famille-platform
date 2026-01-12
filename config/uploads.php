<?php

return [
    // Hard limits enforced both client-side and server-side.
    'max_upload_bytes' => (int) env('MAX_UPLOAD_BYTES', 2 * 1024 * 1024 * 1024),

    // Use multipart above this threshold.
    'multipart_threshold_bytes' => (int) env('MULTIPART_THRESHOLD_BYTES', 200 * 1024 * 1024),

    // Multipart part size. Keep this reasonably large to avoid too many parts.
    'multipart_part_size_bytes' => (int) env('MULTIPART_PART_SIZE_BYTES', 50 * 1024 * 1024),

    // Cloudflare R2 public URL base (custom domain or worker).
    'r2_public_base_url' => (string) env('R2_PUBLIC_BASE_URL', ''),

    // Simple storage quota display (bytes). Cloudflare free tier: 10GB.
    'quota_bytes' => (int) env('UPLOAD_QUOTA_BYTES', 10 * 1024 * 1024 * 1024),
];
