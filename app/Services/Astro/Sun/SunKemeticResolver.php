<?php

namespace App\Services\Astro\Sun;

use App\Models\AstroProfile;
use App\Models\User;
use App\Services\Astro\Kemetic\KemeticDecan;
use Carbon\CarbonImmutable;

class SunKemeticResolver
{
    private const DEFAULT_TZ = 'Europe/Paris';

    public function __construct(private SunEngineClient $client)
    {
    }

    /**
     * @return array{
     *   sun_lon:?float,
     *   sun_deg_in_sign:?float,
     *   kemetic_decan_index:?int,
     *   kemetic_decan_label:?string,
     *   kemetic_decan_keyword:?string,
     *   kemetic_hash:?string,
     *   kemetic_computed_at:?\Carbon\CarbonImmutable
     * }
     */
    public function resolve(User $user, ?AstroProfile $existingProfile): array
    {
        if (!$user->date_of_birth) {
            return [
                'sun_lon' => null,
                'sun_deg_in_sign' => null,
                'kemetic_decan_index' => null,
                'kemetic_decan_label' => null,
                'kemetic_decan_keyword' => null,
                'kemetic_hash' => null,
                'kemetic_computed_at' => null,
            ];
        }

        $date = CarbonImmutable::instance($user->date_of_birth)->format('Y-m-d');
        $time = trim((string) ($user->birth_time ?? ''));
        if ($time === '') {
            $time = '12:00';
        }

        $tz = self::DEFAULT_TZ;
        $hash = sha1(implode('|', ['kemetic_v1', $date, $time, $tz]));

        $hasCached = $existingProfile
            && (string) ($existingProfile->kemetic_hash ?? '') === $hash
            && $existingProfile->kemetic_decan_index !== null
            && trim((string) ($existingProfile->kemetic_decan_label ?? '')) !== ''
            && $existingProfile->sun_lon !== null
            && $existingProfile->sun_deg_in_sign !== null;

        if ($hasCached) {
            return [
                'sun_lon' => (float) $existingProfile->sun_lon,
                'sun_deg_in_sign' => (float) $existingProfile->sun_deg_in_sign,
                'kemetic_decan_index' => (int) $existingProfile->kemetic_decan_index,
                'kemetic_decan_label' => (string) $existingProfile->kemetic_decan_label,
                'kemetic_decan_keyword' => $existingProfile->kemetic_decan_keyword !== null ? (string) $existingProfile->kemetic_decan_keyword : null,
                'kemetic_hash' => (string) $existingProfile->kemetic_hash,
                'kemetic_computed_at' => $existingProfile->kemetic_computed_at ? CarbonImmutable::instance($existingProfile->kemetic_computed_at) : null,
            ];
        }

        $sun = $this->client->sun([
            'date' => $date,
            'time' => $time,
            'timezone' => $tz,
        ]);

        $k = KemeticDecan::fromSunLongitude((float) $sun['sun_lon']);

        return [
            'sun_lon' => (float) $k['sun_lon'],
            'sun_deg_in_sign' => (float) $k['sun_deg_in_sign'],
            'kemetic_decan_index' => (int) $k['kemetic_decan_index'],
            'kemetic_decan_label' => (string) $k['kemetic_decan_label'],
            'kemetic_decan_keyword' => $k['kemetic_decan_keyword'] !== '' ? (string) $k['kemetic_decan_keyword'] : null,
            'kemetic_hash' => $hash,
            'kemetic_computed_at' => CarbonImmutable::now($tz),
        ];
    }
}
