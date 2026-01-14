<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\CloudNode;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatAttachmentController extends Controller
{
    private const BODY_PREFIX = '[[ATTACHMENT]]';

    private function rootFolder(int $userId): CloudNode
    {
        return CloudNode::query()->firstOrCreate(
            ['parent_id' => null, 'type' => 'folder', 'name' => '/'],
            ['uploaded_by' => $userId]
        );
    }

    public function __invoke(Request $request, string $thread)
    {
        $userId = (int) (Auth::id() ?? 0);
        if ($userId <= 0) {
            abort(403);
        }

        $maxKb = 512000; // 500 MB (upper bound; real limit depends on PHP/server).

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:' . $maxKb],
        ]);

        $file = $request->file('file');
        if ($file === null) {
            abort(422);
        }

        $clientMime = (string) ($file->getClientMimeType() ?? '');
        $detectedMime = (string) ($file->getMimeType() ?? '');
        $mime = $detectedMime !== '' ? $detectedMime : $clientMime;

        $originalName = (string) ($file->getClientOriginalName() ?? 'fichier');
        $originalName = trim($originalName) !== '' ? $originalName : 'fichier';

        $mediaType = null;
        $mediaId = null;
        $openUrl = null;
        $thumbUrl = null;

        if (str_starts_with($mime, 'image/')) {
            $dir = 'images/' . now()->format('Y') . '/' . now()->format('m');
            $storedPath = $file->store($dir, 'local');

            $root = $this->rootFolder($userId);

            $node = CloudNode::create([
                'parent_id' => $root->id,
                'type' => 'file',
                'name' => $originalName,
                'stored_path' => $storedPath,
                'mime' => $clientMime !== '' ? $clientMime : $mime,
                'size' => (int) ($file->getSize() ?? 0),
                'uploaded_by' => $userId,
            ]);

            $mediaType = 'image';
            $mediaId = (int) $node->id;
            $openUrl = route('media.photos.show', $node);
            $thumbUrl = route('images.view', $node);
        } else {
            // Treat as video if extension is supported; mime sniffing can be unreliable.
            $ext = strtolower((string) ($file->getClientOriginalExtension() ?? ''));
            $allowed = ['mp4', 'webm', 'avi', 'mov', 'mkv'];
            if (!in_array($ext, $allowed, true) && !str_starts_with($mime, 'video/')) {
                return response()->json([
                    'message' => 'Type de fichier non supporté (photo ou vidéo uniquement).',
                ], 422);
            }

            $path = $file->store('videos', 'public');

            $title = pathinfo($originalName, PATHINFO_FILENAME);
            $title = trim((string) $title);
            if ($title === '') {
                $title = 'Vidéo';
            }

            $video = Video::create([
                'title' => $title,
                'category' => 'docs',
                'created_by' => $userId,
                'video_path' => $path,
            ]);

            $mediaType = 'video';
            $mediaId = (int) $video->id;
            $openUrl = route('videos.show', $video);
            $thumbUrl = route('videos.poster', $video);
        }

        $attachment = [
            'media_type' => $mediaType,
            'media_id' => $mediaId,
            'url' => $openUrl,
            'thumb_url' => $thumbUrl,
            'name' => $originalName,
            'mime' => $mime,
        ];

        $message = ChatMessage::create([
            'user_id' => $userId,
            'body' => self::BODY_PREFIX . json_encode($attachment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $message->loadMissing('user:id,name');
        broadcast(new ChatMessageSent($message))->toOthers();

        return response()->json([
            'media_id' => $mediaId,
            'media_type' => $mediaType,
            'url' => $openUrl,
            'thumb_url' => $thumbUrl,
            'chat_message_id' => (int) $message->id,
            'message' => [
                'id' => (int) $message->id,
                'body' => (string) $message->body,
                'created_at' => $message->created_at?->toISOString(),
                'user' => [
                    'id' => $message->user?->id,
                    'name' => $message->user?->name,
                ],
            ],
        ]);
    }
}
