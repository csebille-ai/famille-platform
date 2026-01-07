<?php

namespace App\Http\Controllers;

use App\Models\Video;
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
    private function generatePosterForVideo(Video $video): void
    {
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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $selectedCategory = (string) $request->query('category', '');
        $categories = ['films', 'series', 'docs'];

        if (! in_array($selectedCategory, $categories, true)) {
            $selectedCategory = '';
        }

        $categoryPreviews = [];
        foreach ($categories as $cat) {
            $categoryPreviews[$cat] = Video::query()
                ->where('category', $cat)
                ->latest()
                ->take(2)
                ->get();
        }

        $videos = null;
        if ($selectedCategory !== '') {
            $videos = Video::query()
                ->where('category', $selectedCategory)
                ->latest()
                ->paginate(12)
                ->withQueryString();
        }

        return view('videos.index', [
            'category' => $selectedCategory,
            'categoryPreviews' => $categoryPreviews,
            'videos' => $videos,
        ]);
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
            $validated = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'category' => ['required', 'string', 'in:films,series,docs'],
                'video_file' => ['required', 'file', 'mimes:mp4,webm,avi,mov,mkv', 'max:3145728'],
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

        return redirect()->route('videos.index')
            ->with('status', 'Vidéo importée');
    }

    /**
     * Display the specified resource.
     */
    public function show(Video $video)
    {
        return view('videos.show', [
            'video' => $video,
        ]);
    }

    public function stream(Request $request, Video $video): \Symfony\Component\HttpFoundation\Response
    {
        if (!$video->video_path) {
            abort(404);
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
        if (!$video->poster_path) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($video->poster_path)) {
            abort(404);
        }

        $mime = $disk->mimeType($video->poster_path) ?: 'image/jpeg';
        $downloadName = 'poster-' . $video->id . '.jpg';

        return $disk->response($video->poster_path, $downloadName, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($downloadName) . '"',
        ]);
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
    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:films,series,docs'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,webm,avi,mov,mkv', 'max:3145728'],
            'description' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('video_file')) {
            if ($video->video_path && Storage::disk('public')->exists($video->video_path)) {
                Storage::disk('public')->delete($video->video_path);
            }

            if ($video->poster_path && Storage::disk('public')->exists($video->poster_path)) {
                Storage::disk('public')->delete($video->poster_path);
            }

            $path = $request->file('video_file')->store('videos', 'public');
            $validated['video_path'] = $path;
            $validated['poster_path'] = null;
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
    public function destroy(Video $video)
    {
        $userId = Auth::id();
        $isOwner = $userId !== null && (int) $video->created_by === (int) $userId;
        if (!$isOwner) {
            Gate::authorize('videos-delete');
        }

        if ($video->video_path && Storage::disk('public')->exists($video->video_path)) {
            Storage::disk('public')->delete($video->video_path);
        }

        if ($video->poster_path && Storage::disk('public')->exists($video->poster_path)) {
            Storage::disk('public')->delete($video->poster_path);
        }

        $video->delete();

        return redirect()->route('videos.index')
            ->with('status', 'Vidéo supprimée.');
    }
}
