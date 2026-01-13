@props(['active'])

@php
$classes = ($active ?? false)
            ? 'ui-nav-link-mobile ui-nav-link-mobile--active'
            : 'ui-nav-link-mobile';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
