<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\CloudNode;
use App\Models\UploadAsset;
use App\Models\Video;
use App\Services\Uploads\R2UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class UploadsController extends Controller
{
    private const ATTACH_PREFIX = '[[ATTACHMENT]]';

    private function requireMigrations(): ?\Illuminate\Http\JsonResponse
    {
        if (!Schema::hasTable('upload_assets')) {
            return response()->json([
                'message' => 'Base de données non à jour. Lance les migrations (php artisan migrate --force).',
                'missing' => ['upload_assets'],
            ], 500);
        }

        if (!Schema::hasTable('cloud_nodes')) {
            return response()->json([
                'message' => 'Base de données non à jour. Lance les migrations (php artisan migrate --force).',
                'missing' => ['cloud_nodes'],
            ], 500);
        }

        if (!Schema::hasTable('videos')) {
            return response()->json([
                'message' => 'Base de données non à jour. Lance les migrations (php artisan migrate --force).',
                'missing' => ['videos'],
            ], 500);
        }

        // Columns added by migrations.
        $missingCols = [];
        if (!Schema::hasColumn('cloud_nodes', 'storage_disk')) $missingCols[] = 'cloud_nodes.storage_disk';
        if (!Schema::hasColumn('cloud_nodes', 'public_url')) $missingCols[] = 'cloud_nodes.public_url';
        if (!Schema::hasColumn('videos', 'storage_disk')) $missingCols[] = 'videos.storage_disk';

        if (!empty($missingCols)) {
            return response()->json([
                'message' => 'Base de données non à jour. Lance les migrations (php artisan migrate --force).',
                'missing' => $missingCols,
            ], 500);
        }

        return null;
    }

    public function quota(Request $request)
    {
        if ($resp = $this->requireMigrations()) {
            return $resp;
        }

        $quotaBytes = (int) config('uploads.quota_bytes', 10 * 1024 * 1024 * 1024);
        try {
            $usedBytes = (int) UploadAsset::query()->sum('size_bytes');
        } catch (\Throwable $e) {
            Log::error('uploads.quota.failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur serveur pendant le calcul du quota.'], 500);
        }
        $remaining = max(0, $quotaBytes - $usedBytes);

        return response()->json([
            'quota_bytes' => $quotaBytes,
            'used_bytes' => $usedBytes,
            'remaining_bytes' => $remaining,
        ]);
    }

    public function presign(Request $request, R2UploadService $r2)
    {
        $max = (int) config('uploads.max_upload_bytes');

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'string', 'in:photo,video'],
            'context' => ['required', 'string', 'in:media,chat'],
        ]);

        $size = (int) $validated['size'];
        if ($size > $max) {
            return response()->json([
                'message' => 'Fichier trop volumineux (max 2 Go).',
            ], 413);
        }

        $mime = (string) $validated['mime'];
        if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
            return response()->json([
                'message' => 'Type de fichier non supporté (photo ou vidéo uniquement).',
            ], 422);
        }

        $bucket = trim($r2->bucket());
        if ($bucket === '') {
            return response()->json([
                'message' => 'R2 bucket is not configured.',
            ], 500);
        }

        $key = $r2->buildObjectKey((string) $validated['context'], (string) $validated['kind'], (string) $validated['filename']);
        $publicUrl = $r2->publicUrlForKey($key);

        $uploadUrl = $r2->presignPutObject($key, $mime, 900);

        return response()->json([
            'method' => 'PUT',
            'upload_url' => $uploadUrl,
            'key' => $key,
            'public_url' => $publicUrl,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'required_headers' => [
                'Content-Type' => $mime,
            ],
        ]);
    }

    public function multipartInit(Request $request, R2UploadService $r2)
    {
        $max = (int) config('uploads.max_upload_bytes');
        $threshold = (int) config('uploads.multipart_threshold_bytes');
        $partSize = (int) config('uploads.multipart_part_size_bytes');

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'string', 'in:photo,video'],
            'context' => ['required', 'string', 'in:media,chat'],
        ]);

        $size = (int) $validated['size'];
        if ($size > $max) {
            return response()->json(['message' => 'Fichier trop volumineux (max 2 Go).'], 413);
        }
        if ($size <= $threshold) {
            return response()->json(['message' => 'Multipart non nécessaire pour ce fichier.'], 422);
        }

        $mime = (string) $validated['mime'];
        if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
            return response()->json(['message' => 'Type de fichier non supporté (photo ou vidéo uniquement).'], 422);
        }

        $bucket = trim($r2->bucket());
        if ($bucket === '') {
            return response()->json(['message' => 'R2 bucket is not configured.'], 500);
        }

        // Clamp part size between 5MiB and 200MiB.
        $partSize = max(5 * 1024 * 1024, min(200 * 1024 * 1024, $partSize));
        $partsCount = (int) ceil($size / $partSize);
        if ($partsCount <= 0 || $partsCount > 10000) {
            return response()->json(['message' => 'Découpage multipart invalide (trop de parties).'], 422);
        }

        $key = $r2->buildObjectKey((string) $validated['context'], (string) $validated['kind'], (string) $validated['filename']);
        $publicUrl = $r2->publicUrlForKey($key);

        try {
            $uploadId = $r2->createMultipartUpload($key, $mime);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Impossible d’initier le multipart upload.'], 500);
        }

        $parts = [];
        for ($i = 1; $i <= $partsCount; $i++) {
            $parts[] = [
                'part_number' => $i,
                'upload_url' => $r2->presignUploadPart($key, $uploadId, $i, 2 * 3600),
            ];
        }

        return response()->json([
            'upload_id' => $uploadId,
            'key' => $key,
            'part_size' => $partSize,
            'parts' => $parts,
            'public_url' => $publicUrl,
        ]);
    }

    public function multipartComplete(Request $request, R2UploadService $r2)
    {
        $max = (int) config('uploads.max_upload_bytes');

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:1024'],
            'upload_id' => ['required', 'string', 'max:1024'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.part_number' => ['required', 'integer', 'min:1'],
            'parts.*.etag' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'string', 'in:photo,video'],
            'context' => ['required', 'string', 'in:media,chat'],
        ]);

        $size = (int) $validated['size'];
        if ($size > $max) {
            return response()->json(['message' => 'Fichier trop volumineux (max 2 Go).'], 413);
        }

        $mime = (string) $validated['mime'];
        if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
            return response()->json(['message' => 'Type de fichier non supporté (photo ou vidéo uniquement).'], 422);
        }

        $bucket = trim($r2->bucket());
        if ($bucket === '') {
            return response()->json(['message' => 'R2 bucket is not configured.'], 500);
        }

        $key = (string) $validated['key'];
        $uploadId = (string) $validated['upload_id'];

        $parts = collect($validated['parts'])
            ->map(fn ($p) => [
                'PartNumber' => (int) $p['part_number'],
                'ETag' => (string) $p['etag'],
            ])
            ->sortBy('PartNumber')
            ->values()
            ->all();

        try {
            $r2->completeMultipartUpload($key, $uploadId, $parts);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Impossible de finaliser le multipart upload.'], 500);
        }

        return response()->json([
            'key' => $key,
            'public_url' => $r2->publicUrlForKey($key),
            'status' => 'ready',
        ]);
    }

    public function finalize(Request $request, R2UploadService $r2)
    {
        if ($resp = $this->requireMigrations()) {
            return $resp;
        }

        $max = (int) config('uploads.max_upload_bytes');

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:1024'],
            'public_url' => ['nullable', 'string', 'max:2048'],
            'mime' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'string', 'in:photo,video'],
            'context' => ['required', 'string', 'in:media,chat'],
            // Optional: helps us enforce correct category conventions.
            // - personal => docs
            // - library  => films|series
            'scope' => ['nullable', 'string', 'in:personal,library'],
            'filename' => ['nullable', 'string', 'max:255'],
            'chat_thread_id' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:films,series,docs'],
            'description' => ['nullable', 'string'],
            'poster_file' => ['nullable', 'image', 'max:5120'],
        ]);

        // Enforce category conventions.
        if ((string) $validated['kind'] === 'video' && (string) $validated['context'] === 'media') {
            $scope = strtolower(trim((string) ($validated['scope'] ?? '')));
            $cat = strtolower(trim((string) ($validated['category'] ?? '')));

            if ($cat === '') {
                return response()->json([
                    'message' => 'Catégorie requise pour les vidéos.',
                ], 422);
            }

            if ($scope === 'library' && !in_array($cat, ['films', 'series'], true)) {
                return response()->json([
                    'message' => 'Catégorie invalide pour la Médiathèque (films / séries).',
                ], 422);
            }

            if ($scope === 'personal' && $cat !== 'docs') {
                return response()->json([
                    'message' => 'Catégorie invalide pour une vidéo perso (docs).',
                ], 422);
            }
        }

        $size = (int) $validated['size'];
        if ($size > $max) {
            return response()->json(['message' => 'Fichier trop volumineux (max 2 Go).'], 413);
        }

        $mime = (string) $validated['mime'];
        if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
            return response()->json(['message' => 'Type de fichier non supporté (photo ou vidéo uniquement).'], 422);
        }

        $userId = (int) Auth::id();
        $key = (string) $validated['key'];
        $publicUrl = $r2->publicUrlForKey($key) ?: (string) ($validated['public_url'] ?? '');

        try {
            $asset = UploadAsset::withTrashed()->firstOrNew([
                'provider' => 'r2',
                'key' => $key,
            ]);

            $asset->fill([
                'public_url' => $publicUrl !== '' ? $publicUrl : null,
                'mime' => $mime,
                'size_bytes' => $size,
                'kind' => (string) $validated['kind'],
                'context' => (string) $validated['context'],
                'user_id' => $userId,
            ]);

            if ($asset->trashed()) {
                $asset->restore();
            }

            $asset->save();
        } catch (\Throwable $e) {
            Log::error('uploads.finalize.asset_failed', [
                'user_id' => $userId,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erreur serveur pendant la finalisation (DB).'], 500);
        }

        $mediaId = null;
        $chatMessageId = null;
        $openUrl = null;
        $thumbUrl = null;

        try {
        if ((string) $validated['kind'] === 'photo') {
            $root = CloudNode::query()
                ->whereNull('parent_id')
                ->where('type', 'folder')
                ->where('name', '/')
                ->first();

            if (!$root) {
                $root = CloudNode::create([
                    'parent_id' => null,
                    'type' => 'folder',
                    'name' => '/',
                    'uploaded_by' => $userId,
                    'stored_path' => null,
                    'mime' => null,
                    'size' => null,
                ]);
            }

            $name = trim((string) ($validated['filename'] ?? ''));
            if ($name === '') {
                $name = 'Photo';
            }

            $node = CloudNode::create([
                'parent_id' => $root->id,
                'type' => 'file',
                'name' => $name,
                'stored_path' => $key,
                'storage_disk' => 'r2',
                'public_url' => $publicUrl !== '' ? $publicUrl : null,
                'mime' => $mime,
                'size' => $size,
                'uploaded_by' => $userId,
            ]);

            $asset->forceFill(['cloud_node_id' => $node->id])->save();

            $mediaId = (int) $node->id;
            $openUrl = route('media.photos.show', $node);
            $thumbUrl = route('images.view', $node);

            if ((string) $validated['context'] === 'chat') {
                $attachment = [
                    'media_type' => 'image',
                    'media_id' => $mediaId,
                    'name' => $name,
                    'url' => $openUrl,
                    'thumb_url' => $thumbUrl,
                    'public_url' => $publicUrl,
                ];

                $msg = ChatMessage::create([
                    'user_id' => $userId,
                    'body' => self::ATTACH_PREFIX . json_encode($attachment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);

                event(new ChatMessageSent($msg));

                $chatMessageId = (int) $msg->id;
                $asset->forceFill(['chat_message_id' => $msg->id])->save();
            }
        } else {
            $title = trim((string) ($validated['title'] ?? ''));
            if ($title === '') {
                $filename = trim((string) ($validated['filename'] ?? ''));
                $base = $filename !== '' ? pathinfo($filename, PATHINFO_FILENAME) : '';
                $title = trim((string) $base);
            }
            if ($title === '') {
                $title = 'Vidéo';
            }

            $category = (string) ($validated['category'] ?? 'docs');
            if (!in_array($category, ['films', 'series', 'docs'], true)) {
                $category = 'docs';
            }

            $video = Video::create([
                'title' => $title,
                'category' => $category,
                'description' => (string) ($validated['description'] ?? null),
                'created_by' => $userId,
                'video_path' => $key,
                'storage_disk' => 'r2',
                'poster_path' => null,
            ]);

            if ($request->hasFile('poster_file')) {
                try {
                    $poster = $request->file('poster_file');
                    $ext = $poster->guessExtension() ?: 'jpg';
                    $posterRelativePath = 'videos/posters/' . $video->id . '.' . $ext;
                    Storage::disk('public')->putFileAs('videos/posters', $poster, $video->id . '.' . $ext);
                    $video->forceFill(['poster_path' => $posterRelativePath])->save();
                } catch (\Throwable $e) {
                    Log::warning('uploads.finalize.poster_failed', [
                        'user_id' => $userId,
                        'key' => $key,
                        'video_id' => $video->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $asset->forceFill(['video_id' => $video->id])->save();

            $mediaId = (int) $video->id;
            $openUrl = route('videos.show', $video);
            $thumbUrl = route('videos.poster', $video);

            if ((string) $validated['context'] === 'chat') {
                $attachment = [
                    'media_type' => 'video',
                    'media_id' => $mediaId,
                    'name' => $title,
                    'url' => $openUrl,
                    'thumb_url' => $thumbUrl,
                    'public_url' => $publicUrl,
                ];

                $msg = ChatMessage::create([
                    'user_id' => $userId,
                    'body' => self::ATTACH_PREFIX . json_encode($attachment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);

                event(new ChatMessageSent($msg));

                $chatMessageId = (int) $msg->id;
                $asset->forceFill(['chat_message_id' => $msg->id])->save();
            }
        }

        } catch (\Throwable $e) {
            Log::error('uploads.finalize.failed', [
                'user_id' => $userId,
                'key' => $key,
                'kind' => (string) $validated['kind'],
                'context' => (string) $validated['context'],
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erreur serveur pendant la création du média.'], 500);
        }

        return response()->json([
            'media_id' => $mediaId,
            'chat_message_id' => $chatMessageId,
            'public_url' => $publicUrl !== '' ? $publicUrl : null,
            'open_url' => $openUrl,
            'thumb_url' => $thumbUrl,
        ]);
    }
}
