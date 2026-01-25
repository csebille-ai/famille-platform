<?php

namespace App\Observers;

use App\Jobs\OptimizeVideoFaststart;
use App\Models\Video;

class VideoObserver
{
    public function created(Video $video): void
    {
        // Avoid slowing down uploads on hosts without a queue worker.
        if (!config('videos.optimize_on_upload', false)) {
            return;
        }
        if ((string) config('queue.default') === 'sync') {
            return;
        }

        $diskName = (string) ($video->storage_disk ?? 'public');
        if (!in_array($diskName, ['public', 'local'], true)) {
            return;
        }
        if (!$video->video_path) {
            return;
        }
        $ext = strtolower((string) pathinfo((string) $video->video_path, PATHINFO_EXTENSION));
        if ($ext !== 'mp4') {
            return;
        }

        OptimizeVideoFaststart::dispatch((int) $video->id);
    }
}
