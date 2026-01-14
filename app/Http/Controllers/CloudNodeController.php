<?php

namespace App\Http\Controllers;

use App\Models\CloudAuditLog;
use App\Models\CloudNode;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudNodeController extends Controller
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

    private function chunkUploadBaseDir(): string
    {
        return storage_path('app/private/chunk-uploads');
    }

    private function chunkUploadDir(string $uploadId): string
    {
        return $this->chunkUploadBaseDir() . DIRECTORY_SEPARATOR . $uploadId;
    }

    private function chunkUploadMetaPath(string $uploadId): string
    {
        return $this->chunkUploadDir($uploadId) . DIRECTORY_SEPARATOR . 'meta.json';
    }

    private function chunkUploadChunkPath(string $uploadId, int $index): string
    {
        return $this->chunkUploadDir($uploadId) . DIRECTORY_SEPARATOR . 'chunk_' . $index . '.bin';
    }

    private function ensureChunkDir(string $uploadId): void
    {
        $dir = $this->chunkUploadDir($uploadId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function readChunkMeta(string $uploadId): array
    {
        $path = $this->chunkUploadMetaPath($uploadId);
        if (!is_file($path)) {
            throw ValidationException::withMessages([
                'upload_id' => __('Unknown upload session.'),
            ]);
        }

        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            throw ValidationException::withMessages([
                'upload_id' => __('Invalid upload session.'),
            ]);
        }

        return $data;
    }

    private function writeChunkMeta(string $uploadId, array $meta): void
    {
        $this->ensureChunkDir($uploadId);
        @file_put_contents($this->chunkUploadMetaPath($uploadId), json_encode($meta, JSON_UNESCAPED_SLASHES));
    }

    private function listReceivedChunks(string $uploadId, int $totalChunks): array
    {
        $received = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            if (is_file($this->chunkUploadChunkPath($uploadId, $i))) {
                $received[] = $i;
            }
        }
        return $received;
    }

    private function detectMimeForPath(string $absolutePath): ?string
    {
        try {
            if (!is_file($absolutePath)) {
                return null;
            }
            if (!function_exists('finfo_open')) {
                return null;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                return null;
            }
            try {
                $mime = finfo_file($finfo, $absolutePath);
                $mime = is_string($mime) ? trim($mime) : '';
                return $mime !== '' ? $mime : null;
            } finally {
                finfo_close($finfo);
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function audit(string $action, ?CloudNode $node = null, array $meta = []): void
    {
        CloudAuditLog::create([
            'action' => $action,
            'node_id' => $node?->id,
            'actor_id' => Auth::id(),
            'meta' => $meta,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $formatted = $value >= 10 ? number_format($value, 0) : number_format($value, 1);
        return $formatted . ' ' . $units[$unitIndex];
    }

    private function cloudQuotaBytes(): int
    {
        $gb = (float) config('cloud.quota_global_gb', 10);
        if ($gb <= 0) {
            return 0;
        }

        return (int) round($gb * 1024 * 1024 * 1024);
    }

    private function cloudUsedBytes(): int
    {
        $sum = CloudNode::query()
            ->where('type', 'file')
            ->whereNotNull('size')
            ->sum('size');

        return (int) $sum;
    }

    private function iniSizeToBytes(?string $value): int
    {
        if ($value === null) {
            return 0;
        }

        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $lastChar = strtoupper(substr($value, -1));
        if (ctype_digit($lastChar)) {
            return (int) $value;
        }

        $number = (float) substr($value, 0, -1);
        return match ($lastChar) {
            'K' => (int) round($number * 1024),
            'M' => (int) round($number * 1024 * 1024),
            'G' => (int) round($number * 1024 * 1024 * 1024),
            'T' => (int) round($number * 1024 * 1024 * 1024 * 1024),
            default => 0,
        };
    }

    private function maxUploadMbFromPhpIni(): int
    {
        $uploadBytes = $this->iniSizeToBytes(ini_get('upload_max_filesize'));
        $postBytes = $this->iniSizeToBytes(ini_get('post_max_size'));

        if ($uploadBytes <= 0 || $postBytes <= 0) {
            return 0;
        }

        $bytes = min($uploadBytes, $postBytes);
        return max(1, (int) floor($bytes / (1024 * 1024)));
    }

    private function phpIniMaxUploadKb(): int
    {
        $uploadBytes = $this->iniSizeToBytes(ini_get('upload_max_filesize'));
        $postBytes = $this->iniSizeToBytes(ini_get('post_max_size'));

        if ($uploadBytes <= 0 || $postBytes <= 0) {
            return 0;
        }

        $bytes = min($uploadBytes, $postBytes);
        return max(1, (int) floor($bytes / 1024));
    }

    private function rootFolder(): CloudNode
    {
        return CloudNode::query()->firstOrCreate(
            ['parent_id' => null, 'type' => 'folder', 'name' => '/'],
            ['uploaded_by' => Auth::id()]
        );
    }

    private function breadcrumb(CloudNode $folder): array
    {
        $crumbs = [];
        $seen = [];
        $current = $folder;

        while ($current !== null) {
            if (isset($seen[$current->id])) {
                break;
            }
            $seen[$current->id] = true;
            $crumbs[] = $current;
            $current = $current->parent;
        }

        return array_reverse($crumbs);
    }

    private function folderOptions(): array
    {
        $folders = CloudNode::query()
            ->where('type', 'folder')
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');

        $parentMap = [];
        foreach ($folders as $folder) {
            $parentMap[$folder->id] = $folder->parent_id;
        }

        $nameMap = [];
        foreach ($folders as $folder) {
            $nameMap[$folder->id] = $folder->name;
        }

        $paths = [];
        foreach ($folders as $folder) {
            $parts = [];
            $currentId = $folder->id;
            $seen = [];
            while ($currentId !== null) {
                if (isset($seen[$currentId])) {
                    break;
                }
                $seen[$currentId] = true;
                $parts[] = $nameMap[$currentId] ?? ('#' . $currentId);
                $currentId = $parentMap[$currentId] ?? null;
            }
            $parts = array_reverse($parts);
            $paths[$folder->id] = implode('/', array_map(fn ($p) => trim($p, '/'), $parts));
            if ($paths[$folder->id] === '') {
                $paths[$folder->id] = '/';
            }
        }

        asort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        return $paths;
    }

    public function index(Request $request)
    {
        $root = $this->rootFolder();

        $folderId = $request->query('folder');
        $currentFolder = $folderId ? CloudNode::query()->with('parent')->findOrFail($folderId) : $root;
        if (!$currentFolder->isFolder()) {
            abort(404);
        }

        $currentFolder->load('parent');
        $breadcrumb = $this->breadcrumb($currentFolder);

        $search = trim((string) $request->query('q', ''));

        $nodesQuery = CloudNode::query()
            ->with('uploader')
            ->where('parent_id', $currentFolder->id)
            ->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name');

        if ($search !== '') {
            $nodesQuery->where('name', 'like', '%' . $search . '%');
        }

        $nodes = $nodesQuery->paginate(20)->withQueryString();

        $classifiedVideosByNodeId = [];
        try {
            $nodeIds = $nodes->getCollection()->pluck('id')->map(fn ($v) => (int) $v)->all();
            if (!empty($nodeIds) && \Illuminate\Support\Facades\Schema::hasTable('videos') && \Illuminate\Support\Facades\Schema::hasColumn('videos', 'cloud_node_id')) {
                $classifiedVideosByNodeId = Video::query()
                    ->whereIn('cloud_node_id', $nodeIds)
                    ->pluck('id', 'cloud_node_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }
        } catch (\Throwable $e) {
            $classifiedVideosByNodeId = [];
        }

        $configMaxKb = (int) config('cloud.max_upload_kb', 10240);
        $iniMaxKb = $this->phpIniMaxUploadKb();
        $effectiveMaxKb = $iniMaxKb > 0 ? min($configMaxKb, $iniMaxKb) : $configMaxKb;
        $effectiveMaxMb = max(1, (int) floor($effectiveMaxKb / 1024));

        $quotaBytes = $this->cloudQuotaBytes();
        $usedBytes = $this->cloudUsedBytes();
        $usagePercent = $quotaBytes > 0 ? (int) min(100, floor(($usedBytes / $quotaBytes) * 100)) : null;

        return view('cloud.index', [
            'currentFolder' => $currentFolder,
            'breadcrumb' => $breadcrumb,
            'nodes' => $nodes,
            'search' => $search,
            'folderOptions' => $this->folderOptions(),
            'classifiedVideosByNodeId' => $classifiedVideosByNodeId,
            'maxUploadMb' => $effectiveMaxMb,
            'quotaBytes' => $quotaBytes,
            'usedBytes' => $usedBytes,
            'quotaHuman' => $quotaBytes > 0 ? $this->formatBytes($quotaBytes) : null,
            'usedHuman' => $this->formatBytes($usedBytes),
            'usagePercent' => $usagePercent,
        ]);
    }

    public function storeFolder(Request $request)
    {
        Gate::authorize('cloud-write');

        $validated = $request->validate([
            'parent_id' => ['required', 'integer', 'exists:cloud_nodes,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $parent = CloudNode::query()->findOrFail($validated['parent_id']);
        if (!$parent->isFolder()) {
            abort(422);
        }

        $folder = CloudNode::create([
            'parent_id' => $parent->id,
            'type' => 'folder',
            'name' => $validated['name'],
            'uploaded_by' => Auth::id(),
        ]);

        $this->audit('create_folder', $folder, ['parent_id' => $parent->id]);

        return redirect()->route('cloud.index', ['folder' => $parent->id])
            ->with('status', __('Folder created.'));
    }

    public function storeFile(Request $request)
    {
        Gate::authorize('cloud-write');

        $configMaxKb = (int) config('cloud.max_upload_kb', 10240);
        $iniMaxKb = $this->phpIniMaxUploadKb();
        $maxKb = $iniMaxKb > 0 ? min($configMaxKb, $iniMaxKb) : $configMaxKb;

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:cloud_nodes,id'],
            'file' => [
                'required',
                'file',
                'max:' . $maxKb,
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }

                    $mime = (string) $value->getClientMimeType();
                    if (!str_starts_with($mime, 'video/')) {
                        return;
                    }

                    $allowedVideoMimes = [
                        'video/mp4',
                        'video/webm',
                        'video/quicktime',
                    ];

                    if (!in_array($mime, $allowedVideoMimes, true)) {
                        $fail(__('Unsupported video format. Allowed: MP4, WebM, MOV.'));
                    }
                },
            ],
        ], [
            'file.max' => __('The file may not be greater than :max kilobytes.', ['max' => $maxKb]),
        ]);

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId === null) {
            $parent = $this->rootFolder();
        } else {
            $parent = CloudNode::query()->findOrFail($parentId);
            if (!$parent->isFolder()) {
                throw ValidationException::withMessages([
                    'parent_id' => __('Invalid destination folder.'),
                ]);
            }
        }

        if (!$request->hasFile('file')) {
            throw ValidationException::withMessages([
                'file' => __('No file received. If the file is large, check PHP upload limits.'),
            ]);
        }

        $file = $request->file('file');
        if (!$file instanceof \Illuminate\Http\UploadedFile || !$file->isValid()) {
            throw ValidationException::withMessages([
                'file' => __('Upload failed. Please try again.'),
            ]);
        }

        $quotaBytes = $this->cloudQuotaBytes();
        if ($quotaBytes > 0) {
            $usedBytes = $this->cloudUsedBytes();
            $newBytes = (int) ($file->getSize() ?? 0);
            if ($newBytes > 0 && ($usedBytes + $newBytes) > $quotaBytes) {
                $quotaHuman = $this->formatBytes($quotaBytes);
                $message = "Quota atteint ({$quotaHuman}). Supprime des fichiers ou contacte un admin.";
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'errors' => ['file' => [$message]],
                    ], 422);
                }

                return redirect()
                    ->route('cloud.index', ['folder' => $parent->id])
                    ->with('error', $message);
            }
        }

        $dir = 'private/cloud/' . now()->format('Y') . '/' . now()->format('m');
        $storedPath = $file->store($dir, 'local');

        $node = CloudNode::create([
            'parent_id' => $parent->id,
            'type' => 'file',
            'name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $this->audit('upload_file', $node, ['parent_id' => $parent->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('File uploaded.'),
                'node' => $node,
            ], 201);
        }

        $mime = (string) ($node->mime ?? '');
        if (str_starts_with($mime, 'video/')) {
            return redirect()->route('videos.classify', ['node' => $node->id]);
        }

        $returnPath = $this->safeReturnPath($request->input('return') ?: $request->query('return'));
        if ($returnPath !== null) {
            return redirect($returnPath)->with('status', __('File uploaded.'));
        }

        return redirect()->route('cloud.index', ['folder' => $parent->id])
            ->with('status', __('File uploaded.'));
    }

    public function uploadInit(Request $request)
    {
        Gate::authorize('cloud-write');

        // Per-request server limit (relevant for the chunk POSTs, not for init).
        $perRequestMaxKb = (int) config('cloud.max_upload_kb', 10240);
        $iniMaxKb = $this->phpIniMaxUploadKb();
        $effectivePerRequestMaxKb = $iniMaxKb > 0 ? min($perRequestMaxKb, $iniMaxKb) : $perRequestMaxKb;
        $effectivePerRequestMaxBytes = max(1, $effectivePerRequestMaxKb) * 1024;

        // Total file size limit (relevant for chunked uploads).
        $maxFileKb = (int) config('cloud.max_file_kb', 0);
        $maxFileBytes = $maxFileKb > 0 ? max(1, $maxFileKb) * 1024 : null;

        $sizeRules = ['required', 'integer', 'min:1'];
        if ($maxFileBytes !== null) {
            $sizeRules[] = 'max:' . $maxFileBytes;
        }

        $validated = $request->validate([
            'upload_id' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'size' => $sizeRules,
            'mime' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:cloud_nodes,id'],
            'return' => ['nullable', 'string', 'max:2048'],
        ]);

        $size = (int) $validated['size'];

        $quotaBytes = $this->cloudQuotaBytes();
        if ($quotaBytes > 0) {
            $usedBytes = $this->cloudUsedBytes();
            if ($size > 0 && ($usedBytes + $size) > $quotaBytes) {
                $quotaHuman = $this->formatBytes($quotaBytes);
                $message = "Quota atteint ({$quotaHuman}). Supprime des fichiers ou contacte un admin.";
                return response()->json([
                    'message' => $message,
                    'errors' => ['file' => [$message]],
                ], 422);
            }
        }

        $mime = trim((string) ($validated['mime'] ?? ''));
        if (str_starts_with($mime, 'video/')) {
            $allowedVideoMimes = ['video/mp4', 'video/webm', 'video/quicktime'];
            if (!in_array($mime, $allowedVideoMimes, true)) {
                return response()->json([
                    'message' => __('Unsupported video format. Allowed: MP4, WebM, MOV.'),
                    'errors' => ['mime' => [__('Unsupported video format. Allowed: MP4, WebM, MOV.')]],
                ], 422);
            }
        }

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId === null) {
            $parent = $this->rootFolder();
        } else {
            $parent = CloudNode::query()->findOrFail($parentId);
            if (!$parent->isFolder()) {
                throw ValidationException::withMessages([
                    'parent_id' => __('Invalid destination folder.'),
                ]);
            }
        }

        // Resume support: if the client provides an existing upload_id and it matches the same
        // user/file/destination, return already-received chunk indexes.
        $resumeId = trim((string) ($validated['upload_id'] ?? ''));
        if ($resumeId !== '') {
            try {
                $meta = $this->readChunkMeta($resumeId);
                $metaUserId = (int) ($meta['user_id'] ?? 0);
                $metaParentId = (int) ($meta['parent_id'] ?? 0);
                $metaName = (string) ($meta['name'] ?? '');
                $metaSize = (int) ($meta['size'] ?? 0);
                $metaChunkSize = (int) ($meta['chunk_size'] ?? 0);
                $metaTotalChunks = (int) ($meta['total_chunks'] ?? 0);

                $createdAtRaw = (string) ($meta['created_at'] ?? '');
                $createdAt = $createdAtRaw !== '' ? \Carbon\Carbon::parse($createdAtRaw) : null;
                $isFresh = $createdAt ? $createdAt->greaterThanOrEqualTo(now()->subDays(2)) : false;

                $sameUser = $metaUserId > 0 && $metaUserId === (int) (Auth::id() ?? 0);
                $sameFile = $metaName !== '' && $metaName === (string) $validated['name'] && $metaSize === $size;
                $sameDest = $metaParentId > 0 && $metaParentId === (int) $parent->id;

                if ($sameUser && $sameFile && $sameDest && $isFresh && $metaChunkSize > 0 && $metaTotalChunks > 0) {
                    $received = $this->listReceivedChunks($resumeId, $metaTotalChunks);
                    return response()->json([
                        'upload_id' => $resumeId,
                        'chunk_size' => $metaChunkSize,
                        'total_chunks' => $metaTotalChunks,
                        'received' => $received,
                        'resumed' => true,
                    ], 200);
                }
            } catch (\Throwable $e) {
                // Ignore invalid resume attempts and create a new session.
            }
        }

        // Choose a conservative chunk size to stay under host limits.
        // Keep it safely under the per-request max (if known) to avoid 413/POST size errors.
        $chunkSize = 5 * 1024 * 1024; // 5MB default
        if ($effectivePerRequestMaxBytes > 0) {
            $headroom = 1024 * 1024; // 1MB for headers/form overhead
            $ceiling = max(1024 * 1024, $effectivePerRequestMaxBytes - $headroom);
            $chunkSize = min($chunkSize, $ceiling);
        }
        $totalChunks = (int) max(1, (int) ceil($size / $chunkSize));

        $uploadId = (string) Str::uuid();
        $returnPath = $this->safeReturnPath($validated['return'] ?? null);

        $meta = [
            'upload_id' => $uploadId,
            'user_id' => (int) (Auth::id() ?? 0),
            'parent_id' => (int) $parent->id,
            'name' => (string) $validated['name'],
            'size' => $size,
            'mime' => $mime,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'return' => $returnPath,
            'created_at' => now()->toIso8601String(),
        ];

        $this->writeChunkMeta($uploadId, $meta);

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'received' => [],
            'resumed' => false,
        ], 201);
    }

    public function uploadChunk(Request $request)
    {
        Gate::authorize('cloud-write');

        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'max:64'],
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        $uploadId = (string) $validated['upload_id'];
        $index = (int) $validated['index'];

        $meta = $this->readChunkMeta($uploadId);
        if ((int) ($meta['user_id'] ?? 0) !== (int) (Auth::id() ?? 0)) {
            abort(403);
        }

        $total = (int) ($meta['total_chunks'] ?? 0);
        if ($total <= 0 || $index >= $total) {
            throw ValidationException::withMessages([
                'index' => __('Invalid chunk index.'),
            ]);
        }

        $file = $request->file('chunk');
        if (!$file instanceof \Illuminate\Http\UploadedFile || !$file->isValid()) {
            throw ValidationException::withMessages([
                'chunk' => __('Upload failed. Please try again.'),
            ]);
        }

        $target = $this->chunkUploadChunkPath($uploadId, $index);
        $this->ensureChunkDir($uploadId);

        // Move chunk to temp dir.
        @rename($file->getRealPath(), $target);
        if (!is_file($target)) {
            // Fallback copy.
            @copy($file->getRealPath(), $target);
        }

        if (!is_file($target)) {
            return response()->json([
                'message' => __('Failed to store chunk.'),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'index' => $index,
        ]);
    }

    public function uploadComplete(Request $request)
    {
        Gate::authorize('cloud-write');

        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'max:64'],
        ]);

        $uploadId = (string) $validated['upload_id'];
        $meta = $this->readChunkMeta($uploadId);
        if ((int) ($meta['user_id'] ?? 0) !== (int) (Auth::id() ?? 0)) {
            abort(403);
        }

        $total = (int) ($meta['total_chunks'] ?? 0);
        $expectedSize = (int) ($meta['size'] ?? 0);
        $parentId = (int) ($meta['parent_id'] ?? 0);
        $name = (string) ($meta['name'] ?? '');
        $hintMime = (string) ($meta['mime'] ?? '');
        $returnPath = $this->safeReturnPath($meta['return'] ?? null);

        if ($total <= 0 || $expectedSize <= 0 || $parentId <= 0 || $name === '') {
            throw ValidationException::withMessages([
                'upload_id' => __('Invalid upload session.'),
            ]);
        }

        $received = $this->listReceivedChunks($uploadId, $total);
        if (count($received) !== $total) {
            return response()->json([
                'message' => __('Missing chunks.'),
                'received' => $received,
                'total' => $total,
            ], 409);
        }

        $parent = CloudNode::query()->findOrFail($parentId);
        if (!$parent->isFolder()) {
            throw ValidationException::withMessages([
                'parent_id' => __('Invalid destination folder.'),
            ]);
        }

        // Assemble into a final local stored path.
        $dir = 'private/cloud/' . now()->format('Y') . '/' . now()->format('m');
        $storedPath = $dir . '/' . $uploadId . '_' . basename($name);
        $absoluteTarget = Storage::disk('local')->path($storedPath);
        $targetDir = dirname($absoluteTarget);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $out = @fopen($absoluteTarget, 'wb');
        if ($out === false) {
            return response()->json([
                'message' => __('Failed to create file.'),
            ], 500);
        }

        try {
            for ($i = 0; $i < $total; $i++) {
                $chunkPath = $this->chunkUploadChunkPath($uploadId, $i);
                $in = @fopen($chunkPath, 'rb');
                if ($in === false) {
                    throw new \RuntimeException('missing_chunk:' . $i);
                }
                try {
                    stream_copy_to_stream($in, $out);
                } finally {
                    fclose($in);
                }
            }
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($absoluteTarget);
            return response()->json([
                'message' => __('Failed to assemble upload.'),
            ], 500);
        }

        fclose($out);

        $actualSize = @filesize($absoluteTarget);
        $actualSize = is_int($actualSize) ? $actualSize : null;
        if ($actualSize === null || $actualSize <= 0) {
            @unlink($absoluteTarget);
            return response()->json([
                'message' => __('Invalid assembled file.'),
            ], 500);
        }

        // Enforce max upload size from config/ini (defense-in-depth).
        $configMaxKb = (int) config('cloud.max_upload_kb', 10240);
        $iniMaxKb = $this->phpIniMaxUploadKb();
        $maxKb = $iniMaxKb > 0 ? min($configMaxKb, $iniMaxKb) : $configMaxKb;
        if ($actualSize > ($maxKb * 1024)) {
            @unlink($absoluteTarget);
            return response()->json([
                'message' => __('The file may not be greater than :max kilobytes.', ['max' => $maxKb]),
            ], 422);
        }

        // Detect mime from assembled file if possible.
        $detectedMime = $this->detectMimeForPath($absoluteTarget) ?: $hintMime;

        if (str_starts_with((string) $detectedMime, 'video/')) {
            $allowedVideoMimes = ['video/mp4', 'video/webm', 'video/quicktime'];
            if (!in_array((string) $detectedMime, $allowedVideoMimes, true)) {
                @unlink($absoluteTarget);
                return response()->json([
                    'message' => __('Unsupported video format. Allowed: MP4, WebM, MOV.'),
                ], 422);
            }
        }

        $node = CloudNode::create([
            'parent_id' => $parent->id,
            'type' => 'file',
            'name' => $name,
            'stored_path' => $storedPath,
            'mime' => $detectedMime,
            'size' => (int) $actualSize,
            'uploaded_by' => Auth::id(),
        ]);

        $this->audit('upload_file', $node, ['parent_id' => $parent->id, 'chunked' => true]);

        // Cleanup temp chunks.
        try {
            $dir = $this->chunkUploadDir($uploadId);
            if (is_dir($dir)) {
                $files = glob($dir . DIRECTORY_SEPARATOR . '*');
                if (is_array($files)) {
                    foreach ($files as $f) {
                        @unlink($f);
                    }
                }
                @rmdir($dir);
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        $redirectUrl = null;
        if (str_starts_with((string) $detectedMime, 'video/')) {
            $redirectUrl = route('videos.classify', ['node' => $node->id]);
        } elseif ($returnPath !== null) {
            $redirectUrl = $returnPath;
        } else {
            $redirectUrl = route('cloud.index', ['folder' => $parent->id]);
        }

        return response()->json([
            'message' => __('File uploaded.'),
            'node_id' => (int) $node->id,
            'redirect_url' => $redirectUrl,
        ], 201);
    }

    public function download(CloudNode $node)
    {
        if (!$node->isFile() || $node->stored_path === null) {
            abort(404);
        }
        if (!Storage::disk('local')->exists($node->stored_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($node->stored_path, $node->name);
    }

    public function preview(CloudNode $node)
    {
        if (!$node->isFile()) {
            abort(404);
        }

        $mime = $node->mime ?? '';
        $canPreview = str_starts_with($mime, 'image/') || $mime === 'application/pdf';

        if (!$canPreview) {
            return redirect()
                ->route('cloud.index', $node->parent_id ? ['folder' => $node->parent_id] : [])
                ->with('status', __('Preview not available, please download.'));
        }

        return view('cloud.preview', [
            'node' => $node->load('uploader'),
        ]);
    }

    public function view(CloudNode $node)
    {
        if (!$node->isFile() || $node->stored_path === null) {
            abort(404);
        }
        if (!Storage::disk('local')->exists($node->stored_path)) {
            abort(404);
        }

        $mime = $node->mime ?? 'application/octet-stream';
        $isInline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';

        if (!$isInline) {
            return Storage::disk('local')->download($node->stored_path, $node->name);
        }

        // Strong caching (private) + conditional requests for inline previews.
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

    public function rename(Request $request, CloudNode $node)
    {
        Gate::authorize('manage-cloud');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $oldName = $node->name;

        $node->update([
            'name' => $validated['name'],
        ]);

        $this->audit('rename', $node, ['from' => $oldName, 'to' => $validated['name']]);

        $folder = $node->parent_id;
        return redirect()->route('cloud.index', $folder ? ['folder' => $folder] : [])
            ->with('status', __('Renamed.'));
    }

    public function move(Request $request)
    {
        Gate::authorize('manage-cloud');

        $validated = $request->validate([
            'node_id' => ['required', 'integer', 'exists:cloud_nodes,id'],
            'new_parent_id' => ['required', 'integer', 'exists:cloud_nodes,id'],
        ]);

        $node = CloudNode::query()->findOrFail($validated['node_id']);
        $newParent = CloudNode::query()->with('parent')->findOrFail($validated['new_parent_id']);

        if (!$newParent->isFolder()) {
            abort(422);
        }

        $root = CloudNode::query()->whereNull('parent_id')->where('type', 'folder')->where('name', '/')->first();
        if ($root && $node->id === $root->id) {
            return redirect()->route('cloud.index')->with('status', __('Cannot move root folder.'));
        }

        if ($node->isFolder()) {
            $current = $newParent;
            $seen = [];
            while ($current !== null) {
                if (isset($seen[$current->id])) {
                    break;
                }
                $seen[$current->id] = true;
                if ($current->id === $node->id) {
                    return redirect()->route('cloud.index', ['folder' => $node->id])
                        ->with('status', __('Cannot move a folder into itself.'));
                }
                $current = $current->parent;
            }
        }

        $oldParentId = $node->parent_id;
        $node->update(['parent_id' => $newParent->id]);

        $this->audit('move', $node, ['from_parent_id' => $oldParentId, 'to_parent_id' => $newParent->id]);

        return redirect()->route('cloud.index', ['folder' => $newParent->id])
            ->with('status', __('Moved.'));
    }

    public function destroy(CloudNode $node)
    {
        Gate::authorize('manage-cloud');

        if ($node->isFolder()) {
            $hasChildren = CloudNode::query()->where('parent_id', $node->id)->exists();
            if ($hasChildren) {
                return redirect()->route('cloud.index', ['folder' => $node->id])
                    ->with('status', __('Folder must be empty to delete.'));
            }

            $parentId = $node->parent_id;
            $node->delete();
            $this->audit('trash', $node, ['type' => 'folder']);
            return redirect()->route('cloud.index', $parentId ? ['folder' => $parentId] : [])
                ->with('status', __('Moved to trash.'));
        }

        if ($node->isFile() && $node->stored_path !== null) {
            // Soft delete: keep stored file on disk until purged.
        }

        $parentId = $node->parent_id;
        $node->delete();

        $this->audit('trash', $node, ['type' => 'file']);

        return redirect()->route('cloud.index', $parentId ? ['folder' => $parentId] : [])
            ->with('status', __('Moved to trash.'));
    }

    public function trash(Request $request)
    {
        Gate::authorize('manage-cloud');

        $nodes = CloudNode::onlyTrashed()
            ->with(['uploader', 'parent'])
            ->orderByDesc('deleted_at')
            ->paginate(50)
            ->withQueryString();

        return view('cloud.trash', [
            'nodes' => $nodes,
        ]);
    }

    public function auditIndex(Request $request)
    {
        Gate::authorize('manage-cloud');

        $logs = CloudAuditLog::query()
            ->with(['actor', 'node'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('cloud.audit', [
            'logs' => $logs,
        ]);
    }

    public function restore(int $id)
    {
        Gate::authorize('manage-cloud');

        $node = CloudNode::withTrashed()->findOrFail($id);
        if ($node->deleted_at === null) {
            return redirect()->route('cloud.trash')->with('status', __('Already active.'));
        }

        $root = $this->rootFolder();
        if ($node->parent_id !== null) {
            $parent = CloudNode::withTrashed()->find($node->parent_id);
            if (!$parent || $parent->deleted_at !== null) {
                $node->parent_id = $root->id;
                $node->save();
            }
        }

        $node->restore();
        $this->audit('restore', $node);
        return redirect()->route('cloud.trash')->with('status', __('Restored.'));
    }

    public function purge(int $id)
    {
        Gate::authorize('manage-cloud');

        $node = CloudNode::withTrashed()->findOrFail($id);
        if ($node->deleted_at === null) {
            return redirect()->route('cloud.trash')->with('status', __('Not in trash.'));
        }

        if ($node->isFolder()) {
            $hasChildren = CloudNode::withTrashed()->where('parent_id', $node->id)->exists();
            if ($hasChildren) {
                return redirect()->route('cloud.trash')->with('status', __('Folder must be empty to purge.'));
            }
        }

        if ($node->isFile() && $node->stored_path !== null) {
            if (Storage::disk('local')->exists($node->stored_path)) {
                Storage::disk('local')->delete($node->stored_path);
            }
        }

        $this->audit('purge', $node);

        $node->forceDelete();
        return redirect()->route('cloud.trash')->with('status', __('Purged.'));
    }
}
