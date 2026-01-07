<?php

namespace App\Services\Spotify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyClient
{
    private function baseRequest(): PendingRequest
    {
        return Http::timeout(8);
    }

    private function token(): ?string
    {
        $clientId = (string) config('services.spotify.client_id', '');
        $clientSecret = (string) config('services.spotify.client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        return Cache::remember('spotify:access_token', now()->addMinutes(50), function () use ($clientId, $clientSecret) {
            $resp = $this->baseRequest()
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$resp->ok()) {
                return null;
            }

            $data = $resp->json();
            $token = (string) ($data['access_token'] ?? '');
            $expiresIn = (int) ($data['expires_in'] ?? 0);

            if ($token === '') {
                return null;
            }

            if ($expiresIn > 120) {
                Cache::put('spotify:access_token', $token, now()->addSeconds($expiresIn - 60));
            }

            return $token;
        });
    }

    /**
     * @return array<int, array{id:string,name:string,artists:string,album_image:?string,duration_ms:int,explicit:bool}>
     */
    public function searchTracks(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $token = $this->token();
        if ($token === null) {
            return [];
        }

        $limit = max(1, min(15, $limit));

        $resp = $this->baseRequest()
            ->withToken($token)
            ->get('https://api.spotify.com/v1/search', [
                'q' => $query,
                'type' => 'track',
                'limit' => $limit,
            ]);

        if (!$resp->ok()) {
            return [];
        }

        $items = $resp->json('tracks.items') ?? [];
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $t) {
            if (!is_array($t)) {
                continue;
            }

            $id = (string) ($t['id'] ?? '');
            $name = (string) ($t['name'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }

            $artistsArr = $t['artists'] ?? [];
            $artistNames = [];
            if (is_array($artistsArr)) {
                foreach ($artistsArr as $a) {
                    if (is_array($a) && !empty($a['name'])) {
                        $artistNames[] = (string) $a['name'];
                    }
                }
            }

            $albumImage = null;
            $images = $t['album']['images'] ?? null;
            if (is_array($images) && count($images) > 0) {
                $first = $images[0] ?? null;
                if (is_array($first) && !empty($first['url'])) {
                    $albumImage = (string) $first['url'];
                }
            }

            $out[] = [
                'id' => $id,
                'name' => $name,
                'artists' => implode(', ', $artistNames),
                'album_image' => $albumImage,
                'duration_ms' => (int) ($t['duration_ms'] ?? 0),
                'explicit' => (bool) ($t['explicit'] ?? false),
            ];
        }

        return $out;
    }
}
