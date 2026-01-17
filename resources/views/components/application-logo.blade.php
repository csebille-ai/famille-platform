<span {{ $attributes->merge(['class' => 'inline-block aspect-square overflow-hidden']) }}>
	<img
		src="{{ asset('images/icon-192.png') }}?v=3"
		srcset="{{ asset('images/icon-192.png') }}?v=3 192w, {{ asset('images/icon-512.png') }}?v=3 512w"
		sizes="80px"
		alt="Logo"
		class="block w-full h-full"
		loading="lazy"
	/>
</span>