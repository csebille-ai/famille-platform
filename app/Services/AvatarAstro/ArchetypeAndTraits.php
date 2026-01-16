<?php

namespace App\Services\AvatarAstro;

use App\Models\User;

class ArchetypeAndTraits
{
    public static function chineseTrait(string $animal): string
    {
        return match ($animal) {
            'Rat' => 'stratège',
            'Boeuf' => 'endurant',
            'Tigre' => 'intrépide',
            'Lapin' => 'charmant',
            'Dragon' => 'magnétique',
            'Serpent' => 'lucide',
            'Cheval' => 'libre',
            'Chevre' => 'créatif',
            'Singe' => 'malicieux',
            'Coq' => 'franc',
            'Chien' => 'loyal',
            'Cochon' => 'généreux',
            default => '',
        };
    }

    public static function kemeticDecanTrait(int $kemeticIndex): string
    {
        if ($kemeticIndex < 1 || $kemeticIndex > 36) {
            return '';
        }

        $signIndex = intdiv($kemeticIndex - 1, 3); // 0..11
        $within = (($kemeticIndex - 1) % 3) + 1; // 1..3

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

        $keywords = [
            'Bélier' => [1 => 'élan', 2 => 'courage', 3 => 'percée'],
            'Taureau' => [1 => 'stabilité', 2 => 'ancrage', 3 => 'patience'],
            'Gémeaux' => [1 => 'curiosité', 2 => 'agilité', 3 => 'connexion'],
            'Cancer' => [1 => 'protection', 2 => 'cocon', 3 => 'intuition'],
            'Lion' => [1 => 'rayonnement', 2 => 'créativité', 3 => 'leadership'],
            'Vierge' => [1 => 'clarté', 2 => 'précision', 3 => 'service'],
            'Balance' => [1 => 'harmonie', 2 => 'lien', 3 => 'justesse'],
            'Scorpion' => [1 => 'profondeur', 2 => 'intensité', 3 => 'transformation'],
            'Sagittaire' => [1 => 'cap', 2 => 'expansion', 3 => 'exploration'],
            'Capricorne' => [1 => 'structure', 2 => 'ambition', 3 => 'maîtrise'],
            'Verseau' => [1 => 'idées', 2 => 'innovation', 3 => 'indépendance'],
            'Poissons' => [1 => 'imagination', 2 => 'empathie', 3 => 'inspiration'],
        ];

        $sign = (string) ($signs[$signIndex] ?? '');
        return (string) (($keywords[$sign][$within] ?? '') ?: '');
    }

    /**
     * @param array{sun_element:string,asc_element:string,moon_element:string,chinese_animal:string,kemetic_decan_index:int} $spec
     * @return array{archetype_title:string,traits_canon:list<string>,traits_surannes:list<string>}
     */
    public function build(User $user, array $spec): array
    {
        $title = $this->archetypeTitle((string) $spec['sun_element'], (string) $spec['asc_element']);

        $traitsCanon = $this->buildTraitsCanon(
            (int) ($user->id ?? 0),
            (string) $spec['sun_element'],
            (string) $spec['asc_element'],
            (string) $spec['moon_element'],
            (string) $spec['chinese_animal'],
            (int) $spec['kemetic_decan_index'],
        );

        $mapper = new TraitsSurannesMapper();
        $traitsSur = [];
        foreach ($traitsCanon as $t) {
            $traitsSur[] = $mapper->toSuranne((int) ($user->id ?? 0), $t);
        }

        return [
            'archetype_title' => $title,
            'traits_canon' => $traitsCanon,
            'traits_surannes' => $traitsSur,
        ];
    }

    private function archetypeTitle(string $sunElement, string $ascElement): string
    {
        $s = trim($sunElement);
        $a = trim($ascElement);

        return match ($s) {
            'Terre' => match ($a) {
                'Terre' => 'Le Pilier',
                'Feu' => 'Le Bâtisseur audacieux',
                'Air' => 'L’Architecte',
                default => 'Le Gardien',
            },
            'Feu' => match ($a) {
                'Terre' => 'Le Leader fiable',
                'Feu' => 'L’Éclaireur',
                'Air' => 'Le Visionnaire',
                default => 'Le Champion du cœur',
            },
            'Air' => match ($a) {
                'Terre' => 'L’Organisateur',
                'Feu' => 'Le Catalyseur',
                'Air' => 'Le Connecteur',
                default => 'Le Diplomate',
            },
            default => match ($a) {
                'Terre' => 'Le Soignant pragmatique',
                'Feu' => 'Le Protecteur combatif',
                'Air' => 'Le Conseiller',
                default => 'L’Empathe',
            },
        };
    }

    /**
     * @return list<string>
     */
    private function buildTraitsCanon(int $userId, string $sunElement, string $ascElement, string $moonElement, string $chineseAnimal, int $kemeticIndex): array
    {
        $chosen = [];

        $elementTraits = [
            'Terre' => ['solide', 'posé', 'pragmatique', 'fiable'],
            'Feu' => ['audacieux', 'enthousiaste', 'leader', 'impulsif'],
            'Air' => ['curieux', 'malin', 'social', 'inventif'],
            'Eau' => ['intuitif', 'doux', 'profond', 'protecteur'],
        ];

        $pick = function (string $bucket, array $list) use (&$chosen, $userId) {
            if ($list === []) {
                return;
            }

            $idx = $this->stableIndex($userId . '|' . $bucket) % count($list);
            for ($i = 0; $i < count($list); $i++) {
                $t = $list[($idx + $i) % count($list)];
                if (!in_array($t, $chosen, true)) {
                    $chosen[] = $t;
                    return;
                }
            }

            // Last resort: allow duplicate.
            $chosen[] = $list[$idx];
        };

        $pick('sun-element:' . $sunElement, $elementTraits[$sunElement] ?? []);
        $pick('asc-element:' . $ascElement, $elementTraits[$ascElement] ?? []);
        $pick('moon-element:' . $moonElement, $elementTraits[$moonElement] ?? []);

        $totemTrait = self::chineseTrait($chineseAnimal);
        if ($totemTrait !== '' && !in_array($totemTrait, $chosen, true)) {
            $chosen[] = $totemTrait;
        }

        $kemeticTrait = self::kemeticDecanTrait($kemeticIndex);
        if ($kemeticTrait !== '' && !in_array($kemeticTrait, $chosen, true)) {
            $chosen[] = $kemeticTrait;
        }

        // Optional “liant” if we ended up with < 5 traits (because of duplicates).
        if (count($chosen) < 5) {
            $linking = ['utile', 'droit', 'constant'];
            $pick('linking', $linking);
        }

        return array_values(array_slice($chosen, 0, 6));
    }

    private function stableIndex(string $seed): int
    {
        // stable 32-bit index
        $h = sha1($seed);
        return (int) hexdec(substr($h, 0, 8));
    }
}
