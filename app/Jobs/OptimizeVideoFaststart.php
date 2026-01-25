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

    private function isFaststartMp4(string $absolutePath): ?bool
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $size = @filesize($absolutePath);
        if (!is_int($size) || $size <= 0) {
            return null;
        }

        $fh = @fopen($absolutePath, 'rb');
        if ($fh === false) {
            return null;
        }

        try {
            $offset = 0;
            $boxes = 0;
            $maxBoxes = 5000;
            $moovOffset = null;
            $mdatOffset = null;

            while ($offset + 8 <= $size && $boxes < $maxBoxes) {
                $boxes++;
                if (@fseek($fh, $offset) !== 0) {
                    break;
                }
                $hdr = @fread($fh, 8);
                if (!is_string($hdr) || strlen($hdr) !== 8) {
                    break;
                }

                $u = @unpack('Nsize/a4type', $hdr);
                if (!is_array($u) || !isset($u['size'], $u['type'])) {
                    break;
                }

                $boxSize = (int) $u['size'];
                $boxType = (string) $u['type'];
                $headerSize = 8;

                if ($boxSize === 1) {
                    $ext = @fread($fh, 8);
                    if (!is_string($ext) || strlen($ext) !== 8) {
                        break;
                    }
                    $uu = @unpack('Nhi/Nlo', $ext);
                    if (!is_array($uu) || !isset($uu['hi'], $uu['lo'])) {
                        break;
                    }
                    $boxSize = (int) ($uu['hi'] * 4294967296 + $uu['lo']);
                    $headerSize = 16;
                } elseif ($boxSize === 0) {
                    $boxSize = $size - $offset;
                }

                if ($boxSize < $headerSize) {
                    break;
                }

                if ($boxType === 'moov' && $moovOffset === null) {
                    $moovOffset = $offset;
                }
                if ($boxType === 'mdat' && $mdatOffset === null) {
                    $mdatOffset = $offset;
                }

                if ($moovOffset !== null && $mdatOffset !== null) {
                    return $moovOffset < $mdatOffset;
                }

                $offset += $boxSize;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            @fclose($fh);
        }
    }

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

        $faststart = $this->isFaststartMp4($input);
        if ($faststart === true) {
            logger()->info('videos.faststart.skip', ['video_id' => $video->id, 'reason' => 'already_faststart']);
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
