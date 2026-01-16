<?php

namespace App\Services\AvatarAstro;

use App\Models\User;

class AvatarSpecBuilder
{
    public const VERSION = 'avatar_astro_v1';

    private const DEFAULT_TZ = 'Europe/Paris';

    /**
     * @return array{
     *   sun_sign:string,
     *   asc_sign:string,
     *   moon_sign:string,
     *   sun_element:'Terre'|'Feu'|'Air'|'Eau',
     *   asc_element:'Terre'|'Feu'|'Air'|'Eau',
     *   moon_element:'Terre'|'Feu'|'Air'|'Eau',
     *   chinese_animal:string,
     *   life_path:int,
     *   generated_at:string,
     *   version:string
     * }
     */
    public function build(User $user): array
    {
        $user->loadMissing('astroProfile');

        $sig = is_array($user->astro_signature_json ?? null) ? (array) $user->astro_signature_json : [];
        $p = $user->astroProfile;

        $sunSign = trim((string) ($sig['sun_sign'] ?? ($p?->western_sign ?? '')));
        $ascSign = trim((string) ($sig['ascendant'] ?? ($p?->ascendant_sign ?? '')));

        if ($sunSign === '' || $ascSign === '') {
            throw new \InvalidArgumentException('Signature astro incomplète (sun_sign + ascendant requis).');
        }

        $moonSign = $this->moonSignFromCache($user);
        if ($moonSign === '') {
            throw new \InvalidArgumentException('Signe lunaire indisponible (date + heure de naissance requises).');
        }

        $sunElement = $this->elementFromWesternSign($sunSign);
        $ascElement = $this->elementFromWesternSign($ascSign);
        $moonElement = $this->elementFromWesternSign($moonSign);

        $chRaw = $this->rawChineseString($sig, $p);
        $chineseAnimal = $this->normalizeChineseAnimal($chRaw);
        if ($chineseAnimal === '') {
            throw new \InvalidArgumentException('Astro chinoise manquante.');
        }

        $lifePath = $this->parseLifePath($sig['life_path'] ?? ($p?->life_path ?? null));
        if ($lifePath <= 0) {
            throw new \InvalidArgumentException('Numérologie manquante (chemin de vie).');
        }

        return [
            'sun_sign' => $sunSign,
            'asc_sign' => $ascSign,
            'moon_sign' => $moonSign,
            'sun_element' => $sunElement,
            'asc_element' => $ascElement,
            'moon_element' => $moonElement,
            'chinese_animal' => $chineseAnimal,
            'life_path' => $lifePath,
            'generated_at' => now()->toISOString(),
            'version' => self::VERSION,
        ];
    }

    private function moonSignFromCache(User $user): string
    {
        $sig = is_array($user->astro_signature_json ?? null) ? (array) $user->astro_signature_json : [];
        $fromSig = trim((string) ($sig['moon_sign'] ?? ''));
        if ($fromSig !== '') {
            return $fromSig;
        }

        $p = $user->astroProfile;
        $fromProfile = trim((string) ($p?->moon_sign ?? ''));
        if ($fromProfile !== '') {
            return $fromProfile;
        }

        // Require time for reliable Moon sign.
        if (!$user->date_of_birth || trim((string) ($user->birth_time ?? '')) === '') {
            return '';
        }

        // If the cache is empty despite required inputs, ask user to trigger a recompute.
        // (ComputeAstroProfile runs on user updates and via astro:recompute.)
        return '';
    }

    /**
     * @return 'Terre'|'Feu'|'Air'|'Eau'
     */
    private function elementFromWesternSign(string $sign): string
    {
        $s = $this->key($sign);

        return match ($s) {
            'belier', 'aries', 'lion', 'leo', 'sagittaire', 'sagittarius' => 'Feu',
            'taureau', 'taurus', 'vierge', 'virgo', 'capricorne', 'capricorn' => 'Terre',
            'gemeaux', 'gemini', 'balance', 'libra', 'verseau', 'aquarius' => 'Air',
            default => 'Eau',
        };
    }

    private function rawChineseString(array $sig, $profile): string
    {
        $ch = $sig['chinese'] ?? null;
        if (is_array($ch)) {
            $animal = trim((string) ($ch['animal'] ?? ''));
            if ($animal !== '') {
                return $animal;
            }
        }

        if (is_string($ch) && trim($ch) !== '') {
            return trim($ch);
        }

        $fallback = trim((string) ($profile?->chinese_animal ?? ''));
        if ($fallback !== '') {
            return $fallback;
        }

        return '';
    }

    private function normalizeChineseAnimal(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // New display format example: "Dragon de Métal (Yang)".
        // If it matches, take the first word as the animal.
        $candidate = '';
        if (preg_match('/^([\p{L}]+)\s+de\s+/u', $raw, $m)) {
            $candidate = (string) $m[1];
        } else {
            // Legacy formats: "Yang Métal Dragon" or "Dragon".
            // Take the last token (spec requirement).
            $tokens = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $candidate = (string) ($tokens[count($tokens) - 1] ?? $raw);
        }

        $k = $this->key($candidate);

        // Normalize variants.
        if (in_array($k, ['boeuf', 'bœuf', 'buffle'], true)) {
            return 'Boeuf';
        }
        if (in_array($k, ['chevre', 'chèvre', 'mouton'], true)) {
            return 'Chevre';
        }
        if (in_array($k, ['cochon', 'porc', 'sanglier'], true)) {
            return 'Cochon';
        }

        return match ($k) {
            'rat' => 'Rat',
            'tigre' => 'Tigre',
            'lapin' => 'Lapin',
            'dragon' => 'Dragon',
            'serpent' => 'Serpent',
            'cheval' => 'Cheval',
            'singe' => 'Singe',
            'coq' => 'Coq',
            'chien' => 'Chien',
            default => '',
        };
    }

    private function parseLifePath(mixed $raw): int
    {
        if (is_int($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return 0;
        }

        // Prefer master numbers.
        if (preg_match('/\b(11|22)\b/u', $s, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/\b([1-9])\b/u', $s, $m)) {
            return (int) $m[1];
        }

        // Last digit fallback.
        if (preg_match('/([0-9])\D*$/u', $s, $m)) {
            $n = (int) $m[1];
            return ($n >= 1 && $n <= 9) ? $n : 0;
        }

        return 0;
    }

    private function key(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace(['œ'], ['oe'], $s);
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
        }
        $s = preg_replace('/[^a-z0-9]+/u', '', $s) ?? $s;
        return $s;
    }
}
