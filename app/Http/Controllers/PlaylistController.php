<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlaylistController extends Controller
{
    public function index()
    {
        $playlists = Playlist::query()
            ->withCount('items')
            ->with('creator:id,name')
            ->where(function ($q) {
                $q->where('is_shared', true)
                    ->orWhere('created_by', Auth::id());
            })
            ->latest()
            ->get();

        return view('playlists.index', [
            'playlists' => $playlists,
        ]);
    }

    public function create()
    {
        return view('playlists.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'spotify_playlist_url' => ['nullable', 'string', 'max:500'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        $spotifyId = Playlist::extractSpotifyPlaylistId($validated['spotify_playlist_url'] ?? null);

        $playlist = Playlist::create([
            'name' => $validated['name'],
            'spotify_playlist_id' => $spotifyId,
            'is_shared' => (bool) ($validated['is_shared'] ?? true),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('playlists.show', $playlist)->with('status', 'Playlist créée.');
    }

    public function show(Playlist $playlist)
    {
        $this->authorizeView($playlist);

        $playlist->loadMissing([
            'creator:id,name',
            'items.adder:id,name',
        ]);

        return view('playlists.show', [
            'playlist' => $playlist,
        ]);
    }

    public function edit(Playlist $playlist)
    {
        $this->authorizeManage($playlist);

        return view('playlists.edit', [
            'playlist' => $playlist,
        ]);
    }

    public function update(Request $request, Playlist $playlist)
    {
        $this->authorizeManage($playlist);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'spotify_playlist_url' => ['nullable', 'string', 'max:500'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        $spotifyId = Playlist::extractSpotifyPlaylistId($validated['spotify_playlist_url'] ?? null);

        $playlist->update([
            'name' => $validated['name'],
            'spotify_playlist_id' => $spotifyId,
            'is_shared' => (bool) ($validated['is_shared'] ?? false),
        ]);

        return redirect()->route('playlists.show', $playlist)->with('status', 'Playlist mise à jour.');
    }

    public function destroy(Playlist $playlist)
    {
        $this->authorizeManage($playlist);

        $playlist->delete();

        return redirect()->route('playlists.index')->with('status', 'Playlist supprimée.');
    }

    private function authorizeView(Playlist $playlist): void
    {
        if ($playlist->is_shared) {
            return;
        }

        if ((int) $playlist->created_by === (int) Auth::id()) {
            return;
        }

        abort(403);
    }

    private function authorizeManage(Playlist $playlist): void
    {
        if ((int) $playlist->created_by === (int) Auth::id()) {
            return;
        }

        abort(403);
    }
}
