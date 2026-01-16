<?php

namespace App\Services\Astro;

class AstroMixer
{
    /**
     * @param array{western_sign?:string,western_element?:string,chinese_animal?:string,chinese_element?:string,chinese_yin_yang?:string,ascendant_sign?:string,kemetic_decan_index?:int} $base
     * @return array{archetype:string,talents:list<string>,weakness:string,signature:string}
     */
    public static function mix(array $base): array
    {
        $westElement = (string) ($base['western_element'] ?? '');
        $westSign = (string) ($base['western_sign'] ?? '');
        $asc = (string) ($base['ascendant_sign'] ?? '');
        $animal = (string) ($base['chinese_animal'] ?? '');
        $chElement = (string) ($base['chinese_element'] ?? '');
        $yy = (string) ($base['chinese_yin_yang'] ?? '');
        $kemeticIndex = (int) ($base['kemetic_decan_index'] ?? 0);

        // Prefer sign-based archetypes so two people of the same element (e.g. Balance vs Verseau)
        // don't always end up with the exact same archetype.
        $archetypeBySign = [
            'Bélier' => 'Moteur',
            'Taureau' => 'Gardien',
            'Gémeaux' => 'Messager',
            'Cancer' => 'Soigneur',
            'Lion' => 'Explorateur',
            'Vierge' => 'Bâtisseur',
            'Balance' => 'Équilibriste',
            'Scorpion' => 'Intuitif',
            'Sagittaire' => 'Explorateur',
            'Capricorne' => 'Bâtisseur',
            'Verseau' => 'Stratège',
            'Poissons' => 'Soigneur',
        ];

        $archetype = $archetypeBySign[$westSign]
            ?? match ($westElement) {
                'Feu' => 'Moteur',
                'Terre' => 'Gardien',
                'Air' => 'Messager',
                'Eau' => 'Soigneur',
                default => 'Équilibriste',
            };

        $talentPool = [
            'Explorateur' => ['Ose commencer', 'Réveille l’énergie du groupe', 'Décide vite', 'Ouvre des chemins', 'Motive par l’exemple', 'Reste curieux'],
            'Moteur' => ['Entraîne les autres', 'Transforme une idée en action', 'Garde le cap', 'Lance le mouvement', 'Tient la cadence', 'Fait avancer'],
            'Gardien' => ['Protège et sécurise', 'Structure le quotidien', 'Rassure naturellement', 'Crée un cocon', 'Tient les repères', 'Veille aux besoins'],
            'Bâtisseur' => ['Optimise et organise', 'Fait grandir ce qui marche', 'Sens du concret', 'Améliore les systèmes', 'Rend les choses solides', 'Pose des fondations'],
            'Messager' => ['Crée du lien', 'Apporte de la légèreté', 'Trouve les mots justes', 'Clarifie', 'Met en relation', 'Fait circuler les idées'],
            'Stratège' => ['Analyse finement', 'Prévoit les risques', 'Améliore les plans', 'Hiérarchise', 'Anticipe', 'Voit les patterns'],
            'Soigneur' => ['Écoute profondément', 'Apaise les tensions', 'Sait réconforter', 'Accueille sans juger', 'Répare en douceur', 'Réconcilie'],
            'Intuitif' => ['Capte l’ambiance', 'Imagine des solutions', 'S’adapte vite', 'Suit son instinct', 'Sent le bon timing', 'Inspire'],
            'Équilibriste' => ['Sait faire la part des choses', 'Fédère', 'Cherche l’harmonie', 'Pacifie', 'Relativise', 'Trouve le juste milieu'],
        ];

        $weakness = match ($archetype) {
            'Explorateur' => 'Peut foncer sans mesurer la fatigue',
            'Moteur' => 'Peut s’impatienter quand ça traîne',
            'Gardien' => 'Peut trop vouloir contrôler',
            'Bâtisseur' => 'Peut devenir rigide sur les détails',
            'Messager' => 'Peut se disperser',
            'Stratège' => 'Peut trop mentaliser',
            'Soigneur' => 'Peut absorber les émotions des autres',
            'Intuitif' => 'Peut fluctuer avec l’humeur',
            default => 'Peut chercher l’accord de tous',
        };

        $pool = $talentPool[$archetype] ?? ['Curiosité', 'Adaptation', 'Humour'];

        // Deterministic variation: same inputs => same talents.
        // Different ascendant/chinese/kemetic decan will rotate the pool.
        $seed = implode('|', [$westSign, $asc, $animal, $chElement, $yy, (string) $kemeticIndex, $archetype]);
        $offset = (int) (abs((int) crc32($seed)) % max(1, count($pool)));
        $rotated = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));
        $talents = array_slice($rotated, 0, 3);

        $signatureParts = [];
        if ($westSign !== '') $signatureParts[] = "$westSign";
        if ($asc !== '' && $asc !== $westSign) $signatureParts[] = "Asc $asc";
        if ($animal !== '') $signatureParts[] = ChineseZodiac::formatDisplayLabel($animal, $chElement, $yy);
        if ($kemeticIndex > 0) $signatureParts[] = "Décan #$kemeticIndex";

        $signature = implode(' · ', $signatureParts);
        if ($signature === '') {
            $signature = 'Profil en cours (ajoute date/heure/lieu)';
        }

        return [
            'archetype' => $archetype,
            'talents' => $talents,
            'weakness' => $weakness,
            'signature' => $signature,
        ];
    }
}
