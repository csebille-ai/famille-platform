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
			try {
				root.style.position = 'fixed';
				root.style.inset = '0';
				root.style.width = '100vw';
				root.style.height = '100vh';
				root.style.pointerEvents = 'none';
				root.style.zIndex = '2147483647';
				root.style.overflow = 'hidden';
				root.style.contain = 'layout paint style';
			} catch {
				// ignore
			}
			document.documentElement.appendChild(root);
		}
		return root;
	};

	const getBackdropColorForCurrentPage = () => {
		// Default app background is light, but the photo viewer page is dark.
		try {
			if (document.getElementById('image-viewer')) return '#020617'; // slate-950
		} catch {
			// ignore
		}
		try {
			const bodyBg = String(window.getComputedStyle(document.body).backgroundColor || '').trim();
			if (bodyBg && bodyBg !== 'rgba(0, 0, 0, 0)' && bodyBg !== 'transparent') return bodyBg;
		} catch {
			// ignore
		}
		return 'var(--fam-bg, #F6F2EC)';
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

	const makeBackdrop = (bg, opacity = 0) => {
		const d = document.createElement('div');
		d.style.position = 'absolute';
		d.style.inset = '0';
		d.style.background = String(bg || 'var(--fam-bg, #F6F2EC)');
		d.style.opacity = String(opacity);
		return d;
	};

	// ------------------------------------------------------------
	// Option B: Premium crossfade overlay (no morph/zoom)
	// ------------------------------------------------------------
	const waapi = (el, keyframes, { duration = 180, easing = 'ease' } = {}) => {
		if (!el) return Promise.resolve();
		if (el.animate) {
			const a = el.animate(keyframes, { duration, easing, fill: 'forwards' });
			return a.finished.catch(() => {});
		}
		// Fallback: set final styles (best-effort).
		try {
			const last = Array.isArray(keyframes) ? keyframes[keyframes.length - 1] : null;
			if (last && typeof last === 'object') Object.assign(el.style, last);
		} catch {}
		return new Promise((r) => setTimeout(r, Math.max(0, duration)));
	};

	const delay = (ms) => new Promise((r) => setTimeout(r, Math.max(0, Number(ms || 0))));

	const makePremiumOverlay = ({ thumbSrc, hdSrc, backdropColor = '#020617', radiusPx = 0 } = {}) => {
		const root = getOverlayRoot();
		root.innerHTML = '';
		try { root.style.pointerEvents = 'auto'; } catch {}

		const overlay = document.createElement('div');
		overlay.style.position = 'absolute';
		overlay.style.inset = '0';
		overlay.style.pointerEvents = 'auto';
		root.appendChild(overlay);

		const backdrop = document.createElement('div');
		backdrop.style.position = 'absolute';
		backdrop.style.inset = '0';
		backdrop.style.opacity = '0';
		backdrop.style.background = `linear-gradient(to bottom, rgba(2,6,23,0.55), rgba(2,6,23,0.92)), ${String(backdropColor || '#020617')}`;
		backdrop.style.pointerEvents = 'auto';
		overlay.appendChild(backdrop);

		const stage = document.createElement('div');
		stage.style.position = 'absolute';
		stage.style.inset = '0';
		stage.style.display = 'block';
		stage.style.padding = '0';
		stage.style.pointerEvents = 'none';
		overlay.appendChild(stage);

		const frame = document.createElement('div');
		frame.style.position = 'absolute';
		frame.style.inset = '0';
		frame.style.borderRadius = `${Math.max(0, Number(radiusPx || 16))}px`;
		frame.style.overflow = 'hidden';
		frame.style.background = 'rgba(2,6,23,0.35)';
		// Keep micro-scale extremely subtle; big scale reads as "zoom".
		frame.style.transform = 'scale(0.992)';
		frame.style.willChange = 'transform, opacity';
		frame.style.backfaceVisibility = 'hidden';
		frame.style.pointerEvents = 'none';
		stage.appendChild(frame);

		const thumb = document.createElement('img');
		thumb.alt = '';
		thumb.decoding = 'async';
		thumb.src = String(thumbSrc || '');
		thumb.style.position = 'absolute';
		thumb.style.inset = '0';
		thumb.style.width = '100%';
		thumb.style.height = '100%';
		thumb.style.objectFit = 'contain';
		thumb.style.opacity = '1';
		thumb.style.transform = 'translateZ(0)';
		thumb.style.backfaceVisibility = 'hidden';
		frame.appendChild(thumb);

		const hd = document.createElement('img');
		hd.alt = '';
		hd.decoding = 'async';
		if (hdSrc) hd.src = String(hdSrc || '');
		hd.style.position = 'absolute';
		hd.style.inset = '0';
		hd.style.width = '100%';
		hd.style.height = '100%';
		hd.style.objectFit = 'contain';
		hd.style.opacity = '0';
		hd.style.transform = 'translateZ(0)';
		hd.style.backfaceVisibility = 'hidden';
		frame.appendChild(hd);

		return { root, overlay, backdrop, stage, frame, thumb, hd };
	};

	const makeMediaFrame = ({ rect, radiusPx = 16 } = {}) => {
		const r = normalizeRect(rect);
		const frame = document.createElement('div');
		frame.style.position = 'absolute';
		frame.style.left = `${r.x}px`;
		frame.style.top = `${r.y}px`;
		frame.style.width = `${Math.max(0, r.w)}px`;
		frame.style.height = `${Math.max(0, r.h)}px`;
		frame.style.overflow = 'hidden';
		frame.style.borderRadius = `${Math.max(0, Number(radiusPx || 0))}px`;
		frame.style.transformOrigin = 'top left';
		frame.style.willChange = 'transform, border-radius';
		frame.style.backfaceVisibility = 'hidden';
		frame.style.transform = 'translate3d(0,0,0)';
		return frame;
	};

	const makeFrameImg = ({ src, fit = 'contain', bg = 'transparent' } = {}) => {
		const img = document.createElement('img');
		img.src = String(src || '');
		img.alt = '';
		img.decoding = 'async';
		img.style.width = '100%';
		img.style.height = '100%';
		img.style.display = 'block';
		img.style.objectFit = String(fit || 'contain');
		img.style.background = String(bg || 'transparent');
		img.style.transform = 'translateZ(0)';
		img.style.backfaceVisibility = 'hidden';
		return img;
	};

	const transformFromRects = (fromRect, toRect) => {
		const from = normalizeRect(fromRect);
		const to = normalizeRect(toRect);
		const tw = Math.max(1, to.w);
		const th = Math.max(1, to.h);
		const sx = Math.max(0.0001, from.w / tw);
		const sy = Math.max(0.0001, from.h / th);
		const tx = (from.x - to.x);
		const ty = (from.y - to.y);
		return `translate3d(${tx}px, ${ty}px, 0) scale(${sx}, ${sy})`;
	};

	const animateFrameTransform = (
		frame,
		{ fromRect, toRect, fromRadiusPx = 16, toRadiusPx = 0, duration = 260, easing = 'cubic-bezier(0.2, 0.8, 0.2, 1)' } = {}
	) => {
		if (!frame) return Promise.resolve();
		const fromT = transformFromRects(fromRect, toRect);
		const toT = 'translate3d(0,0,0) scale(1,1)';
		const kf0 = { transform: fromT, borderRadius: `${Math.max(0, Number(fromRadiusPx || 0))}px` };
		const kf1 = { transform: toT, borderRadius: `${Math.max(0, Number(toRadiusPx || 0))}px` };

		// Prefer WAAPI for smoothness; fallback to CSS transitions.
		if (frame.animate) {
			const anim = frame.animate([kf0, kf1], { duration, easing, fill: 'forwards' });
			return anim.finished.catch(() => {});
		}

		frame.style.transition = `transform ${duration}ms ${easing}, border-radius ${duration}ms ${easing}`;
		frame.style.transform = kf0.transform;
		frame.style.borderRadius = kf0.borderRadius;
		// Force style flush.
		try { void frame.offsetHeight; } catch {}
		frame.style.transform = kf1.transform;
		frame.style.borderRadius = kf1.borderRadius;
		return new Promise((resolve) => setTimeout(resolve, duration));
	};

	const makeCloneImg = (src, rect, radiusPx = 16, bg = 'transparent') => {
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
		img.style.background = String(bg || 'transparent');
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

	const calcObjectFitContentRect = ({ boxRect, aspect, fit }) => {
		const box = normalizeRect(boxRect);
		const f = String(fit || '').trim();
		if (!box || box.w <= 0 || box.h <= 0) return box;
		if (f !== 'contain') return box;
		return calcContainRectInBox({ x: box.x, y: box.y, w: box.w, h: box.h, aspect });
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
		const type = String(st.type || 'enter');
		const thumbSrc = String(st.thumbSrc || st.src || '').trim();

		// Option B: only handle "enter".
		if (type !== 'enter') {
			clearPending();
			return;
		}

		document.documentElement.classList.add('tm-animating');
		document.documentElement.classList.add('tm-reveal');

		const backdropColor = getBackdropColorForCurrentPage();
		const overlay = makePremiumOverlay({ thumbSrc, backdropColor, radiusPx: 0 });

		const startAt = now();
		const openDur = isLowEnd() ? 170 : 220;
		await Promise.all([
			waapi(overlay.backdrop, [{ opacity: 0 }, { opacity: 1 }], { duration: openDur, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
			waapi(overlay.frame, [{ transform: 'scale(0.992)' }, { transform: 'scale(1)' }], { duration: openDur, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
		]);

		// Find the real destination image (HD) and use it for the crossfade.
		const destEl = findSharedElement(id);
		const destImg = destEl
			? (destEl.tagName === 'IMG' ? destEl : (destEl.querySelector ? destEl.querySelector('img') : null))
			: null;
		const hdSrc = String(destImg?.currentSrc || destImg?.src || '').trim();
		try {
			if (destImg) {
				const fit = getObjectFitFrom(destImg, 'contain');
				overlay.thumb.style.objectFit = fit;
				overlay.hd.style.objectFit = fit;
			}
		} catch {}
		if (hdSrc) {
			try { overlay.hd.src = hdSrc; } catch {}
		}

		// Wait for overlay HD, then crossfade. If it never loads, keep thumb (never blank).
		try {
			if (overlay.hd && overlay.hd.src) {
				await waitForImageReady(overlay.hd, isLowEnd() ? 1800 : 3000);
			}
		} catch {}

		// Avoid a "pop" when HD is already cached: enforce a minimal delay so the eye reads a single motion.
		const minCrossfadeDelay = isLowEnd() ? 80 : 110;
		const elapsed = now() - startAt;
		if (elapsed < minCrossfadeDelay) await delay(minCrossfadeDelay - elapsed);

		const fadeDur = isLowEnd() ? 140 : 180;
		if (overlay.hd && overlay.hd.src) {
			await Promise.all([
				waapi(overlay.hd, [{ opacity: 0 }, { opacity: 1 }], { duration: fadeDur, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
				waapi(overlay.thumb, [{ opacity: 1 }, { opacity: 0 }], { duration: fadeDur, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
			]);
		}

		// Ensure the real page image is ready before removing overlay (prevents a flash).
		if (destImg) {
			try { await waitForImageReady(destImg, isLowEnd() ? 2200 : 4200); } catch {}
		}

		// Settle: fade the overlay away very quickly so the handoff to the real DOM image is imperceptible.
		const settleDur = isLowEnd() ? 90 : 120;
		await Promise.all([
			waapi(overlay.frame, [{ opacity: 1 }, { opacity: 0 }], { duration: settleDur, easing: 'linear' }),
			waapi(overlay.backdrop, [{ opacity: 1 }, { opacity: 0 }], { duration: settleDur, easing: 'linear' }),
		]);

		try { overlay.root.innerHTML = ''; } catch {}
		document.documentElement.classList.remove('tm-animating');
		document.documentElement.classList.remove('tm-reveal');
		clearPending();
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

		// Option B: premium overlay (no rect morph / no border-radius animation).
		document.documentElement.classList.add('tm-animating');
		document.documentElement.classList.add('tm-reveal');
		const enteringViewer = href.includes('/media/photos/');
		const backdropColor = enteringViewer ? '#020617' : getBackdropColorForCurrentPage();
		const overlay = makePremiumOverlay({ thumbSrc: src, backdropColor, radiusPx: 0 });
		const duration = isLowEnd() ? 170 : 220;
		await Promise.all([
			waapi(overlay.backdrop, [{ opacity: 0 }, { opacity: 1 }], { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
			waapi(overlay.frame, [{ transform: 'scale(0.992)' }, { transform: 'scale(1)' }], { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
		]);

		// Pending only needs: id + thumbSrc + ts. The destination page will handle the HD crossfade.
		try {
			storageSet(KEY_LAST_ORIGIN_URL, window.location.href);
			setPending({
				v: 1,
				type: 'enter',
				id,
				thumbSrc: src,
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

		// Option B close: fade-out backdrop + micro-scale down. No reverse morph.
		try {
			viewerEl.classList.add('viewer-ui-hidden');
		} catch {}

		document.documentElement.classList.add('tm-animating');
		document.documentElement.classList.add('tm-reveal');
		const src = String(imageEl.currentSrc || imageEl.src || '').trim();
		const backdropColor = getBackdropColorForCurrentPage();
		const overlay = makePremiumOverlay({ thumbSrc: src, hdSrc: src, backdropColor, radiusPx: 0 });
		try { overlay.hd.style.opacity = '1'; overlay.thumb.style.opacity = '0'; } catch {}
		// Match the real viewer rendering so closing doesn't read as a zoom jump.
		try {
			const cs = window.getComputedStyle(imageEl);
			const fit = String(cs.objectFit || '').trim();
			if (fit) {
				overlay.hd.style.objectFit = fit;
				overlay.thumb.style.objectFit = fit;
			}
			const origin = String(cs.transformOrigin || '').trim();
			if (origin) {
				overlay.hd.style.transformOrigin = origin;
				overlay.thumb.style.transformOrigin = origin;
			}
			const t = String(cs.transform || '').trim();
			if (t && t !== 'none') {
				overlay.hd.style.transform = t;
				overlay.thumb.style.transform = t;
			}
		} catch {}

		const duration = isLowEnd() ? 160 : 210;
		await Promise.all([
			waapi(overlay.backdrop, [{ opacity: 1 }, { opacity: 0 }], { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
			waapi(overlay.frame, [{ transform: 'scale(1)' }, { transform: 'scale(0.992)' }], { duration, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }),
		]);
		try { overlay.root.innerHTML = ''; } catch {}
		clearPending();

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

		backLink.addEventListener('click', async (e) => {
			const lastOriginHref = sameOriginHrefOrNull(storageGet(KEY_LAST_ORIGIN_URL));
			const targetHref = lastOriginHref || String(backLink.href || '/');
			try {
				if (targetHref && String(backLink.href || '') !== targetHref) backLink.href = targetHref;
			} catch {
				// ignore
			}

			// Reverse morph on close.
			e.preventDefault();
			await runOutgoingReturn({ backLink, viewerEl: viewer, imageEl, returnHref: targetHref });
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
