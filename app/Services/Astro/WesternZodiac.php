<?php

namespace App\Services\Astro;

use Carbon\CarbonImmutable;

class WesternZodiac
{
    /**
     * @return array{sign:string, element:string}
     */
    public static function fromDate(CarbonImmutable $date): array
    {
        $m = (int) $date->month;
        $d = (int) $date->day;

        // Boundaries are month/day (tropical zodiac).
        $sign = match (true) {
            ($m === 3 && $d >= 21) || ($m === 4 && $d <= 19) => 'Bélier',
            ($m === 4 && $d >= 20) || ($m === 5 && $d <= 20) => 'Taureau',
            ($m === 5 && $d >= 21) || ($m === 6 && $d <= 20) => 'Gémeaux',
            ($m === 6 && $d >= 21) || ($m === 7 && $d <= 22) => 'Cancer',
            ($m === 7 && $d >= 23) || ($m === 8 && $d <= 22) => 'Lion',
            ($m === 8 && $d >= 23) || ($m === 9 && $d <= 22) => 'Vierge',
            ($m === 9 && $d >= 23) || ($m === 10 && $d <= 22) => 'Balance',
            ($m === 10 && $d >= 23) || ($m === 11 && $d <= 21) => 'Scorpion',
            ($m === 11 && $d >= 22) || ($m === 12 && $d <= 21) => 'Sagittaire',
            ($m === 12 && $d >= 22) || ($m === 1 && $d <= 19) => 'Capricorne',
            ($m === 1 && $d >= 20) || ($m === 2 && $d <= 18) => 'Verseau',
            default => 'Poissons',
        };

        $element = match ($sign) {
            'Bélier', 'Lion', 'Sagittaire' => 'Feu',
            'Taureau', 'Vierge', 'Capricorne' => 'Terre',
            'Gémeaux', 'Balance', 'Verseau' => 'Air',
            default => 'Eau',
        };

        return ['sign' => $sign, 'element' => $element];
    }
}
