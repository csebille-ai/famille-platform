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

    private function streamUserCard(User $user)
    {
        $url = trim((string) ($user->astro_card_image_url ?? ''));
        abort_if($url === '', 404);

        $key = $this->extractR2KeyFromUrl($url);
        abort_if($key === null || $key === '', 404);

        $disk = Storage::disk('r2');

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
            'Cache-Control' => 'private, max-age=3600',
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
