import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const reverbEnv = {
	key: import.meta.env.VITE_REVERB_APP_KEY,
	host: import.meta.env.VITE_REVERB_HOST,
	port: import.meta.env.VITE_REVERB_PORT,
	scheme: import.meta.env.VITE_REVERB_SCHEME,
};

window.__reverbEnv = reverbEnv;
console.log('[reverb] env', reverbEnv);

try {
	if (!reverbEnv.key || !reverbEnv.host) {
		console.error('[reverb] missing VITE_REVERB_* env vars', reverbEnv);
	} else {
		window.Echo = new Echo({
			broadcaster: 'reverb',
			key: reverbEnv.key,
			wsHost: reverbEnv.host,
			wsPort: Number(reverbEnv.port ?? 80),
			wssPort: Number(reverbEnv.port ?? 443),
			forceTLS: (reverbEnv.scheme ?? 'https') === 'https',
			enabledTransports: ['ws', 'wss'],
			authEndpoint: '/broadcasting/auth',
			auth: {
				headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
			},
		});

		try {
			window.dispatchEvent(new CustomEvent('echo:ready', { detail: { echo: window.Echo } }));
		} catch (e) {
			// no-op
		}
	}
} catch (e) {
	console.error('[reverb] failed to init Echo', e, reverbEnv);
}

// Minimal debug hooks (useful on Windows/Docker where WS/auth issues are common)
try {
	const pusher = window.Echo?.connector?.pusher;
	if (pusher?.connection) {
		pusher.connection.bind('connected', () => console.log('[reverb] connected'));
		pusher.connection.bind('disconnected', () => console.log('[reverb] disconnected'));
		pusher.connection.bind('error', (err) => console.error('[reverb] error', err));
		pusher.connection.bind('state_change', (states) => console.log('[reverb] state', states));
	}
} catch (e) {
	// no-op
}
