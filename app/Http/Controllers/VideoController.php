<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\UploadAsset;
use App\Services\Uploads\R2UploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    private function maxVideoUploadKb(): int
    {
        return max(1, (int) config('videos.max_upload_kb', 2097152));
    }

    private function generatePosterForVideo(Video $video): void
    {
        $diskName = (string) ($video->storage_disk ?? 'public');
        if ($diskName !== 'public') {
            return;
        }

        if ($video->poster_path) {
            return;
        }

        if (!$video->video_path) {
            return;
        }

        $disk = Storage::disk('public');
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
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);
            return;
        }

        $video->forceFill(['poster_path' => $posterRelativePath])->save();
    }

    private function probeDurationSeconds(string $absolutePath): ?int
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $absolutePath,
        ]);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$process->isSuccessful()) {
            return null;
        }

        $out = trim((string) $process->getOutput());
        if ($out === '') {
            return null;
        }

        $secondsFloat = (float) str_replace(',', '.', $out);
        $seconds = (int) round($secondsFloat);
        if ($seconds <= 0) {
            return null;
        }

        return $seconds;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $tab = strtolower(trim((string) $request->query('tab', '')));

        // Backward-compat: allow old ?category=series/films to drive the current UI.
        $legacyCategory = strtolower(trim((string) $request->query('category', '')));
        if ($tab === '' && in_array($legacyCategory, ['films', 'series'], true)) {
            $tab = $legacyCategory;
        }
        if (!in_array($tab, ['films', 'series'], true)) {
            $tab = 'films';
        }

        $encodeCursor = function ($createdAt, int $id): ?string {
            if (!$createdAt) {
                return null;
            }

            $payload = [
                't' => $createdAt->getTimestamp(),
                'id' => $id,
            ];

            return rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        };

        $hasDuration = \Illuminate\Support\Facades\Schema::hasTable('videos')
            && \Illuminate\Support\Facades\Schema::hasColumn('videos', 'duration_seconds');

        $buildItems = function (string $category) use ($hasDuration, $encodeCursor): array {
            if (!\Illuminate\Support\Facades\Schema::hasTable('videos')) {
                return [[], null];
            }

            $limit = 24;
            $rows = Video::query()
                ->with('creator:id,name')
                ->where('category', $category)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $items = $rows->map(fn (Video $v) => [
                'id' => (int) $v->id,
                'type' => 'video',
                'title' => (string) ($v->title ?? ''),
                'by' => (string) ($v->creator?->name ?? 'Quelqu’un'),
                'at' => $v->created_at?->toIso8601String(),
                'at_human' => $v->created_at?->diffForHumans(),
                'poster_url' => $v->video_path ? route('videos.poster', $v) : null,
                'duration_seconds' => $hasDuration ? (int) ($v->duration_seconds ?? 0) : null,
                'open_url' => route('videos.show', $v),
            ])->values()->all();

            $nextCursor = null;
            if ($rows->count() === $limit) {
                $last = $rows->last();
                if ($last) {
                    $nextCursor = $encodeCursor($last->created_at, (int) $last->id);
                }
            }

            return [$items, $nextCursor];
        };

        [$filmsItems, $filmsNextCursor] = $buildItems('films');
        [$seriesItems, $seriesNextCursor] = $buildItems('series');

        $response = response()->view('videos.index', [
            'tab' => $tab,
            'filmsItems' => $filmsItems,
            'seriesItems' => $seriesItems,
            'filmsNextCursor' => $filmsNextCursor,
            'seriesNextCursor' => $seriesNextCursor,
            'pageSize' => 24,
        ]);

        // Some deployments (LiteSpeed / reverse proxies) may cache full HTML pages.
        // This view is highly dynamic; force bypass of any page cache.
        return $response
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-LiteSpeed-Cache-Control', 'no-cache');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('videos.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $t0 = microtime(true);
        logger()->info('videos.upload.start', [
            'user_id' => Auth::id(),
            'content_length' => $request->server('CONTENT_LENGTH'),
            'content_type' => $request->header('Content-Type'),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $maxKb = $this->maxVideoUploadKb();
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'category' => ['required', 'string', 'in:films,series,docs'],
                'video_file' => ['required', 'file', 'mimes:mp4,webm,avi,mov,mkv', 'max:' . $maxKb],
                'poster_file' => ['nullable', 'image', 'max:5120'],
                'description' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            $file = $request->file('video_file');
            $phpFile = $_FILES['video_file'] ?? null;

            logger()->warning('videos.upload.validation_failed', [
                'ms' => (int) round((microtime(true) - $t0) * 1000),
                'user_id' => Auth::id(),
                'has_file' => $request->hasFile('video_file'),
                'file_error' => is_array($phpFile) ? ($phpFile['error'] ?? null) : null,
                'file_size' => $file?->getSize(),
                'file_mime' => $file?->getClientMimeType(),
                'errors' => $e->errors(),
            ]);

            throw $e;
        }

        logger()->info('videos.upload.validated', [
            'ms' => (int) round((microtime(true) - $t0) * 1000),
            'has_file' => $request->hasFile('video_file'),
        ]);

        $validated['created_by'] = Auth::id();

        if ($request->hasFile('video_file')) {
            try {
                $file = $request->file('video_file');
                logger()->info('videos.upload.storing', [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ]);

                $path = $request->file('video_file')->store('videos', 'public');
                $validated['video_path'] = $path;

                if (\Illuminate\Support\Facades\Schema::hasColumn('videos', 'duration_seconds')) {
                    try {
                        $abs = Storage::disk('public')->path($path);
                        $dur = $this->probeDurationSeconds($abs);
                        if ($dur !== null) {
                            $validated['duration_seconds'] = $dur;
                        }
                    } catch (\Throwable $e) {
                        // best-effort
                    }
                }

                logger()->info('videos.upload.stored', [
                    'ms' => (int) round((microtime(true) - $t0) * 1000),
                    'path' => $path,
                ]);
            } catch (\Exception $e) {
                logger()->error('videos.upload.store_failed', [
                    'ms' => (int) round((microtime(true) - $t0) * 1000),
                    'error' => $e->getMessage(),
                ]);

                $message = 'Erreur lors du stockage du fichier: ' . $e->getMessage();
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 500);
                }

                return back()->withErrors(['video_file' => $message]);
            }
        }

        unset($validated['video_file']);
        unset($validated['poster_file']);
        $video = Video::create($validated);

        if ($request->hasFile('poster_file')) {
            try {
                $poster = $request->file('poster_file');
                $ext = $poster->guessExtension() ?: 'jpg';
                $posterRelativePath = 'videos/posters/' . $video->id . '.' . $ext;
                Storage::disk('public')->putFileAs('videos/posters', $poster, $video->id . '.' . $ext);
                $video->forceFill(['poster_path' => $posterRelativePath])->save();
            } catch (\Throwable $e) {
                logger()->warning('videos.poster.upload_failed', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $video->poster_path) {
            $this->generatePosterForVideo($video);
        }

        logger()->info('videos.upload.created', [
            'ms' => (int) round((microtime(true) - $t0) * 1000),
            'video_id' => $video->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Vidéo importée',
                'video_id' => $video->id,
            ]);
        }

        $returnPath = $this->safeReturnPath($request->input('return') ?: $request->query('return'));
        if ($returnPath !== null) {
            return redirect($returnPath)->with('status', 'Vidéo importée');
        }

        return redirect()->route('videos.index')
            ->with('status', 'Vidéo importée');
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
        if (!$video->video_path) {
            abort(404);
        }

        $diskName = (string) ($video->storage_disk ?? 'public');
        if ($diskName !== 'public') {
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

        $disk = Storage::disk('public');
        if (!$disk->exists($video->video_path)) {
            abort(404);
        }

        $absolutePath = $disk->path($video->video_path);
        $mime = $disk->mimeType($video->video_path) ?: 'application/octet-stream';
        $downloadName = basename($video->video_path);

        $size = @filesize($absolutePath);
        if (!is_int($size) || $size <= 0) {
            return response()->file($absolutePath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($downloadName) . '"',
                'Accept-Ranges' => 'bytes',
            ]);
        }

        $range = (string) $request->header('Range', '');
        if ($range === '' || !preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m)) {
            return response()->file($absolutePath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($downloadName) . '"',
                'Accept-Ranges' => 'bytes',
                'Content-Length' => (string) $size,
            ]);
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
        $response->headers->set('Content-Disposition', 'inline; filename="' . addslashes($downloadName) . '"');
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Content-Length', (string) $length);
        $response->headers->set('Content-Range', "bytes {$start}-{$end}/{$size}");

        return $response;
    }

    public function poster(Video $video): \Symfony\Component\HttpFoundation\Response
    {
            if ((string) ($video->storage_disk ?? 'public') === 'public' && !$video->poster_path) {
                // Best-effort lazy generation for older uploads or servers where
                // synchronous generation may fail intermittently.
                $this->generatePosterForVideo($video);
                $video->refresh();
            }

        $disk = Storage::disk('public');
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

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Video $video)
    {
        return view('videos.edit', [
            'video' => $video,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Video $video, R2UploadService $r2)
    {
        $maxKb = $this->maxVideoUploadKb();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:films,series,docs'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm,avi,mov,mkv', 'max:' . $maxKb],
            'description' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('video_file')) {
            $oldDisk = (string) ($video->storage_disk ?? 'public');
            if ($video->video_path) {
                try {
                    if ($oldDisk === 'r2') {
                        $r2->deleteObject($video->video_path);
                    } else {
                        Storage::disk($oldDisk)->delete($video->video_path);
                    }
                } catch (\Throwable $e) {
                    // Best-effort.
                }
            }

            if ($video->poster_path && Storage::disk('public')->exists($video->poster_path)) {
                Storage::disk('public')->delete($video->poster_path);
            }

            $path = $request->file('video_file')->store('videos', 'public');
            $validated['video_path'] = $path;
            $validated['poster_path'] = null;
            $validated['storage_disk'] = 'public';
        }

        unset($validated['video_file']);

        $video->update($validated);

        if ($request->hasFile('video_file') && ! $video->poster_path) {
            $this->generatePosterForVideo($video);
        }

        return redirect()->route('videos.show', $video)
            ->with('status', 'Vidéo mise à jour avec succès.');
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

        return redirect($returnPath)->with('status', 'Vidéo supprimée.');
    }
}
