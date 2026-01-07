import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// PWA (production only): register the service worker.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker
			.register('/service-worker.js', { scope: '/' })
			.catch((err) => console.error('[pwa] service worker registration failed', err));
	});
}
