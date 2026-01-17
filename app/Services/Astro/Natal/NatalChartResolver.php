<?php

namespace App\Services\Astro\Natal;

use App\Models\AstroProfile;
use App\Models\User;
use Illuminate\Support\Carbon;

class NatalChartResolver
{
    private const DEFAULT_TZ = 'Europe/Paris';

    /**
     * Keys expected in astro-engine planets[] payload.
     */
    private const OUTER_PLANET_KEYS = ['jupiter', 'saturn', 'uranus', 'neptune', 'pluto'];

    public function __construct(private NatalChartEngineClient $client)
    {
    }

    /**
     * @return array{natal:?array,natal_hash:?string,natal_computed_at:?\Carbon\CarbonInterface}
     */
    public function resolve(User $user, ?AstroProfile $existingProfile = null): array
    {
        $dob = $user->date_of_birth;
        $birthTime = trim((string) ($user->birth_time ?? ''));
        $lat = $user->birth_latitude !== null ? (float) $user->birth_latitude : null;
        $lng = $user->birth_longitude !== null ? (float) $user->birth_longitude : null;

        if (!$dob || $birthTime === '' || $lat === null || $lng === null) {
            return [
                'natal' => null,
                'natal_hash' => null,
                'natal_computed_at' => null,
            ];
        }

        $date = Carbon::instance($dob)->format('Y-m-d');
        $tz = self::DEFAULT_TZ;

        $hash = sha1(implode('|', [
            $date,
            $birthTime,
            $tz,
            number_format($lat, 6, '.', ''),
            number_format($lng, 6, '.', ''),
        ]));

        if ($existingProfile
            && $existingProfile->natal_hash === $hash
            && is_array($existingProfile->natal)
            && $existingProfile->natal !== []
            && self::hasOuterPlanets((array) $existingProfile->natal)
        ) {
            return [
                'natal' => (array) $existingProfile->natal,
                'natal_hash' => $existingProfile->natal_hash,
                'natal_computed_at' => $existingProfile->natal_computed_at,
            ];
        }

        $chart = $this->client->chart([
            'date' => $date,
            'time' => $birthTime,
            'timezone' => $tz,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        return [
            'natal' => $chart,
            'natal_hash' => $hash,
            'natal_computed_at' => now(),
        ];
    }

    /**
     * @param array<string,mixed> $natal
     */
    private static function hasOuterPlanets(array $natal): bool
    {
        $planets = $natal['planets'] ?? null;
        if (!is_array($planets) || $planets === []) {
            return false;
        }

        $keys = [];
        foreach ($planets as $p) {
            if (is_array($p) && isset($p['key']) && is_string($p['key'])) {
                $keys[strtolower($p['key'])] = true;
            }
        }

        foreach (self::OUTER_PLANET_KEYS as $k) {
            if (!isset($keys[$k])) {
                return false;
            }
        }

        return true;
    }
}
