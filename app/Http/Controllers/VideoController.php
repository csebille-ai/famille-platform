<?php

namespace App\Http\Controllers;

use App\Models\UploadAsset;
use App\Models\Video;
use App\Services\Uploads\R2UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class VideoController extends Controller
{
    private function safeReturnPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        // Only allow same-site relative paths to avoid open redirects.
        if (str_contains($path, '://') || str_starts_with($path, '//')) {
            return null;
        }
        if (!str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }

    private function generatePosterForVideo(Video $video): void
    {
        $diskName = (string) ($video->storage_disk ?? 'public');
        if (!in_array($diskName, ['public', 'local'], true)) {
            return;
        }

        if ($video->poster_path) {
            return;
        }

        if (!$video->video_path) {
            return;
        }

        $disk = Storage::disk($diskName);
        if (!$disk->exists($video->video_path)) {
            return;
        }

        $videoAbsolutePath = $disk->path($video->video_path);

        $posterRelativePath = 'videos/posters/' . $video->id . '.jpg';
        $posterAbsolutePath = $disk->path($posterRelativePath);

        $posterDir = dirname($posterAbsolutePath);
        if (!is_dir($posterDir)) {
            @mkdir($posterDir, 0775, true);
        }

        // Capture an early frame (1s) to avoid black first frames.
        $process = new Process([
            'ffmpeg',
            '-y',
            '-hide_banner',
            '-loglevel', 'error',
            '-ss', '00:00:01.000',
            '-i', $videoAbsolutePath,
            '-vframes', '1',
            '-q:v', '2',
            $posterAbsolutePath,
        ]);

        // Avoid hanging on huge files; poster generation should be fast.
        $process->setTimeout(60);

        try {
            $process->run();
        } catch (\Throwable $e) {
            logger()->warning('videos.poster.failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if (!$process->isSuccessful() || !is_file($posterAbsolutePath)) {
            logger()->warning('videos.poster.failed', [
                'video_id' => $video->id,
            ]);
            return;
        }

        $video->forceFill(['poster_path' => $posterRelativePath])->save();
    }

    /**
     * Display the specified resource.
     */
    public function show(Video $video)
    {
        $video->loadMissing('creator:id,name');

        return view('videos.show', [
            'video' => $video,
        ]);
    }

    public function stream(Request $request, Video $video, \App\Services\Uploads\R2UploadService $r2): \Symfony\Component\HttpFoundation\Response
    {
        // IMPORTANT: Streaming responses can keep the PHP session lock open for a long time.
        // Some browsers (notably iOS Safari/PWA) issue multiple parallel Range requests.
        // If the session is locked, those requests serialize and playback can take minutes
        // or never start.
        try {
            if (function_exists('session_write_close')) {
                @session_write_close();
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        if (!$video->video_path) {
            abort(404);
        }

        $isDownload = $request->boolean('download');
        $dispositionType = $isDownload ? 'attachment' : 'inline';

        $diskName = (string) ($video->storage_disk ?? 'public');
        if (!in_array($diskName, ['public', 'local'], true)) {
            $url = '';
            if ($diskName === 'r2') {
                $url = trim((string) $r2->publicUrlForKey((string) $video->video_path));
            }
            if ($url === '') {
                // Backward-compat for legacy rows.
                $url = trim((string) ($video->url ?? ''));
            }
            if ($url === '') {
                abort(404);
            }
            return redirect()->away($url);
        }

        $disk = Storage::disk($diskName);
        if (!$disk->exists($video->video_path)) {
            abort(404);
        }

        // When stored on the public disk and viewed inline, prefer a direct URL so the web server
        // (Apache/Nginx) can handle buffering efficiently.
        // IMPORTANT: do NOT redirect if the client sends a Range header, otherwise some browsers
        // (notably iOS) can lose the byte-range request and end up downloading the whole file
        // before playback starts.
        if ($diskName === 'public' && !$request->boolean('download') && !$request->headers->has('Range')) {
            $publicUrl = trim((string) $disk->url($video->video_path));
            if ($publicUrl !== '') {
                return redirect()->to($publicUrl);
            }
        }

        $absolutePath = $disk->path($video->video_path);
        $mime = $disk->mimeType($video->video_path) ?: 'application/octet-stream';
        if ($mime === 'application/octet-stream') {
            $ext = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
            if ($ext === 'mp4') {
                $mime = 'video/mp4';
            }
        }
        $downloadName = basename($video->video_path);

        $size = @filesize($absolutePath);
        if (!is_int($size) || $size <= 0) {
            $headers = [
                'Content-Type' => $mime,
                'Accept-Ranges' => 'bytes',
            ];
            if ($isDownload) {
                $headers['Content-Disposition'] = $dispositionType . '; filename="' . addslashes($downloadName) . '"';
            }
            return response()->file($absolutePath, $headers);
        }

        $range = (string) $request->header('Range', '');
        if ($range === '') {
            $headers = [
                'Content-Type' => $mime,
                'Accept-Ranges' => 'bytes',
                'Content-Length' => (string) $size,
            ];
            if ($isDownload) {
                $headers['Content-Disposition'] = $dispositionType . '; filename="' . addslashes($downloadName) . '"';
            }
            return response()->file($absolutePath, $headers);
        }

        // Some clients send multiple ranges: "bytes=0-1, 200-300".
        // We don't implement multipart/byteranges; serving the first range is enough for playback.
        $rangeOne = trim(explode(',', $range, 2)[0]);
        if (!preg_match('/^bytes\s*=\s*(\d*)-(\d*)\s*$/', $rangeOne, $m)) {
            $headers = [
                'Content-Type' => $mime,
                'Accept-Ranges' => 'bytes',
                'Content-Length' => (string) $size,
            ];
            if ($isDownload) {
                $headers['Content-Disposition'] = $dispositionType . '; filename="' . addslashes($downloadName) . '"';
            }
            return response()->file($absolutePath, $headers);
        }

        $startRaw = $m[1];
        $endRaw = $m[2];

        // Handle suffix-byte-range-spec: "bytes=-500" means last 500 bytes.
        if ($startRaw === '' && $endRaw !== '') {
            $suffixLength = (int) $endRaw;
            $suffixLength = max(0, $suffixLength);
            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int) ($startRaw === '' ? 0 : $startRaw);
            $end = (int) ($endRaw === '' ? ($size - 1) : $endRaw);
            $start = max(0, $start);
            $end = min($size - 1, $end);
        }

        if ($start > $end || $start >= $size) {
            return response('', 416, [
                'Content-Range' => 'bytes */' . $size,
                'Accept-Ranges' => 'bytes',
            ]);
        }

        $length = ($end - $start) + 1;

        $response = new StreamedResponse(function () use ($absolutePath, $start, $length) {
            $handle = fopen($absolutePath, 'rb');
            if ($handle === false) {
                return;
            }

            try {
                fseek($handle, $start);
                $remaining = $length;
                $chunkSize = 1024 * 1024; // 1MB

                while ($remaining > 0 && !feof($handle)) {
                    $read = ($remaining > $chunkSize) ? $chunkSize : $remaining;
                    $buffer = fread($handle, $read);
                    if ($buffer === false || $buffer === '') {
                        break;
                    }
                    echo $buffer;
                    $remaining -= strlen($buffer);
                    if (function_exists('flush')) {
                        flush();
                    }
                }
            } finally {
                fclose($handle);
            }
        }, 206);

        $response->headers->set('Content-Type', $mime);
        if ($isDownload) {
            $response->headers->set('Content-Disposition', $dispositionType . '; filename="' . addslashes($downloadName) . '"');
        }
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Content-Length', (string) $length);
        $response->headers->set('Content-Range', "bytes {$start}-{$end}/{$size}");

        return $response;
    }

    public function poster(Video $video): \Symfony\Component\HttpFoundation\Response
    {
        $diskName = (string) ($video->storage_disk ?? 'public');
        if (!in_array($diskName, ['public', 'local'], true)) {
            $diskName = 'public';
        }

        if (in_array($diskName, ['public', 'local'], true) && !$video->poster_path) {
            // Best-effort lazy generation for older uploads or servers where
            // synchronous generation may fail intermittently.
            $this->generatePosterForVideo($video);
            $video->refresh();
        }

        $disk = Storage::disk($diskName);
        if (!$video->poster_path || !$disk->exists($video->poster_path)) {
                        $title = trim((string) ($video->title ?? 'Vidéo'));
                        $label = htmlspecialchars($title !== '' ? $title : 'Vidéo', ENT_QUOTES, 'UTF-8');
                        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
    <defs>
        <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#0f172a"/>
            <stop offset="1" stop-color="#111827"/>
        </linearGradient>
    </defs>
    <rect width="1280" height="720" fill="url(#g)"/>
    <g opacity="0.35">
        <rect x="120" y="140" width="1040" height="440" rx="28" fill="#ffffff"/>
    </g>
    <g>
        <circle cx="640" cy="360" r="78" fill="rgba(0,0,0,0.35)"/>
        <path d="M618 318v84l72-42z" fill="#ffffff"/>
    </g>
    <rect x="0" y="560" width="1280" height="160" fill="rgba(0,0,0,0.35)"/>
    <text x="80" y="650" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto" font-size="44" font-weight="700" fill="#ffffff">{$label}</text>
</svg>
SVG;

                        // Do not cache the fallback, so when a poster becomes available
                        // the UI can pick it up immediately.
                        return response($svg, 200, [
                            'Content-Type' => 'image/svg+xml',
                            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                            'Pragma' => 'no-cache',
                            'Expires' => '0',
                        ]);
        }

        $mime = $disk->mimeType($video->poster_path) ?: 'image/jpeg';
        $downloadName = 'poster-' . $video->id . '.jpg';

        // Strong-ish caching for posters (they rarely change). Use conditional requests (ETag/If-Modified-Since)
        // so browsers can get 304 instead of re-downloading the image.
        $etag = null;
        $lastModified = null;
        try {
            $abs = $disk->path($video->poster_path);
            if (is_file($abs)) {
                $mtime = @filemtime($abs) ?: null;
                $size = @filesize($abs) ?: null;
                if ($mtime) {
                    $lastModified = gmdate('D, d M Y H:i:s', (int) $mtime) . ' GMT';
                }
                if ($mtime && $size !== null) {
                    $etag = '"' . sha1((string) $video->id . '|' . (string) $mtime . '|' . (string) $size) . '"';
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        $req = request();
        if ($etag !== null) {
            $ifNoneMatch = (string) $req->headers->get('If-None-Match', '');
            if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
                return response('', 304, array_filter([
                    'ETag' => $etag,
                    'Last-Modified' => $lastModified,
                    'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=86400',
                ]));
            }
        }
        if ($lastModified !== null) {
            $ifModifiedSince = (string) $req->headers->get('If-Modified-Since', '');
            if ($ifModifiedSince !== '' && trim($ifModifiedSince) === $lastModified) {
                return response('', 304, array_filter([
                    'ETag' => $etag,
                    'Last-Modified' => $lastModified,
                    'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=86400',
                ]));
            }
        }

        return $disk->response($video->poster_path, $downloadName, array_filter([
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($downloadName) . '"',
            'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=86400',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ]));
    }

    public function storePoster(Request $request, Video $video)
    {
        Gate::authorize('cloud-write');

        $validated = $request->validate([
            // We intentionally validate via extension/mime (not the "image" rule)
            // so tests and lightweight clients can upload a poster without GD/Imagick.
            'poster' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $diskName = (string) ($video->storage_disk ?? 'public');
        if (!in_array($diskName, ['public', 'local'], true)) {
            $diskName = 'public';
        }

        $file = $validated['poster'];
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg'));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $relative = 'videos/posters/' . $video->id . '.' . $ext;
        Storage::disk($diskName)->putFileAs('videos/posters', $file, $video->id . '.' . $ext);

        $video->forceFill(['poster_path' => $relative])->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'poster_url' => route('videos.poster', $video) . '?v=' . time(),
            ]);
        }

        return back()->with('status', 'Poster mis à jour');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Video $video, R2UploadService $r2)
    {
        $returnPath = $this->safeReturnPath($request->input('return'))
            ?? route('media.index', ['tab' => 'videos'], false);

        $userId = Auth::id();
        $isOwner = $userId !== null && (int) $video->created_by === (int) $userId;
        if (!$isOwner) {
            Gate::authorize('videos-delete');
        }

        $diskName = (string) ($video->storage_disk ?? 'public');
        if ($video->video_path) {
            try {
                if ($diskName === 'r2') {
                    $r2->deleteObject($video->video_path);
                } else {
                    Storage::disk($diskName)->delete($video->video_path);
                }
            } catch (\Throwable $e) {
                // Best-effort.
            }
        }

        if ($video->poster_path && Storage::disk('public')->exists($video->poster_path)) {
            Storage::disk('public')->delete($video->poster_path);
        }

        $video->delete();

        UploadAsset::query()
            ->where('video_id', $video->id)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'Vidéo supprimée.',
                'redirect' => $returnPath,
            ]);
        }

        return redirect($returnPath)->with('status', 'Vidéo supprimée.');
    }
}
