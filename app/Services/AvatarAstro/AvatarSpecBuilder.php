<?php

namespace App\Services\AvatarAstro;

use App\Models\User;
use App\Services\Astro\MoonSignCalculator;
use Carbon\CarbonImmutable;

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

        $moonSign = $this->computeMoonSign($user);
        if ($moonSign === '') {
            throw new \InvalidArgumentException('Signe lunaire indisponible (date de naissance requise).');
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

    private function computeMoonSign(User $user): string
    {
        $dob = $user->date_of_birth;
        if (!$dob) {
            return '';
        }

        $tz = self::DEFAULT_TZ;
        $date = CarbonImmutable::instance($dob);

        $time = trim((string) ($user->birth_time ?? ''));
        if ($time === '') {
            // No time: approximate with local noon.
            $time = '12:00';
        }

        try {
            $dt = CarbonImmutable::parse($date->format('Y-m-d') . ' ' . $time, $tz);
        } catch (\Throwable) {
            $dt = $date->setTime(12, 0);
        }

        return (string) (MoonSignCalculator::moonSign($dt, $tz) ?? '');
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
            $element = trim((string) ($ch['element'] ?? ''));
            $polarity = trim((string) ($ch['polarity'] ?? ''));
            $joined = trim(implode(' ', array_values(array_filter([$polarity, $element, $animal], fn ($v) => trim((string) $v) !== ''))));
            if ($joined !== '') {
                return $joined;
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

        // Take the last token (spec requirement).
        $tokens = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $last = (string) ($tokens[count($tokens) - 1] ?? $raw);
        $k = $this->key($last);

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
