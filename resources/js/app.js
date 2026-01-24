import './bootstrap';

import '@phosphor-icons/web/regular';

import Alpine from 'alpinejs';

import './transition-manager';
import './chess-page';

window.Alpine = Alpine;

Alpine.start();

function getMeta(name) {
	const el = document.querySelector(`meta[name="${name}"]`);
	return el ? el.getAttribute('content') : null;
}

function isPushOptedOut() {
	try {
		return window.localStorage.getItem('famille_push_optout') === '1';
	} catch (e) {
		return false;
	}
}

function isPushOptedIn() {
	try {
		return window.localStorage.getItem('famille_push_optin') === '1';
	} catch (e) {
		return false;
	}
}

function setPushOptedOut(value) {
	try {
		if (value) window.localStorage.setItem('famille_push_optout', '1');
		else window.localStorage.removeItem('famille_push_optout');
	} catch (e) {
		// ignore
	}
}

function setPushOptedIn(value) {
	try {
		if (value) window.localStorage.setItem('famille_push_optin', '1');
		else window.localStorage.removeItem('famille_push_optin');
	} catch (e) {
		// ignore
	}
}

function urlBase64ToUint8Array(base64String) {
	const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
	const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
	const rawData = window.atob(base64);
	const outputArray = new Uint8Array(rawData.length);
	for (let i = 0; i < rawData.length; ++i) {
		outputArray[i] = rawData.charCodeAt(i);
	}
	return outputArray;
}

async function sendSubscriptionToBackend(subscription) {
	const csrf = getMeta('csrf-token');
	if (!csrf) return;

	const payload = subscription.toJSON();
	payload.userAgent = navigator.userAgent;

	await fetch('/push/subscribe', {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': csrf,
			Accept: 'application/json',
		},
		body: JSON.stringify(payload),
	});
}

async function sendUnsubscribeToBackend(endpoint) {
	const csrf = getMeta('csrf-token');
	if (!csrf) return;
	if (!endpoint) return;

	await fetch('/push/unsubscribe', {
		method: 'DELETE',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': csrf,
			Accept: 'application/json',
		},
		body: JSON.stringify({ endpoint }),
	});
}

async function ensurePushSubscription() {
	if (isPushOptedOut()) return;
	const vapidPublicKey = getMeta('vapid-public-key');
	if (!vapidPublicKey) return;
	if (!('serviceWorker' in navigator)) return;
	if (!('PushManager' in window)) return;
	if (!('Notification' in window)) return;
	if (Notification.permission !== 'granted') return;

	const registration = await navigator.serviceWorker.ready;
	let subscription = await registration.pushManager.getSubscription();

	// If we already have a subscription (common on Android/PWA even after storage resets),
	// always ensure the backend has it as well.
	if (subscription) {
		await sendSubscriptionToBackend(subscription);
		return;
	}

	// Do not create a new subscription unless the user explicitly opted in.
	if (!isPushOptedIn()) return;

	if (!subscription) {
		subscription = await registration.pushManager.subscribe({
			userVisibleOnly: true,
			applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
		});
	}

	await sendSubscriptionToBackend(subscription);
}

function isPushSupported() {
	return (
		('serviceWorker' in navigator) &&
		('PushManager' in window) &&
		('Notification' in window)
	);
}

async function ensureServiceWorkerRegistered() {
	if (!('serviceWorker' in navigator)) return null;
	const swVersion = (import.meta.env.PROD ? (() => {
		try {
			const url = new URL(import.meta.url);
			const file = url.pathname.split('/').pop() || '';
			const m = file.match(/app-([A-Za-z0-9_-]+)\.(?:js|mjs)$/);
			return m ? m[1] : 'prod';
		} catch (e) {
			return 'prod';
		}
	})() : 'dev');
	const swUrl = `/service-worker.js?v=${encodeURIComponent(swVersion)}`;

	// If already registered, ready will resolve.
	try {
		const reg = await navigator.serviceWorker.ready;
		if (reg) return reg;
	} catch (e) {
		// ignore
	}

	// Register on-demand (explicit user action may call this).
	try {
		await navigator.serviceWorker.register(swUrl, { scope: '/' });
		return await navigator.serviceWorker.ready;
	} catch (e) {
		return null;
	}
}

async function enablePushNotifications() {
	if (!isPushSupported()) {
		return { ok: false, reason: 'unsupported' };
	}

	const vapidPublicKey = getMeta('vapid-public-key');
	if (!vapidPublicKey) {
		return { ok: false, reason: 'missing_vapid' };
	}

	const reg = await ensureServiceWorkerRegistered();
	if (!reg) {
		return { ok: false, reason: 'no_service_worker' };
	}

	let perm = Notification.permission;
	if (perm !== 'granted') {
		try {
			perm = await Notification.requestPermission();
		} catch (e) {
			return { ok: false, reason: 'permission_error' };
		}
	}
	if (perm !== 'granted') {
		return { ok: false, reason: 'permission_denied' };
	}

	let subscription = await reg.pushManager.getSubscription();
	if (!subscription) {
		subscription = await reg.pushManager.subscribe({
			userVisibleOnly: true,
			applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
		});
	}

	await sendSubscriptionToBackend(subscription);
	setPushOptedIn(true);
	setPushOptedOut(false);
	return { ok: true };
}

async function disablePushNotifications() {
	if (!('serviceWorker' in navigator)) return { ok: false, reason: 'unsupported' };
	let reg = null;
	try {
		reg = await navigator.serviceWorker.ready;
	} catch (e) {
		return { ok: false, reason: 'no_service_worker' };
	}
	if (!reg) return { ok: false, reason: 'no_service_worker' };

	const subscription = await reg.pushManager.getSubscription();
	if (!subscription) return { ok: true, already: true };

	const endpoint = subscription?.endpoint;
	try {
		await subscription.unsubscribe();
	} catch (e) {
		// ignore
	}
	try {
		await sendUnsubscribeToBackend(endpoint);
	} catch (e) {
		// ignore
	}
	setPushOptedIn(false);
	setPushOptedOut(true);
	return { ok: true };
}

async function hasActivePushSubscription() {
	if (!('serviceWorker' in navigator)) return false;
	try {
		const reg = await navigator.serviceWorker.ready;
		const subscription = await reg.pushManager.getSubscription();
		return !!subscription;
	} catch (e) {
		return false;
	}
}

window.famillePush = {
	enable: enablePushNotifications,
	disable: disablePushNotifications,
	ensureIfGranted: ensurePushSubscription,
	hasActive: hasActivePushSubscription,
};

function isStandalonePwa() {
	try {
		if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) return true;
		// iOS Safari
		if (typeof window.navigator === 'object' && window.navigator && window.navigator.standalone) return true;
	} catch (e) {
		// ignore
	}
	return false;
}

function isEditableElement(el) {
	if (!el) return false;
	const tag = (el.tagName || '').toLowerCase();
	if (tag === 'textarea') return true;
	if (tag !== 'input') return false;
	const type = String(el.getAttribute('type') || 'text').toLowerCase();
	// Consider only text-like inputs.
	return !['button', 'submit', 'reset', 'checkbox', 'radio', 'range', 'file', 'color', 'image'].includes(type);
}

function setupPwaKeyboardDockFix() {
	// PWA only: keep the fixed bottom dock visible above the on-screen keyboard.
	if (!isStandalonePwa()) return;
	if (!window.visualViewport) return;

	const root = document.documentElement;
	let focused = false;

	const computeBottom = () => {
		const vv = window.visualViewport;
		if (!vv) return 0;

		// Prefer the layout viewport height (more stable on iOS/PWA than innerHeight).
		const layoutH = document.documentElement ? document.documentElement.clientHeight : 0;
		const innerH = window.innerHeight || 0;
		const visualBottom = vv.offsetTop + vv.height;

		// Distance from the bottom of the layout viewport to the bottom of the visual viewport.
		const overlap1 = (layoutH > 0) ? (layoutH - visualBottom) : 0;
		const overlap2 = (innerH > 0) ? (innerH - visualBottom) : 0;
		const bottom = Math.max(0, overlap1, overlap2);

		// Ignore tiny jitter; add a cushion so the composer doesn't get clipped.
		if (bottom < 12) return 0;
		return Math.round(bottom + 16);
	};

	const apply = () => {
		const px = focused ? computeBottom() : 0;
		root.style.setProperty('--vv-bottom', `${px}px`);
	};

	const schedule = () => {
		requestAnimationFrame(() => requestAnimationFrame(apply));
	};

	let pollTimer = null;
	const startPolling = () => {
		if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
		let n = 0;
		pollTimer = setInterval(() => {
			n += 1;
			schedule();
			if (n >= 16) {
				clearInterval(pollTimer);
				pollTimer = null;
			}
		}, 50);
	};

	document.addEventListener('focusin', (e) => {
		if (!isEditableElement(e.target)) return;
		focused = true;
		root.dataset.kbd = '1';
		schedule();
		// iOS can update viewport metrics late (sometimes only after first keystroke).
		startPolling();
	});

	document.addEventListener('focusout', (e) => {
		if (!isEditableElement(e.target)) return;
		focused = false;
		if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
		try {
			delete root.dataset.kbd;
		} catch (e2) {}
		// Let iOS settle viewport metrics.
		setTimeout(schedule, 60);
	});

	window.visualViewport.addEventListener('resize', schedule, { passive: true });
	window.visualViewport.addEventListener('scroll', schedule, { passive: true });
	window.addEventListener('orientationchange', () => setTimeout(schedule, 250), { passive: true });

	schedule();
}

setupPwaKeyboardDockFix();

// PWA (production only): register the service worker.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		let swVersion = 'prod';
		try {
			const url = new URL(import.meta.url);
			const file = url.pathname.split('/').pop() || '';
			const m = file.match(/app-([A-Za-z0-9_-]+)\.(?:js|mjs)$/);
			swVersion = m ? m[1] : 'prod';
		} catch (e) {
			// ignore
		}
		const swUrl = `/service-worker.js?v=${encodeURIComponent(swVersion)}`;

		// If a new SW takes control, reload once to ensure fresh assets.
		navigator.serviceWorker.addEventListener('controllerchange', () => {
			if (window.__familleSwReloaded) return;
			window.__familleSwReloaded = true;
			window.location.reload();
		});

		navigator.serviceWorker
			.register(swUrl, { scope: '/' })
			.then(() => {
				try {
					if (navigator.serviceWorker && navigator.serviceWorker.controller) {
						navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_BADGE' });
					}
				} catch (e) {
					// ignore
				}
				// If the user already granted permission previously, keep the subscription fresh.
				ensurePushSubscription().catch(() => {});
			})
			.catch((err) => console.error('[pwa] service worker registration failed', err));
	});
}

// Chat UI wiring (explicit opt-in)
document.addEventListener('DOMContentLoaded', () => {
	const btn = document.getElementById('chatPushToggle');
	const status = document.getElementById('chatPushStatus');
	if (!btn || !status) return;

	function setStatus(text) {
		status.textContent = String(text || '');
	}

	async function refreshLabel() {
		if (!isPushSupported()) {
			btn.disabled = true;
			btn.classList.add('opacity-50', 'cursor-not-allowed');
			setStatus('Notifications non supportées sur cet appareil/navigateur.');
			return;
		}

		const perm = Notification.permission;
		const active = await hasActivePushSubscription();
		if (active && !isPushOptedIn()) {
			setPushOptedIn(true);
		}
		if (perm === 'denied') {
			btn.disabled = true;
			btn.classList.add('opacity-50', 'cursor-not-allowed');
			setStatus('Notifications refusées dans le navigateur.');
			return;
		}

		btn.disabled = false;
		btn.classList.remove('opacity-50', 'cursor-not-allowed');
		btn.textContent = active ? '🔕 Notifications' : '🔔 Notifications';
		if (active) {
			setStatus('Notifications activées (push).');
		} else if (isPushOptedOut() || !isPushOptedIn()) {
			setStatus('Notifications désactivées.');
		} else {
			setStatus('');
		}

		// iOS hint (best-effort): only show when permission is default and not active.
		if (!active && perm === 'default' && !isPushOptedOut()) {
			setStatus('iPhone: fonctionne via PWA installée (Ajouter à l’écran d’accueil).');
		}
	}

	refreshLabel().catch(() => {});

	btn.addEventListener('click', async () => {
		btn.disabled = true;
		try {
			const active = await hasActivePushSubscription();
			if (active) {
				await disablePushNotifications();
				setStatus('Notifications désactivées.');
			} else {
				const res = await enablePushNotifications();
				if (!res.ok) {
					setStatus('Impossible d’activer les notifications.');
				} else {
					setStatus('Notifications activées (push).');
				}
			}
		} finally {
			await refreshLabel();
		}
	});
});
