<?php

namespace App\Services\Ephemeris;

use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;

class EphemerisService
{
    /**
     * Returns the "today" ephemeris for the configured timezone.
     */
    public function today(): array
    {
        $tz = (string) config('ephemeris.timezone', 'Europe/Paris');
        return $this->forDate(now()->timezone($tz));
    }

    /**
     * Returns ephemeris for a given date (timezone-aware), cached per day.
     */
    public function forDate(Carbon $date): array
    {
        $tz = (string) config('ephemeris.timezone', 'Europe/Paris');
        $day = $date->copy()->timezone($tz)->startOfDay();
        $key = 'ephemeris:' . $day->format('Y-m-d') . ':' . $tz;

        $ttlSeconds = max(300, $day->copy()->addDay()->diffInSeconds(now()->timezone($tz)));

        return Cache::remember($key, $ttlSeconds, function () use ($day, $tz) {
            $saintName = $this->saintForDate($day);
            $saintToken = $this->saintToken($saintName);

            $sun = $this->sunTimes($day, $tz);

            $themeUsed = $this->pickTheme($day);
            $proverbText = $this->generateProverb($day, $saintName, $saintToken, $themeUsed);

            return [
                'date_label' => $this->dateLabel($day),
                'sunrise_time' => $sun['sunrise_time'],
                'sunset_time' => $sun['sunset_time'],
                'saint_name' => $saintName,
                'proverb_text' => $proverbText,
                'theme_used' => $themeUsed,
            ];
        });
    }

    private function dateLabel(Carbon $date): string
    {
        // French short labels without trailing dots.
        $weekdays = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        $months = [1 => 'jan', 2 => 'fév', 3 => 'mar', 4 => 'avr', 5 => 'mai', 6 => 'jun', 7 => 'jul', 8 => 'aoû', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'déc'];

        $wd = $weekdays[(int) $date->dayOfWeekIso] ?? '—';
        $m = $months[(int) $date->month] ?? '—';

        return $wd . ' ' . $date->format('j') . ' ' . $m;
    }

    private function saintForDate(Carbon $date): string
    {
        $mmdd = $date->format('m-d');
        $saints = (array) config('ephemeris.saints', []);
        $saint = (string) ($saints[$mmdd] ?? 'Saint Mystère');
        return trim($saint) !== '' ? trim($saint) : 'Saint Mystère';
    }

    /**
     * Extracts the rhyme token from the saint name.
     * Examples: "Sainte Claire" => "Claire", "Saint-Jean" => "Jean".
     */
    private function saintToken(string $saintName): string
    {
        $s = trim($saintName);
        if ($s === '') return 'Mystère';

        // Normalize hyphens to spaces and remove common prefixes.
        $s = str_replace(['-', '’', "'"], [' ', ' ', ' '], $s);
        $s = preg_replace('/\bsaints?\b\s*/iu', '', $s) ?? $s;
        $s = preg_replace('/\bsaintes?\b\s*/iu', '', $s) ?? $s;
        $s = trim($s);

        $parts = preg_split('/\s+/u', $s) ?: [];
        $last = (string) (end($parts) ?: 'Mystère');

        // Keep letters only.
        $last = preg_replace('/[^\p{L}]+/u', '', $last) ?? $last;
        $last = trim($last);

        return $last !== '' ? $last : 'Mystère';
    }

    private function sunTimes(Carbon $day, string $tz): array
    {
        $lat = (float) data_get(config('ephemeris.location'), 'lat', 48.8566);
        $lng = (float) data_get(config('ephemeris.location'), 'lng', 2.3522);

        $offsetHours = 0.0;
        try {
            $offsetHours = (new DateTimeZone($tz))->getOffset($day->toDateTime()) / 3600;
        } catch (\Throwable $e) {
            $offsetHours = 0.0;
        }

        $zenith = 90 + (50 / 60); // official

        $sunrise = date_sunrise($day->timestamp, SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $offsetHours);
        $sunset = date_sunset($day->timestamp, SUNFUNCS_RET_STRING, $lat, $lng, $zenith, $offsetHours);

        return [
            'sunrise_time' => is_string($sunrise) ? $sunrise : '',
            'sunset_time' => is_string($sunset) ? $sunset : '',
        ];
    }

    private function pickTheme(Carbon $day): string
    {
        $themes = array_keys((array) config('ephemeris.themes', []));
        if (count($themes) === 0) return 'nature';
        $idx = $this->stableIndex($day->format('Y-m-d') . '|theme', count($themes));
        return (string) ($themes[$idx] ?? 'nature');
    }

    private function generateProverb(Carbon $day, string $saintName, string $saintToken, string $theme): string
    {
        $key = $this->rhymeKeyForToken($saintToken);
        $banks = (array) config('ephemeris.rhyme_banks', []);
        $bank = ($key !== null && isset($banks[$key]) && is_array($banks[$key])) ? array_values(array_filter($banks[$key])) : [];

        $templates = (array) config('ephemeris.templates', []);
        if (count($templates) === 0) {
            $templates = ['À la {saint_name}, {fragment}, {rhyme_word}.'];
        }

        $fragment = function (int $attempt) use ($day, $theme): string {
            $themes = (array) config('ephemeris.themes', []);
            $t = (array) ($themes[$theme] ?? []);
            $actions = array_values(array_filter((array) ($t['actions'] ?? [])));
            $advices = array_values(array_filter((array) ($t['advices'] ?? [])));
            $images = array_values(array_filter((array) ($t['images'] ?? [])));

            $action = $this->pickStable($day, $actions, 'action|' . $theme . '|' . $attempt, 'Fais');
            $advice = $this->pickStable($day, $advices, 'advice|' . $theme . '|' . $attempt, 'reste serein');
            $image = $this->pickStable($day, $images, 'image|' . $theme . '|' . $attempt, 'le temps');

            return trim($action . ' ' . $image . ', ' . $advice);
        };

        // Try N times to satisfy strict rhyme bank rule.
        for ($i = 0; $i < 12; $i++) {
            if (count($bank) === 0) break;

            $rhymeWord = (string) $this->pickStable($day, $bank, 'rhyme|' . $key . '|' . $i, $saintToken);
            $tpl = (string) $this->pickStable($day, $templates, 'tpl|' . $i, $templates[0]);

            $text = str_replace(
                ['{saint_name}', '{fragment}', '{rhyme_word}'],
                [$saintName, $fragment($i), $this->ucFirst($rhymeWord)],
                $tpl
            );
            $text = preg_replace('/\s+/u', ' ', trim((string) $text)) ?? trim((string) $text);

            if ($this->isValidRhyme($text, $bank)) {
                return $text;
            }
        }

        // Fallback: repeat saint token (guaranteed "rhyme" by repetition).
        $fallbackTpl = 'À la {saint_name}, {fragment}, {rhyme_word}.';
        $text = str_replace(
            ['{saint_name}', '{fragment}', '{rhyme_word}'],
            [$saintName, $fragment(99), $this->ucFirst($saintToken)],
            $fallbackTpl
        );

        return preg_replace('/\s+/u', ' ', trim((string) $text)) ?? trim((string) $text);
    }

    private function rhymeKeyForToken(string $token): ?string
    {
        $map = (array) config('ephemeris.rhyme_keys', []);
        $t = mb_strtolower(trim($token), 'UTF-8');
        if ($t === '') return null;
        return isset($map[$t]) ? (string) $map[$t] : null;
    }

    private function isValidRhyme(string $proverb, array $bank): bool
    {
        $last = $this->lastWord($proverb);
        if ($last === '') return false;

        $lastLower = mb_strtolower($last, 'UTF-8');
        $bankLower = array_map(static fn ($w) => mb_strtolower((string) $w, 'UTF-8'), $bank);

        return in_array($lastLower, $bankLower, true);
    }

    private function lastWord(string $text): string
    {
        $t = trim($text);
        $t = preg_replace('/[\p{P}\p{S}]+$/u', '', $t) ?? $t;
        $parts = preg_split('/\s+/u', $t) ?: [];
        $last = (string) (end($parts) ?: '');
        $last = preg_replace('/[^\p{L}]+/u', '', $last) ?? $last;
        return trim($last);
    }

    private function pickStable(Carbon $day, array $list, string $salt, string $fallback): string
    {
        $list = array_values(array_filter($list, static fn ($v) => trim((string) $v) !== ''));
        if (count($list) === 0) return $fallback;
        $idx = $this->stableIndex($day->format('Y-m-d') . '|' . $salt, count($list));
        return (string) ($list[$idx] ?? $fallback);
    }

    private function stableIndex(string $seed, int $mod): int
    {
        if ($mod <= 1) return 0;
        $n = (int) sprintf('%u', crc32($seed));
        return (int) ($n % $mod);
    }

    private function ucFirst(string $s): string
    {
        $s = trim($s);
        if ($s === '') return $s;
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    }
}
