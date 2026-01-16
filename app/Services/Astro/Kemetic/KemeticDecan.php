<?php

namespace App\Services\Astro\Kemetic;

final class KemeticDecan
{
    public static function nameFromIndex(int $kemeticIndex): string
    {
        $names = self::names();

        return (string) ($names[$kemeticIndex] ?? '');
    }

    /**
     * @return array{
     *   sun_lon: float,
     *   sun_sign: string,
     *   sun_deg_in_sign: float,
     *   decan_within_sign: 1|2|3,
     *   kemetic_decan_index: int,
     *   kemetic_decan_label: string,
     *   kemetic_decan_keyword: string
     * }
     */
    public static function fromSunLongitude(float $sunLongitude): array
    {
        $normalized = fmod($sunLongitude, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        $signIndex = (int) floor($normalized / 30.0); // 0..11
        $signs = [
            'Bélier',
            'Taureau',
            'Gémeaux',
            'Cancer',
            'Lion',
            'Vierge',
            'Balance',
            'Scorpion',
            'Sagittaire',
            'Capricorne',
            'Verseau',
            'Poissons',
        ];

        $sunSign = (string) ($signs[$signIndex] ?? '');
        $degInSign = $normalized - ($signIndex * 30.0);

        $decanWithin = (int) floor($degInSign / 10.0) + 1;
        if ($decanWithin < 1) $decanWithin = 1;
        if ($decanWithin > 3) $decanWithin = 3;

        $kemeticIndex = ($signIndex * 3) + $decanWithin; // 1..36

        $ordinal = match ($decanWithin) {
            1 => '1er',
            2 => '2e',
            default => '3e',
        };

        $label = $sunSign !== ''
            ? ($sunSign . ' — ' . $ordinal . ' décan')
            : ('Décan #' . $kemeticIndex);

        $keywords = self::keywords();
        $keyword = (string) (($keywords[$sunSign][$decanWithin] ?? '') ?: '');

        return [
            'sun_lon' => $normalized,
            'sun_sign' => $sunSign,
            'sun_deg_in_sign' => $degInSign,
            'decan_within_sign' => $decanWithin,
            'kemetic_decan_index' => $kemeticIndex,
            'kemetic_decan_label' => $label,
            'kemetic_decan_keyword' => $keyword,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function keywords(): array
    {
        return [
            'Bélier' => [1 => 'Élan', 2 => 'Courage', 3 => 'Percée'],
            'Taureau' => [1 => 'Stabilité', 2 => 'Ancrage', 3 => 'Patience'],
            'Gémeaux' => [1 => 'Curiosité', 2 => 'Agilité', 3 => 'Connexion'],
            'Cancer' => [1 => 'Protection', 2 => 'Cocon', 3 => 'Intuition'],
            'Lion' => [1 => 'Rayonnement', 2 => 'Créativité', 3 => 'Leadership'],
            'Vierge' => [1 => 'Clarté', 2 => 'Précision', 3 => 'Service'],
            'Balance' => [1 => 'Harmonie', 2 => 'Lien', 3 => 'Justesse'],
            'Scorpion' => [1 => 'Profondeur', 2 => 'Intensité', 3 => 'Transformation'],
            'Sagittaire' => [1 => 'Cap', 2 => 'Expansion', 3 => 'Exploration'],
            'Capricorne' => [1 => 'Structure', 2 => 'Ambition', 3 => 'Maîtrise'],
            'Verseau' => [1 => 'Idées', 2 => 'Innovation', 3 => 'Indépendance'],
            'Poissons' => [1 => 'Imagination', 2 => 'Empathie', 3 => 'Inspiration'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function names(): array
    {
        return [
            1 => 'Khent-kheru',
            2 => 'Qet',
            3 => 'Sasaqet',
            4 => 'Art',
            5 => 'Khau',
            6 => 'Remen-heru-an-Sah',
            7 => 'Mestcher-Sah',
            8 => 'Remen-kher-Sah',
            9 => 'A-Sah',
            10 => 'Septet',
            11 => 'Tepa-Kenmut',
            12 => 'Kenmut',
            13 => 'Kher-khept-Kenmut',
            14 => 'Ha-tchat',
            15 => 'Pehui-tchat',
            16 => 'Themat-hert',
            17 => 'Themat-khert',
            18 => 'Ustha',
            19 => 'Bekatha',
            20 => 'Tepa-khentet',
            21 => 'Khentet-hert',
            22 => 'Khentet-khert',
            23 => 'Themes-en-khentet',
            24 => 'Sapt-khennu',
            25 => 'Her-ab-uaa',
            26 => 'Shesmu',
            27 => 'Kenmu',
            28 => 'Semtet',
            29 => 'Tepa-semt',
            30 => 'Sert',
            31 => 'Sasa-sert',
            32 => 'Kher-khept-sert',
            33 => 'Khukhu',
            34 => 'Baba',
            35 => 'Khent-heru',
            36 => 'Her-ab-khentu',
        ];
    }
}
