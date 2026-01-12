<?php

namespace App\Services\Astro;

use App\Models\User;
use Carbon\CarbonImmutable;

class AstroProfileComputer
{
    public function __construct(private NatalChartProvider $natalProvider)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function compute(User $user): array
    {
        $dob = $user->date_of_birth;
        if (!$dob) {
            return [];
        }

        $date = CarbonImmutable::instance($dob);

        $west = WesternZodiac::fromDate($date);
        $ch = ChineseZodiac::fromDate($date);
        $life = Numerology::lifePath($date);

        $ascendant = null;
        $lat = $user->birth_latitude !== null ? (float) $user->birth_latitude : null;
        $lng = $user->birth_longitude !== null ? (float) $user->birth_longitude : null;

        $tz = (string) ($user->birth_timezone ?: config('app.timezone'));
        $birthTime = $user->birth_time ? (string) $user->birth_time : null;

        if ($birthTime && $lat !== null && $lng !== null) {
            // Combine date + time in the provided timezone.
            try {
                $dt = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $birthTime, $tz);
                $ascendant = AscendantCalculator::ascendantSign($dt, $lat, $lng, $tz);
            } catch (\Throwable $e) {
                $ascendant = null;
            }
        }

        $base = [
            'western_sign' => $west['sign'],
            'western_element' => $west['element'],
            'chinese_animal' => $ch['animal'],
            'chinese_element' => $ch['element'],
            'chinese_yin_yang' => $ch['yin_yang'],
            'life_path' => $life,
            'ascendant_sign' => $ascendant,
        ];

        $mix = AstroMixer::mix($base);

        // Optional natal chart (real planets/houses) if a provider is configured.
        $natal = $this->natalProvider->compute($user);

        return array_merge($base, [
            'natal' => $natal,
            'archetype' => $mix['archetype'],
            'talents' => $mix['talents'],
            'weakness' => $mix['weakness'],
            'signature' => $mix['signature'],
        ]);
    }
}
