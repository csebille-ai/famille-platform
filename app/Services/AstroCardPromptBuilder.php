<?php

namespace App\Services;

use App\Models\User;

class AstroCardPromptBuilder
{
    /**
     * @param array<string,mixed> $signature
     * @return array{prompt:string, seed:string, title:string, signature_line:string, tag1:string, tag2:string, tag3:string}
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

        [$tag1, $tag2, $tag3] = $this->buildTags($talents);

        $seed = sha1((string) $user->id . '|' . $this->canonicalJson($signature));

        $prompt = $this->buildPrompt([
            'TITLE' => $title,
            'SIGNATURE_LINE' => $signatureLine,
            'LIFE_PATH' => (string) $lifePath,
            'TAG1' => $tag1,
            'TAG2' => $tag2,
            'TAG3' => $tag3,
        ]);

        return [
            'prompt' => $prompt,
            'seed' => $seed,
            'title' => $title,
            'signature_line' => $signatureLine,
            'tag1' => $tag1,
            'tag2' => $tag2,
            'tag3' => $tag3,
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
     * @param array{TITLE:string,SIGNATURE_LINE:string,LIFE_PATH:string,TAG1:string,TAG2:string,TAG3:string} $vars
     */
    private function buildPrompt(array $vars): string
    {
        $template = <<<PROMPT
Modern Tarot card design, premium editorial style, portrait 2:3.
A single symbolic illustration (no realistic person), minimal yet detailed like high-end tarot reimagined for a mobile app.

Card layout:
- Thin border, subtle rounded corners, off-white paper texture, soft grain.
- Top title: “{{TITLE}}” (small caps, elegant serif).
- Bottom line: “{{SIGNATURE_LINE}}” (tiny, clean).

Symbolic body illustration:
- A “guardian gate” motif (abstract doorway/threshold) centered, symmetrical and grounded (Taurus).
- One sharp chevron accent or subtle horn-like curve (Aries rising) integrated into the gate design.
- A small embossed seal of a guardian dog (stylized, minimal line engraving) placed like a wax seal in a corner.
- Yang Metal mood: graphite + brushed silver + ONE cold blue accent, crisp contrast, premium.
- Life path {{LIFE_PATH}}: subtle infinity ribbon and an {{LIFE_PATH}}-point geometric pattern worked into the background.

Include 3 tiny talent tags near the bottom:
“{{TAG1}}” • “{{TAG2}}” • “{{TAG3}}”

Avoid:
kitsch astrology clichés, stars everywhere, neon, cartoon, institutional/insurance look, medical symbols, hearts, hands.
Clean negative space, modern tarot, elegant, collectible.
PROMPT;

        $out = $template;
        foreach ($vars as $k => $v) {
            $out = str_replace('{{' . $k . '}}', (string) $v, $out);
        }

        return $out;
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
