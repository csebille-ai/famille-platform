<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AstroCardImageController
{
    public function __invoke(Request $request)
    {
        return $this->show($request);
    }

    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $this->streamUserCard($user);
    }

    public function showForUser(Request $request, User $user)
    {
        abort_unless($request->user() !== null, 401);
        abort_unless($request->user()?->can('manage-users') === true, 403);

        return $this->streamUserCard($user);
    }

    public function showForUserPublic(Request $request, User $user)
    {
        // Auth is enforced by the route group; this is a double-safety.
        abort_unless($request->user() !== null, 401);

        return $this->streamUserCard($user);
    }

    private function streamUserCard(User $user)
    {
        $url = trim((string) ($user->astro_card_image_url ?? ''));
        abort_if($url === '', 404);

        $key = $this->extractR2KeyFromUrl($url);
        abort_if($key === null || $key === '', 404);

        $disk = Storage::disk('r2');

        // Best-effort conditional caching based on object metadata.
        $etag = null;
        $lastModified = null;
        try {
            $mtime = $disk->lastModified($key);
            $size = $disk->size($key);
            if (is_int($mtime) && $mtime > 0) {
                $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
            }
            if (is_int($mtime) && $mtime > 0 && is_int($size) && $size >= 0) {
                $etag = '"' . sha1($key . '|' . (string) $mtime . '|' . (string) $size) . '"';
            }
        } catch (\Throwable) {
            // best-effort
        }

        $req = request();
        if ($etag !== null) {
            $ifNoneMatch = (string) $req->headers->get('If-None-Match', '');
            if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
                return response('', 304, array_filter([
                    'ETag' => $etag,
                    'Last-Modified' => $lastModified,
                    'Cache-Control' => 'private, max-age=604800, stale-while-revalidate=86400',
                ]));
            }
        }
        if ($lastModified !== null) {
            $ifModifiedSince = (string) $req->headers->get('If-Modified-Since', '');
            if ($ifModifiedSince !== '' && trim($ifModifiedSince) === $lastModified) {
                return response('', 304, array_filter([
                    'ETag' => $etag,
                    'Last-Modified' => $lastModified,
                    'Cache-Control' => 'private, max-age=604800, stale-while-revalidate=86400',
                ]));
            }
        }

        try {
            $stream = $disk->readStream($key);
        } catch (\Throwable) {
            $stream = false;
        }

        abort_if($stream === false, 404);

        $mime = null;
        try {
            $mime = $disk->mimeType($key);
        } catch (\Throwable) {
            $mime = null;
        }

        if (!is_string($mime) || trim($mime) === '') {
            $ext = strtolower((string) pathinfo($key, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                default => 'image/png',
            };
        }

        return response()->stream(function () use ($stream) {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=604800, stale-while-revalidate=86400',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ]);
    }

    private function extractR2KeyFromUrl(string $url): ?string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = ltrim($path, '/');
        if ($path === '') {
            return null;
        }

        // Some public base URLs may include the bucket name as the first path segment.
        $bucket = trim((string) config('filesystems.disks.r2.bucket'));
        if ($bucket !== '') {
            $prefix = $bucket . '/';
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return $path !== '' ? $path : null;
    }
}
