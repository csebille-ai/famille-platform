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

    public static function numerologyTrait(int $lifePath): string
    {
        return match ($lifePath) {
            1 => 'pionnier',
            2 => 'harmonieux',
            3 => 'drôle',
            4 => 'méthodique',
            5 => 'aventurier',
            6 => 'bienveillant',
            7 => 'sage',
            8 => 'ambitieux',
            9 => 'inspirant',
            11 => 'visionnaire',
            22 => 'bâtisseur',
            default => '',
        };
    }

    /**
     * @param array{sun_element:string,asc_element:string,moon_element:string,chinese_animal:string,life_path:int} $spec
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
            (int) $spec['life_path'],
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
    private function buildTraitsCanon(int $userId, string $sunElement, string $ascElement, string $moonElement, string $chineseAnimal, int $lifePath): array
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

        $lifeTrait = self::numerologyTrait($lifePath);
        if ($lifeTrait !== '' && !in_array($lifeTrait, $chosen, true)) {
            $chosen[] = $lifeTrait;
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
