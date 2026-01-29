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
     * Extract Spotify playlist ID from URL or ID.
     */
    public static function extractSpotifyPlaylistId(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $input = trim($input);

        // Direct ID (22 chars alphanumeric)
        if (preg_match('/^[a-zA-Z0-9]{22}$/', $input)) {
            return $input;
        }

        // URL format: https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M?si=...
        if (preg_match('#spotify\.com/playlist/([a-zA-Z0-9]{22})#', $input, $m)) {
            return $m[1];
        }

        // URI format: spotify:playlist:37i9dQZF1DXcBWIGoYBM5M
        if (preg_match('/^spotify:playlist:([a-zA-Z0-9]{22})$/', $input, $m)) {
            return $m[1];
        }

        return null;
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
