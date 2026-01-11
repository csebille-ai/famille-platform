import './bootstrap';

import '@phosphor-icons/web/regular';

import Alpine from 'alpinejs';

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
	if (!isPushOptedIn()) return;
	const vapidPublicKey = getMeta('vapid-public-key');
	if (!vapidPublicKey) return;
	if (!('serviceWorker' in navigator)) return;
	if (!('PushManager' in window)) return;
	if (!('Notification' in window)) return;
	if (Notification.permission !== 'granted') return;

	const registration = await navigator.serviceWorker.ready;
	let subscription = await registration.pushManager.getSubscription();
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
	// If already registered, ready will resolve.
	try {
		const reg = await navigator.serviceWorker.ready;
		if (reg) return reg;
	} catch (e) {
		// ignore
	}

	// Register on-demand (explicit user action may call this).
	try {
		await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
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

// PWA (production only): register the service worker.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker
			.register('/service-worker.js', { scope: '/' })
			.then(() => {
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
