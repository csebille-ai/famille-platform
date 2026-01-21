@props([
    'subject' => null,
    'sizeClass' => 'w-9 h-9',
    'class' => '',
    'fallbackClass' => 'bg-slate-900 text-white',
    'alt' => '',
])

@php
    $avatarUrl = '';
    try {
        $avatarUrl = (string) (avatarUrl($subject) ?? '');
    } catch (\Throwable $e) {
        $avatarUrl = '';
    }

    $initials = '';
    try {
        if (is_object($subject) && method_exists($subject, 'initials')) {
            $initials = (string) $subject->initials();
        }
    } catch (\Throwable $e) {
        $initials = '';
    }
    $initials = trim($initials) !== '' ? $initials : '—';

    $wrapperClass = trim($sizeClass . ' rounded-full overflow-hidden flex items-center justify-center font-semibold ' . $class);
    $fallback = trim($fallbackClass);
@endphp

<div {{ $attributes->merge(['class' => $wrapperClass]) }}>
    @if($avatarUrl !== '')
        <img src="{{ $avatarUrl }}" alt="{{ $alt }}" class="h-full w-full object-cover" loading="lazy" />
    @else
        <div class="h-full w-full flex items-center justify-center text-xs {{ $fallback }}">
            {{ $initials }}
        </div>
    @endif
</div>
