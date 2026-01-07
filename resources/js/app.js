import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

function getMeta(name) {
	const el = document.querySelector(`meta[name="${name}"]`);
	return el ? el.getAttribute('content') : null;
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

async function ensurePushSubscription() {
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

function setupOneTimePermissionClick() {
	if (!('Notification' in window)) return;
	if (Notification.permission !== 'default') return;
	// Request permission on the first user gesture (most browsers require a gesture).
	const handler = async () => {
		document.removeEventListener('click', handler, { capture: true });
		try {
			const result = await Notification.requestPermission();
			if (result === 'granted') {
				await ensurePushSubscription();
			}
		} catch (e) {
			// ignore
		}
	};
	document.addEventListener('click', handler, { capture: true, once: true });
}

// PWA (production only): register the service worker.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
	window.addEventListener('load', () => {
		navigator.serviceWorker
			.register('/service-worker.js', { scope: '/' })
			.then(() => {
				setupOneTimePermissionClick();
				ensurePushSubscription().catch(() => {});
			})
			.catch((err) => console.error('[pwa] service worker registration failed', err));
	});
}
