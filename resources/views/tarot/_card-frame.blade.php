@php
    /**
     * Tarot card frame (PNG majors): crops the external green frame while keeping the whole card visible.
     *
     * Expects:
     * - $src: string (required)
     * - $alt: string
     * - $class: string (extra classes for the wrapper)
     * - $imgClass: string (extra classes for the <img>)
     * - $loading: 'lazy'|'eager'
     * - $decoding: 'async'|'sync'|'auto'
     * - $styleVars: string (optional CSS vars e.g. "--tarot-crop-top:12%; --tarot-aspect:0.69;")
     * - $imgStyle: string (optional inline style; typically transform for reversed cards)
     * - $debug: bool
     */

    $src = (string) ($src ?? '');
    $alt = (string) ($alt ?? '');
    $class = (string) ($class ?? '');
    $imgClass = (string) ($imgClass ?? '');
    $loading = (string) ($loading ?? 'lazy');
    $decoding = (string) ($decoding ?? 'async');
    $styleVars = (string) ($styleVars ?? '');
    $imgStyle = (string) ($imgStyle ?? '');
    $debug = (bool) ($debug ?? false);

    if ($src === '') {
        return;
    }
@endphp

<div
    class="tarot-card-frame {{ $debug ? 'tarot-card-frame--debug' : '' }} {{ $class }}"
    style="{{ $styleVars }}"
    @if($debug) data-tarot-debug="1" @endif
>
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        class="tarot-card-frame__img {{ $imgClass }}"
        style="{{ $imgStyle }}"
        loading="{{ $loading }}"
        decoding="{{ $decoding }}"
    />
</div>
