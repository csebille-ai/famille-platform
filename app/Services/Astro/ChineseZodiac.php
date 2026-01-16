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

    /**
     * UI label.
     * Examples:
     * - "Dragon de Métal (Yang)"
     * - "Rat d'Eau (Yin)"
     */
    public static function formatDisplayLabel(string $animal, string $element, string $yinYang): string
    {
        $animal = trim($animal);
        $element = trim($element);
        $yinYang = trim($yinYang);

        if ($animal === '') {
            return '';
        }

        $label = $animal;

        if ($element !== '') {
            $needsApostrophe = (bool) preg_match('/^[AEIOUYÀÂÄÉÈÊËÎÏÔÖÙÛÜŒ]/u', $element);
            $label .= $needsApostrophe ? " d'" . $element : ' de ' . $element;
        }

        if ($yinYang !== '') {
            $label .= ' (' . $yinYang . ')';
        }

        return $label;
    }

    /**
     * Best-effort formatter for legacy strings like "Yang Métal Dragon".
     */
    public static function formatLegacyDisplayString(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) < 3) {
            return $raw;
        }

        $first = (string) ($tokens[0] ?? '');
        if (!in_array($first, ['Yin', 'Yang'], true)) {
            return $raw;
        }

        $polarity = $first;
        $element = (string) ($tokens[1] ?? '');
        $animal = trim(implode(' ', array_slice($tokens, 2)));

        $formatted = self::formatDisplayLabel($animal, $element, $polarity);
        return $formatted !== '' ? $formatted : $raw;
    }
}
