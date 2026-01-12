<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class AstroCardPromptBuilder
{
    /**
     * @param array<string,mixed> $signature
     * @return array{prompt:string, negative_prompt:string, seed:string, title:string, signature_line:string, tag1:string, tag2:string, tag3:string, rpg_class:string}
     */
    public function build(User $user, array $signature): array
    {
        $sun = trim((string) ($signature['sun_sign'] ?? ''));
        $asc = trim((string) ($signature['ascendant'] ?? ''));
        $lifePathRaw = $signature['life_path'] ?? null;
        $lifePath = is_numeric($lifePathRaw) ? (int) $lifePathRaw : null;

        $chinese = is_array($signature['chinese'] ?? null) ? (array) $signature['chinese'] : [];
        $polarity = trim((string) ($chinese['polarity'] ?? ''));
        $element = trim((string) ($chinese['element'] ?? ''));
        $animal = trim((string) ($chinese['animal'] ?? ''));

        $archetype = trim((string) ($signature['archetype'] ?? ''));
        $talentsRaw = $signature['talents'] ?? [];
        $talents = is_array($talentsRaw) ? array_values(array_filter(array_map('strval', $talentsRaw))) : [];

        $vigilance = trim((string) ($signature['vigilance'] ?? ''));

        if ($sun === '' || $asc === '' || $polarity === '' || $element === '' || $animal === '' || !$lifePath || $archetype === '') {
            throw new \InvalidArgumentException('Signature astro incomplète (sun_sign, ascendant, chinese.*, life_path, archetype requis).');
        }

        $signatureLine = sprintf(
            '%s • Asc %s • %s %s %s • Chemin %d',
            $sun,
            $asc,
            $polarity,
            $element,
            $animal,
            $lifePath,
        );

        $title = 'LE ' . mb_strtoupper($archetype, 'UTF-8');

        $rpgClass = $this->mapArchetypeToRpgClass($archetype);
        $palette = $this->mapChineseMoodToPalette($polarity, $element);
        $sunMotif = $this->mapSunSignToMotif($sun);
        $ascAccent = $this->mapAscendantToAccent($asc);
        $animalMotif = $this->mapChineseAnimalToCompanionOrSeal($animal);

        [$tag1, $tag2, $tag3] = $this->buildTags($talents);

        $seed = sha1((string) $user->id . '|' . $this->canonicalJson($signature));

        [$prompt, $negativePrompt] = $this->buildPrompt([
            'TITLE' => $title,
            'SIGNATURE_LINE' => $signatureLine,
            'RPG_CLASS' => $rpgClass,
            'SUN_SIGN' => $sun,
            'ASCENDANT' => $asc,
            'POLARITY' => $polarity,
            'CHINESE_ELEMENT' => $element,
            'CHINESE_ANIMAL' => $animal,
            'LIFE_PATH' => (string) $lifePath,
            'TAG1' => $tag1,
            'TAG2' => $tag2,
            'TAG3' => $tag3,
            'SUN_MOTIF' => $sunMotif,
            'ASC_ACCENT' => $ascAccent,
            'PALETTE' => $palette,
            'ANIMAL_MOTIF' => $animalMotif,
            'VIGILANCE' => $vigilance,
        ]);

        return [
            'prompt' => $prompt,
            'negative_prompt' => $negativePrompt,
            'seed' => $seed,
            'title' => $title,
            'signature_line' => $signatureLine,
            'tag1' => $tag1,
            'tag2' => $tag2,
            'tag3' => $tag3,
            'rpg_class' => $rpgClass,
        ];
    }

    /**
     * Convenience for UI overlays.
     *
     * @param array<string,mixed> $signature
     * @return array{title:string, signature_line:string, tags:list<string>}
     */
    public function overlay(User $user, array $signature): array
    {
        $built = $this->build($user, $signature);
        return [
            'title' => $built['title'],
            'signature_line' => $built['signature_line'],
            'tags' => array_values(array_filter([$built['tag1'], $built['tag2'], $built['tag3']], fn ($t) => is_string($t) && trim($t) !== '' && trim($t) !== '—')),
        ];
    }

    /**
     * @param list<string> $talents
     * @return array{0:string,1:string,2:string}
     */
    private function buildTags(array $talents): array
    {
        $tags = [];
        foreach ($talents as $t) {
            $t = trim($t);
            if ($t === '') {
                continue;
            }

            // Heuristic: first word often is the core verb.
            $first = preg_split('/\s+/u', $t, 2)[0] ?? '';
            $first = trim((string) preg_replace('/[^\p{L}\p{N}-]+/u', '', $first));
            if ($first === '') {
                continue;
            }

            $tags[] = mb_convert_case($first, MB_CASE_TITLE, 'UTF-8');
            if (count($tags) >= 3) {
                break;
            }
        }

        while (count($tags) < 3) {
            $tags[] = '—';
        }

        return [$tags[0], $tags[1], $tags[2]];
    }

    /**
     * @param array{TITLE:string,SIGNATURE_LINE:string,RPG_CLASS:string,SUN_SIGN:string,ASCENDANT:string,POLARITY:string,CHINESE_ELEMENT:string,CHINESE_ANIMAL:string,LIFE_PATH:string,TAG1:string,TAG2:string,TAG3:string,SUN_MOTIF:string,ASC_ACCENT:string,PALETTE:string,ANIMAL_MOTIF:string,VIGILANCE:string} $vars
     * @return array{0:string,1:string}
     */
    private function buildPrompt(array $vars): array
    {
        $template = <<<PROMPT
Modern tarot-RPG character card, premium editorial illustration, portrait 2:3.
Single fictional character (NOT a real person), stylized high-end, clean, collectible.

Card layout (generate the artwork ONLY — NO TEXT / NO LETTERS):
- Thin border, subtle rounded corners, off-white paper texture, soft grain.
- Reserve a clean blank area at top for the title (do not render any text).
- Reserve a clean blank area at bottom for a signature line (do not render any text).
- Reserve 3 small tag-pill shapes near the bottom (no text inside).

Character concept (derived from the signature):
- Archetype/class: “{{RPG_CLASS}}” — protective stance, calm authority, grounded silhouette.
- Sun sign {{SUN_SIGN}} motif: {{SUN_MOTIF}}.
- Ascendant {{ASCENDANT}} accent: {{ASC_ACCENT}}.
- {{POLARITY}} {{CHINESE_ELEMENT}} mood: {{PALETTE}}.
- Chinese animal {{CHINESE_ANIMAL}}: {{ANIMAL_MOTIF}}.
- Life path {{LIFE_PATH}}: incorporate an infinity ribbon + an {{LIFE_PATH}}-point geometric pattern (very subtle) in background.

Optional tension note (very subtle): {{VIGILANCE}}

Constraints:
- No neon, no cartoon, no institutional/insurance look, no medical symbols, no hearts, no hand icons.
- Clean negative space, modern collectible card, premium editorial.
PROMPT;

        $negative = 'text, letters, watermark, logo, real person, photoreal face, messy, clutter, low quality, blurry, extra limbs';

        $out = $template;
        foreach ($vars as $k => $v) {
            $out = str_replace('{{' . $k . '}}', (string) $v, $out);
        }

        return [$out, $negative];
    }

    private function mapArchetypeToRpgClass(string $archetype): string
    {
        $k = $this->key($archetype);
        $map = [
            'gardien' => 'Sentinelle',
            'protecteur' => 'Sentinelle',
            'sage' => 'Oracle',
            'visionnaire' => 'Oracle',
            'rebelle' => 'Duelliste',
            'aventurier' => 'Rôdeur',
            'createur' => 'Artisan Mystique',
            'artiste' => 'Artisan Mystique',
            'messager' => 'Héraut',
            'diplomate' => 'Héraut',
            'battisseur' => 'Architecte',
            'strategiste' => 'Architecte',
            'alchimiste' => 'Alchimiste',
            'magicien' => 'Arcaniste',
        ];

        return $map[$k] ?? 'Voyageur';
    }

    private function mapChineseMoodToPalette(string $polarity, string $element): string
    {
        $p = $this->key($polarity);
        $e = $this->key($element);

        $base = match ($e) {
            'metal' => 'brushed metal / graphite materials + ONE cold blue accent, high contrast, premium',
            'bois', 'wood' => 'aged wood / deep green materials + ONE warm gold accent, premium',
            'eau', 'water' => 'deep navy / silver materials + ONE icy cyan accent, premium',
            'feu', 'fire' => 'obsidian / brass materials + ONE crimson accent, premium',
            'terre', 'earth' => 'sandstone / bronze materials + ONE olive accent, premium',
            default => 'graphite materials + ONE accent color, premium',
        };

        // Polarity influences finish.
        if ($p === 'yin') {
            return 'matte, soft, restrained — ' . $base;
        }
        if ($p === 'yang') {
            return 'crisp, sharp, high contrast — ' . $base;
        }

        return $base;
    }

    private function mapSunSignToMotif(string $sun): string
    {
        $k = $this->key($sun);

        return match ($k) {
            'taureau', 'taurus' => 'sturdy, grounded proportions, rounded shapes, subtle stone/earth motifs',
            'belier', 'aries' => 'forward-leaning silhouette, confident lines, subtle horn/chevron echoes',
            'gemeaux', 'gemini' => 'twin motifs, mirrored details, light agile silhouette, airy negative space',
            'cancer' => 'protective shell-like curves, crescent shapes, soft lunar glow (subtle)',
            'lion', 'leo' => 'regal posture, sunburst geometry (minimal), bold central emblem shape',
            'vierge', 'virgo' => 'precise detailing, clean seams, refined minimal ornament, tidy silhouette',
            'balance', 'libra' => 'symmetry, balanced proportions, elegant scale-like geometry (subtle)',
            'scorpion', 'scorpio' => 'sleek intensity, shadowed accents, fine stinger-like curve detail (subtle)',
            'sagittaire', 'sagittarius' => 'arrow linework, travel motifs, upward motion, adventurous silhouette',
            'capricorne', 'capricorn' => 'mountain ridges, disciplined structure, angular strength with restraint',
            'verseau', 'aquarius' => 'wave geometry, modern tech-like accents, flowing yet precise lines',
            'poissons', 'pisces' => 'soft fluid curves, dual-flow pattern, dreamlike haze (very subtle)',
            default => 'coherent silhouette motifs derived from the sun sign, subtle and premium',
        };
    }

    private function mapAscendantToAccent(string $asc): string
    {
        $k = $this->key($asc);

        return match ($k) {
            'belier', 'aries' => 'one sharp chevron accent / dynamic shoulder detail',
            'taureau', 'taurus' => 'a weighty belt / grounded stance accent',
            'gemeaux', 'gemini' => 'split cape edge / dual-layer collar accent',
            'cancer' => 'rounded mantle / protective collar accent',
            'lion', 'leo' => 'sun-like clasp / proud shoulder flourish',
            'vierge', 'virgo' => 'precise stitching / clean seam accent',
            'balance', 'libra' => 'symmetrical clasp / centered ornament accent',
            'scorpion', 'scorpio' => 'dark trim line / subtle stinger curve accent',
            'sagittaire', 'sagittarius' => 'arrow-fletch accent / diagonal strap detail',
            'capricorne', 'capricorn' => 'angular pauldron / mountain ridge trim',
            'verseau', 'aquarius' => 'wave-cut trim / futuristic line accent',
            'poissons', 'pisces' => 'flowing scarf edge / soft swirl accent',
            default => 'one dynamic accent detail derived from the ascendant',
        };
    }

    private function mapChineseAnimalToCompanionOrSeal(string $animal): string
    {
        $k = $this->key($animal);
        $map = [
            'chien' => 'subtle companion animal OR a wax-seal emblem (guardian dog), elegant not cute',
            'dragon' => 'subtle scaled seal emblem (dragon), elegant not cute',
            'rat' => 'tiny engraved seal emblem (rat), elegant not cute',
            'boeuf' => 'small horned seal emblem (ox), elegant not cute',
            'tigre' => 'striped seal emblem (tiger), elegant not cute',
            'lapin' => 'small moon-rabbit seal emblem, elegant not cute',
            'serpent' => 'serpentine wax-seal emblem, elegant not cute',
            'cheval' => 'small heraldic horse seal emblem, elegant not cute',
            'chevre' => 'small mountain-goat seal emblem, elegant not cute',
            'singe' => 'small clever monkey seal emblem, elegant not cute',
            'coq' => 'small feathered rooster seal emblem, elegant not cute',
            'cochon' => 'small boar seal emblem, elegant not cute',
        ];

        return $map[$k] ?? 'subtle companion animal OR a wax-seal emblem (elegant, not cute)';
    }

    private function key(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        // Normalize accents/spaces for stable matching.
        $v = (string) Str::of($v)->lower()->ascii();
        $v = (string) preg_replace('/[^a-z0-9]+/', '', $v);

        return $v;
    }

    /**
     * Stable JSON string regardless of key order.
     *
     * @param array<string,mixed> $data
     */
    private function canonicalJson(array $data): string
    {
        $sorted = $this->ksortRecursive($data);
        return (string) json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function ksortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Preserve numeric-indexed arrays ordering.
        if (array_is_list($value)) {
            return array_map(fn ($v) => $this->ksortRecursive($v), $value);
        }

        $out = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $k) {
            $out[$k] = $this->ksortRecursive($value[$k]);
        }

        return $out;
    }
}
