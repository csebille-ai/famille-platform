<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AstroProfileController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['astroProfile', 'person']);

        $sig = is_array($user->astro_signature_json ?? null) ? (array) $user->astro_signature_json : [];
        $p = $user->astroProfile;

        $sun = trim((string) ($sig['sun_sign'] ?? ($p?->western_sign ?? '')));
        $asc = trim((string) ($sig['ascendant'] ?? ($p?->ascendant_sign ?? '')));
        $life = (int) ($sig['life_path'] ?? ($p?->life_path ?? 0));

        $ch = $sig['chinese'] ?? null;
        $chStr = '';
        if (is_array($ch)) {
            $animal = trim((string) ($ch['animal'] ?? ''));
            $element = trim((string) ($ch['element'] ?? ''));
            $polarity = trim((string) ($ch['polarity'] ?? ''));
            $chStr = trim(implode(' ', array_values(array_filter([$polarity, $element, $animal], fn ($v) => trim((string) $v) !== ''))));
        } elseif (is_string($ch)) {
            $chStr = trim($ch);
        }
        if ($chStr === '') {
            $chStr = trim((string) ($p?->chinese_yin_yang ?? '')) . ' ' . trim((string) ($p?->chinese_element ?? '')) . ' ' . trim((string) ($p?->chinese_animal ?? ''));
            $chStr = trim(preg_replace('/\s+/u', ' ', $chStr) ?? $chStr);
        }

        $archetype = trim((string) ($sig['archetype'] ?? ($p?->archetype ?? '')));
        $talents = $sig['talents'] ?? ($p?->talents ?? []);
        $talents = is_array($talents) ? array_values(array_filter(array_map('strval', $talents))) : [];
        $vigilance = trim((string) ($sig['vigilance'] ?? ($p?->weakness ?? '')));

        $birthTime = trim((string) ($user->birth_time ?? ''));
        $birthPlace = trim((string) ($user->birth_place ?? ''));
        $hasCoords = $user->birth_latitude !== null && $user->birth_longitude !== null;

        $precision = 'unknown';
        if ($user->date_of_birth) {
            $precision = 'approx';
            if ($birthTime !== '' && $birthPlace !== '' && $hasCoords) {
                $precision = 'exact';
            }
        }

        $tab = (string) $request->query('tab', 'profile');
        if (!in_array($tab, ['profile', 'chart', 'places'], true)) {
            $tab = 'profile';
        }

        $astro = [
            'sun_sign' => $sun,
            'moon_sign' => '', // MVP placeholder
            'ascendant' => $asc,
            'chinese' => $chStr,
            'life_path' => $life,
            'archetype' => $archetype,
            'talents' => $talents,
            'vigilance' => $vigilance,
            'precision' => $precision,
        ];

        $person = $user->person;

        return view('astro.show', compact('user', 'person', 'astro', 'tab'));
    }
}
