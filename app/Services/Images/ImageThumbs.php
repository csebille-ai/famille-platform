<?php

namespace App\Services\Images;

use App\Models\CloudNode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImageThumbs
{
    public function cacheRelPath(CloudNode $node, int $width): string
    {
        $width = max(64, min(960, $width));
        return 'thumbs/images/' . (int) $node->id . '/w' . $width . '.jpg';
    }

    public function cachedAbsolutePathIfExists(CloudNode $node, int $width): ?string
    {
        $cacheRel = $this->cacheRelPath($node, $width);
        try {
            if (!Storage::disk('local')->exists($cacheRel)) {
                return null;
            }
            $abs = Storage::disk('local')->path($cacheRel);
            return (is_string($abs) && $abs !== '' && is_file($abs)) ? $abs : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Returns JPEG bytes (and caches them) or null on failure.
     */
    public function getOrCreateJpeg(CloudNode $node, int $width): ?string
    {
        if (!$node->isFile() || $node->stored_path === null) {
            return null;
        }

        $mime = (string) ($node->mime ?? '');
        if (!str_starts_with($mime, 'image/')) {
            return null;
        }

        $width = (int) $width;
        if ($width <= 0) $width = 480;
        $width = max(64, min(960, $width));

        $cacheRel = $this->cacheRelPath($node, $width);

        // Already cached
        try {
            if (Storage::disk('local')->exists($cacheRel)) {
                $abs = Storage::disk('local')->path($cacheRel);
                if (is_string($abs) && $abs !== '' && is_file($abs)) {
                    $jpg = @file_get_contents($abs);
                    if (is_string($jpg) && $jpg !== '') {
                        return $jpg;
                    }
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        $diskName = (string) ($node->storage_disk ?? 'local');

        $sourceAbs = null;
        $tmpSource = null;
        if ($diskName === 'local') {
            try {
                if (!Storage::disk('local')->exists($node->stored_path)) {
                    return null;
                }
                $sourceAbs = Storage::disk('local')->path($node->stored_path);
            } catch (\Throwable $e) {
                $sourceAbs = null;
            }
        } else {
            $url = trim((string) ($node->public_url ?? ''));
            if ($url === '') {
                try {
                    $url = Storage::disk($diskName)->url($node->stored_path);
                } catch (\Throwable $e) {
                    $url = '';
                }
            }

            if ($url === '') {
                return null;
            }

            try {
                $tmpSource = @tempnam(sys_get_temp_dir(), 'famille_thumb_');
                if (is_string($tmpSource) && $tmpSource !== '') {
                    $resp = Http::timeout(15)
                        ->withOptions(['sink' => $tmpSource])
                        ->get($url);

                    $ct = '';
                    try {
                        $ct = strtolower((string) $resp->header('Content-Type', ''));
                    } catch (\Throwable $e) {
                        $ct = '';
                    }

                    $ok = $resp->successful() && ($ct === '' || str_starts_with($ct, 'image/'));
                    if ($ok) {
                        $sourceAbs = $tmpSource;
                    } else {
                        @unlink($tmpSource);
                        $tmpSource = null;
                        $sourceAbs = null;
                    }
                }
            } catch (\Throwable $e) {
                $sourceAbs = null;
            }
        }

        if (!is_string($sourceAbs) || $sourceAbs === '' || !is_file($sourceAbs)) {
            if (is_string($tmpSource) && $tmpSource !== '') {
                @unlink($tmpSource);
            }
            return null;
        }

        if (!function_exists('imagecreatefromstring')) {
            if (is_string($tmpSource) && $tmpSource !== '') {
                @unlink($tmpSource);
            }
            return null;
        }

        $bytes = null;
        try {
            $bytes = @file_get_contents($sourceAbs);
        } catch (\Throwable $e) {
            $bytes = null;
        }

        if (!is_string($bytes) || $bytes === '') {
            if (is_string($tmpSource) && $tmpSource !== '') {
                @unlink($tmpSource);
            }
            return null;
        }

        $srcImg = @imagecreatefromstring($bytes);
        if (!$srcImg) {
            if (is_string($tmpSource) && $tmpSource !== '') {
                @unlink($tmpSource);
            }
            return null;
        }

        $srcW = (int) @imagesx($srcImg);
        $srcH = (int) @imagesy($srcImg);
        if ($srcW <= 0 || $srcH <= 0) {
            @imagedestroy($srcImg);
            if (is_string($tmpSource) && $tmpSource !== '') {
                @unlink($tmpSource);
            }
            return null;
        }

        $targetW = min($width, $srcW);
        $targetH = max(1, (int) round($srcH * ($targetW / $srcW)));

        $dstImg = @imagecreatetruecolor($targetW, $targetH);
        if (!$dstImg) {
            @imagedestroy($srcImg);
            if (is_string($tmpSource) && $tmpSource !== '') {
                @unlink($tmpSource);
            }
            return null;
        }

        @imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        $jpg = null;
        try {
            ob_start();
            @imagejpeg($dstImg, null, 72);
            $jpg = (string) ob_get_clean();
        } catch (\Throwable $e) {
            try { ob_end_clean(); } catch (\Throwable $e2) {}
            $jpg = null;
        }

        @imagedestroy($dstImg);
        @imagedestroy($srcImg);
        if (is_string($tmpSource) && $tmpSource !== '') {
            @unlink($tmpSource);
        }

        if (!is_string($jpg) || $jpg === '') {
            return null;
        }

        try {
            Storage::disk('local')->put($cacheRel, $jpg);
        } catch (\Throwable $e) {
            // best-effort
        }

        return $jpg;
    }

    /**
     * Best-effort pre-generation.
     */
    public function warmUp(CloudNode $node, array $widths = [480]): void
    {
        $widths = array_values(array_unique(array_map(fn ($v) => (int) $v, $widths)));
        foreach ($widths as $w) {
            if ($w <= 0) continue;
            $this->getOrCreateJpeg($node, $w);
        }
    }
}
