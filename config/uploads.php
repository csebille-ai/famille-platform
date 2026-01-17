<?php

$appEnv = (string) env('APP_ENV', 'production');
$r2Bucket = trim((string) env('R2_BUCKET', ''));

// Default behavior:
// - If R2 isn't configured, automatically allow local fallback (prevents hard-blocking uploads in prod).
// - Otherwise allow in local/development/testing.
$allowLocalFallback = ($r2Bucket === '') || in_array($appEnv, ['local', 'development', 'testing'], true);

// Explicit override (string in .env, e.g. "true" / "false").
$explicit = env('UPLOADS_ALLOW_LOCAL_FALLBACK');
if ($explicit !== null) {
    $allowLocalFallback = (bool) filter_var($explicit, FILTER_VALIDATE_BOOL);
}

return [
    // Hard limits enforced both client-side and server-side.
    'max_upload_bytes' => (int) env('MAX_UPLOAD_BYTES', 2 * 1024 * 1024 * 1024),

    // Use multipart above this threshold.
    'multipart_threshold_bytes' => (int) env('MULTIPART_THRESHOLD_BYTES', 200 * 1024 * 1024),

    // Multipart part size. Keep this reasonably large to avoid too many parts.
    'multipart_part_size_bytes' => (int) env('MULTIPART_PART_SIZE_BYTES', 50 * 1024 * 1024),

    // Cloudflare R2 public URL base (custom domain or worker).
    'r2_public_base_url' => (string) env('R2_PUBLIC_BASE_URL', ''),

    // Dev-friendly fallback: if R2 is not configured, allow uploading to local disk
    // through the app (same API shape as presigned uploads).
    'allow_local_fallback' => $allowLocalFallback,

    // Simple storage quota display (bytes). Cloudflare free tier: 10GB.
    'quota_bytes' => (int) env('UPLOAD_QUOTA_BYTES', 10 * 1024 * 1024 * 1024),
];
