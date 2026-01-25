<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class OptimizeVideoFaststart implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $videoId)
    {
    }

    public function handle(): void
    {
        $video = Video::query()->find($this->videoId);
        if (!$video) {
            return;
        }

        $diskName = (string) ($video->storage_disk ?? 'public');
        if (!in_array($diskName, ['public', 'local'], true)) {
            return;
        }
        if (!$video->video_path) {
            return;
        }

        $path = (string) $video->video_path;
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'mp4') {
            return;
        }

        $disk = Storage::disk($diskName);
        if (!$disk->exists($path)) {
            return;
        }

        $input = $disk->path($path);
        if (!is_file($input)) {
            return;
        }

        $tmp = $input . '.faststart.tmp';

        // Re-mux only (no re-encode): moves the MOOV atom to the beginning for instant start/seek.
        $process = new Process([
            'ffmpeg',
            '-y',
            '-hide_banner',
            '-loglevel', 'error',
            '-i', $input,
            '-c', 'copy',
            '-movflags', '+faststart',
            $tmp,
        ]);

        // Large files can take time to re-mux on shared hosting.
        $process->setTimeout(60 * 15);

        try {
            $process->run();
        } catch (\Throwable $e) {
            @unlink($tmp);
            logger()->warning('videos.faststart.failed', ['video_id' => $video->id, 'error' => $e->getMessage()]);
            return;
        }

        if (!$process->isSuccessful() || !is_file($tmp) || filesize($tmp) < 1024) {
            @unlink($tmp);
            logger()->warning('videos.faststart.failed', ['video_id' => $video->id]);
            return;
        }

        // Replace the original atomically-ish.
        $backup = $input . '.bak';
        @unlink($backup);
        @rename($input, $backup);
        if (!@rename($tmp, $input)) {
            // Restore on failure.
            @rename($backup, $input);
            @unlink($tmp);
            logger()->warning('videos.faststart.replace_failed', ['video_id' => $video->id]);
            return;
        }
        @unlink($backup);
    }
}
