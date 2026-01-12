@php
	// The provided logo image contains padding; we "zoom" it in a clipped square
	// so it reads better at small sizes.
	$zoom = 1.55;
@endphp

<span {{ $attributes->merge(['class' => 'inline-block aspect-square overflow-hidden']) }}>
	<img
		src="{{ asset('images/logo1.png') }}"
		alt="Logo"
		class="block w-full h-full"
		style="transform: scale({{ $zoom }}); transform-origin: center;"
	/>
</span>