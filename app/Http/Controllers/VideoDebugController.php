<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\Uploads\R2UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VideoDebugController extends Controller
{
    public function __invoke(Request $request, Video $video, R2UploadService $r2)
    {
        Gate::authorize('videos-delete');

        $diskName = (string) ($video->storage_disk ?? 'public');
        $path = $video->video_path ? (string) $video->video_path : '';

        $publicUrl = null;
        $exists = false;
        $size = null;
        $mime = null;
        $moov = [
            'found_in_first_bytes' => false,
            'found_in_last_bytes' => false,
            'notes' => null,
        ];

        if ($path !== '' && in_array($diskName, ['public', 'local'], true)) {
            $disk = Storage::disk($diskName);
            $exists = $disk->exists($path);
            $mime = $exists ? ($disk->mimeType($path) ?: null) : null;

            try {
                $abs = $disk->path($path);
                if (is_file($abs)) {
                    $s = @filesize($abs);
                    if (is_int($s) && $s > 0) $size = $s;

                    // Heuristic: faststart MP4 has the 'moov' atom near the beginning.
                    if (strtolower((string) pathinfo($abs, PATHINFO_EXTENSION)) === 'mp4') {
                        $first = @file_get_contents($abs, false, null, 0, 1024 * 1024 * 2);
                        if (is_string($first) && $first !== '') {
                            $moov['found_in_first_bytes'] = (strpos($first, 'moov') !== false);
                        }

                        if ($size !== null && $size > 0) {
                            $tailOffset = max(0, $size - (1024 * 1024 * 2));
                            $last = @file_get_contents($abs, false, null, $tailOffset, 1024 * 1024 * 2);
                            if (is_string($last) && $last !== '') {
                                $moov['found_in_last_bytes'] = (strpos($last, 'moov') !== false);
                            }
                        }

                        if (!$moov['found_in_first_bytes'] && $moov['found_in_last_bytes']) {
                            $moov['notes'] = 'Likely NOT faststart (moov near end).';
                        } elseif ($moov['found_in_first_bytes']) {
                            $moov['notes'] = 'Looks like faststart (moov near start).';
                        }
                    }
                }
            } catch (\Throwable $e) {
                // best-effort
            }

            if ($diskName === 'public' && $path !== '') {
                $publicUrl = $disk->url($path);
            }
        }

        if ($publicUrl === null && $diskName === 'r2') {
            $u = trim((string) $r2->publicUrlForKey($path));
            $publicUrl = $u !== '' ? $u : null;
        }

        $tests = [
            'head' => null,
            'range_0_1' => null,
        ];

        // External HTTP tests (server -> server). These help confirm Range + MIME in production.
        if ($publicUrl) {
            try {
                $t0 = microtime(true);
                $res = Http::timeout(10)->withHeaders(['Accept' => '*/*'])->head($publicUrl);
                $tests['head'] = [
                    'ok' => $res->successful(),
                    'status' => $res->status(),
                    'ms' => (int) round((microtime(true) - $t0) * 1000),
                    'content_type' => $res->header('Content-Type'),
                    'content_length' => $res->header('Content-Length'),
                    'accept_ranges' => $res->header('Accept-Ranges'),
                    'cache_control' => $res->header('Cache-Control'),
                ];
            } catch (\Throwable $e) {
                $tests['head'] = ['ok' => false, 'error' => $e->getMessage()];
            }

            try {
                $t0 = microtime(true);
                $res = Http::timeout(10)->withHeaders(['Range' => 'bytes=0-1'])->get($publicUrl);
                $tests['range_0_1'] = [
                    'ok' => $res->successful(),
                    'status' => $res->status(),
                    'ms' => (int) round((microtime(true) - $t0) * 1000),
                    'content_type' => $res->header('Content-Type'),
                    'content_length' => $res->header('Content-Length'),
                    'content_range' => $res->header('Content-Range'),
                    'accept_ranges' => $res->header('Accept-Ranges'),
                ];
            } catch (\Throwable $e) {
                $tests['range_0_1'] = ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'video_id' => (int) $video->id,
            'storage_disk' => $diskName,
            'video_path' => $path,
            'exists' => $exists,
            'size_bytes' => $size,
            'mime' => $mime,
            'public_url' => $publicUrl,
            'viewer_url' => route('videos.show', $video),
            'stream_url' => route('videos.stream', $video),
            'moov_heuristic' => $moov,
            'http_tests' => $tests,
        ]);
    }
}
