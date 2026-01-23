@props([
    'days' => 0,
    'maxDays' => 45,
    'prefix' => 'J-',
])

@php
    $daysInt = (int) $days;

    $badgeMaxDays = max(0, (int) $maxDays);
    $d = max(0, min($daysInt, $badgeMaxDays));
    $t = 1 - ($badgeMaxDays > 0 ? ($d / $badgeMaxDays) : 0);

    $redHue = 12;
    $greenHue = 150;
    $badgeHue = (int) round($redHue + (($greenHue - $redHue) * $t));

    // Add a subtle gradient so the progression reads better than a flat fill.
    $h1 = max(0, min(360, $badgeHue - 10));
    $h2 = max(0, min(360, $badgeHue + 10));
    // Keep it premium: desaturated, not neon.
    $bg1 = "hsl({$h1} 34% 93%)";
    $bg2 = "hsl({$h2} 34% 87%)";
    $badgeBorder = "hsl({$badgeHue} 26% 70%)";

    $badgeStyle = "background:linear-gradient(135deg,{$bg1},{$bg2});border-color:{$badgeBorder};color:var(--fam-text);";

    $userStyle = $attributes->get('style');
    $attrs = $attributes->except('style');
    $style = $badgeStyle . (is_string($userStyle) && trim($userStyle) !== '' ? (';' . $userStyle) : '');
@endphp

<div {{ $attrs->merge(['class' => 'inline-flex items-center rounded-full border px-2 py-0.5 text-[0.7rem] font-extrabold']) }} style="{{ $style }}">
    {{ $prefix }}{{ $daysInt }}
</div>
