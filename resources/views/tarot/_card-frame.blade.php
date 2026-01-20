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
    $finalStyleVars = trim(($styleVars !== '' ? $styleVars . ' ' : '') . $rotateVar);
@endphp

<div
    class="tarot-card-frame {{ $variantClass }} {{ $debug ? 'tarot-card-frame--debug tarot-debug-outline' : '' }} {{ $class }}"
    style="{{ $finalStyleVars }}"
    data-debug="card-frame-v2"
    @if($debug) data-tarot-debug="1" @endif
>
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        class="tarot-card-frame__img {{ $imgClass }}"
        loading="{{ $loading }}"
        decoding="{{ $decoding }}"
    />
</div>
