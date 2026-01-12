<?php

namespace App\Services\Astro\Geo;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BirthPlaceAutoResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(User $user): array
    {
        $place = trim((string) ($user->birth_place ?? ''));
        if ($place === '') {
            return [];
        }

        $updates = [];

        $hasLat = $user->birth_latitude !== null && $user->birth_latitude !== '';
        $hasLon = $user->birth_longitude !== null && $user->birth_longitude !== '';

        if (!$hasLat || !$hasLon) {
            $coords = $this->geocodePlace($place);
            if ($coords) {
                $updates['birth_latitude'] = $coords['lat'];
                $updates['birth_longitude'] = $coords['lon'];
                $hasLat = true;
                $hasLon = true;
            }
        }

        $tz = trim((string) ($user->birth_timezone ?? ''));
        if ($tz === '' && $hasLat && $hasLon) {
            $lat = (float) ($updates['birth_latitude'] ?? $user->birth_latitude);
            $lon = (float) ($updates['birth_longitude'] ?? $user->birth_longitude);
            $resolvedTz = $this->timezoneForCoordinates($lat, $lon);
            if ($resolvedTz) {
                $updates['birth_timezone'] = $resolvedTz;
            }
        }

        return $updates;
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function geocodePlace(string $place): ?array
    {
        $place = trim($place);
        if ($place === '' || mb_strlen($place) < 3) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.geo.nominatim_url', 'https://nominatim.openstreetmap.org'), '/');
        $userAgent = (string) config('services.geo.nominatim_user_agent', 'FamillePlatform/1.0');
        $cacheDays = (int) config('services.geo.cache_days', 365);

        $cacheKey = 'geo:nominatim:' . hash('sha256', mb_strtolower($place, 'UTF-8'));

        return Cache::remember($cacheKey, now()->addDays($cacheDays), function () use ($baseUrl, $userAgent, $place) {
            try {
                $resp = Http::timeout(6)
                    ->retry(1, 250)
                    ->withHeaders([
                        'User-Agent' => $userAgent,
                        'Accept-Language' => 'fr',
                        'Accept' => 'application/json',
                    ])
                    ->get($baseUrl . '/search', [
                        'q' => $place,
                        'format' => 'jsonv2',
                        'limit' => 1,
                    ]);

                if (!$resp->ok()) {
                    return null;
                }

                $data = $resp->json();
                if (!is_array($data) || empty($data)) {
                    return null;
                }

                $first = $data[0] ?? null;
                if (!is_array($first)) {
                    return null;
                }

                $latRaw = $first['lat'] ?? null;
                $lonRaw = $first['lon'] ?? null;
                if (!is_numeric($latRaw) || !is_numeric($lonRaw)) {
                    return null;
                }

                $lat = (float) $latRaw;
                $lon = (float) $lonRaw;

                if (!is_finite($lat) || !is_finite($lon)) {
                    return null;
                }

                if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
                    return null;
                }

                return [
                    'lat' => round($lat, 7),
                    'lon' => round($lon, 7),
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    private function timezoneForCoordinates(float $lat, float $lon): ?string
    {
        if (!is_finite($lat) || !is_finite($lon)) {
            return null;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            return null;
        }

        $endpoint = (string) config('services.geo.timezone_url', 'https://timeapi.io/api/TimeZone/coordinate');
        $cacheDays = (int) config('services.geo.cache_days', 365);

        $cacheKey = 'geo:tz:' . hash('sha256', round($lat, 5) . ',' . round($lon, 5));

        return Cache::remember($cacheKey, now()->addDays($cacheDays), function () use ($endpoint, $lat, $lon) {
            try {
                $resp = Http::timeout(6)
                    ->retry(1, 250)
                    ->withHeaders([
                        'Accept' => 'application/json',
                    ])
                    ->get($endpoint, [
                        'latitude' => $lat,
                        'longitude' => $lon,
                    ]);

                if (!$resp->ok()) {
                    return null;
                }

                $data = $resp->json();
                if (!is_array($data)) {
                    return null;
                }

                $tz = (string) ($data['timeZone'] ?? $data['timeZoneName'] ?? '');
                $tz = trim($tz);

                // Very light validation.
                if ($tz === '' || !str_contains($tz, '/')) {
                    return null;
                }

                return $tz;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
