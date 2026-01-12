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

        [$rpgClass, $primaryGear, $secondaryGear] = $this->mapArchetypeToLoadout($archetype);

        [$sunVibe, $sunMotif] = $this->mapSunSignToVibeAndMotif($sun);
        $ascEmblem = $this->mapAscendantToEmblem($asc);
        [$materialsPalette, $shapeLanguage] = $this->mapPolarityElementToMaterialsAndShapes($polarity, $element);
        $chineseSign = trim(implode(' ', array_values(array_filter([$polarity, $element, $animal], fn ($v) => trim((string) $v) !== ''))));
        $chineseRender = $this->mapChineseAnimalToRender($animal);
        $lifePathSigil = $this->mapLifePathToSigil($lifePath);
        $vigilanceCue = $this->mapVigilanceToCue($vigilance, $seedHint = (string) $user->id . '|' . $this->canonicalJson($signature));

        // Talents -> 3 concrete gear details/badges (NOT text).
        [$talent1, $talent2, $talent3] = $this->pickThreeTalents($talents);
        [$t1Gear, $t2Gear, $t3Gear] = $this->mapTalentsToGearDetails([$talent1, $talent2, $talent3], $seedHint);

        [$tag1, $tag2, $tag3] = $this->buildTags($talents);

        $seed = sha1((string) $user->id . '|' . $this->canonicalJson($signature));

        [$prompt, $negativePrompt] = $this->buildPrompt([
            'RPG_CLASS' => $rpgClass,
            'ARCHETYPE' => $archetype,
            'PRIMARY_GEAR' => $primaryGear,
            'SECONDARY_GEAR' => $secondaryGear,
            'VIGILANCE_CUE' => $vigilanceCue,
            'SUN_SIGN' => $sun,
            'SUN_VIBE' => $sunVibe,
            'SUN_MOTIF' => $sunMotif,
            'ASC_SIGN' => $asc,
            'ASC_EMBLEM' => $ascEmblem,
            'CHINESE_SIGN' => $chineseSign,
            'CHINESE_RENDER' => $chineseRender,
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
     * @param array{RPG_CLASS:string,ARCHETYPE:string,PRIMARY_GEAR:string,SECONDARY_GEAR:string,VIGILANCE_CUE:string,SUN_SIGN:string,SUN_VIBE:string,SUN_MOTIF:string,ASC_SIGN:string,ASC_EMBLEM:string,CHINESE_SIGN:string,CHINESE_RENDER:string,POLARITY:string,ELEMENT:string,MATERIALS_PALETTE:string,SHAPE_LANGUAGE:string,LIFE_PATH:string,LIFE_PATH_SIGIL:string,TALENT_1:string,TALENT_2:string,TALENT_3:string,TALENT_1_GEAR:string,TALENT_2_GEAR:string,TALENT_3_GEAR:string} $vars
     * @return array{0:string,1:string}
     */
    private function buildPrompt(array $vars): array
    {
        $template = <<<PROMPT
Premium collectible RPG character card artwork, portrait 2:3. NO TEXT.

ONE single fictional character, FULL-BODY, centered, readable silhouette, strong pose.
Style: modern AAA game key art / premium TCG illustration (NOT art nouveau, NOT tarot poster).

Core identity:
- RPG class: {{RPG_CLASS}} (driven by archetype {{ARCHETYPE}}).
- Primary gear: {{PRIMARY_GEAR}} + secondary gear {{SECONDARY_GEAR}}.
- Pose/expression: {{VIGILANCE_CUE}} (subtle).

Astro synthesis (MANDATORY, all must be visually present, not as text):
- Sun sign {{SUN_SIGN}} => overall vibe + silhouette cue: {{SUN_VIBE}} and subtle background motif: {{SUN_MOTIF}}.
- Ascendant {{ASC_SIGN}} => clearly visible emblem on helmet/shoulders: {{ASC_EMBLEM}} (must be recognizable).
- Chinese sign {{CHINESE_SIGN}} => {{CHINESE_RENDER}} (either emblem/cameo wax seal or small serious companion; not cute).
- Yin/Yang + element {{POLARITY}} {{ELEMENT}} => dominant materials + palette: {{MATERIALS_PALETTE}} + shape language: {{SHAPE_LANGUAGE}}.
- Life path {{LIFE_PATH}} => subtle geometric sigil integrated into shield/cloak engraving: {{LIFE_PATH_SIGIL}}.

Talents (3) must appear as 3 subtle gear details/badges (not text):
1) {{TALENT_1}} => {{TALENT_1_GEAR}}
2) {{TALENT_2}} => {{TALENT_2_GEAR}}
3) {{TALENT_3}} => {{TALENT_3_GEAR}}

Background: clean atmospheric gradient, faint sigils only, no ornate frame, no clutter.
Lighting: cinematic, high readability, crisp shapes.

Generate the artwork ONLY. NO TEXT, NO LETTERS.
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
            'extra limbs',
            'extra fingers',
            'duplicate person',
            'two characters',
            'childish cute mascot',
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
    private function mapSunSignToVibeAndMotif(string $sun): array
    {
        $k = $this->key($sun);

        return match ($k) {
            'belier', 'aries' => ['aggressive forward momentum, athletic silhouette, angled lines', 'subtle chevron sparks / ember-streaks in the gradient'],
            'taureau', 'taurus' => ['grounded, sturdy stance, broad silhouette, weighty proportions', 'subtle stone strata / earth plates in the background'],
            'gemeaux', 'gemini' => ['agile, layered silhouette, dual details, light footwork', 'faint mirrored arcs / twin crescents'],
            'cancer' => ['protective, wrapped silhouette, rounded guard shapes', 'soft crescent glow + shell-curve motif'],
            'lion', 'leo' => ['regal, upright posture, bold shoulders, commanding silhouette', 'minimal sunburst halo geometry (very subtle)'],
            'vierge', 'virgo' => ['precise, clean silhouette, refined seams, controlled posture', 'faint gridlines / meticulous filigree pattern'],
            'balance', 'libra' => ['balanced proportions, elegant symmetry, calm poise', 'subtle scale geometry / centered balance mark'],
            'scorpion', 'scorpio' => ['intense, sleek silhouette, sharp contour accents', 'faint stinger curve / shadowed arc motif'],
            'sagittaire', 'sagittarius' => ['adventurous, upward motion, dynamic cape flow', 'faint arrow trajectory line / star waypoint dots'],
            'capricorne', 'capricorn' => ['disciplined, structured silhouette, angular strength', 'mountain ridge bands / stepped geometry'],
            'verseau', 'aquarius' => ['innovative, modern silhouette, flowing yet precise lines', 'faint wave geometry + subtle circuit-like streaks'],
            'poissons', 'pisces' => ['dreamlike, fluid silhouette, soft drape motion', 'dual-flow ripples / subtle mist swirls'],
            default => ['coherent vibe + silhouette cue driven by the sun sign', 'a subtle motif derived from the sun sign'],
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

    private function mapChineseAnimalToRender(string $animal): string
    {
        $k = $this->key($animal);

        return match ($k) {
            'dragon' => 'a serious scaled companion dragon (small, stern) OR a dragon wax-seal cameo on the belt',
            'tigre' => 'a serious companion tiger (small, stern) OR a striped wax-seal cameo',
            'chien' => 'a serious companion guard dog (small, stern) OR a wax-seal emblem',
            'cheval' => 'a heraldic horse wax-seal cameo (not cute), embossed on cloak clasp',
            'serpent' => 'a serpentine wax-seal cameo with subtle scale texture',
            'lapin' => 'a moon-rabbit wax-seal cameo (serious, not cute)',
            'coq' => 'a feathered rooster wax-seal cameo, sharp and heraldic',
            'singe' => 'a clever monkey wax-seal cameo, understated and serious',
            'rat' => 'a small engraved rat wax-seal cameo, discreet',
            'boeuf' => 'an ox-head wax-seal cameo with horned silhouette',
            'chevre' => 'a mountain-goat wax-seal cameo, ridged horns',
            'cochon' => 'a boar wax-seal cameo, rugged and serious',
            default => 'either a wax-seal cameo emblem or a small serious companion matching the chinese animal (not cute)',
        };
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

    private function mapVigilanceToCue(string $vigilance, string $seedHint): string
    {
        $v = Str::of($vigilance)->lower()->ascii();

        $keywords = [
            'control' => ['tight grip, jaw set, guarded gaze', 'subtle defensive posture, shield slightly raised'],
            'doute' => ['slight brow tension, cautious stance', 'subtle hesitation in the eyes, measured pose'],
            'peur' => ['subtle alertness, scanning gaze', 'slight tension in shoulders, ready stance'],
            'colere' => ['subtle clenched fist, intense stare', 'contained intensity, controlled breath'],
        ];

        if (str_contains($v, 'control') || str_contains($v, 'controle') || str_contains($v, 'contr')) {
            return $keywords['control'][0];
        }
        if (str_contains($v, 'doute')) {
            return $keywords['doute'][0];
        }
        if (str_contains($v, 'peur') || str_contains($v, 'anx')) {
            return $keywords['peur'][0];
        }
        if (str_contains($v, 'colere') || str_contains($v, 'rage')) {
            return $keywords['colere'][0];
        }

        $fallback = [
            'calm vigilance, focused gaze, steady stance',
            'subtle guarded expression, composed posture',
            'quiet intensity, controlled breathing',
            'measured confidence, relaxed but ready stance',
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
