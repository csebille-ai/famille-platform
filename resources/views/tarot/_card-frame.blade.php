@php
    /**
     * Tarot card frame (PNG majors): crops the external green frame while keeping the whole card visible.
     *
     * Expects:
     * - $src: string (required)
     * - $alt: string
        * - $variant: string ('hero'|'thumb')
     * - $class: string (extra classes for the wrapper)
     * - $imgClass: string (extra classes for the <img>)
     * - $loading: 'lazy'|'eager'
     * - $decoding: 'async'|'sync'|'auto'
     * - $styleVars: string (optional CSS vars e.g. "--tarot-crop-top:12%; --tarot-aspect:0.69;")
        * - $rotate: int|float (optional degrees; typically 180 for reversed cards)
     * - $debug: bool
     */

    $src = (string) ($src ?? '');
    $alt = (string) ($alt ?? '');
    $variant = (string) ($variant ?? '');
    $class = (string) ($class ?? '');
    $imgClass = (string) ($imgClass ?? '');
    $loading = (string) ($loading ?? 'lazy');
    $decoding = (string) ($decoding ?? 'async');
    $styleVars = (string) ($styleVars ?? '');
    $rotate = $rotate ?? 0;
    $debug = (bool) ($debug ?? false);

    if ($src === '') {
        return;
    }

    $variant = trim($variant);
    $variantClass = $variant !== '' ? ('tarot-card-frame--' . $variant) : '';
    $rotateDeg = (is_numeric($rotate) ? ((float) $rotate) : 0.0);
    $rotateVar = abs($rotateDeg) > 0.0001 ? ('--tarot-rotate: ' . rtrim(rtrim(sprintf('%.2f', $rotateDeg), '0'), '.') . 'deg;') : '';

    // Robust defaults (can be overridden by $styleVars in debug mode).
    $defaultAspect = '--tarot-aspect: 0.665;';
    $defaultPosX = '--tarot-pos-x: 50%;';
    $defaultPosY = '--tarot-pos-y: ' . ($variant === 'hero' ? '52%' : '50%') . ';';
    $defaultZoom = '--tarot-zoom: ' . ($variant === 'hero' ? '1.08' : '1.12') . ';';
    $defaultRotate = ($rotateVar !== '' ? $rotateVar : '--tarot-rotate: 0deg;');

    // Order matters: defaults first, then caller overrides.
    $finalStyleVars = trim(implode(' ', array_filter([
        $defaultAspect,
        $defaultZoom,
        $defaultPosX,
        $defaultPosY,
        $defaultRotate,
        $styleVars,
    ])));
@endphp

<div
    class="tarot-card-frame {{ $variantClass }} relative block w-full overflow-hidden {{ $debug ? 'tarot-card-frame--debug tarot-debug-outline' : '' }} {{ $class }}"
    style="position: relative; overflow: hidden; width: 100%; display: block; aspect-ratio: var(--tarot-aspect); {{ $finalStyleVars }} {{ $debug ? 'outline:2px solid rgba(99,102,241,0.85); outline-offset:-2px;' : '' }}"
    data-debug="card-frame-v2"
    @if($debug) data-tarot-debug="1" @endif
>
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        class="tarot-card-frame__img absolute inset-0 block h-full w-full object-cover {{ $imgClass }}"
        style="position: absolute; inset: 0; width: 100%; height: 100%; display: block; object-fit: cover; object-position: var(--tarot-pos-x) var(--tarot-pos-y); transform: rotate(var(--tarot-rotate)) scale(var(--tarot-zoom)); transform-origin: center;"
        loading="{{ $loading }}"
        decoding="{{ $decoding }}"
    />
</div>
