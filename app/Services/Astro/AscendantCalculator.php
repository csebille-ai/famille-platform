<?php

namespace App\Services\Astro;

use Carbon\CarbonImmutable;

class AscendantCalculator
{
    /**
     * Computes an approximate ascendant sign from UTC time and geo coords.
     * Requires latitude/longitude (decimal degrees).
     */
    public static function ascendantSign(CarbonImmutable $localDateTime, float $latitude, float $longitude, string $timezone): ?string
    {
        if (!is_finite($latitude) || !is_finite($longitude)) {
            return null;
        }

        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            return null;
        }

        try {
            $dtLocal = $localDateTime->setTimezone($timezone);
        } catch (\Throwable $e) {
            $dtLocal = $localDateTime;
        }

        $dtUtc = $dtLocal->utc();

        $jd = self::julianDay($dtUtc);
        $t = ($jd - 2451545.0) / 36525.0;

        // GMST in degrees.
        $gmst = 280.46061837
            + 360.98564736629 * ($jd - 2451545.0)
            + 0.000387933 * ($t * $t)
            - ($t * $t * $t) / 38710000.0;

        $gmst = self::normalizeDegrees($gmst);

        // Local sidereal time in degrees.
        $lst = self::normalizeDegrees($gmst + $longitude);

        // Obliquity of the ecliptic (approx).
        $epsilon = deg2rad(23.439291 - 0.0130042 * $t);

        $theta = deg2rad($lst);
        $phi = deg2rad($latitude);

        // Ascendant ecliptic longitude.
        $y = sin($theta) * cos($epsilon) - tan($phi) * sin($epsilon);
        $x = cos($theta);
        $lambda = atan2($y, $x);
        $lambdaDeg = self::normalizeDegrees(rad2deg($lambda));

        return self::zodiacSignFromLongitude($lambdaDeg);
    }

    private static function julianDay(CarbonImmutable $dtUtc): float
    {
        $y = (int) $dtUtc->year;
        $m = (int) $dtUtc->month;
        $d = (int) $dtUtc->day;

        $hour = (int) $dtUtc->hour;
        $min = (int) $dtUtc->minute;
        $sec = (int) $dtUtc->second;

        $dayFraction = ($hour + ($min / 60.0) + ($sec / 3600.0)) / 24.0;

        if ($m <= 2) {
            $y -= 1;
            $m += 12;
        }

        $a = (int) floor($y / 100);
        $b = 2 - $a + (int) floor($a / 4);

        $jd = floor(365.25 * ($y + 4716))
            + floor(30.6001 * ($m + 1))
            + $d
            + $b
            - 1524.5
            + $dayFraction;

        return (float) $jd;
    }

    private static function normalizeDegrees(float $deg): float
    {
        $deg = fmod($deg, 360.0);
        if ($deg < 0) {
            $deg += 360.0;
        }
        return $deg;
    }

    private static function zodiacSignFromLongitude(float $lambdaDeg): string
    {
        $index = (int) floor(self::normalizeDegrees($lambdaDeg) / 30.0);
        return match ($index) {
            0 => 'Bélier',
            1 => 'Taureau',
            2 => 'Gémeaux',
            3 => 'Cancer',
            4 => 'Lion',
            5 => 'Vierge',
            6 => 'Balance',
            7 => 'Scorpion',
            8 => 'Sagittaire',
            9 => 'Capricorne',
            10 => 'Verseau',
            default => 'Poissons',
        };
    }
}
