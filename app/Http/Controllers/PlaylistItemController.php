<?php

namespace App\Http\Controllers;

use App\Models\CloudAuditLog;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Services\Spotify\SpotifyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PlaylistItemController extends Controller
{
    public function search(Request $request, Playlist $playlist)
    {
        $this->authorizeAdd($playlist);

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['tracks' => []]);
        }

        $client = new SpotifyClient();
        $tracks = $client->searchTracks($q, 8);

        return response()->json([
            'tracks' => $tracks,
            'configured' => !empty(config('services.spotify.client_id')),
        ]);
    }

    public function store(Request $request, Playlist $playlist)
    {
        $this->authorizeAdd($playlist);

        $validated = $request->validate([
            'spotify' => ['required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $trackId = $this->extractSpotifyTrackId($validated['spotify']);
        if (!$trackId) {
            return back()->withErrors(['spotify' => 'Colle un lien Spotify de type track (ex: open.spotify.com/track/...).'])->withInput();
        }

        $position = (int) (PlaylistItem::query()->where('playlist_id', $playlist->id)->max('position') ?? -1) + 1;

        PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'spotify_track_id' => $trackId,
            'label' => $validated['label'] ?? null,
            'position' => $position,
            'added_by' => Auth::id(),
        ]);

        CloudAuditLog::create([
            'action' => 'playlist_item_added',
            'node_id' => null,
            'actor_id' => Auth::id(),
            'meta' => [
                'playlist_id' => (int) $playlist->id,
                'spotify_track_id' => (string) $trackId,
                'label' => $validated['label'] ?? null,
                'input' => $validated['spotify'],
            ],
        ]);

        return redirect()->route('playlists.show', $playlist)->with('status', 'Morceau ajouté.');
    }

    public function destroy(Playlist $playlist, PlaylistItem $item)
    {
        $this->authorizeRemove($playlist);

        if ((int) $item->playlist_id !== (int) $playlist->id) {
            abort(404);
        }

        CloudAuditLog::create([
            'action' => 'playlist_item_removed',
            'node_id' => null,
            'actor_id' => Auth::id(),
            'meta' => [
                'playlist_id' => (int) $playlist->id,
                'playlist_item_id' => (int) $item->id,
                'spotify_track_id' => (string) ($item->spotify_track_id ?? ''),
                'label' => $item->label,
                'added_by' => (int) ($item->added_by ?? 0),
            ],
        ]);

        $item->delete();

        return redirect()->route('playlists.show', $playlist)->with('status', 'Morceau supprimé.');
    }

    private function authorizeAdd(Playlist $playlist): void
    {
        if ($playlist->is_shared) {
            return;
        }

        if ((int) $playlist->created_by === (int) Auth::id()) {
            return;
        }

        abort(403);
    }

    private function authorizeRemove(Playlist $playlist): void
    {
        $this->authorizeAdd($playlist);
        Gate::authorize('playlists-delete-items');
    }

    private function extractSpotifyTrackId(string $input): ?string
    {
        $input = trim($input);

        // Raw track id
        if (preg_match('/^[A-Za-z0-9]{10,}$/', $input)) {
            return $input;
        }

        // spotify:track:<id>
        if (preg_match('/^spotify:track:([A-Za-z0-9]+)$/', $input, $m)) {
            return $m[1];
        }

        // https://open.spotify.com/track/<id>
        // https://open.spotify.com/intl-fr/track/<id>
        if (preg_match('~open\.spotify\.com/(?:intl-[a-z-]+/)?track/([A-Za-z0-9]+)~i', $input, $m)) {
            return $m[1];
        }

        return null;
    }
}
