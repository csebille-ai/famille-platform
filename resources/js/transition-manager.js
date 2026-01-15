/* Cross-page shared element transitions (Accueil -> Viewer) without changing routes.
   Works by storing a small "pending" payload in sessionStorage and replaying the morph on the next page.
*/

(() => {
	const KEY_PENDING = 'famille_tm_pending';
	const KEY_ORIGINS = 'famille_tm_origins';

	const now = () => Date.now();

	const prefersReducedMotion = () => {
		try {
			return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
		} catch {
			return false;
		}
	};

	const isLowEnd = () => {
		try {
			const hc = Number(navigator.hardwareConcurrency || 0);
			const dm = Number(navigator.deviceMemory || 0);
			return (hc && hc <= 4) || (dm && dm <= 4);
		} catch {
			return false;
		}
	};

	const getOverlayRoot = () => {
		let root = document.getElementById('tm-overlay-root');
		if (!root) {
			root = document.createElement('div');
			root.id = 'tm-overlay-root';
			document.documentElement.appendChild(root);
		}
		return root;
	};

	const safeParse = (raw) => {
		try {
			return JSON.parse(String(raw || ''));
		} catch {
			return null;
		}
	};

	const readPending = () => {
		if (!window.sessionStorage) return null;
		const raw = sessionStorage.getItem(KEY_PENDING);
		const st = safeParse(raw);
		if (!st || !st.id || !st.ts) return null;
		if (now() - Number(st.ts) > 6000) return null;
		return st;
	};

	const clearPending = () => {
		try {
			sessionStorage.removeItem(KEY_PENDING);
		} catch {
			// ignore
		}
	};

	const readOrigins = () => {
		if (!window.sessionStorage) return {};
		const raw = sessionStorage.getItem(KEY_ORIGINS);
		const obj = safeParse(raw);
		return obj && typeof obj === 'object' ? obj : {};
	};

	const writeOrigins = (origins) => {
		try {
			sessionStorage.setItem(KEY_ORIGINS, JSON.stringify(origins || {}));
		} catch {
			// ignore
		}
	};

	const normalizeRect = (r) => ({
		x: Number(r?.x || 0),
		y: Number(r?.y || 0),
		w: Number(r?.w || r?.width || 0),
		h: Number(r?.h || r?.height || 0),
	});

	const rectFromEl = (el) => {
		const b = el.getBoundingClientRect();
		return { x: b.left, y: b.top, w: b.width, h: b.height };
	};

	const findSharedAnchor = (target) => {
		if (!target) return null;
		const el = target.closest ? target.closest('[data-shared-id]') : null;
		if (!el) return null;
		const a = el.tagName === 'A' ? el : (el.closest ? el.closest('a[href]') : null);
		return a || null;
	};

	const shouldIgnoreClick = (e, a) => {
		if (!a) return true;
		if (e.defaultPrevented) return true;
		if (e.button !== 0) return true;
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return true;
		const href = String(a.getAttribute('href') || '');
		if (!href || href.startsWith('#')) return true;
		const target = String(a.getAttribute('target') || '');
		if (target && target !== '_self') return true;
		return false;
	};

	const makeBackdrop = (opacity = 0) => {
		const d = document.createElement('div');
		d.style.position = 'absolute';
		d.style.inset = '0';
		d.style.background = 'rgba(0,0,0,1)';
		d.style.opacity = String(opacity);
		return d;
	};

	const makeCloneImg = (src, rect, radiusPx = 16) => {
		const img = document.createElement('img');
		img.src = src;
		img.alt = '';
		img.decoding = 'async';
		img.style.position = 'absolute';
		img.style.left = `${rect.x}px`;
		img.style.top = `${rect.y}px`;
		img.style.width = `${Math.max(0, rect.w)}px`;
		img.style.height = `${Math.max(0, rect.h)}px`;
		img.style.objectFit = 'cover';
		img.style.borderRadius = `${Math.max(0, radiusPx)}px`;
		img.style.background = 'rgba(255,255,255,0.06)';
		img.style.transformOrigin = 'top left';
		return img;
	};

	const animateRect = (el, fromRect, toRect, { duration = 320, easing = 'cubic-bezier(0.2, 0.8, 0.2, 1)' } = {}) => {
		if (!el) return Promise.resolve();
		const from = normalizeRect(fromRect);
		const to = normalizeRect(toRect);

		const keyframes = [
			{
				left: `${from.x}px`,
				top: `${from.y}px`,
				width: `${Math.max(0, from.w)}px`,
				height: `${Math.max(0, from.h)}px`,
			},
			{
				left: `${to.x}px`,
				top: `${to.y}px`,
				width: `${Math.max(0, to.w)}px`,
				height: `${Math.max(0, to.h)}px`,
			},
		];

		if (el.animate) {
			const anim = el.animate(keyframes, { duration, easing, fill: 'forwards' });
			return anim.finished.catch(() => {});
		}

		// Fallback: no WAAPI
		el.style.transition = `left ${duration}ms ${easing}, top ${duration}ms ${easing}, width ${duration}ms ${easing}, height ${duration}ms ${easing}`;
		el.style.left = `${to.x}px`;
		el.style.top = `${to.y}px`;
		el.style.width = `${Math.max(0, to.w)}px`;
		el.style.height = `${Math.max(0, to.h)}px`;
		return new Promise((resolve) => setTimeout(resolve, duration));
	};

	const animateOpacity = (el, from, to, { duration = 320, easing = 'cubic-bezier(0.2, 0.8, 0.2, 1)' } = {}) => {
		if (!el) return Promise.resolve();
		if (el.animate) {
			const anim = el.animate([{ opacity: String(from) }, { opacity: String(to) }], { duration, easing, fill: 'forwards' });
			return anim.finished.catch(() => {});
		}
		el.style.transition = `opacity ${duration}ms ${easing}`;
		el.style.opacity = String(to);
		return new Promise((resolve) => setTimeout(resolve, duration));
	};

	const getSharedIdFrom = (el) => {
		if (!el) return '';
		const id = String(el.getAttribute('data-shared-id') || el.dataset?.sharedId || '').trim();
		return id;
	};

	const findSharedElement = (id) => {
		if (!id) return null;
		const el = document.querySelector(`[data-shared-id="${CSS.escape(id)}"]`);
		return el;
	};

	const getSrcFromSource = (anchorOrEl) => {
		if (!anchorOrEl) return '';
		const ds = anchorOrEl.dataset || {};
		const direct = String(ds.sharedSrc || anchorOrEl.getAttribute('data-shared-src') || '').trim();
		if (direct) return direct;
		const img = anchorOrEl.tagName === 'IMG' ? anchorOrEl : anchorOrEl.querySelector?.('img');
		if (img) return String(img.currentSrc || img.src || '').trim();
		return '';
	};

	const getRadiusFrom = (el) => {
		try {
			const r = window.getComputedStyle(el).borderRadius;
			const n = Number(String(r || '').replace('px', ''));
			return Number.isFinite(n) ? n : 16;
		} catch {
			return 16;
		}
	};

	const setPending = (state) => {
		try {
			sessionStorage.setItem(KEY_PENDING, JSON.stringify(state));
		} catch {
			// ignore
		}
	};

	const runIncoming = async ({ fromBfcache = false } = {}) => {
		if (prefersReducedMotion()) {
			clearPending();
			return;
		}

		const st = readPending();
		if (!st) return;

		const id = String(st.id || '');
		const src = String(st.src || '');
		const type = String(st.type || 'enter');

		// Ensure we can show content behind the overlay.
		document.documentElement.classList.add('tm-animating');
		document.documentElement.classList.add('tm-reveal');

		const root = getOverlayRoot();
		root.innerHTML = '';

		const backdrop = makeBackdrop(type === 'return' ? 1 : 1);
		root.appendChild(backdrop);

		const fromRect = normalizeRect(st.fromRect);
		let cloneRect = fromRect;
		if (type === 'enter') {
			// If coming from another page load, rect still matches viewport coords.
			cloneRect = fromRect;
		}

		const clone = makeCloneImg(src, cloneRect, Number(st.radiusPx || 16));
		root.appendChild(clone);

		// Make sure the destination is in the right scroll position for "return".
		if (type === 'return' && typeof st.toScrollY === 'number' && Number.isFinite(st.toScrollY)) {
			try {
				window.scrollTo(0, Number(st.toScrollY));
			} catch {
				// ignore
			}
		}

		await new Promise((r) => requestAnimationFrame(() => r()));
		await new Promise((r) => requestAnimationFrame(() => r()));

		let toRect = null;
		if (type === 'return' && st.toRect) {
			toRect = normalizeRect(st.toRect);
		} else {
			const destEl = findSharedElement(id);
			if (destEl) {
				toRect = rectFromEl(destEl);
				// Hide the real destination element until we settle.
				try { destEl.style.visibility = 'hidden'; } catch {}
			}
		}

		const duration = isLowEnd() ? 240 : 340;

		if (!toRect || toRect.w <= 0 || toRect.h <= 0) {
			// Fallback: simple fade.
			await animateOpacity(backdrop, 1, 0, { duration: Math.max(160, Math.floor(duration * 0.7)) });
			document.documentElement.classList.remove('tm-animating');
			document.documentElement.classList.remove('tm-reveal');
			root.innerHTML = '';
			clearPending();
			return;
		}

		// Morph clone to destination.
		await Promise.all([
			animateRect(clone, cloneRect, toRect, { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
			type === 'return'
				? animateOpacity(backdrop, 1, 0, { duration })
				: Promise.resolve(),
		]);

		// Reveal destination element and controls.
		try {
			const destEl = findSharedElement(id);
			if (destEl) destEl.style.visibility = '';
		} catch {}

		root.innerHTML = '';
		document.documentElement.classList.remove('tm-animating');
		document.documentElement.classList.remove('tm-reveal');
		clearPending();

		// If coming back from bfcache, ensure interactions are normal.
		if (fromBfcache) {
			// no-op
		}
	};

	const runOutgoingEnter = async ({ anchor, sharedEl }) => {
		if (prefersReducedMotion()) return false;
		if (!anchor || !sharedEl) return false;

		const id = getSharedIdFrom(sharedEl) || getSharedIdFrom(anchor);
		if (!id) return false;

		const href = String(anchor.href || '').trim();
		if (!href) return false;

		const src = getSrcFromSource(anchor) || getSrcFromSource(sharedEl);
		if (!src) return false;

		const fromRect = rectFromEl(sharedEl);
		const origins = readOrigins();
		origins[id] = {
			fromRect,
			scrollY: window.scrollY || 0,
			src,
			radiusPx: getRadiusFrom(sharedEl),
			ts: now(),
		};
		writeOrigins(origins);

		setPending({
			v: 1,
			type: 'enter',
			id,
			src,
			fromRect,
			fromScrollY: window.scrollY || 0,
			radiusPx: getRadiusFrom(sharedEl),
			ts: now(),
		});

		// Quick pre-navigation morph (premium feel); keep it short to avoid feeling sluggish.
		const root = getOverlayRoot();
		root.innerHTML = '';
		document.documentElement.classList.add('tm-animating');
		document.documentElement.classList.add('tm-reveal');

		const backdrop = makeBackdrop(0);
		root.appendChild(backdrop);
		const clone = makeCloneImg(src, fromRect, getRadiusFrom(sharedEl));
		root.appendChild(clone);

		const duration = isLowEnd() ? 180 : 220;
		const full = { x: 0, y: 0, w: window.innerWidth, h: window.innerHeight };
		await Promise.all([
			animateOpacity(backdrop, 0, 1, { duration }),
			animateRect(clone, fromRect, full, { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
		]);

		window.location.href = href;
		return true;
	};

	const runOutgoingReturn = async ({ backLink, viewerEl, imageEl }) => {
		if (prefersReducedMotion()) return false;
		if (!backLink || !viewerEl || !imageEl) return false;

		const href = String(backLink.href || '').trim();
		if (!href) return false;

		const id = getSharedIdFrom(imageEl);
		const origins = readOrigins();
		const origin = origins[id];
		if (!origin || !origin.fromRect) return false;

		const fromRect = rectFromEl(imageEl);
		const toRect = normalizeRect(origin.fromRect);
		const src = String(origin.src || imageEl.currentSrc || imageEl.src || '').trim();

		setPending({
			v: 1,
			type: 'return',
			id,
			src,
			fromRect,
			toRect,
			toScrollY: Number(origin.scrollY || 0),
			radiusPx: Number(origin.radiusPx || 16),
			ts: now(),
		});

		// Quick pre-navigation shrink to reduce the perceived cut.
		try {
			const root = getOverlayRoot();
			root.innerHTML = '';
			document.documentElement.classList.add('tm-animating');
			document.documentElement.classList.add('tm-reveal');

			const backdrop = makeBackdrop(1);
			root.appendChild(backdrop);
			const clone = makeCloneImg(src, fromRect, getRadiusFrom(imageEl));
			root.appendChild(clone);

			const duration = isLowEnd() ? 160 : 200;
			await Promise.all([
				animateRect(clone, fromRect, toRect, { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
				animateOpacity(backdrop, 1, 0, { duration }),
			]);
		} catch {
			// ignore
		}

		// Use history.back when it makes sense (better UX / preserves state).
		if (history.length > 1) {
			history.back();
			return true;
		}

		window.location.href = href;
		return true;
	};

	const bindClickInterception = () => {
		document.addEventListener('click', async (e) => {
			if (prefersReducedMotion()) return;
			const a = findSharedAnchor(e.target);
			if (!a) return;
			if (shouldIgnoreClick(e, a)) return;

			const shared = (e.target && e.target.closest)
				? (e.target.closest('[data-shared-id]') || a.querySelector('[data-shared-id]'))
				: null;
			const sharedEl = shared || a;
			const id = getSharedIdFrom(sharedEl);
			if (!id) return;

			// Prefer the <img> for rect measurement when available.
			const img = sharedEl.tagName === 'IMG' ? sharedEl : (sharedEl.querySelector ? sharedEl.querySelector('img') : null);
			const measureEl = img || sharedEl;
			if (!measureEl) return;

			e.preventDefault();
			await runOutgoingEnter({ anchor: a, sharedEl: measureEl });
		}, { capture: true });
	};

	const bindViewerBack = () => {
		// Only activates on pages that have the viewer structure.
		const viewer = document.getElementById('image-viewer');
		if (!viewer) return;

		const backLink = viewer.querySelector('a[data-tm-back="1"]');
		const imageEl = viewer.querySelector('img[data-shared-id]');
		if (!backLink || !imageEl) return;

		backLink.addEventListener('click', async (e) => {
			if (prefersReducedMotion()) return;
			e.preventDefault();
			const ok = await runOutgoingReturn({ backLink, viewerEl: viewer, imageEl });
			if (!ok) {
				window.location.href = String(backLink.href || '/');
			}
		});
	};

	const init = () => {
		if (prefersReducedMotion()) {
			clearPending();
			return;
		}

		// If there's a pending transition, run it as soon as possible.
		// - on full load: DOMContentLoaded
		// - on bfcache restore: pageshow
		runIncoming({ fromBfcache: false }).catch(() => {});
		window.addEventListener('pageshow', (e) => {
			if (e && e.persisted) {
				runIncoming({ fromBfcache: true }).catch(() => {});
			}
		});

		bindClickInterception();
		bindViewerBack();
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
