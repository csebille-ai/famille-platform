<?php

namespace App\Http\Controllers;

use App\Models\CloudNode;
use App\Models\UploadAsset;
use App\Models\User;
use App\Services\Uploads\R2UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\WebPush\WebPushNotifier;
use Symfony\Component\HttpFoundation\Response;

class ImageController extends Controller
{
    private function normalizeInternalReturnUrl(Request $request, ?string $returnUrl): ?string
    {
        $returnUrl = trim((string) $returnUrl);
        if ($returnUrl === '') {
            return null;
        }

        $parsed = parse_url($returnUrl);
        if ($parsed === false) {
            return null;
        }

        $returnHost = (string) ($parsed['host'] ?? '');
        if ($returnHost !== '' && $returnHost !== $request->getHost()) {
            return null;
        }

        $scheme = (string) ($parsed['scheme'] ?? '');
        if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $path = (string) ($parsed['path'] ?? '');
        if ($path === '' || !str_starts_with($path, '/')) {
            return null;
        }

        $query = (string) ($parsed['query'] ?? '');
        $fragment = (string) ($parsed['fragment'] ?? '');

        $normalized = $path;
        if ($query !== '') {
            $normalized .= '?' . $query;
        }
        if ($fragment !== '') {
            $normalized .= '#' . $fragment;
        }

        return $normalized;
    }

    private function rootFolder(): CloudNode
    {
        return CloudNode::query()->firstOrCreate(
            ['parent_id' => null, 'type' => 'folder', 'name' => '/'],
            ['uploaded_by' => Auth::id()]
        );
    }

    public function index(Request $request)
    {
        $selectedUserId = (int) $request->query('user', 0);

        $userImageCounts = CloudNode::query()
            ->where('type', 'file')
            ->whereNotNull('stored_path')
            ->where('mime', 'like', 'image/%')
            ->whereNotNull('uploaded_by')
            ->selectRaw('uploaded_by, COUNT(*) as image_count')
            ->groupBy('uploaded_by')
            ->pluck('image_count', 'uploaded_by')
            ->map(fn ($v) => (int) $v)
            ->all();

        $images = CloudNode::query()
            ->with('uploader')
            ->withCount('likers')
            ->where('type', 'file')
            ->whereNotNull('stored_path')
            ->where('mime', 'like', 'image/%')
            ->when($selectedUserId === -1, function ($query) {
                $query->whereNull('uploaded_by');
            })
            ->when($selectedUserId > 0, function ($query) use ($selectedUserId) {
                $query->where('uploaded_by', $selectedUserId);
            })
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $likedImageIds = [];
        $authUserId = Auth::id();
        if ($authUserId !== null && $images->count() > 0) {
            $pageIds = $images->getCollection()->pluck('id')->all();
            $likedImageIds = DB::table('image_likes')
                ->where('user_id', $authUserId)
                ->whereIn('cloud_node_id', $pageIds)
                ->pluck('cloud_node_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $users = User::query()
            ->whereIn('id', array_keys($userImageCounts))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('images.index', [
            'images' => $images,
            'users' => $users,
            'userImageCounts' => $userImageCounts,
            'selectedUserId' => $selectedUserId,
            'likedImageIds' => $likedImageIds,
            'maxUploadMb' => max(1, (int) floor(((int) config('cloud.max_upload_kb', 10240)) / 1024)),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('images-upload');

        return view('images.create', [
            'maxUploadMb' => max(1, (int) floor(((int) config('cloud.max_upload_kb', 10240)) / 1024)),
        ]);
    }

    public function toggleLike(CloudNode $node)
    {
        $userId = Auth::id();
        if ($userId === null) {
            abort(403);
        }

        if (!$node->isFile() || $node->stored_path === null) {
            abort(404);
        }

        $mime = (string) ($node->mime ?? '');
        if (!str_starts_with($mime, 'image/')) {
            abort(404);
        }

        $exists = DB::table('image_likes')
            ->where('cloud_node_id', $node->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            DB::table('image_likes')
                ->where('cloud_node_id', $node->id)
                ->where('user_id', $userId)
                ->delete();
        } else {
            DB::table('image_likes')->insert([
                'cloud_node_id' => $node->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back();
    }

    public function store(Request $request)
    {
        Gate::authorize('images-upload');

        $maxKb = (int) config('cloud.max_upload_kb', 10240);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'max:' . $maxKb],
        ], [
            'image.max' => __('The file may not be greater than :max kilobytes.', ['max' => $maxKb]),
        ]);

        $file = $request->file('image');
        if ($file === null) {
            abort(422);
        }

        $dir = 'images/' . now()->format('Y') . '/' . now()->format('m');
        $storedPath = $file->store($dir, 'local');

        $root = $this->rootFolder();

        $node = CloudNode::create([
            'parent_id' => $root->id,
            'type' => 'file',
            'name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        try {
            $actorName = Auth::user()?->name ?: 'Quelqu’un';
            app(WebPushNotifier::class)->notifyAll([
                'title' => 'Nouvelle photo',
                'body' => $actorName . ' a ajouté une photo',
                'url' => route('media.photos.show', $node),
            ]);
        } catch (\Throwable $e) {
            // Never block uploads on push issues.
        }

        return redirect()->route('media.index', ['tab' => 'photos'])->with('status', __('Image uploaded.'));
    }

    public function view(CloudNode $node): Response
    {
        if (!$node->isFile() || $node->stored_path === null) {
            abort(404);
        }

        $mime = (string) ($node->mime ?? '');
        if (!str_starts_with($mime, 'image/')) {
            abort(404);
        }

        $diskName = (string) ($node->storage_disk ?? 'local');
        if ($diskName !== 'local') {
            $url = (string) ($node->public_url ?? '');
            if ($url === '') {
                try {
                    $url = Storage::disk($diskName)->url($node->stored_path);
                } catch (\Throwable $e) {
                    $url = '';
                }
            }
            if ($url === '') {
                abort(404);
            }
            return redirect()->away($url);
        }

        if (!Storage::disk('local')->exists($node->stored_path)) {
            abort(404);
        }

        // Strong caching (private) + conditional requests.
        $etag = null;
        $lastModified = null;
        $abs = null;
        try {
            $abs = Storage::disk('local')->path($node->stored_path);
            if (is_file($abs)) {
                $mtime = @filemtime($abs) ?: null;
                $size = @filesize($abs) ?: null;
                if ($mtime) {
                    $lastModified = gmdate('D, d M Y H:i:s', (int) $mtime) . ' GMT';
                }
                if ($mtime && $size !== null) {
                    $etag = '"' . sha1((string) $node->id . '|' . (string) $mtime . '|' . (string) $size) . '"';
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
                    'Cache-Control' => 'private, max-age=604800, stale-while-revalidate=86400',
                ]));
            }
        }
        if ($lastModified !== null) {
            $ifModifiedSince = (string) $req->headers->get('If-Modified-Since', '');
            if ($ifModifiedSince !== '' && trim($ifModifiedSince) === $lastModified) {
                return response('', 304, array_filter([
                    'ETag' => $etag,
                    'Last-Modified' => $lastModified,
                    'Cache-Control' => 'private, max-age=604800, stale-while-revalidate=86400',
                ]));
            }
        }

        if (!is_string($abs) || $abs === '' || !is_file($abs)) {
            abort(404);
        }

        return response()->file($abs, array_filter([
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($node->name) . '"',
            'Cache-Control' => 'private, max-age=604800, stale-while-revalidate=86400',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ]));
    }

    public function show(Request $request, CloudNode $node)
    {
        if (!$node->isFile() || $node->stored_path === null) {
            abort(404);
        }

        $mime = (string) ($node->mime ?? '');
        if (!str_starts_with($mime, 'image/')) {
            abort(404);
        }

        $diskName = (string) ($node->storage_disk ?? 'local');
        if ($diskName === 'local') {
            if (!Storage::disk('local')->exists($node->stored_path)) {
                abort(404);
            }
        }

        $selectedUserId = (int) $request->query('user', 0);
        $returnUrl = $this->normalizeInternalReturnUrl($request, $request->query('return'));
        if ($returnUrl === null) {
            $returnUrl = $this->normalizeInternalReturnUrl($request, (string) $request->headers->get('referer'));
        }

        $node->loadMissing('uploader');

        $prevNode = null;
        $nextNode = null;

        if ($node->created_at !== null) {
            $baseQuery = CloudNode::query()
                ->where('type', 'file')
                ->whereNotNull('stored_path')
                ->where('mime', 'like', 'image/%')
                ->when($selectedUserId === -1, function ($query) {
                    $query->whereNull('uploaded_by');
                })
                ->when($selectedUserId > 0, function ($query) use ($selectedUserId) {
                    $query->where('uploaded_by', $selectedUserId);
                });

            $prevNode = (clone $baseQuery)
                ->where(function ($q) use ($node) {
                    $q->where('created_at', '>', $node->created_at)
                        ->orWhere(function ($q2) use ($node) {
                            $q2->where('created_at', '=', $node->created_at)
                                ->where('id', '>', $node->id);
                        });
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            $nextNode = (clone $baseQuery)
                ->where(function ($q) use ($node) {
                    $q->where('created_at', '<', $node->created_at)
                        ->orWhere(function ($q2) use ($node) {
                            $q2->where('created_at', '=', $node->created_at)
                                ->where('id', '<', $node->id);
                        });
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
        }

        return view('images.show', [
            'node' => $node,
            'prevNode' => $prevNode,
            'nextNode' => $nextNode,
            'selectedUserId' => $selectedUserId,
            'returnUrl' => $returnUrl,
        ]);
    }

    public function destroy(CloudNode $node, R2UploadService $r2)
    {
        Gate::authorize('images-delete');

        if (!$node->isFile() || $node->stored_path === null) {
            abort(404);
        }

        $mime = (string) ($node->mime ?? '');
        if (!str_starts_with($mime, 'image/')) {
            abort(404);
        }

        $diskName = (string) ($node->storage_disk ?? 'local');
        if ($diskName === 'local') {
            if (Storage::disk('local')->exists($node->stored_path)) {
                Storage::disk('local')->delete($node->stored_path);
            }
        } elseif ($diskName === 'r2') {
            try {
                $r2->deleteObject($node->stored_path);
            } catch (\Throwable $e) {
                // Best-effort.
            }
        } else {
            try {
                Storage::disk($diskName)->delete($node->stored_path);
            } catch (\Throwable $e) {
                // Best-effort.
            }
        }

        $node->forceDelete();

        UploadAsset::query()
            ->where('cloud_node_id', $node->id)
            ->delete();

        return redirect()->route('media.index', ['tab' => 'photos'])->with('status', __('Image deleted.'));
    }
}
