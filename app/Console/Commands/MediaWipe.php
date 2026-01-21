<?php

namespace App\Console\Commands;

use App\Models\CloudFile;
use App\Models\CloudNode;
use App\Models\ChatMessage;
use App\Models\UploadAsset;
use App\Models\User;
use App\Models\Person;
use App\Models\Video;
use App\Services\Uploads\R2UploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MediaWipe extends Command
{
    protected $signature = 'media:wipe
        {--force : Actually delete media (dangerous).}
        {--yes : Skip interactive confirmation when using --force.}
        {--include-cloud-files : Also wipe legacy cloud_files image/video rows (if table exists).}
        {--purge-storage : Also delete leftover local storage dirs (public/videos, public/avatars, local/images, local/private/cloud, local/chunk-uploads).}
        {--hard : Force-delete UploadAsset rows instead of soft-deleting.}
        {--limit=0 : Limit how many rows per category to process (for testing).}';

    protected $description = 'Purge ALL photo/video media (DB + storage). Dry-run unless --force.';

    private const CHAT_ATTACHMENT_PREFIX = '[[ATTACHMENT]]';

    public function handle(R2UploadService $r2): int
    {
        $apply = (bool) $this->option('force');
        $skipConfirm = (bool) $this->option('yes');
        $includeCloudFiles = (bool) $this->option('include-cloud-files');
        $purgeStorage = (bool) $this->option('purge-storage');
        $hard = (bool) $this->option('hard');
        $limit = max(0, (int) $this->option('limit'));

        $this->newLine();
        $this->warn('MEDIA WIPE');
        $this->line('Mode: ' . ($apply ? 'DELETE' : 'DRY-RUN'));
        $this->line('UploadAsset delete mode: ' . ($hard ? 'HARD (forceDelete)' : 'SOFT (delete)'));
        $this->line('Purge local storage dirs: ' . ($purgeStorage ? 'YES' : 'NO'));

        try {
            $dbName = (string) (DB::selectOne('select database() as db')->db ?? '');
        } catch (\Throwable $e) {
            $dbName = '';
        }

        $dbHost = (string) config('database.connections.mysql.host');
        $dbPort = (string) config('database.connections.mysql.port');
        $this->line('Connected DB: ' . ($dbName !== '' ? $dbName : '(unknown)') . ' @ ' . $dbHost . ':' . $dbPort);
        if ($limit > 0) {
            $this->line('Limit: ' . $limit);
        }
        $this->newLine();

        try {
            $hasCloudNodes = Schema::hasTable('cloud_nodes');
            $hasVideos = Schema::hasTable('videos');
            $hasUploadAssets = Schema::hasTable('upload_assets');
            $hasCloudFiles = $includeCloudFiles && Schema::hasTable('cloud_files');
            $hasChatMessages = Schema::hasTable('chat_messages');
            $hasUsers = Schema::hasTable('users');
            $hasPeople = Schema::hasTable('people');
        } catch (\Throwable $e) {
            $this->error('Database connection failed.');
            $this->line('Hint: check your .env DB_* settings and that the DB server is running.');
            $this->line('Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!$hasCloudNodes && !$hasVideos && !$hasUploadAssets && !$hasCloudFiles) {
            $this->error('No relevant tables found (cloud_nodes/videos/upload_assets/cloud_files). Nothing to do.');
            return self::FAILURE;
        }

        $nodeCount = 0;
        $videoCount = 0;
        $assetCount = 0;
        $cloudFileCount = 0;
        $chatAttachmentMessageCount = 0;
        $avatarUserCount = 0;
        $avatarPeopleCount = 0;
        $publicVideosFileCount = 0;
        $localImagesFileCount = 0;
        $localPrivateCloudFileCount = 0;
        $localChunkUploadsFileCount = 0;

        if ($hasCloudNodes) {
            $nodeCount = CloudNode::withTrashed()
                ->where('type', 'file')
                ->whereNotNull('stored_path')
                ->where(function ($q) {
                    $q->where('mime', 'like', 'image/%')
                        ->orWhere('mime', 'like', 'video/%');
                })
                ->count();
        }

        if ($hasVideos) {
            $videoCount = Video::query()->count();
        }

        if ($hasUploadAssets) {
            $assetCount = UploadAsset::withTrashed()
                ->whereIn('kind', ['photo', 'video'])
                ->count();
        }

        if ($hasChatMessages) {
            try {
                $chatAttachmentMessageCount = ChatMessage::query()
                    ->where('body', 'like', self::CHAT_ATTACHMENT_PREFIX . '%')
                    ->count();
            } catch (\Throwable $e) {
                $chatAttachmentMessageCount = 0;
            }
        }

        if ($hasUsers && Schema::hasColumn('users', 'avatar_path')) {
            try {
                $avatarUserCount = User::query()
                    ->whereNotNull('avatar_path')
                    ->where('avatar_path', '!=', '')
                    ->count();
            } catch (\Throwable $e) {
                $avatarUserCount = 0;
            }
        }

        if ($hasPeople && Schema::hasColumn('people', 'avatar_path')) {
            try {
                $avatarPeopleCount = Person::query()
                    ->whereNotNull('avatar_path')
                    ->where('avatar_path', '!=', '')
                    ->count();
            } catch (\Throwable $e) {
                $avatarPeopleCount = 0;
            }
        }

        // Local disk leftovers (not necessarily referenced by DB).
        try {
            $publicVideosFileCount = count(Storage::disk('public')->allFiles('videos'));
        } catch (\Throwable $e) {
            $publicVideosFileCount = 0;
        }
        try {
            $localImagesFileCount = count(Storage::disk('local')->allFiles('images'));
        } catch (\Throwable $e) {
            $localImagesFileCount = 0;
        }
        try {
            // Some code stores under local disk with a 'private/cloud/..' prefix.
            $localPrivateCloudFileCount = count(Storage::disk('local')->allFiles('private/cloud'));
        } catch (\Throwable $e) {
            $localPrivateCloudFileCount = 0;
        }
        try {
            $localChunkUploadsFileCount = count(Storage::disk('local')->allFiles('chunk-uploads'));
        } catch (\Throwable $e) {
            $localChunkUploadsFileCount = 0;
        }

        if ($hasCloudFiles) {
            $cloudFileCount = CloudFile::query()
                ->whereNotNull('stored_path')
                ->where(function ($q) {
                    $q->where('mime', 'like', 'image/%')
                        ->orWhere('mime', 'like', 'video/%');
                })
                ->count();
        }

        $this->info('Plan:');
        $this->line('- videos rows: ' . $videoCount);
        $this->line('- cloud_nodes (image/* or video/*): ' . $nodeCount);
        $this->line('- upload_assets (photo|video, incl trashed): ' . $assetCount);
        $this->line('- chat_messages attachments ([[ATTACHMENT]]…): ' . $chatAttachmentMessageCount);
        $this->line('- users avatar_path to clear: ' . $avatarUserCount);
        $this->line('- people avatar_path to clear: ' . $avatarPeopleCount);
        $this->line('- local files (public/videos/**): ' . $publicVideosFileCount);
        $this->line('- local files (local/images/**): ' . $localImagesFileCount);
        $this->line('- local files (local/private/cloud/**): ' . $localPrivateCloudFileCount);
        $this->line('- local files (local/chunk-uploads/**): ' . $localChunkUploadsFileCount);
        if ($includeCloudFiles) {
            $this->line('- cloud_files legacy (image/* or video/*): ' . $cloudFileCount);
        }
        $this->newLine();

        if ($apply) {
            if (!$skipConfirm) {
                $confirmed = $this->confirm('This will PERMANENTLY delete media files and DB rows. Continue?', false);
                if (!$confirmed) {
                    $this->warn('Aborted.');
                    return self::SUCCESS;
                }
            }
        }

        // 1) Videos (delete storage objects + posters, then DB + upload_assets)
        if ($hasVideos) {
            $this->line('Processing videos…');
            $processed = 0;
            Video::query()->orderBy('id')->chunkById(100, function ($videos) use (&$processed, $apply, $limit, $hard, $r2) {
                foreach ($videos as $video) {
                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }

                    $processed++;

                    $diskName = (string) ($video->storage_disk ?? 'public');
                    $videoPath = (string) ($video->video_path ?? '');
                    $posterPath = (string) ($video->poster_path ?? '');

                    if (!$apply) {
                        continue;
                    }

                    if ($videoPath !== '') {
                        try {
                            if ($diskName === 'r2') {
                                $r2->deleteObject($videoPath);
                            } else {
                                Storage::disk($diskName)->delete($videoPath);
                            }
                        } catch (\Throwable $e) {
                            // Best-effort.
                        }
                    }

                    if ($posterPath !== '') {
                        try {
                            if (Storage::disk('public')->exists($posterPath)) {
                                Storage::disk('public')->delete($posterPath);
                            }
                        } catch (\Throwable $e) {
                            // Best-effort.
                        }
                    }

                    $videoId = (int) $video->id;
                    try {
                        $video->delete();
                    } catch (\Throwable $e) {
                        // Continue.
                    }

                    if (Schema::hasTable('upload_assets')) {
                        $q = UploadAsset::withTrashed()->where('video_id', $videoId);
                        try {
                            $hard ? $q->forceDelete() : $q->delete();
                        } catch (\Throwable $e) {
                            // Best-effort.
                        }
                    }
                }

                return true;
            });
            $this->line('Videos processed: ' . $processed);
            $this->newLine();
        }

        // 2) Cloud nodes for image/video (delete storage objects, then forceDelete + upload_assets)
        if ($hasCloudNodes) {
            $this->line('Processing cloud nodes (image/video)…');
            $processed = 0;

            $query = CloudNode::withTrashed()
                ->where('type', 'file')
                ->whereNotNull('stored_path')
                ->where(function ($q) {
                    $q->where('mime', 'like', 'image/%')
                        ->orWhere('mime', 'like', 'video/%');
                })
                ->orderBy('id');

            $query->chunkById(200, function ($nodes) use (&$processed, $apply, $limit, $hard, $r2) {
                foreach ($nodes as $node) {
                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }

                    $processed++;

                    if (!$apply) {
                        continue;
                    }

                    $diskName = (string) ($node->storage_disk ?? 'local');
                    $storedPath = (string) ($node->stored_path ?? '');

                    if ($storedPath !== '') {
                        try {
                            if ($diskName === 'r2') {
                                $r2->deleteObject($storedPath);
                            } else {
                                Storage::disk($diskName)->delete($storedPath);
                            }
                        } catch (\Throwable $e) {
                            // Best-effort.
                        }
                    }

                    $nodeId = (int) $node->id;
                    try {
                        $node->forceDelete();
                    } catch (\Throwable $e) {
                        // Continue.
                    }

                    if (Schema::hasTable('upload_assets')) {
                        $q = UploadAsset::withTrashed()->where('cloud_node_id', $nodeId);
                        try {
                            $hard ? $q->forceDelete() : $q->delete();
                        } catch (\Throwable $e) {
                            // Best-effort.
                        }
                    }
                }

                return true;
            });

            $this->line('Cloud nodes processed: ' . $processed);
            $this->newLine();
        }

        // 3) Legacy cloud_files (optional)
        if ($hasCloudFiles) {
            $this->line('Processing legacy cloud_files (image/video)…');
            $processed = 0;

            CloudFile::query()
                ->whereNotNull('stored_path')
                ->where(function ($q) {
                    $q->where('mime', 'like', 'image/%')
                        ->orWhere('mime', 'like', 'video/%');
                })
                ->orderBy('id')
                ->chunkById(200, function ($files) use (&$processed, $apply, $limit) {
                    foreach ($files as $file) {
                        if ($limit > 0 && $processed >= $limit) {
                            return false;
                        }

                        $processed++;

                        if (!$apply) {
                            continue;
                        }

                        $storedPath = (string) ($file->stored_path ?? '');
                        if ($storedPath !== '') {
                            try {
                                Storage::disk('local')->delete($storedPath);
                            } catch (\Throwable $e) {
                                // Best-effort.
                            }
                        }

                        try {
                            $file->delete();
                        } catch (\Throwable $e) {
                            // Continue.
                        }
                    }

                    return true;
                });

            $this->line('Legacy cloud_files processed: ' . $processed);
            $this->newLine();
        }

        // 4) Upload assets (kind photo/video) – also removes orphaned R2/local objects.
        if ($hasUploadAssets) {
            $this->line('Processing upload_assets (photo/video)…');
            $processed = 0;

            UploadAsset::withTrashed()
                ->whereIn('kind', ['photo', 'video'])
                ->orderBy('id')
                ->chunkById(200, function ($assets) use (&$processed, $apply, $limit, $hard, $r2) {
                    foreach ($assets as $asset) {
                        if ($limit > 0 && $processed >= $limit) {
                            return false;
                        }

                        $processed++;

                        if (!$apply) {
                            continue;
                        }

                        $provider = (string) ($asset->provider ?? 'r2');
                        $key = (string) ($asset->key ?? '');
                        $chatMessageId = (int) ($asset->chat_message_id ?? 0);

                        if ($key !== '') {
                            try {
                                if ($provider === 'local') {
                                    Storage::disk('local')->delete($key);
                                } else {
                                    $r2->deleteObject($key);
                                }
                            } catch (\Throwable $e) {
                                // Best-effort.
                            }
                        }

                        try {
                            $hard ? $asset->forceDelete() : $asset->delete();
                        } catch (\Throwable $e) {
                            // Continue.
                        }

                        // Keep chat clean: remove attachment messages too (best-effort).
                        if ($chatMessageId > 0 && Schema::hasTable('chat_messages')) {
                            try {
                                ChatMessage::query()->whereKey($chatMessageId)->delete();
                            } catch (\Throwable $e) {
                                // Best-effort.
                            }
                        }
                    }

                    return true;
                });

            $this->line('Upload assets processed: ' . $processed);
            $this->newLine();
        }

        // 5) Chat attachment messages created without upload_assets (legacy/direct uploads).
        if ($hasChatMessages) {
            $this->line('Processing chat_messages attachment placeholders…');
            $processed = 0;

            ChatMessage::query()
                ->where('body', 'like', self::CHAT_ATTACHMENT_PREFIX . '%')
                ->orderBy('id')
                ->chunkById(500, function ($msgs) use (&$processed, $apply, $limit) {
                    foreach ($msgs as $m) {
                        if ($limit > 0 && $processed >= $limit) {
                            return false;
                        }
                        $processed++;
                        if (!$apply) {
                            continue;
                        }
                        try {
                            $m->delete();
                        } catch (\Throwable $e) {
                            // Best-effort.
                        }
                    }
                    return true;
                });

            $this->line('Chat attachment messages processed: ' . $processed);
            $this->newLine();
        }

        // 6) User avatars (stored locally on the public disk).
        if ($hasUsers && Schema::hasColumn('users', 'avatar_path')) {
            $this->line('Clearing user avatar photos…');
            $processed = 0;

            User::query()
                ->whereNotNull('avatar_path')
                ->where('avatar_path', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($users) use (&$processed, $apply, $limit) {
                    foreach ($users as $u) {
                        if ($limit > 0 && $processed >= $limit) {
                            return false;
                        }
                        $processed++;

                        $path = trim((string) ($u->avatar_path ?? ''));
                        if ($apply && $path !== '') {
                            try {
                                Storage::disk('public')->delete($path);
                            } catch (\Throwable $e) {
                                // Best-effort.
                            }
                        }

                        if ($apply) {
                            try {
                                $u->forceFill([
                                    'avatar_path' => null,
                                    'avatar_updated_at' => null,
                                ])->save();
                            } catch (\Throwable $e) {
                                // Best-effort.
                            }
                        }
                    }

                    return true;
                });

            $this->line('User avatars cleared: ' . $processed);
            $this->newLine();
        }

        if ($hasPeople && Schema::hasColumn('people', 'avatar_path')) {
            $this->line('Clearing people.avatar_path…');
            $processed = 0;

            Person::query()
                ->whereNotNull('avatar_path')
                ->where('avatar_path', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($people) use (&$processed, $apply, $limit) {
                    foreach ($people as $p) {
                        if ($limit > 0 && $processed >= $limit) {
                            return false;
                        }
                        $processed++;
                        if (!$apply) {
                            continue;
                        }
                        try {
                            $p->forceFill(['avatar_path' => null])->save();
                        } catch (\Throwable $e) {
                            // Best-effort.
                        }
                    }
                    return true;
                });

            $this->line('People avatars cleared: ' . $processed);
            $this->newLine();
        }

        // 7) Purge leftover local storage dirs (optional).
        if ($purgeStorage) {
            $this->line('Purging local storage directories…');

            if ($apply) {
                try { Storage::disk('public')->deleteDirectory('videos'); } catch (\Throwable $e) {}
                try { Storage::disk('public')->deleteDirectory('avatars'); } catch (\Throwable $e) {}
                try { Storage::disk('local')->deleteDirectory('images'); } catch (\Throwable $e) {}
                try { Storage::disk('local')->deleteDirectory('private/cloud'); } catch (\Throwable $e) {}
                try { Storage::disk('local')->deleteDirectory('chunk-uploads'); } catch (\Throwable $e) {}
            }

            $this->line('Local storage purge done' . ($apply ? '.' : ' (dry-run).'));
            $this->newLine();
        }

        if (!$apply) {
            $this->info('Dry-run complete. Re-run with --force to delete.');
            $this->line('Tip: use --include-cloud-files if you also want to wipe legacy cloud_files.');
            return self::SUCCESS;
        }

        $this->info('Media wipe completed (best-effort).');
        return self::SUCCESS;
    }
}
