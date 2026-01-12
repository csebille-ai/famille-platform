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

        $seedHint = (string) $user->id . '|' . $this->canonicalJson($signature);

        [$rpgClass, $weapon, $secondaryGear] = $this->mapArchetypeToLoadout($archetype);

        [$sunSilhouette, $sunMotif] = $this->mapSunSignToSilhouetteAndMotif($sun);
        $ascEmblem = $this->mapAscendantToEmblem($asc);
        [$materialsPalette, $shapeLanguage] = $this->mapPolarityElementToMaterialsAndShapes($polarity, $element);

        $chineseSign = trim((string) $animal);
        $chineseElementPolarity = trim(implode(' ', array_values(array_filter([$polarity, $element], fn ($v) => trim((string) $v) !== ''))));
        $chineseTotem = $this->mapChineseAnimalToTotem($animal);
        $chinesePattern = $this->mapChineseElementPolarityToPattern($polarity, $element);

        $lifePathSigil = $this->mapLifePathToSigil($lifePath);
        [$poseCue, $expressionCue, $vigilanceFlawCue] = $this->mapVigilanceToPoseExpressionAndFlaw($vigilance, $seedHint);

        // Talents -> 3 concrete gear details/badges (NOT text).
        [$talent1, $talent2, $talent3] = $this->pickThreeTalents($talents);
        [$t1Gear, $t2Gear, $t3Gear] = $this->mapTalentsToGearDetails([$talent1, $talent2, $talent3], $seedHint);

        [$tag1, $tag2, $tag3] = $this->buildTags($talents);

        $seed = sha1((string) $user->id . '|' . $this->canonicalJson($signature));

        [$prompt, $negativePrompt] = $this->buildPrompt([
            'RPG_CLASS' => $rpgClass,
            'ARCHETYPE' => $archetype,
            'WEAPON' => $weapon,
            'SECONDARY_GEAR' => $secondaryGear,

            'POSE_CUE' => $poseCue,
            'EXPRESSION_CUE' => $expressionCue,
            'VIGILANCE_FLAW_CUE' => $vigilanceFlawCue,

            'SUN_SIGN' => $sun,
            'SUN_SILHOUETTE' => $sunSilhouette,
            'SUN_MOTIF' => $sunMotif,
            'ASC_SIGN' => $asc,
            'ASC_EMBLEM' => $ascEmblem,
            'CHINESE_SIGN' => $chineseSign,

            'CHINESE_ELEMENT_POLARITY' => $chineseElementPolarity,
            'CHINESE_TOTEM' => $chineseTotem,
            'CHINESE_PATTERN' => $chinesePattern,

            'POLARITY' => $polarity,
            'ELEMENT' => $element,
            'MATERIALS_PALETTE' => $materialsPalette,
            'SHAPE_LANGUAGE' => $shapeLanguage,
            'LIFE_PATH' => (string) $lifePath,
            'LIFE_PATH_SIGIL' => $lifePathSigil,
            'TALENT_1' => $talent1,
            'TALENT_2' => $talent2,
            'TALENT_3' => $talent3,
            'TALENT_1_GEAR' => $t1Gear,
            'TALENT_2_GEAR' => $t2Gear,
            'TALENT_3_GEAR' => $t3Gear,
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
      * @param array{RPG_CLASS:string,ARCHETYPE:string,POSE_CUE:string,EXPRESSION_CUE:string,WEAPON:string,SECONDARY_GEAR:string,MATERIALS_PALETTE:string,SHAPE_LANGUAGE:string,SUN_SIGN:string,SUN_SILHOUETTE:string,SUN_MOTIF:string,ASC_SIGN:string,ASC_EMBLEM:string,CHINESE_SIGN:string,CHINESE_ELEMENT_POLARITY:string,CHINESE_TOTEM:string,CHINESE_PATTERN:string,LIFE_PATH:string,LIFE_PATH_SIGIL:string,TALENT_1:string,TALENT_2:string,TALENT_3:string,TALENT_1_GEAR:string,TALENT_2_GEAR:string,TALENT_3_GEAR:string,VIGILANCE_FLAW_CUE:string} $vars
     * @return array{0:string,1:string}
     */
    private function buildPrompt(array $vars): array
    {
        $template = <<<PROMPT
Premium RPG character card, 2:3 portrait, full-body single character centered, cinematic AAA game key art. NO TEXT.

Character core:
- Class: {{RPG_CLASS}} (from archetype: {{ARCHETYPE}})
- Pose: {{POSE_CUE}}. Expression: {{EXPRESSION_CUE}}.
- Primary weapon: {{WEAPON}}. Secondary gear: {{SECONDARY_GEAR}}.
- Outfit materials/palette: {{MATERIALS_PALETTE}}. Shape language: {{SHAPE_LANGUAGE}}.

Astro synthesis (MUST be visible, not text):
1) Sun sign {{SUN_SIGN}} => silhouette cue + subtle aura motif:
    - silhouette: {{SUN_SILHOUETTE}}
    - background motif (very subtle): {{SUN_MOTIF}}
2) Ascendant {{ASC_SIGN}} => MUST be clearly readable on helmet/shoulders:
    - emblem: {{ASC_EMBLEM}} (recognizable)
3) Chinese sign {{CHINESE_SIGN}} + element/polarity {{CHINESE_ELEMENT_POLARITY}} =>
    - totem: {{CHINESE_TOTEM}} (wax seal emblem or serious spirit companion, NOT cute)
    - micro-pattern: {{CHINESE_PATTERN}}
4) Life path {{LIFE_PATH}} =>
    - geometric sigil integrated as engraving on armor/cloak/shield: {{LIFE_PATH_SIGIL}}
5) Talents (3) => 3 gear details (badges/tools/amulets), no text:
    - {{TALENT_1}} => {{TALENT_1_GEAR}}
    - {{TALENT_2}} => {{TALENT_2_GEAR}}
    - {{TALENT_3}} => {{TALENT_3_GEAR}}
6) Vigilance point => subtle flaw cue: {{VIGILANCE_FLAW_CUE}}

Background:
Clean atmospheric gradient + faint sigils only. No ornate frame. High readability.

NO TEXT inside the image.
PROMPT;

        $negative = implode(', ', [
            'text',
            'letters',
            'numbers',
            'watermark',
            'logo',
            'signature',
            'caption',
            'typography',
            'ornamental frame',
            'tarot poster',
            'art nouveau',
            'symmetrical decorative poster',
            'abstract drapery-only',
            'messy clutter',
            'blurry',
            'low detail',
            'blurred face',
            'extra limbs',
            'extra fingers',
            'duplicate person',
            'two characters',
            'childish cute mascot',
            'chibi',
            'cartoon',
        ]);

        $out = $template;
        foreach ($vars as $k => $v) {
            $out = str_replace('{{' . $k . '}}', (string) $v, $out);
        }

        return [$out, $negative];
    }

    /**
     * @return array{0:string,1:string,2:string} [rpgClass, primaryGear, secondaryGear]
     */
    private function mapArchetypeToLoadout(string $archetype): array
    {
        $k = $this->key($archetype);

        $map = [
            'gardien' => ['Sentinel', 'tower shield with engraved sigil', 'spear or longsword'],
            'protecteur' => ['Sentinel', 'tower shield with engraved sigil', 'spear or longsword'],
            'sage' => ['Oracle', 'staff with subtle inlays', 'scrying orb or charm talisman'],
            'visionnaire' => ['Oracle', 'staff with subtle inlays', 'scrying orb or charm talisman'],
            'rebelle' => ['Duelist', 'rapier or curved blade', 'dagger or parrying buckler'],
            'aventurier' => ['Ranger', 'bow with modern fittings', 'twin blades or utility knife'],
            'createur' => ['Mystic Artisan', 'runic gauntlet or tool-hammer', 'etched chisel or modular gadget'],
            'artiste' => ['Mystic Artisan', 'runic gauntlet or tool-hammer', 'etched chisel or modular gadget'],
            'messager' => ['Herald', 'banner-spear or polearm', 'signal horn or sealed scroll-case'],
            'diplomate' => ['Herald', 'banner-spear or polearm', 'signal horn or sealed scroll-case'],
            'battisseur' => ['Warden-Engineer', 'reinforced hammer', 'blueprint satchel or measuring chain'],
            'strategiste' => ['Warden-Engineer', 'reinforced hammer', 'blueprint satchel or measuring chain'],
            'alchimiste' => ['Alchemist', 'vial belt + alchemical injector', 'compact crossbow or smoke canister'],
            'magicien' => ['Arcanist', 'arcane focus staff', 'spellbook or floating shard'],
        ];

        return $map[$k] ?? ['Adventurer', 'signature weapon (coherent)', 'secondary tool (coherent)'];
    }

    /**
     * @return array{0:string,1:string} [sunVibe, sunMotif]
     */
    private function mapSunSignToSilhouetteAndMotif(string $sun): array
    {
        $k = $this->key($sun);

        return match ($k) {
            'belier', 'aries' => ['forward-leaning athletic silhouette, angled lines, horn-echo forms (minimal)', 'very subtle chevron sparks / ember-streaks'],
            'taureau', 'taurus' => ['broad grounded silhouette, weighty stance, strong core shape (minimal)', 'very subtle stone strata / earth plates'],
            'gemeaux', 'gemini' => ['agile layered silhouette, dual elements (minimal)', 'very subtle mirrored arcs / twin crescents'],
            'cancer' => ['protective wrapped silhouette, rounded guard forms (minimal)', 'very subtle crescent glow + shell-curve'],
            'lion', 'leo' => ['upright regal silhouette, bold shoulders (minimal)', 'very subtle sun-halo geometry'],
            'vierge', 'virgo' => ['clean precise silhouette, refined seams (minimal)', 'very subtle gridlines / tidy filigree'],
            'balance', 'libra' => ['balanced proportions, elegant symmetry (minimal)', 'very subtle scale geometry'],
            'scorpion', 'scorpio' => ['sleek intense silhouette, sharp contour accents (minimal)', 'very subtle stinger curve arc'],
            'sagittaire', 'sagittarius' => ['dynamic travel silhouette, upward motion (minimal)', 'very subtle arrow trajectory line'],
            'capricorne', 'capricorn' => ['disciplined structured silhouette, angular strength (minimal)', 'very subtle mountain ridge bands'],
            'verseau', 'aquarius' => ['modern flowing silhouette, precise lines (minimal)', 'very subtle wave geometry'],
            'poissons', 'pisces' => ['fluid dreamlike silhouette, soft motion (minimal)', 'very subtle dual-flow ripples'],
            default => ['minimal silhouette cue driven by the sun sign', 'a very subtle motif derived from the sun sign'],
        };
    }

    private function mapAscendantToEmblem(string $asc): string
    {
        $k = $this->key($asc);

        return match ($k) {
            'belier', 'aries' => 'ram-horn crest emblem (two curved horns)',
            'taureau', 'taurus' => 'bull-head sigil emblem (strong horns)',
            'gemeaux', 'gemini' => 'twin pillar emblem (two vertical bars / mirrored twins)',
            'cancer' => 'crab-claw crescent emblem (two opposing crescents)',
            'lion', 'leo' => 'lion mane sun-sigil emblem (mane-like rays)',
            'vierge', 'virgo' => 'maiden-sigil emblem (clean wheat/leaf glyph)',
            'balance', 'libra' => 'scales emblem (balanced scale silhouette)',
            'scorpion', 'scorpio' => 'scorpion-tail hook emblem (curved stinger glyph)',
            'sagittaire', 'sagittarius' => 'arrowhead emblem (clear arrow glyph)',
            'capricorne', 'capricorn' => 'mountain-goat horn emblem (ridged horn glyph)',
            'verseau', 'aquarius' => 'double-wave emblem (two horizontal waves)',
            'poissons', 'pisces' => 'two-fish loop emblem (two opposing curves)',
            default => 'a clearly recognizable emblem matching the ascendant sign',
        };
    }

    /**
     * @return array{0:string,1:string} [materialsPalette, shapeLanguage]
     */
    private function mapPolarityElementToMaterialsAndShapes(string $polarity, string $element): array
    {
        $p = $this->key($polarity);
        $e = $this->key($element);

        $materials = match ($e) {
            'metal' => 'brushed steel, graphite, platinum trims, cold blue accent',
            'bois', 'wood' => 'dark wood, jade-green accents, warm gold fittings, leather',
            'eau', 'water' => 'deep navy, silver, wet sheen highlights, icy cyan accent',
            'feu', 'fire' => 'obsidian, brass, ember-crimson accents, heat-glow edge lighting',
            'terre', 'earth' => 'sandstone, bronze, olive accents, worn stone + leather',
            default => 'premium metal/leather materials with one restrained accent color',
        };

        $shape = match ($e) {
            'metal' => 'faceted polygons, straight lines, crisp edges',
            'bois', 'wood' => 'organic arcs, growth rings, layered natural forms',
            'eau', 'water' => 'flowing curves, wave sweeps, smooth continuous lines',
            'feu', 'fire' => 'spikes, flame tongues, dynamic tapered shapes',
            'terre', 'earth' => 'blocky terraces, stacked plates, grounded geometry',
            default => 'coherent shape language matching the element',
        };

        if ($p === 'yin') {
            return ['matte, restrained finish — ' . $materials, 'inward curves, calmer rhythm — ' . $shape];
        }
        if ($p === 'yang') {
            return ['high contrast, crisp finish — ' . $materials, 'forward angles, strong rhythm — ' . $shape];
        }

        return [$materials, $shape];
    }

    private function mapChineseAnimalToTotem(string $animal): string
    {
        $k = $this->key($animal);

        return match ($k) {
            'dragon' => 'serious spirit companion dragon (small, stern) OR dragon wax-seal medallion on belt',
            'tigre' => 'serious spirit companion tiger (small, stern) OR striped wax-seal medallion',
            'chien' => 'serious spirit companion guard dog (small, stern) OR wax-seal guard-dog medallion',
            'cheval' => 'heraldic horse wax-seal medallion on cloak clasp',
            'serpent' => 'serpentine wax-seal medallion with subtle scale texture',
            'lapin' => 'moon-rabbit wax-seal medallion (serious, not cute)',
            'coq' => 'sharp heraldic rooster wax-seal medallion',
            'singe' => 'understated monkey wax-seal medallion (serious)',
            'rat' => 'small engraved rat wax-seal medallion (discreet)',
            'boeuf' => 'ox-head wax-seal medallion with horned silhouette',
            'chevre' => 'mountain-goat wax-seal medallion, ridged horns',
            'cochon' => 'boar wax-seal medallion, rugged and serious',
            default => 'wax-seal medallion emblem OR small serious spirit companion matching the chinese animal (not cute)',
        };
    }

    private function mapChineseElementPolarityToPattern(string $polarity, string $element): string
    {
        $p = $this->key($polarity);
        $e = $this->key($element);

        $pattern = match ($e) {
            'metal' => 'fine etched hatching + micro-chevron facets',
            'bois', 'wood' => 'subtle woodgrain rings + leaf-vein micro-lines',
            'eau', 'water' => 'soft wave micro-lines + ripple interference pattern',
            'feu', 'fire' => 'ember micro-specks + tapered flame micro-strokes',
            'terre', 'earth' => 'stone micro-specks + layered sediment lines',
            default => 'subtle micro-pattern matching the element',
        };

        if ($p === 'yin') {
            return 'very subtle, matte micro-pattern — ' . $pattern;
        }
        if ($p === 'yang') {
            return 'crisp micro-pattern with higher contrast — ' . $pattern;
        }

        return $pattern;
    }

    private function mapLifePathToSigil(int $lifePath): string
    {
        $n = max(1, min(9, $lifePath));

        return match ($n) {
            1 => 'single vertical line inside a circle, minimal engraving',
            2 => 'paired crescent arcs, mirrored, minimal engraving',
            3 => 'triangular knot / three-point triangle glyph, minimal engraving',
            4 => 'square labyrinth / four-corner glyph, minimal engraving',
            5 => 'pentagonal star / five-point geometry, minimal engraving',
            6 => 'hexagon honeycomb / six-sided glyph, minimal engraving',
            7 => 'heptagram / seven-point glyph, minimal engraving',
            8 => 'octagon + infinity loop hint, minimal engraving',
            9 => 'nine-node ring / enneagram-like glyph, minimal engraving',
            default => 'a subtle geometric glyph with the life-path number of points, minimal engraving',
        };
    }

    /**
     * @return array{0:string,1:string,2:string} [poseCue, expressionCue, flawCue]
     */
    private function mapVigilanceToPoseExpressionAndFlaw(string $vigilance, string $seedHint): array
    {
        $v = Str::of($vigilance)->lower()->ascii();

        if (str_contains($v, 'control') || str_contains($v, 'controle') || str_contains($v, 'contr')) {
            return [
                'defensive stance, shield/guard slightly raised',
                'guarded gaze, jaw set',
                'overcontrolled posture, too rigid grip (subtle)',
            ];
        }
        if (str_contains($v, 'doute')) {
            return [
                'measured stance, weight slightly back',
                'subtle hesitation in the eyes',
                'micro-tension in shoulders, cautious restraint (subtle)',
            ];
        }
        if (str_contains($v, 'peur') || str_contains($v, 'anx')) {
            return [
                'ready stance, scanning posture',
                'alert gaze, controlled breath',
                'restless vigilance, slightly tense hands (subtle)',
            ];
        }
        if (str_contains($v, 'colere') || str_contains($v, 'rage')) {
            return [
                'contained power stance, feet planted',
                'intense stare, restrained fury',
                'micro-clenched fist, barely contained heat (subtle)',
            ];
        }

        $fallback = [
            ['steady heroic stance, centered posture', 'calm focused gaze', 'a hint of guarded restraint (subtle)'],
            ['relaxed-but-ready stance, balanced footing', 'soft confident expression', 'a hint of impatience (subtle)'],
            ['quiet vigilant stance, minimal movement', 'composed expression, attentive eyes', 'a hint of distance (subtle)'],
            ['forward-ready stance, dynamic weight shift', 'determined gaze', 'a hint of stubbornness (subtle)'],
        ];

        $idx = hexdec(substr(sha1($seedHint . '|vigilance'), 0, 2)) % count($fallback);
        return $fallback[$idx];
    }

    /**
     * @param list<string> $talents
     * @return array{0:string,1:string,2:string}
     */
    private function pickThreeTalents(array $talents): array
    {
        $t = array_values(array_filter(array_map(fn ($x) => trim((string) $x), $talents), fn ($x) => $x !== ''));
        $t = array_values(array_unique($t));

        while (count($t) < 3) {
            $t[] = match (count($t)) {
                0 => 'Protects and secures',
                1 => 'Structures and stabilizes',
                default => 'Reassures naturally',
            };
        }

        return [$t[0], $t[1], $t[2]];
    }

    /**
     * @param array{0:string,1:string,2:string} $talents
     * @return array{0:string,1:string,2:string}
     */
    private function mapTalentsToGearDetails(array $talents, string $seedHint): array
    {
        $pool = [
            'an enamel badge on the chest strap (icon-only, no letters)',
            'a small engraved medallion on the belt (icon-only, no letters)',
            'a wax-seal charm hanging from the weapon hilt (icon-only)',
            'a utility clasp on the glove with a distinct icon (no text)',
            'a shoulder pin with a clear pictogram (no letters)',
            'a ring with an embossed icon, visible on the hand',
            'a stitched patch on the satchel with an icon silhouette (no text)',
            'a metal token on the cloak clasp, embossed icon-only',
            'a small totem charm tied to the belt, icon-only',
        ];

        $used = [];
        $out = [];
        foreach ($talents as $i => $talent) {
            $h = sha1($seedHint . '|talent|' . (string) $i . '|' . $talent);
            $start = hexdec(substr($h, 0, 4));
            $pick = null;
            for ($step = 0; $step < count($pool); $step++) {
                $idx = ($start + $step) % count($pool);
                if (!isset($used[$idx])) {
                    $used[$idx] = true;
                    $pick = $pool[$idx];
                    break;
                }
            }
            $out[] = $pick ?? $pool[($i % count($pool))];
        }

        return [$out[0] ?? $pool[0], $out[1] ?? $pool[1], $out[2] ?? $pool[2]];
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
