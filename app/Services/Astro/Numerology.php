<?php

namespace App\Services\Astro;

use Carbon\CarbonImmutable;

class Numerology
{
    public static function lifePath(CarbonImmutable $date): int
    {
        $digits = preg_replace('/\D+/', '', $date->format('Ymd'));
        $sum = 0;
        foreach (str_split((string) $digits) as $ch) {
            $sum += (int) $ch;
        }

        // Reduce, keeping master numbers 11/22.
        while ($sum > 9 && $sum !== 11 && $sum !== 22) {
            $tmp = 0;
            foreach (str_split((string) $sum) as $ch) {
                $tmp += (int) $ch;
            }
            $sum = $tmp;
        }

        return $sum;
    }
}
