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
    $bg1 = "hsl({$h1} 62% 92%)";
    $bg2 = "hsl({$h2} 62% 86%)";
    $badgeBorder = "hsl({$badgeHue} 45% 72%)";

    $badgeStyle = "background:linear-gradient(135deg,{$bg1},{$bg2});border-color:{$badgeBorder};color:#0f172a;";

    $userStyle = $attributes->get('style');
    $attrs = $attributes->except('style');
    $style = $badgeStyle . (is_string($userStyle) && trim($userStyle) !== '' ? (';' . $userStyle) : '');
@endphp

<div {{ $attrs->merge(['class' => 'inline-flex items-center rounded-full border px-2 py-1 text-xs font-extrabold']) }} style="{{ $style }}">
    {{ $prefix }}{{ $daysInt }}
</div>
