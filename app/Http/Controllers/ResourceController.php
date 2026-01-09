<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\ResourceFile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $resources = Resource::query()
            ->with(['concernedUser:id,name', 'creator:id,name', 'files'])
            ->latest()
            ->get();

        $users = User::query()->orderBy('name')->get(['id', 'name']);

        $selected = (string) request()->query('user', '');
        $selectedCategory = trim((string) request()->query('category', ''));
        $resourcesForUser = collect();

        // Only show results once the user has picked a filter.
        if ($selected !== '') {
            $resourcesForUser = Resource::query()
                ->with(['concernedUser:id,name', 'creator:id,name', 'files'])
                ->when($selected === 'common', fn($q) => $q->whereNull('concerned_user_id'))
                ->when($selected === 'all', fn($q) => $q)
                ->when(is_numeric($selected), function ($q) use ($selected) {
                    $userId = (int) $selected;
                    $q->where(function ($sub) use ($userId) {
                        $sub->whereNull('concerned_user_id')
                            ->orWhere('concerned_user_id', $userId);
                    });
                })
                ->when($selectedCategory !== '', fn($q) => $q->where('category', $selectedCategory))
                ->latest()
                ->get();
        }

        return view('resources.index', [
            'resources' => $resources,
            'users' => $users,
            'selectedUser' => $selected,
            'resourcesForUser' => $resourcesForUser,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::query()->orderBy('name')->get(['id', 'name']);

        return view('resources.create', [
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', 'in:administratives,pratiques,utiles'],
            'folder' => ['required', 'string', 'in:A1,A2,A3'],
            'concerned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $validated['created_by'] = Auth::id();

        // Legacy attachment_* kept for backward compatibility, but new uploads are stored in resource_files.
        unset(
            $validated['attachment_path'],
            $validated['attachment_name'],
            $validated['attachment_mime'],
            $validated['attachment_size']
        );

        $resource = Resource::create($validated);

        $uploaded = [];
        if ($request->hasFile('files')) {
            $uploaded = array_values(array_filter((array) $request->file('files')));
        } elseif ($request->hasFile('file')) {
            $uploaded = [$request->file('file')];
        }

        foreach ($uploaded as $file) {
            $mime = (string) ($file->getMimeType() ?? '');
            if (str_starts_with($mime, 'video/')) {
                return back()
                    ->withErrors(['files' => 'Les vidéos ne sont pas autorisées pour les ressources.'])
                    ->withInput();
            }

            $path = Storage::disk('local')->putFile('private/resources', $file);

            ResourceFile::create([
                'resource_id' => $resource->id,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => (int) $file->getSize(),
                'created_by' => Auth::id(),
            ]);
        }

        return redirect()->route('resources.show', $resource);
    }

    /**
     * Display the specified resource.
     */
    public function show(Resource $resource)
    {
        $resource->loadMissing('concernedUser:id,name');

        return view('resources.show', [
            'resource' => $resource,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Resource $resource)
    {
        $users = User::query()->orderBy('name')->get(['id', 'name']);

        return view('resources.edit', [
            'resource' => $resource,
            'users' => $users,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Resource $resource)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', 'in:administratives,pratiques,utiles'],
            'folder' => ['required', 'string', 'in:A1,A2,A3'],
            'concerned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $resource->update($validated);

        $uploaded = [];
        if ($request->hasFile('files')) {
            $uploaded = array_values(array_filter((array) $request->file('files')));
        } elseif ($request->hasFile('file')) {
            $uploaded = [$request->file('file')];
        }

        foreach ($uploaded as $file) {
            $mime = (string) ($file->getMimeType() ?? '');
            if (str_starts_with($mime, 'video/')) {
                return back()
                    ->withErrors(['files' => 'Les vidéos ne sont pas autorisées pour les ressources.'])
                    ->withInput();
            }

            $path = Storage::disk('local')->putFile('private/resources', $file);

            ResourceFile::create([
                'resource_id' => $resource->id,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => (int) $file->getSize(),
                'created_by' => Auth::id(),
            ]);
        }

        return redirect()->route('resources.show', $resource);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Resource $resource)
    {
        if ($resource->attachment_path) {
            Storage::disk('local')->delete($resource->attachment_path);
        }

        $resource->delete();

        return redirect()->route('resources.index');
    }

    public function download(Resource $resource)
    {
        $file = $resource->displayFiles()->first();
        if (!$file || !$file->path) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $file->path,
            $file->name ?: 'resource'
        );
    }

    public function open(Resource $resource)
    {
        $file = $resource->displayFiles()->first();
        if (!$file || !$file->path) {
            abort(404);
        }

        $headers = [];
        $name = (string) ($file->name ?: 'resource');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (string) ($file->mime ?: '');

        if ($mime === '') {
            $mime = match ($ext) {
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => '',
            };
        }

        if ($mime !== '') {
            $headers['Content-Type'] = $mime;
        }

        return Storage::disk('local')->response(
            $file->path,
            $file->name ?: 'resource',
            $headers,
            'inline'
        );
    }

    public function preview(Resource $resource)
    {
        $resource->loadMissing('concernedUser:id,name');

        $displayName = $resource->attachment_name ?: $resource->title;
        $mime = (string) ($resource->attachment_mime ?: '');
        $ext = strtolower(pathinfo((string) $displayName, PATHINFO_EXTENSION));

        $previewType = 'none';
        if ($mime === 'application/pdf' || $ext === 'pdf') {
            $previewType = 'pdf';
        } elseif (str_starts_with($mime, 'image/') || in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'heic'], true)) {
            $previewType = 'image';
        }

        if (in_array($previewType, ['pdf', 'image'], true)) {
            return redirect()->route('resources.open', $resource);
        }

        return view('resources.preview', [
            'resource' => $resource,
            'displayName' => $displayName,
            'previewType' => $previewType,
        ]);
    }

    private function assertFileBelongsToResource(Resource $resource, ResourceFile $file): void
    {
        if ((int) $file->resource_id !== (int) $resource->id) {
            abort(404);
        }
    }

    public function downloadFile(Resource $resource, ResourceFile $file)
    {
        $this->assertFileBelongsToResource($resource, $file);

        return Storage::disk('local')->download(
            $file->path,
            $file->name ?: 'resource'
        );
    }

    public function openFile(Resource $resource, ResourceFile $file)
    {
        $this->assertFileBelongsToResource($resource, $file);

        $headers = [];
        $name = (string) ($file->name ?: 'resource');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (string) ($file->mime ?: '');

        if ($mime === '') {
            $mime = match ($ext) {
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => '',
            };
        }

        if ($mime !== '') {
            $headers['Content-Type'] = $mime;
        }

        return Storage::disk('local')->response(
            $file->path,
            $file->name ?: 'resource',
            $headers,
            'inline'
        );
    }

    public function previewFile(Resource $resource, ResourceFile $file)
    {
        $this->assertFileBelongsToResource($resource, $file);

        $displayName = $file->name ?: $resource->title;
        $mime = (string) ($file->mime ?: '');
        $ext = strtolower(pathinfo((string) $displayName, PATHINFO_EXTENSION));

        $previewType = 'none';
        if ($mime === 'application/pdf' || $ext === 'pdf') {
            $previewType = 'pdf';
        } elseif (str_starts_with($mime, 'image/') || in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'heic'], true)) {
            $previewType = 'image';
        }

        if (in_array($previewType, ['pdf', 'image'], true)) {
            return redirect()->route('resources.files.open', [$resource, $file]);
        }

        $resource->loadMissing('concernedUser:id,name');

        return view('resources.file-preview', [
            'resource' => $resource,
            'file' => $file,
            'displayName' => $displayName,
        ]);
    }
}
