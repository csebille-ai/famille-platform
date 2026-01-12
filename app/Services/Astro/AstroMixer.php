<?php

namespace App\Services\Astro;

class AstroMixer
{
    /**
     * @param array{western_sign?:string,western_element?:string,chinese_animal?:string,chinese_element?:string,chinese_yin_yang?:string,life_path?:int,ascendant_sign?:string} $base
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
        $lifePath = (int) ($base['life_path'] ?? 0);

        $archetype = match ($westElement) {
            'Feu' => in_array($lifePath, [1, 3, 5, 11], true) ? 'Explorateur' : 'Moteur',
            'Terre' => in_array($lifePath, [4, 8, 22], true) ? 'Gardien' : 'Bâtisseur',
            'Air' => in_array($lifePath, [7], true) ? 'Stratège' : 'Messager',
            'Eau' => in_array($lifePath, [2, 6, 9], true) ? 'Soigneur' : 'Intuitif',
            default => 'Équilibriste',
        };

        $talentPool = [
            'Explorateur' => ['Ose commencer', 'Réveille l’énergie du groupe', 'Décide vite'],
            'Moteur' => ['Entraîne les autres', 'Transforme une idée en action', 'Garde le cap'],
            'Gardien' => ['Protège et sécurise', 'Structure le quotidien', 'Rassure naturellement'],
            'Bâtisseur' => ['Optimise et organise', 'Fait grandir ce qui marche', 'Sens du concret'],
            'Messager' => ['Crée du lien', 'Apporte de la légèreté', 'Trouve les mots justes'],
            'Stratège' => ['Analyse finement', 'Prévoit les risques', 'Améliore les plans'],
            'Soigneur' => ['Écoute profondément', 'Apaise les tensions', 'Sait réconforter'],
            'Intuitif' => ['Capte l’ambiance', 'Imagine des solutions', 'S’adapte vite'],
            'Équilibriste' => ['Sait faire la part des choses', 'Fédère', 'Cherche l’harmonie'],
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

        $talents = array_slice($talentPool[$archetype] ?? ['Curiosité', 'Adaptation', 'Humour'], 0, 3);

        $signatureParts = [];
        if ($westSign !== '') $signatureParts[] = "$westSign";
        if ($asc !== '' && $asc !== $westSign) $signatureParts[] = "Asc $asc";
        if ($animal !== '') $signatureParts[] = "$yy $chElement $animal";
        if ($lifePath > 0) $signatureParts[] = "Chemin $lifePath";

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
