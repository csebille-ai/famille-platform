<?php

namespace App\Console\Commands;

use App\Jobs\OptimizeVideoFaststart;
use App\Models\Video;
use Illuminate\Console\Command;

class VideosFaststartCommand extends Command
{
    protected $signature = 'videos:faststart {videoId? : ID de la vidéo} {--all : Traiter toutes les vidéos MP4 (public/local)} {--dispatch : Met en queue au lieu de traiter en ligne}';

    protected $description = 'Optimise les MP4 (faststart) pour démarrage instantané (iOS/PWA).';

    public function handle(): int
    {
        $dispatch = (bool) $this->option('dispatch');

        if ($this->option('all')) {
            $query = Video::query()
                ->whereNotNull('video_path')
                ->whereIn('storage_disk', ['public', 'local'])
                ->orWhere(function ($q) {
                    // Backward compat: null disk defaults to public.
                    $q->whereNull('storage_disk')->whereNotNull('video_path');
                });

            $ids = $query->pluck('id')->all();
            $count = 0;
            foreach ($ids as $id) {
                $video = Video::query()->find($id);
                if (!$video || !$video->video_path) {
                    continue;
                }
                $ext = strtolower((string) pathinfo((string) $video->video_path, PATHINFO_EXTENSION));
                if ($ext !== 'mp4') {
                    continue;
                }

                $count++;
                if ($dispatch) {
                    OptimizeVideoFaststart::dispatch((int) $video->id);
                    $this->line("Queued video #{$video->id}");
                } else {
                    OptimizeVideoFaststart::dispatchSync((int) $video->id);
                    $this->line("Optimized video #{$video->id}");
                }
            }

            $this->info("Done. {$count} MP4 video(s) processed.");
            return Command::SUCCESS;
        }

        $id = $this->argument('videoId');
        if (!$id) {
            $this->error('Provide {videoId} or use --all');
            return Command::INVALID;
        }

        $video = Video::query()->find((int) $id);
        if (!$video) {
            $this->error('Video not found.');
            return Command::FAILURE;
        }

        if ($dispatch) {
            OptimizeVideoFaststart::dispatch((int) $video->id);
            $this->info("Queued video #{$video->id}");
            return Command::SUCCESS;
        }

        OptimizeVideoFaststart::dispatchSync((int) $video->id);
        $this->info("Optimized video #{$video->id}");
        return Command::SUCCESS;
    }
}
