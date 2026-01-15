<?php

namespace App\Services\Astro;

use Carbon\CarbonImmutable;

class MoonSignCalculator
{
    /**
     * Computes an approximate tropical Moon sign.
     *
     * This is a lightweight approximation good enough for “fun” UX.
     */
    public static function moonSign(CarbonImmutable $localDateTime, string $timezone): ?string
    {
        try {
            $dtLocal = $localDateTime->setTimezone($timezone);
        } catch (\Throwable) {
            $dtLocal = $localDateTime;
        }

        $dtUtc = $dtLocal->utc();

        $jd = self::julianDay($dtUtc);
        $d = $jd - 2451545.0; // days since J2000.0

        // Mean elements (degrees), simplified.
        $L = 218.316 + 13.176396 * $d; // mean longitude
        $Mm = 134.963 + 13.064993 * $d; // Moon mean anomaly
        $D = 297.850 + 12.190749 * $d; // elongation

        // Ecliptic longitude (degrees), low-order series.
        $lambda = $L
            + 6.289 * sin(deg2rad($Mm))
            + 1.274 * sin(deg2rad(2 * $D - $Mm))
            + 0.658 * sin(deg2rad(2 * $D))
            + 0.214 * sin(deg2rad(2 * $Mm))
            + 0.110 * sin(deg2rad($D));

        $lambda = self::normalizeDegrees($lambda);

        return self::zodiacSignFromLongitude($lambda);
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
