<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Astro\ChineseZodiac;
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
        $moon = trim((string) ($sig['moon_sign'] ?? ($p?->moon_sign ?? '')));
        $asc = trim((string) ($sig['ascendant'] ?? ($p?->ascendant_sign ?? '')));

        $kemeticIndex = (int) ($sig['kemetic_decan_index'] ?? ($p?->kemetic_decan_index ?? 0));
        $kemeticLabel = trim((string) ($sig['kemetic_decan_label'] ?? ($p?->kemetic_decan_label ?? '')));
        $kemeticKeyword = trim((string) ($sig['kemetic_decan_keyword'] ?? ($p?->kemetic_decan_keyword ?? '')));

        $ch = $sig['chinese'] ?? null;
        $chStr = '';
        if (is_array($ch)) {
            $animal = trim((string) ($ch['animal'] ?? ''));
            $element = trim((string) ($ch['element'] ?? ''));
            $polarity = trim((string) ($ch['polarity'] ?? ''));
            $chStr = ChineseZodiac::formatDisplayLabel($animal, $element, $polarity);
        } elseif (is_string($ch)) {
            $chStr = ChineseZodiac::formatLegacyDisplayString($ch);
        }
        if ($chStr === '') {
            $chStr = ChineseZodiac::formatDisplayLabel(
                trim((string) ($p?->chinese_animal ?? '')),
                trim((string) ($p?->chinese_element ?? '')),
                trim((string) ($p?->chinese_yin_yang ?? '')),
            );
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
        if (!in_array($tab, ['profile', 'chart'], true)) {
            $tab = 'profile';
        }

        $astro = [
            'sun_sign' => $sun,
            'moon_sign' => $moon,
            'ascendant' => $asc,
            'chinese' => $chStr,
            'kemetic_decan_index' => $kemeticIndex > 0 ? $kemeticIndex : null,
            'kemetic_decan_label' => $kemeticLabel !== '' ? $kemeticLabel : null,
            'kemetic_decan_keyword' => $kemeticKeyword !== '' ? $kemeticKeyword : null,
            'natal' => $p?->natal,
            'archetype' => $archetype,
            'talents' => $talents,
            'vigilance' => $vigilance,
            'precision' => $precision,
        ];

        $person = $user->person;

        return view('astro.show', compact('user', 'person', 'astro', 'tab'));
    }
}
