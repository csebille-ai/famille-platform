/* Cross-page shared element transitions (Accueil -> Viewer) without changing routes.
   Works by storing a small "pending" payload in sessionStorage and replaying the morph on the next page.
*/

(() => {
	const KEY_PENDING = 'famille_tm_pending';
	const KEY_ORIGINS = 'famille_tm_origins';
	const KEY_LAST_ORIGIN_URL = 'famille_tm_last_origin_url';

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

	const storageGet = (key) => {
		try {
			return window.sessionStorage ? window.sessionStorage.getItem(key) : null;
		} catch {
			return null;
		}
	};

	const storageSet = (key, value) => {
		try {
			if (!window.sessionStorage) return;
			window.sessionStorage.setItem(key, value);
		} catch {
			// ignore
		}
	};

	const storageRemove = (key) => {
		try {
			if (!window.sessionStorage) return;
			window.sessionStorage.removeItem(key);
		} catch {
			// ignore
		}
	};

	const sameOriginHrefOrNull = (raw) => {
		const s = String(raw || '').trim();
		if (!s) return null;
		try {
			const u = new URL(s, window.location.href);
			if (u.origin !== window.location.origin) return null;
			return u.href;
		} catch {
			return null;
		}
	};

	const readPending = () => {
		const raw = storageGet(KEY_PENDING);
		const st = safeParse(raw);
		if (!st || !st.id || !st.ts) return null;
		if (now() - Number(st.ts) > 6000) return null;
		return st;
	};

	const clearPending = () => {
		storageRemove(KEY_PENDING);
	};

	const readOrigins = () => {
		const raw = storageGet(KEY_ORIGINS);
		const obj = safeParse(raw);
		return obj && typeof obj === 'object' ? obj : {};
	};

	const writeOrigins = (origins) => {
		storageSet(KEY_ORIGINS, JSON.stringify(origins || {}));
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
		d.style.background = 'var(--fam-bg, #F6F2EC)';
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

	const getObjectFitFrom = (el, fallback = 'cover') => {
		try {
			const fit = String(window.getComputedStyle(el).objectFit || '').trim();
			return fit || fallback;
		} catch {
			return fallback;
		}
	};

	const animateMorph = (
		el,
		fromRect,
		toRect,
		{
			duration = 320,
			easing = 'cubic-bezier(0.2, 0.8, 0.2, 1)',
			fromRadiusPx = null,
			toRadiusPx = null,
		} = {}
	) => {
		if (!el) return Promise.resolve();
		const from = normalizeRect(fromRect);
		const to = normalizeRect(toRect);

		const kf0 = {
			left: `${from.x}px`,
			top: `${from.y}px`,
			width: `${Math.max(0, from.w)}px`,
			height: `${Math.max(0, from.h)}px`,
		};
		const kf1 = {
			left: `${to.x}px`,
			top: `${to.y}px`,
			width: `${Math.max(0, to.w)}px`,
			height: `${Math.max(0, to.h)}px`,
		};

		if (fromRadiusPx != null && toRadiusPx != null) {
			kf0.borderRadius = `${Math.max(0, Number(fromRadiusPx) || 0)}px`;
			kf1.borderRadius = `${Math.max(0, Number(toRadiusPx) || 0)}px`;
		}

		if (el.animate) {
			const anim = el.animate([kf0, kf1], { duration, easing, fill: 'forwards' });
			return anim.finished.catch(() => {});
		}

		// Fallback: no WAAPI
		const props = ['left', 'top', 'width', 'height'];
		if (fromRadiusPx != null && toRadiusPx != null) props.push('border-radius');
		el.style.transition = props.map((p) => `${p} ${duration}ms ${easing}`).join(', ');
		Object.assign(el.style, kf1);
		return new Promise((resolve) => setTimeout(resolve, duration));
	};

	const animateRect = (el, fromRect, toRect, opts = {}) => animateMorph(el, fromRect, toRect, opts);

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

	const escapeAttrValueForSelector = (value) => {
		// Minimal escaping for use inside an attribute selector: [data-x="..."]
		// Prefer CSS.escape when available; otherwise escape backslash and quotes.
		const s = String(value ?? '');
		try {
			if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(s);
		} catch {
			// ignore
		}
		return s.replace(/\\/g, '\\\\').replace(/\"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
	};

	const findSharedElement = (id) => {
		if (!id) return null;
		const escaped = escapeAttrValueForSelector(id);
		const el = document.querySelector(`[data-shared-id="${escaped}"]`);
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
			const r = String(window.getComputedStyle(el).borderRadius || '').trim();
			// border-radius can be "16px" or "16px 16px 16px 16px" (or mixed). Take the first px value.
			const m = r.match(/([0-9.]+)px/);
			const n = m ? Number(m[1]) : NaN;
			return Number.isFinite(n) ? n : 16;
		} catch {
			return 16;
		}
	};

	const getAspectRatioFrom = (el, fallback = 1) => {
		try {
			const w = Number(el?.naturalWidth || 0);
			const h = Number(el?.naturalHeight || 0);
			if (w > 0 && h > 0) return w / h;
		} catch {
			// ignore
		}
		try {
			const r = rectFromEl(el);
			if (r.w > 0 && r.h > 0) return r.w / r.h;
		} catch {
			// ignore
		}
		return fallback;
	};

	const calcContainRect = ({ viewportW, viewportH, aspect }) => {
		const vw = Math.max(1, Number(viewportW || 1));
		const vh = Math.max(1, Number(viewportH || 1));
		const ar = Math.max(0.05, Number(aspect || 1));

		let w = vw;
		let h = w / ar;
		if (h > vh) {
			h = vh;
			w = h * ar;
		}
		const x = (vw - w) / 2;
		const y = (vh - h) / 2;
		return { x, y, w, h };
	};

	const calcContainRectInBox = ({ x, y, w, h, aspect }) => {
		const bx = Number(x || 0);
		const by = Number(y || 0);
		const bw = Math.max(1, Number(w || 1));
		const bh = Math.max(1, Number(h || 1));
		const ar = Math.max(0.05, Number(aspect || 1));

		let ww = bw;
		let hh = ww / ar;
		if (hh > bh) {
			hh = bh;
			ww = hh * ar;
		}
		return { x: bx + (bw - ww) / 2, y: by + (bh - hh) / 2, w: ww, h: hh };
	};

	const waitForImageReady = async (imgEl, timeoutMs = 2500) => {
		if (!imgEl) return false;
		const start = now();
		const remaining = () => Math.max(0, timeoutMs - (now() - start));

		const alreadyOk = () => {
			try {
				return !!(imgEl.complete && (imgEl.naturalWidth || 0) > 0);
			} catch {
				return false;
			}
		};

		if (alreadyOk()) {
			try {
				if (typeof imgEl.decode === 'function') {
					await Promise.race([
						imgEl.decode().catch(() => {}),
						new Promise((r) => setTimeout(r, 400)),
					]);
				}
			} catch {
				// ignore
			}
			return true;
		}

		return await new Promise((resolve) => {
			let done = false;
			const finish = (ok) => {
				if (done) return;
				done = true;
				cleanup();
				resolve(!!ok);
			};
			const onLoad = () => finish(alreadyOk());
			const onErr = () => finish(false);
			const cleanup = () => {
				try { imgEl.removeEventListener('load', onLoad); } catch {}
				try { imgEl.removeEventListener('error', onErr); } catch {}
			};
			try { imgEl.addEventListener('load', onLoad, { once: true }); } catch {}
			try { imgEl.addEventListener('error', onErr, { once: true }); } catch {}
			setTimeout(() => finish(alreadyOk()), remaining());
		});
	};

	const setPending = (state) => {
		storageSet(KEY_PENDING, JSON.stringify(state));
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
		// Default to source rendering (thumbnail style).
		clone.style.objectFit = String(st.fit || 'cover');
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
		let destFit = null;
		let destRadiusPx = null;
		let destImg = null;
		let destElForHide = null;
		if (type === 'return' && st.toRect) {
			toRect = normalizeRect(st.toRect);
		} else {
			const destEl = findSharedElement(id);
			if (destEl) {
				destFit = getObjectFitFrom(destEl, 'contain');
				destRadiusPx = getRadiusFrom(destEl);
				destImg = destEl.tagName === 'IMG' ? destEl : (destEl.querySelector ? destEl.querySelector('img') : null);

				// Large/vertical images may not have a layout box yet at this point.
				// Wait briefly for the destination image to decode/load so rect measurement isn't 0x0.
				if (destImg) {
					try {
						await waitForImageReady(destImg, isLowEnd() ? 700 : 1400);
					} catch {
						// ignore
					}
				}

				toRect = rectFromEl(destEl);

				// Hide the real destination element until we fully swap (prevents any clone<->real crossfade).
				// Only do this if we have a valid destination rect; otherwise we can accidentally hide it forever.
				destElForHide = destEl;
				if (toRect && toRect.w > 0 && toRect.h > 0) {
					try { destEl.style.visibility = 'hidden'; } catch {}
					try { destEl.style.opacity = '0'; } catch {}
				}
			}
		}

		const duration = isLowEnd() ? 240 : 340;

		if (!toRect || toRect.w <= 0 || toRect.h <= 0) {
			// Fallback: simple fade.
			await animateOpacity(backdrop, 1, 0, { duration: Math.max(160, Math.floor(duration * 0.7)) });
			// Ensure the destination element is visible even if we had hidden it.
			try {
				if (destElForHide) {
					destElForHide.style.visibility = '';
					destElForHide.style.opacity = '';
				}
			} catch {}
			document.documentElement.classList.remove('tm-animating');
			document.documentElement.classList.remove('tm-reveal');
			root.innerHTML = '';
			clearPending();
			return;
		}

		// Match destination rendering near the end to avoid a visible jump when swapping overlay -> real element.
		if (destFit) {
			setTimeout(() => {
				try { clone.style.objectFit = String(destFit || 'contain'); } catch {}
			}, Math.max(0, Math.floor(duration * 0.75)));
		}

		await Promise.all([
			animateMorph(clone, cloneRect, toRect, {
				duration,
				easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)',
				fromRadiusPx: Number(st.radiusPx || 16),
				toRadiusPx: destRadiusPx != null ? Number(destRadiusPx || 0) : 0,
			}),
			type === 'return'
				? animateOpacity(backdrop, 1, 0, { duration })
				: Promise.resolve(),
		]);

		// Keep the clone until the real image is ready to avoid a blank/blue flash.
		if (destImg) {
			try {
				await waitForImageReady(destImg, isLowEnd() ? 1400 : 2800);
			} catch {
				// ignore
			}
		}

		// Swap instantly (no visible fade between clone and real image).
		try {
			if (destElForHide) {
				destElForHide.style.visibility = '';
				destElForHide.style.opacity = '';
			}
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

		// Destination-aware rendering: the photo viewer uses `contain`, while grids typically use `cover`.
		// If we keep `cover` until the end, the final switch to `contain` reads as an elastic bounce.
		const pendingFit = href.includes('/media/photos/') ? 'contain' : getObjectFitFrom(sharedEl, 'cover');

		// Quick pre-navigation morph (premium feel); keep it short to avoid feeling sluggish.
		const fromRect = rectFromEl(sharedEl);
		const root = getOverlayRoot();
		root.innerHTML = '';
		document.documentElement.classList.add('tm-animating');
		document.documentElement.classList.add('tm-reveal');

		const backdrop = makeBackdrop(0);
		root.appendChild(backdrop);
		const clone = makeCloneImg(src, fromRect, getRadiusFrom(sharedEl));
		clone.style.objectFit = getObjectFitFrom(sharedEl, 'cover');
		root.appendChild(clone);

		const duration = isLowEnd() ? 180 : 220;
		// Match the viewer's image area (maximized contain): expand to a centered contain rect inside a near-full viewport box.
		const aspect = getAspectRatioFrom(sharedEl, (fromRect.w > 0 && fromRect.h > 0) ? (fromRect.w / fromRect.h) : 1);
		const vw = window.innerWidth;
		const vh = window.innerHeight;
		const padX = 8;
		const padY = 12;
		const box = { x: padX, y: padY, w: Math.max(1, vw - padX * 2), h: Math.max(1, vh - padY * 2) };
		const target = calcContainRectInBox({ ...box, aspect });
		setTimeout(() => {
			try { clone.style.objectFit = String(pendingFit || 'contain'); } catch {}
		}, Math.max(0, Math.floor(duration * 0.25)));

		await Promise.all([
			animateOpacity(backdrop, 0, 1, { duration }),
			animateMorph(clone, fromRect, target, {
				duration,
				easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)',
				fromRadiusPx: getRadiusFrom(sharedEl),
				toRadiusPx: 0,
			}),
		]);

		// Best-effort persistence for cross-page settle.
		try {
			const origins = readOrigins();
			origins[id] = {
				fromRect,
				scrollY: window.scrollY || 0,
				src,
				radiusPx: getRadiusFrom(sharedEl),
				originUrl: window.location.href,
				ts: now(),
			};
			writeOrigins(origins);
			storageSet(KEY_LAST_ORIGIN_URL, window.location.href);
			setPending({
				v: 1,
				type: 'enter',
				id,
				src,
				fromRect: target,
				fromScrollY: window.scrollY || 0,
				radiusPx: getRadiusFrom(sharedEl),
					fit: pendingFit,
				ts: now(),
			});
		} catch {
			// ignore
		}

		window.location.href = href;
		return true;
	};

	const runOutgoingReturn = async ({ backLink, viewerEl, imageEl, returnHref }) => {
		if (prefersReducedMotion()) return false;
		if (!backLink || !viewerEl || !imageEl) return false;

		const href = String(returnHref || backLink.href || '').trim();
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
			if (!sharedEl) return;

			e.preventDefault();
			await runOutgoingEnter({ anchor: a, sharedEl });
		}, { capture: true });
	};

	const bindViewerBack = () => {
		// Only activates on pages that have the viewer structure.
		const viewer = document.getElementById('image-viewer');
		if (!viewer) return;

		const backLink = viewer.querySelector('a[data-tm-back="1"]');
		const imageEl = viewer.querySelector('img[data-shared-id]');
		if (!backLink || !imageEl) return;

		backLink.addEventListener('click', (e) => {
			// UX decision: no reverse morph on close (it feels like a PiP / "big image in background").
			// Keep navigation reliable by preferring the last captured origin URL, but navigate immediately.
			const lastOriginHref = sameOriginHrefOrNull(storageGet(KEY_LAST_ORIGIN_URL));
			const targetHref = lastOriginHref || String(backLink.href || '/');
			try {
				if (targetHref && String(backLink.href || '') !== targetHref) backLink.href = targetHref;
			} catch {
				// ignore
			}
			// Do not prevent default: let the browser navigate directly.
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
