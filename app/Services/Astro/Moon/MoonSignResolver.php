<?php

namespace App\Services\Astro\Moon;

use App\Models\AstroProfile;
use App\Models\User;
use Carbon\CarbonImmutable;

class MoonSignResolver
{
    private const DEFAULT_TZ = 'Europe/Paris';

    public function __construct(private MoonEngineClient $client)
    {
    }

    /**
     * @return array{moon_sign:?string,moon_lon:?float,moon_deg_in_sign:?float,astro_hash:?string,astro_computed_at:?\Carbon\CarbonImmutable}
     */
    public function resolve(User $user, ?AstroProfile $existingProfile): array
    {
        $dob = $user->date_of_birth;
        $birthTime = $user->birth_time ? trim((string) $user->birth_time) : '';

        if (!$dob || $birthTime === '') {
            // Invalidate cached moon computation if minimal inputs are missing.
            return [
                'moon_sign' => null,
                'moon_lon' => null,
                'moon_deg_in_sign' => null,
                'astro_hash' => null,
                'astro_computed_at' => null,
            ];
        }

        $date = CarbonImmutable::instance($dob);
        $tz = self::DEFAULT_TZ;

        $hash = hash('sha256', $date->format('Y-m-d') . '|' . $birthTime . '|' . $tz);

        if ($existingProfile && (string) ($existingProfile->astro_hash ?? '') === $hash) {
            $existingMoon = trim((string) ($existingProfile->moon_sign ?? ''));
            if ($existingMoon !== '') {
                // Cache hit.
                return [
                    'moon_sign' => $existingMoon,
                    'moon_lon' => $existingProfile->moon_lon !== null ? (float) $existingProfile->moon_lon : null,
                    'moon_deg_in_sign' => $existingProfile->moon_deg_in_sign !== null ? (float) $existingProfile->moon_deg_in_sign : null,
                    'astro_hash' => $hash,
                    'astro_computed_at' => $existingProfile->astro_computed_at ? CarbonImmutable::instance($existingProfile->astro_computed_at) : null,
                ];
            }
        }

        // Normalize local datetime in Europe/Paris and convert to UTC with DST.
        try {
            $local = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $birthTime, $tz);
            $utc = $local->setTimezone('UTC');
        } catch (\Throwable) {
            return [
                'moon_sign' => null,
                'moon_lon' => null,
                'moon_deg_in_sign' => null,
                'astro_hash' => null,
                'astro_computed_at' => null,
            ];
        }

        $result = $this->client->moonForUtc([
            'utc' => $utc->format('Y-m-d\\TH:i:s\\Z'),
        ]);

        return [
            'moon_sign' => (string) $result['moon_sign'],
            'moon_lon' => (float) $result['moon_lon'],
            'moon_deg_in_sign' => (float) $result['moon_deg_in_sign'],
            'astro_hash' => $hash,
            'astro_computed_at' => CarbonImmutable::now('UTC'),
        ];
    }
}
