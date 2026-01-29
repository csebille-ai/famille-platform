<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    protected $fillable = [
        'name',
        'spotify_playlist_id',
        'is_shared',
        'created_by',
    ];

    /**
     * Extract Spotify playlist/album ID from URL or ID.
     * Returns format: "playlist:ID" or "album:ID" or null
     */
    public static function extractSpotifyPlaylistId(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $input = trim($input);

        // URL format: https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M?si=...
        if (preg_match('#spotify\.com/(?:intl-[^/]+/)?playlist/([a-zA-Z0-9]{22})#', $input, $m)) {
            return 'playlist:' . $m[1];
        }

        // URL format: https://open.spotify.com/album/0aLoFT88EQEjIaxLrCxphg?si=...
        if (preg_match('#spotify\.com/(?:intl-[^/]+/)?album/([a-zA-Z0-9]{22})#', $input, $m)) {
            return 'album:' . $m[1];
        }

        // URI format: spotify:playlist:37i9dQZF1DXcBWIGoYBM5M
        if (preg_match('/^spotify:playlist:([a-zA-Z0-9]{22})$/', $input, $m)) {
            return 'playlist:' . $m[1];
        }

        // URI format: spotify:album:0aLoFT88EQEjIaxLrCxphg
        if (preg_match('/^spotify:album:([a-zA-Z0-9]{22})$/', $input, $m)) {
            return 'album:' . $m[1];
        }

        // Direct ID with type prefix
        if (preg_match('/^(playlist|album):([a-zA-Z0-9]{22})$/', $input, $m)) {
            return $m[1] . ':' . $m[2];
        }

        // Legacy: Direct ID (22 chars) - assume playlist
        if (preg_match('/^[a-zA-Z0-9]{22}$/', $input)) {
            return 'playlist:' . $input;
        }

        return null;
    }

    /**
     * Get Spotify embed URL
     */
    public function getSpotifyEmbedUrl(): ?string
    {
        if (!$this->spotify_playlist_id) {
            return null;
        }

        // Format stored: "playlist:ID" or "album:ID"
        if (preg_match('/^(playlist|album):([a-zA-Z0-9]{22})$/', $this->spotify_playlist_id, $m)) {
            $type = $m[1];
            $id = $m[2];
            return "https://open.spotify.com/embed/{$type}/{$id}?utm_source=generator&theme=0";
        }

        // Fallback for legacy IDs without type
        return "https://open.spotify.com/embed/playlist/{$this->spotify_playlist_id}?utm_source=generator&theme=0";
    }

    /**
     * Get Spotify open URL
     */
    public function getSpotifyOpenUrl(): ?string
    {
        if (!$this->spotify_playlist_id) {
            return null;
        }

        if (preg_match('/^(playlist|album):([a-zA-Z0-9]{22})$/', $this->spotify_playlist_id, $m)) {
            $type = $m[1];
            $id = $m[2];
            return "https://open.spotify.com/{$type}/{$id}";
        }

        return "https://open.spotify.com/playlist/{$this->spotify_playlist_id}";
    }

    protected $casts = [
        'is_shared' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position');
    }
}
