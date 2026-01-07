<?php

namespace App\Http\Controllers;

use App\Models\Resource;
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
            ->with('concernedUser:id,name')
            ->latest()
            ->get();

        $users = User::query()->orderBy('name')->get(['id', 'name']);

        $selected = (string) request()->query('user', '');
        $resourcesForUser = collect();

        // Only show results once the user has picked a filter.
        if ($selected !== '') {
            $resourcesForUser = Resource::query()
                ->with('concernedUser:id,name')
                ->when($selected === 'common', fn($q) => $q->whereNull('concerned_user_id'))
                ->when($selected === 'all', fn($q) => $q)
                ->when(is_numeric($selected), function ($q) use ($selected) {
                    $userId = (int) $selected;
                    $q->where(function ($sub) use ($userId) {
                        $sub->whereNull('concerned_user_id')
                            ->orWhere('concerned_user_id', $userId);
                    });
                })
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
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = (string) ($file->getMimeType() ?? '');
            if (str_starts_with($mime, 'video/')) {
                return back()
                    ->withErrors(['file' => 'Les vidéos ne sont pas autorisées pour les ressources.'])
                    ->withInput();
            }

            $path = Storage::disk('local')->putFile('private/resources', $file);
            $validated['attachment_path'] = $path;
            $validated['attachment_name'] = $file->getClientOriginalName();
            $validated['attachment_mime'] = $mime;
            $validated['attachment_size'] = (int) $file->getSize();
        }

        $validated['created_by'] = Auth::id();

        $resource = Resource::create($validated);

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
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = (string) ($file->getMimeType() ?? '');
            if (str_starts_with($mime, 'video/')) {
                return back()
                    ->withErrors(['file' => 'Les vidéos ne sont pas autorisées pour les ressources.'])
                    ->withInput();
            }

            if ($resource->attachment_path) {
                Storage::disk('local')->delete($resource->attachment_path);
            }

            $path = Storage::disk('local')->putFile('private/resources', $file);
            $validated['attachment_path'] = $path;
            $validated['attachment_name'] = $file->getClientOriginalName();
            $validated['attachment_mime'] = $mime;
            $validated['attachment_size'] = (int) $file->getSize();
        }

        $resource->update($validated);

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
        if (!$resource->attachment_path) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $resource->attachment_path,
            $resource->attachment_name ?: 'resource'
        );
    }

    public function open(Resource $resource)
    {
        if (!$resource->attachment_path) {
            abort(404);
        }

        $headers = [];
        if ($resource->attachment_mime) {
            $headers['Content-Type'] = $resource->attachment_mime;
        }

        return Storage::disk('local')->response(
            $resource->attachment_path,
            $resource->attachment_name ?: 'resource',
            $headers,
            'inline'
        );
    }
}
