<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlaylistItemController extends Controller
{
    public function store(Request $request, Playlist $playlist)
    {
        $this->authorizeManage($playlist);

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

        return redirect()->route('playlists.show', $playlist)->with('status', 'Morceau ajouté.');
    }

    public function destroy(Playlist $playlist, PlaylistItem $item)
    {
        $this->authorizeManage($playlist);

        if ((int) $item->playlist_id !== (int) $playlist->id) {
            abort(404);
        }

        $item->delete();

        return redirect()->route('playlists.show', $playlist)->with('status', 'Morceau supprimé.');
    }

    private function authorizeManage(Playlist $playlist): void
    {
        if ((int) $playlist->created_by === (int) Auth::id()) {
            return;
        }

        abort(403);
    }

    private function extractSpotifyTrackId(string $input): ?string
    {
        $input = trim($input);

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
