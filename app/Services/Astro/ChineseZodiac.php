<?php

namespace App\Services\Astro;

use Carbon\CarbonImmutable;

class ChineseZodiac
{
    private const ANIMALS = [
        'Rat',
        'Bœuf',
        'Tigre',
        'Lapin',
        'Dragon',
        'Serpent',
        'Cheval',
        'Chèvre',
        'Singe',
        'Coq',
        'Chien',
        'Cochon',
    ];

    private const ELEMENTS = ['Bois', 'Feu', 'Terre', 'Métal', 'Eau'];

    /**
     * Simplified: uses Feb 4 (Start of Spring) boundary.
     * @return array{animal:string, element:string, yin_yang:string}
     */
    public static function fromDate(CarbonImmutable $date): array
    {
        $year = (int) $date->year;

        // Approx boundary: if born before Feb 4, treat as previous zodiac year.
        $boundary = $date->setMonth(2)->setDay(4)->startOfDay();
        if ($date->lessThan($boundary)) {
            $year -= 1;
        }

        // 1984 is commonly used as a Rat year (start of a 60-year cycle).
        $cycleIndex = ($year - 1984) % 60;
        if ($cycleIndex < 0) {
            $cycleIndex += 60;
        }

        $animal = self::ANIMALS[$cycleIndex % 12];
        $stem = $cycleIndex % 10; // Heavenly stem 0..9
        $element = self::ELEMENTS[(int) floor($stem / 2)];
        $yinYang = ($stem % 2 === 0) ? 'Yang' : 'Yin';

        return ['animal' => $animal, 'element' => $element, 'yin_yang' => $yinYang];
    }
}
