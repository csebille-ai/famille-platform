<?php

declare(strict_types=1);

namespace App\Services\Astro\Natal;

final class NatalNarrativeGenerator
{
    private const OUTER_PLANET_KEYS = ['jupiter', 'saturn', 'uranus', 'neptune', 'pluto'];

    /**
     * @param array<string,mixed>|null $natal
     */
    public function generate(?array $natal, string $firstName = ''): string
    {
        if (!is_array($natal) || $natal === []) {
            return '';
        }

        $firstName = trim($firstName);
        if ($firstName === '') {
            $firstName = 'toi';
        }

        $angles = is_array($natal['angles'] ?? null) ? (array) $natal['angles'] : [];
        $planetsRaw = is_array($natal['planets'] ?? null) ? (array) $natal['planets'] : [];

        $planets = $this->normalizePlanets($planetsRaw);
        if ($planets === []) {
            return '';
        }

        $asc = $this->normalizePoint('asc', $angles['asc'] ?? null, 'Ascendant');
        $mc = $this->normalizePoint('mc', $angles['mc'] ?? null, 'Milieu du Ciel');

        $title = 'Thème astral de ' . $firstName;

        $sun = $planets['sun'] ?? null;
        $moon = $planets['moon'] ?? null;

        $sections = [];

        // 1) Essentiel
        $essentialLines = [];
        if ($sun) {
            $essentialLines[] = 'Soleil en ' . $this->posLabel($sun) . ' : ' . $this->oneLine('tu tends à chercher ta place et ta cohérence.', 'tu te sens aligné quand tu avances avec clarté.');
        }
        if ($moon) {
            $essentialLines[] = 'Lune en ' . $this->posLabel($moon) . ' : ' . $this->oneLine('tes émotions te guident beaucoup.', 'tu as besoin de sécurité intérieure pour être à l’aise.');
        }
        if ($asc) {
            $essentialLines[] = 'Ascendant ' . $this->posLabel($asc, includeHouse: false) . ' : ' . $this->oneLine('c’est ta manière d’entrer en relation avec le monde.', 'tu peux donner cette première impression.');
        }
        if ($essentialLines !== []) {
            $sections[] = "1) Essentiel\n- " . implode("\n- ", $essentialLines);
        }

        // 2) Moteur intérieur
        $inner = [];
        foreach (['mercury' => 'Mercure', 'venus' => 'Vénus', 'mars' => 'Mars'] as $key => $label) {
            $p = $planets[$key] ?? null;
            if (!$p) continue;
            $inner[] = $label . ' en ' . $this->posLabel($p) . ' : ' . $this->planetSentence($key);
        }
        if ($inner !== []) {
            $sections[] = "2) Ton moteur intérieur (Mercure/Vénus/Mars)\n- " . implode("\n- ", $inner);
        }

        // 3) Grandes dynamiques
        $dyn = [];
        foreach (self::OUTER_PLANET_KEYS as $key) {
            $p = $planets[$key] ?? null;
            if (!$p) continue;
            $dyn[] = $this->planetLabel($key) . ' en ' . $this->posLabel($p) . ' : ' . $this->outerPlanetSentence($key);
        }
        if ($dyn !== []) {
            $sections[] = "3) Les grandes dynamiques\n- " . implode("\n- ", $dyn);
        }

        // 4) Aspects
        $pointsForAspects = $planets;
        if ($asc) $pointsForAspects['asc'] = $asc;
        if ($mc) $pointsForAspects['mc'] = $mc;

        $aspects = $this->computeAspects($pointsForAspects);
        $aspectLines = [];
        foreach ($aspects as $a) {
            $aspectLines[] = $a['label'] . ' : ' . $a['meaning'];
        }
        if ($aspectLines !== []) {
            $sections[] = "4) Aspects clés\n- " . implode("\n- ", $aspectLines);
        }

        // 5) Conclusion
        $sections[] = "5) Conclusion\nTu as des atouts naturels à cultiver, et aussi quelques zones qui demandent de la douceur et de la méthode. Prends ce thème comme un miroir: il peut t’aider à mieux te comprendre, pas à te juger.";

        $text = $title . "\n\n" . implode("\n\n", $sections);

        // Length control (target 900–1400 chars)
        $text = $this->shrinkToTarget($text, $title, $sections, $pointsForAspects);

        return trim($text);
    }

    private function oneLine(string $a, string $b): string
    {
        return $a . ' ' . $b;
    }

    /**
     * @param array<int,mixed> $planetsRaw
     * @return array<string,array{key:string,name:string,lon:float,sign:string,deg_in_sign:float,house:?int}>
     */
    private function normalizePlanets(array $planetsRaw): array
    {
        $out = [];
        foreach ($planetsRaw as $p) {
            if (!is_array($p)) continue;
            $key = strtolower(trim((string) ($p['key'] ?? '')));
            if ($key === '') continue;
            $lon = $p['lon'] ?? null;
            $sign = trim((string) ($p['sign'] ?? ''));
            $degInSign = $p['deg_in_sign'] ?? null;
            if (!is_numeric($lon) || !is_numeric($degInSign) || $sign === '') continue;
            $house = $p['house'] ?? null;
            $houseInt = is_numeric($house) ? (int) $house : null;
            if ($houseInt !== null && ($houseInt < 1 || $houseInt > 12)) {
                $houseInt = null;
            }

            $out[$key] = [
                'key' => $key,
                'name' => (string) ($p['name'] ?? $this->planetLabel($key)),
                'lon' => (float) $lon,
                'sign' => $sign,
                'deg_in_sign' => (float) $degInSign,
                'house' => $houseInt,
            ];
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return array{key:string,name:string,lon:float,sign:string,deg_in_sign:float,house:?int}|null
     */
    private function normalizePoint(string $key, mixed $raw, string $fallbackName): ?array
    {
        if (!is_array($raw)) return null;
        $lon = $raw['lon'] ?? null;
        $sign = trim((string) ($raw['sign'] ?? ''));
        $degInSign = $raw['deg_in_sign'] ?? null;
        if (!is_numeric($lon) || !is_numeric($degInSign) || $sign === '') return null;

        return [
            'key' => $key,
            'name' => $fallbackName,
            'lon' => (float) $lon,
            'sign' => $sign,
            'deg_in_sign' => (float) $degInSign,
            'house' => null,
        ];
    }

    /**
     * @param array{key:string,name:string,lon:float,sign:string,deg_in_sign:float,house:?int} $p
     */
    private function posLabel(array $p, bool $includeHouse = true): string
    {
        $deg = $this->fmtDeg($p['deg_in_sign']);
        $label = trim($p['sign'] . ' ' . $deg);
        if ($includeHouse && $p['house']) {
            $label .= ' (maison ' . $p['house'] . ')';
        }
        return $label;
    }

    private function fmtDeg(float $deg): string
    {
        $deg = fmod(($deg + 30.0), 30.0);
        $d = (int) floor($deg);
        $m = (int) round(($deg - $d) * 60);
        if ($m >= 60) {
            $d += 1;
            $m = 0;
        }
        $d = $d % 30;
        return sprintf('%d°%02d', $d, $m);
    }

    private function planetLabel(string $key): string
    {
        return match ($key) {
            'sun' => 'Soleil',
            'moon' => 'Lune',
            'mercury' => 'Mercure',
            'venus' => 'Vénus',
            'mars' => 'Mars',
            'jupiter' => 'Jupiter',
            'saturn' => 'Saturne',
            'uranus' => 'Uranus',
            'neptune' => 'Neptune',
            'pluto' => 'Pluton',
            'asc' => 'Ascendant',
            'mc' => 'Milieu du Ciel',
            default => ucfirst($key),
        };
    }

    private function planetSentence(string $key): string
    {
        return match ($key) {
            'mercury' => 'ta façon de réfléchir et de communiquer peut être très marquée: tu vas souvent droit à l’essentiel, mais tu gagnes à nuancer.',
            'venus' => 'ta manière d’aimer et de te relier aux autres peut être douce et exigeante à la fois: tu cherches du vrai, du simple, du rassurant.',
            'mars' => 'ton énergie et ta motivation se déclenchent quand il y a un objectif clair: tu peux être très efficace, mais attention au surmenage.',
            default => 'ça colore ton fonctionnement au quotidien.',
        };
    }

    private function outerPlanetSentence(string $key): string
    {
        return match ($key) {
            'jupiter' => 'tu grandis quand tu élargis ton horizon: apprendre, transmettre, faire confiance, sans en faire trop.',
            'saturn' => 'tu consolides ta vie par la structure: patience, limites, régularité — parfois au prix d’une autocritique à apprivoiser.',
            'uranus' => 'tu as besoin d’air et de liberté: tu peux surprendre, innover, casser les habitudes quand elles t’étouffent.',
            'neptune' => 'ton intuition est un radar: elle t’aide à sentir l’ambiance, mais tu gagnes à garder des repères concrets.',
            'pluto' => 'tu traverses des cycles de transformation: quand tu lâches l’ancien, tu retrouves une force très profonde.',
            default => 'c’est une dynamique de fond qui se révèle avec le temps.',
        };
    }

    /**
     * @param array<string,array{key:string,name:string,lon:float,sign:string,deg_in_sign:float,house:?int}> $points
     * @return array<int,array{label:string,meaning:string,score:int,delta:float}>
     */
    private function computeAspects(array $points): array
    {
        $keys = array_keys($points);
        sort($keys);

        $pairs = [];
        for ($i = 0; $i < count($keys); $i++) {
            for ($j = $i + 1; $j < count($keys); $j++) {
                $a = $points[$keys[$i]];
                $b = $points[$keys[$j]];

                $dist = $this->angleDistance($a['lon'], $b['lon']);
                $aspect = $this->matchAspect($dist);
                if (!$aspect) continue;

                [$type, $exact, $delta] = $aspect;
                $score = $this->aspectPriority($a['key'], $b['key']);

                $pairs[] = [
                    'label' => $this->planetLabel($a['key']) . ' ' . $type . ' ' . $this->planetLabel($b['key']),
                    'meaning' => $this->aspectMeaning($type),
                    'score' => $score,
                    'delta' => $delta,
                ];
            }
        }

        usort($pairs, function ($x, $y) {
            // Higher score first, then tighter orb (smaller delta).
            if ($x['score'] !== $y['score']) return $y['score'] <=> $x['score'];
            if ($x['delta'] !== $y['delta']) return $x['delta'] <=> $y['delta'];
            return strcmp($x['label'], $y['label']);
        });

        // Limit to 10 max.
        return array_slice($pairs, 0, 10);
    }

    private function angleDistance(float $a, float $b): float
    {
        $d = abs(fmod(($a - $b), 360.0));
        if ($d > 180.0) $d = 360.0 - $d;
        return $d;
    }

    /**
     * @return array{0:string,1:float,2:float}|null [label, exactDeg, delta]
     */
    private function matchAspect(float $dist): ?array
    {
        $defs = [
            ['conjonction', 0.0, 8.0],
            ['sextile', 60.0, 6.0],
            ['carré', 90.0, 7.0],
            ['trigone', 120.0, 7.0],
            ['opposition', 180.0, 8.0],
        ];

        foreach ($defs as [$label, $exact, $orb]) {
            $delta = abs($dist - $exact);
            if ($delta <= $orb) {
                return [(string) $label, (float) $exact, (float) $delta];
            }
        }

        return null;
    }

    private function aspectMeaning(string $type): string
    {
        return match ($type) {
            'conjonction' => 'ça fusionne deux parts de toi: très puissant, mais parfois intense; tu peux apprendre à doser.',
            'opposition' => 'ça te pousse à chercher l’équilibre entre deux besoins: quand tu ajustes, tu gagnes en maturité.',
            'carré' => 'c’est un petit défi moteur: ça peut crisper, mais ça t’aide souvent à progresser et à agir.',
            'trigone' => 'c’est une facilité naturelle: ça coule, mais tu gagnes à l’utiliser consciemment.',
            'sextile' => 'c’est une opportunité: quand tu la déclenches, ça ouvre des portes et des solutions.',
            default => 'ça colore tes dynamiques.',
        };
    }

    private function aspectPriority(string $a, string $b): int
    {
        $p = fn (string $k) => match ($k) {
            'sun', 'moon', 'asc' => 300,
            'mercury', 'venus', 'mars' => 200,
            'jupiter', 'saturn', 'uranus', 'neptune', 'pluto' => 120,
            'mc' => 80,
            default => 50,
        };

        return $p($a) + $p($b);
    }

    /**
     * @param string $full
     * @param string $title
     * @param array<int,string> $sections
     * @param array<string,mixed> $points
     */
    private function shrinkToTarget(string $full, string $title, array $sections, array $points): string
    {
        $min = 900;
        $max = 1400;

        if (mb_strlen($full) <= $max) {
            return $full;
        }

        // If too long, reduce aspects count progressively.
        $natal = ['planets' => []];
        foreach ($points as $k => $p) {
            if ($k === 'asc' || $k === 'mc') continue;
            $natal['planets'][] = $p;
        }

        $aspectsAll = $this->computeAspects($points);
        foreach ([7, 5] as $limit) {
            $aspectLines = [];
            foreach (array_slice($aspectsAll, 0, $limit) as $a) {
                $aspectLines[] = $a['label'] . ' : ' . $a['meaning'];
            }

            $re = [];
            foreach ($sections as $s) {
                if (str_starts_with($s, '4) Aspects')) {
                    if ($aspectLines === []) continue;
                    $re[] = "4) Aspects clés\n- " . implode("\n- ", $aspectLines);
                } else {
                    $re[] = $s;
                }
            }

            $txt = $title . "\n\n" . implode("\n\n", $re);
            if (mb_strlen($txt) <= $max) {
                return $txt;
            }
        }

        // Last resort: trim to max length without cutting mid-word too harshly.
        $cut = mb_substr($full, 0, $max - 1);
        $cut = preg_replace('/\s+\S*$/u', '', (string) $cut) ?: $cut;
        return rtrim($cut) . '…';
    }
}
