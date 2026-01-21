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

    $badgeBg = "hsl({$badgeHue} 55% 90%)";
    $badgeBorder = "hsl({$badgeHue} 45% 72%)";

    $badgeStyle = "background-color:{$badgeBg};border-color:{$badgeBorder};color:#0f172a;";

    $userStyle = $attributes->get('style');
    $attrs = $attributes->except('style');
    $style = $badgeStyle . (is_string($userStyle) && trim($userStyle) !== '' ? (';' . $userStyle) : '');
@endphp

<div {{ $attrs->merge(['class' => 'inline-flex items-center rounded-full border px-2 py-1 text-xs font-extrabold']) }} style="{{ $style }}">
    {{ $prefix }}{{ $daysInt }}
</div>
