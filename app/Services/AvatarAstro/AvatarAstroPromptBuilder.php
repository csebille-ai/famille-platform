<?php

namespace App\Services\AvatarAstro;

use App\Models\User;
use Carbon\CarbonImmutable;

class AvatarAstroPromptBuilder
{
    /**
     * @param array<string,mixed> $avatarSpec
     * @param array{archetype_title:string,traits_canon:list<string>,traits_surannes:list<string>} $meta
     * @return array{prompt:string,negative_prompt:string,seed:string}
     */
    public function build(User $user, array $avatarSpec, array $meta): array
    {
        $sunElement = (string) ($avatarSpec['sun_element'] ?? '');
        $ascElement = (string) ($avatarSpec['asc_element'] ?? '');

        if (!in_array($sunElement, ['Terre', 'Feu', 'Air', 'Eau'], true)) {
            throw new \InvalidArgumentException('AvatarSpec invalide (sun_element).');
        }
        if (!in_array($ascElement, ['Terre', 'Feu', 'Air', 'Eau'], true)) {
            throw new \InvalidArgumentException('AvatarSpec invalide (asc_element).');
        }

        $palette = $this->paletteCue($sunElement, $ascElement);
        $ageCue = $this->ageCue($user);

        // IMPORTANT: do not instruct the model to draw icons/symbols/animals/text.
        $prompt = implode("\n", array_values(array_filter([
            'Square 1:1 portrait (head and shoulders only), stylized 2D Franco-Belgian comic / bande dessinée (BD) illustration style.',
            'Clean ink linework, simplified shapes, cel shading with subtle paper texture; tasteful and premium.',
            'Simple background: smooth light gradient, no scenery.',
            $ageCue,
            'Modern, warm, approachable; generic face (do not resemble any real person).',
            'Astro influence ONLY via color palette and ambience: ' . $palette . '.',
            'Centered composition, crop-safe margins (do not cut head/hair/shoulders).',
        ])));

        $negative = implode(", ", [
            'coat of arms', 'blazon', 'shield', 'crest', 'emblem', 'heraldry',
            'text', 'letters', 'numbers', 'logo', 'watermark', 'signature',
            'animals', 'multiple symbols', 'zodiac symbols', 'glyphs',
            'photorealistic', 'photo', 'photography', 'camera', 'lens', 'realistic skin pores', 'hyperrealistic', 'ultra realistic',
            '3d render', 'cgi', 'octane render', 'unreal engine', 'ray tracing',
            'child', 'kid', 'teenager', 'young girl', 'baby face',
            'weapons', 'blood', 'nudity',
        ]);

        $seed = sha1((string) ($user->id ?? 0) . '|' . json_encode($avatarSpec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'prompt' => $prompt,
            'negative_prompt' => $negative,
            'seed' => $seed,
        ];
    }

    private function paletteCue(string $sunElement, string $ascElement): string
    {
        $primary = match ($sunElement) {
            'Terre' => 'warm neutral earth tones (sand, clay, olive, stone)',
            'Feu' => 'warm vivid tones (amber, coral, deep red, gold)',
            'Air' => 'cool bright tones (sky, pale cyan, light violet, silver)',
            default => 'cool deep tones (navy, teal, soft indigo, moonlit gray)',
        };

        $accent = match ($ascElement) {
            'Terre' => 'grounded accents (soft brown, moss, muted beige)',
            'Feu' => 'energized accents (golden highlights, warm glow)',
            'Air' => 'airy accents (light gradients, clean whites, crisp contrast)',
            default => 'watery accents (soft teal, gentle shimmer, calm contrast)',
        };

        return $primary . ' with ' . $accent;
    }

    private function ageCue(User $user): string
    {
        $dob = $user->date_of_birth;
        if (!$dob) {
            return 'Adult character, age-appropriate mature features; not childlike.';
        }

        try {
            $age = CarbonImmutable::instance($dob)->diffInYears(CarbonImmutable::now());
        } catch (\Throwable) {
            return 'Adult character, age-appropriate mature features; not childlike.';
        }

        // Clamp to avoid extreme/incoherent guidance.
        $age = max(18, min(90, (int) $age));

        if ($age >= 50) {
            return 'Mature adult (around ' . $age . ' years old), age-appropriate face; avoid youthful/teen look.';
        }

        if ($age >= 35) {
            return 'Adult (around ' . $age . ' years old), age-appropriate face; avoid teen look.';
        }

        return 'Adult (around ' . $age . ' years old), not childlike.';
    }
}
