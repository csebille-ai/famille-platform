
        (function () {
            const start = () => {
            const bootstrap = (window && window.__CHAT_BOOTSTRAP__) ? window.__CHAT_BOOTSTRAP__ : {};
            const recipientsUrl = String(bootstrap.recipientsUrl || '').trim() || null;
            const isAdmin = !!bootstrap.isAdmin;

            function firstExisting(...els) {
                for (const el of els) {
                    if (el) return el;
                }
                return null;
            }

            const scrollEl = document.getElementById('chatScroll');
            const messagesEl = document.getElementById('chatMessages');
            const emptyEl = document.getElementById('chatEmptyState');
            const onlineCountEl = document.getElementById('chatOnlineCount');
            const presenceLabelEl = document.getElementById('chatPresenceLabel');
            const composer = {
                mobile: {
                    key: 'mobile',
                    form: document.getElementById('chatForm'),
                    textarea: document.getElementById('body'),
                    attachBtn: document.getElementById('chatAttachBtn'),
                    photoInput: document.getElementById('chatPhotoInput'),
                    videoInput: document.getElementById('chatVideoInput'),
                    sendBtn: document.getElementById('chatSendBtn'),
                    soloHint: document.getElementById('chatSoloHint'),
                },
                desktop: {
                    key: 'desktop',
                    form: document.getElementById('chatFormDesktop'),
                    textarea: document.getElementById('bodyDesktop'),
                    attachBtn: document.getElementById('chatAttachBtnDesktop'),
                    photoInput: document.getElementById('chatPhotoInputDesktop'),
                    videoInput: document.getElementById('chatVideoInputDesktop'),
                    sendBtn: document.getElementById('chatSendBtnDesktop'),
                    soloHint: document.getElementById('chatSoloHintDesktop'),
                },
            };

            let activeComposerKey = 'mobile';
            try {
                activeComposerKey = window.matchMedia && window.matchMedia('(min-width: 640px)').matches ? 'desktop' : 'mobile';
            } catch {}

            function setActiveComposerKey(key) {
                if (key === 'mobile' || key === 'desktop') {
                    activeComposerKey = key;
                }
            }

            function getActiveComposer() {
                return composer[activeComposerKey] || composer.mobile;
            }

            // --- Focus Dock (3 actions: Photo, Video, Micro) ---
            const focusDock = {
                mobile: {
                    dock: document.getElementById('chatFocusDock'),
                    backdrop: document.getElementById('chatFocusDockBackdrop'),
                    panel: document.getElementById('chatFocusDockPanel'),
                    photo: document.getElementById('chatFocusPhoto'),
                    video: document.getElementById('chatFocusVideo'),
                    micro: document.getElementById('chatFocusMicro'),
                    close: document.getElementById('chatFocusClose'),
                },
                desktop: {
                    dock: document.getElementById('chatFocusDockDesktop'),
                    panel: document.getElementById('chatFocusDockPanelDesktop'),
                    photo: document.getElementById('chatFocusPhotoDesktop'),
                    video: document.getElementById('chatFocusVideoDesktop'),
                    micro: document.getElementById('chatFocusMicroDesktop'),
                    close: document.getElementById('chatFocusCloseDesktop'),
                },
            };

            let focusDockOpen = false;
            let dictationRecognition = null;
            let isDictating = false;
            let focusDockCloseTimer = null;

            const FOCUS_DOCK_DURATION = 220;

            function isReducedMotion() {
                try {
                    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                } catch {
                    return false;
                }
            }

            function showToast(message) {
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-[100] bg-slate-900 text-white px-4 py-2 rounded-xl shadow-2xl text-sm font-medium opacity-0 transition-opacity duration-200';
                toast.textContent = message;
                document.body.appendChild(toast);
                
                requestAnimationFrame(() => {
                    toast.style.opacity = '1';
                });
                
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 200);
                }, 2500);
            }

            function getActiveFocusDock() {
                return focusDock[activeComposerKey] || focusDock.mobile;
            }

            function setFocusDockOpen(open) {
                const els = getActiveFocusDock();
                if (!els.dock) return;

                if (focusDockCloseTimer) {
                    clearTimeout(focusDockCloseTimer);
                    focusDockCloseTimer = null;
                }

                const c = getActiveComposer();
                const isMobile = activeComposerKey === 'mobile';

                if (open) {
                    focusDockOpen = true;
                    
                    if (c.attachBtn) {
                        c.attachBtn.setAttribute('aria-expanded', 'true');
                    }

                    els.dock.classList.remove('hidden');
                    els.dock.setAttribute('aria-hidden', 'false');

                    if (isMobile) {
                        if (els.backdrop) {
                            els.backdrop.classList.add('opacity-0');
                            els.backdrop.classList.remove('opacity-100');
                        }
                        if (els.panel) {
                            els.panel.classList.add('opacity-0', 'translate-y-6');
                            els.panel.classList.remove('opacity-100', 'translate-y-0');
                        }

                        requestAnimationFrame(() => {
                            if (els.backdrop) {
                                els.backdrop.classList.remove('opacity-0');
                                els.backdrop.classList.add('opacity-100');
                            }
                            if (els.panel) {
                                els.panel.classList.remove('opacity-0', 'translate-y-6');
                                els.panel.classList.add('opacity-100', 'translate-y-0');
                            }
                        });
                    } else {
                        if (els.panel) {
                            els.panel.classList.add('opacity-0', 'scale-95');
                            els.panel.classList.remove('opacity-100', 'scale-100');
                        }

                        requestAnimationFrame(() => {
                            if (els.panel) {
                                els.panel.classList.remove('opacity-0', 'scale-95');
                                els.panel.classList.add('opacity-100', 'scale-100');
                            }
                        });
                    }

                    setTimeout(() => {
                        els.photo?.focus();
                    }, FOCUS_DOCK_DURATION);

                    return;
                }

                focusDockOpen = false;

                if (c.attachBtn) {
                    c.attachBtn.setAttribute('aria-expanded', 'false');
                }

                els.dock.setAttribute('aria-hidden', 'true');

                if (isMobile) {
                    if (els.backdrop) {
                        els.backdrop.classList.add('opacity-0');
                        els.backdrop.classList.remove('opacity-100');
                    }
                    if (els.panel) {
                        els.panel.classList.add('opacity-0', 'translate-y-6');
                        els.panel.classList.remove('opacity-100', 'translate-y-0');
                    }
                } else {
                    if (els.panel) {
                        els.panel.classList.add('opacity-0', 'scale-95');
                        els.panel.classList.remove('opacity-100', 'scale-100');
                    }
                }

                if (!isReducedMotion()) {
                    focusDockCloseTimer = setTimeout(() => {
                        els.dock.classList.add('hidden');
                        focusDockCloseTimer = null;
                    }, FOCUS_DOCK_DURATION);
                } else {
                    els.dock.classList.add('hidden');
                }

                stopDictation();
            }

            function startDictation() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
                    showToast('Dictée non supportée sur cet appareil');
                    return;
                }

                if (isDictating) {
                    stopDictation();
                    return;
                }

                const c = getActiveComposer();
                const els = getActiveFocusDock();
                if (!c.textarea) return;

                try {
                    dictationRecognition = new SpeechRecognition();
                    dictationRecognition.lang = 'fr-FR';
                    dictationRecognition.continuous = false;
                    dictationRecognition.interimResults = true;
                    dictationRecognition.maxAlternatives = 1;

                    let finalTranscript = '';
                    const initialText = c.textarea.value.trim();

                    dictationRecognition.onstart = () => {
                        isDictating = true;
                        if (els.micro) {
                            els.micro.setAttribute('data-dictating', 'true');
                            const span = els.micro.querySelector('span');
                            if (span) span.textContent = 'Stop';
                            
                            const iconDiv = els.micro.querySelector('div');
                            if (iconDiv) {
                                iconDiv.style.animation = 'pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite';
                            }
                        }
                    };

                    dictationRecognition.onresult = (event) => {
                        let interimTranscript = '';

                        for (let i = event.resultIndex; i < event.results.length; i++) {
                            const transcript = event.results[i][0].transcript;
                            if (event.results[i].isFinal) {
                                finalTranscript += transcript + ' ';
                            } else {
                                interimTranscript += transcript;
                            }
                        }

                        const preview = (initialText ? initialText + ' ' : '') + (finalTranscript + interimTranscript).trim();
                        c.textarea.value = preview;
                        
                        c.textarea.style.height = 'auto';
                        c.textarea.style.height = c.textarea.scrollHeight + 'px';
                        
                        c.textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    };

                    dictationRecognition.onend = () => {
                        if (isDictating) {
                            const final = (initialText ? initialText + ' ' : '') + finalTranscript.trim();
                            c.textarea.value = final;
                            c.textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        stopDictation();
                    };

                    dictationRecognition.onerror = (event) => {
                        console.error('[Dictation] Error:', event.error);
                        if (event.error === 'no-speech') {
                            showToast('Aucune parole détectée');
                        } else if (event.error === 'not-allowed') {
                            showToast('Autorisation microphone refusée');
                        } else {
                            showToast('Erreur de dictée');
                        }
                        stopDictation();
                    };

                    dictationRecognition.start();
                } catch (e) {
                    console.error('[Dictation] Failed to start:', e);
                    showToast('Impossible de démarrer la dictée');
                    stopDictation();
                }
            }

            function stopDictation() {
                if (dictationRecognition) {
                    try {
                        dictationRecognition.stop();
                    } catch {}
                    dictationRecognition = null;
                }

                isDictating = false;

                Object.values(focusDock).forEach(els => {
                    if (els.micro) {
                        els.micro.setAttribute('data-dictating', 'false');
                        const span = els.micro.querySelector('span');
                        if (span) span.textContent = 'Micro';
                        
                        const iconDiv = els.micro.querySelector('div');
                        if (iconDiv) {
                            iconDiv.style.animation = '';
                        }
                    }
                });
            }

            // --- Audience (targeted messages) ---
            const audienceState = {
                type: 'all',
                userIds: [],
            };

            const recipientsIndex = new Map();
            let recipientsLoaded = false;
            let recipientsLoading = null;

            function normalizeUserIds(ids) {
                const out = [];
                const seen = new Set();
                for (const raw of (Array.isArray(ids) ? ids : [])) {
                    const id = Number(raw || 0);
                    if (!id || id <= 0) continue;
                    if (currentUserId && Number(id) === Number(currentUserId)) continue;
                    if (seen.has(id)) continue;
                    seen.add(id);
                    out.push(id);
                }
                return out;
            }

            function setAudienceUserIds(ids) {
                const next = normalizeUserIds(ids);
                if (!next.length) {
                    audienceState.type = 'all';
                    audienceState.userIds = [];
                } else {
                    audienceState.type = 'subset';
                    audienceState.userIds = next;
                }
                syncAudienceUi();

                // UX: if the user targets exactly one person, switch the chat to "conversation" mode
                // immediately (hide unrelated messages), even if navigation is blocked (PWA/back-forward cache).
                if (next.length === 1) {
                    conversationUserId = Number(next[0] || 0) || 0;
                } else if (!next.length) {
                    conversationUserId = 0;
                }
                try {
                    syncConversationUi();
                    applyConversationFilterToDom();
                } catch {}
            }

            function resetAudience() {
                audienceState.type = 'all';
                audienceState.userIds = [];
                syncAudienceUi();
            }

            async function ensureRecipientsLoaded() {
                if (recipientsLoaded) return;
                if (recipientsLoading) return recipientsLoading;
                if (!recipientsUrl) {
                    recipientsLoaded = true;
                    return;
                }

                recipientsLoading = (async () => {
                    try {
                        const res = await fetch(recipientsUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('recipients fetch failed');
                        const json = await res.json();
                        const users = Array.isArray(json?.users) ? json.users : [];
                        recipientsIndex.clear();
                        for (const u of users) {
                            const id = Number(u?.id || 0);
                            if (!id) continue;
                            recipientsIndex.set(id, {
                                id,
                                name: String(u?.name || '—'),
                                avatar_url: String(u?.avatar_url || ''),
                            });
                        }
                    } catch (e) {
                        // Best-effort: audience can still work; labels may be generic.
                    } finally {
                        recipientsLoaded = true;
                        recipientsLoading = null;
                        syncAudienceUi();
                        decorateExistingAudienceBadges();
                    }
                })();

                return recipientsLoading;
            }

            function audienceLabelForIds(ids) {
                const list = normalizeUserIds(ids);
                if (!list.length) return 'Tout le monde';
                const names = list
                    .map((id) => recipientsIndex.get(id)?.name)
                    .filter((n) => typeof n === 'string' && n.trim() !== '');

                if (!names.length) {
                    return list.length === 1 ? '1 personne' : `${list.length} personnes`;
                }

                const first = names.slice(0, 3);
                const more = names.length - first.length;
                return more > 0 ? `${first.join(', ')} +${more}` : first.join(', ');
            }

            function audiencePillTextFromPayload(payload) {
                const t = String(payload?.audience_type || payload?.audience?.type || 'all');
                if (t !== 'subset') return '';
                const ids = payload?.audience_user_ids || payload?.audience?.user_ids || [];
                const users = Array.isArray(payload?.audience_users) ? payload.audience_users : [];
                if (users.length) {
                    const names = users.map((u) => String(u?.name || '').trim()).filter(Boolean);
                    if (names.length) {
                        const first = names.slice(0, 3);
                        const more = names.length - first.length;
                        const label = more > 0 ? `${first.join(', ')} +${more}` : first.join(', ');
                        return `À: ${label}`;
                    }
                }
                return `À: ${audienceLabelForIds(ids)}`;
            }

            const audienceUi = {
                mobile: { bar: null, label: null, btn: null },
                desktop: { bar: null, label: null, btn: null },
            };

            function ensureAudienceUiFor(key) {
                if (chatMode !== 'legacy') return;
                const c = composer[key];
                if (!c?.form || !c?.textarea) return;
                if (audienceUi[key]?.bar) return;

                const bar = document.createElement('div');
                bar.className = 'mb-2 flex items-center justify-between gap-2';

                const left = document.createElement('div');
                left.className = 'text-xs font-semibold text-slate-600';
                left.textContent = 'À:';

                const label = document.createElement('div');
                label.className = 'text-xs font-semibold text-slate-700 truncate';
                label.textContent = 'Tout le monde';
                label.style.maxWidth = '58vw';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shrink-0 inline-flex items-center justify-center rounded-full border border-black/10 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700';
                btn.textContent = 'Cibler';
                btn.addEventListener('click', async () => {
                    setActiveComposerKey(key);
                    await ensureRecipientsLoaded();
                    openAudienceModal();
                });

                const leftWrap = document.createElement('div');
                leftWrap.className = 'min-w-0 flex items-center gap-2';
                leftWrap.appendChild(left);
                leftWrap.appendChild(label);

                bar.appendChild(leftWrap);
                bar.appendChild(btn);

                // Insert above textarea.
                c.textarea.parentNode?.insertBefore(bar, c.textarea);

                audienceUi[key] = { bar, label, btn };
            }

            function syncAudienceUi() {
                const labelText = audienceState.type === 'subset'
                    ? audienceLabelForIds(audienceState.userIds)
                    : 'Tout le monde';

                for (const k of ['mobile', 'desktop']) {
                    const ui = audienceUi[k];
                    const c = composer[k];
                    if (ui?.label) ui.label.textContent = labelText;
                    if (c?.textarea) {
                        c.textarea.placeholder = audienceState.type === 'subset'
                            ? `Message pour ${labelText}…`
                            : 'Votre message…';
                    }
                }
            }

            // --- Audience modal ---
            let audienceModal = null;
            let audienceModalBackdrop = null;
            let audienceModalPanel = null;
            let audienceModalList = null;
            let audienceModalSearch = null;
            let audienceModalSave = null;
            let audienceModalClear = null;

            function ensureAudienceModal() {
                if (audienceModal) return;

                audienceModal = document.createElement('div');
                audienceModal.id = 'chatAudienceModal';
                audienceModal.className = 'fixed inset-0 z-[70] hidden';

                audienceModalBackdrop = document.createElement('div');
                audienceModalBackdrop.className = 'absolute inset-0 bg-black/30 backdrop-blur-sm';
                audienceModalBackdrop.addEventListener('click', () => setAudienceModalOpen(false));

                audienceModalPanel = document.createElement('div');
                audienceModalPanel.className = 'absolute left-1/2 top-1/2 w-[min(560px,92vw)] -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white border border-black/10 shadow-xl p-4';

                const title = document.createElement('div');
                title.className = 'text-sm font-bold text-slate-900';
                title.textContent = 'Destinataires';

                const hint = document.createElement('div');
                hint.className = 'mt-0.5 text-xs text-slate-500';
                hint.textContent = 'Sélectionne 1 ou plusieurs personnes (vide = tout le monde).';

                audienceModalSearch = document.createElement('input');
                audienceModalSearch.type = 'search';
                audienceModalSearch.className = 'mt-3 w-full rounded-xl border border-black/10 bg-white px-3 py-2 text-sm';
                audienceModalSearch.placeholder = 'Rechercher…';
                audienceModalSearch.addEventListener('input', () => renderAudienceModalList());

                audienceModalList = document.createElement('div');
                audienceModalList.className = 'mt-3 max-h-[52vh] overflow-auto rounded-xl border border-black/10';

                const actions = document.createElement('div');
                actions.className = 'mt-4 flex items-center justify-between gap-2';

                audienceModalClear = document.createElement('button');
                audienceModalClear.type = 'button';
                audienceModalClear.className = 'inline-flex items-center justify-center rounded-full border border-black/10 bg-white px-3 py-2 text-xs font-semibold text-slate-700';
                audienceModalClear.textContent = 'Tout le monde';
                audienceModalClear.addEventListener('click', () => {
                    if (conversationUserId > 0) {
                        navigateToConversation(0);
                        return;
                    }
                    setAudienceUserIds([]);
                    setAudienceModalOpen(false);
                });

                audienceModalSave = document.createElement('button');
                audienceModalSave.type = 'button';
                audienceModalSave.className = 'inline-flex items-center justify-center rounded-full bg-slate-900 text-white px-4 py-2 text-xs font-semibold';
                audienceModalSave.textContent = 'Valider';
                audienceModalSave.addEventListener('click', () => {
                    const ids = [];
                    audienceModalList?.querySelectorAll('input[type="checkbox"][data-recipient-id]').forEach((el) => {
                        if (el.checked) ids.push(Number(el.dataset.recipientId || 0));
                    });
                    setAudienceUserIds(ids);
                    setAudienceModalOpen(false);

                    // If the user selected exactly one recipient, switch to a "conversation" view
                    // showing only the messages shared with that user.
                    if (ids.length === 1) {
                        navigateToConversation(ids[0]);
                    } else if (conversationUserId > 0 && ids.length !== 1) {
                        // Can't represent multi-recipient conversation yet; return to the full chat.
                        navigateToConversation(0);
                    }
                });

                actions.appendChild(audienceModalClear);
                actions.appendChild(audienceModalSave);

                audienceModalPanel.appendChild(title);
                audienceModalPanel.appendChild(hint);
                audienceModalPanel.appendChild(audienceModalSearch);
                audienceModalPanel.appendChild(audienceModalList);
                audienceModalPanel.appendChild(actions);

                audienceModal.appendChild(audienceModalBackdrop);
                audienceModal.appendChild(audienceModalPanel);
                document.body.appendChild(audienceModal);
            }

            function setAudienceModalOpen(open) {
                ensureAudienceModal();
                audienceModal.classList.toggle('hidden', !open);
                if (open) {
                    renderAudienceModalList();
                    try { audienceModalSearch?.focus(); } catch {}
                }
            }

            function renderAudienceModalList() {
                if (!audienceModalList) return;
                const q = String(audienceModalSearch?.value || '').trim().toLocaleLowerCase();
                const selected = new Set(normalizeUserIds(audienceState.userIds));
                audienceModalList.innerHTML = '';

                const users = Array.from(recipientsIndex.values());
                for (const u of users) {
                    const name = String(u?.name || '—');
                    if (q && !name.toLocaleLowerCase().includes(q)) continue;

                    const row = document.createElement('label');
                    row.className = 'flex items-center gap-3 px-3 py-2 border-b border-black/5 last:border-b-0 cursor-pointer';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.className = 'h-4 w-4';
                    cb.dataset.recipientId = String(u.id);
                    cb.checked = selected.has(Number(u.id));

                    const txt = document.createElement('div');
                    txt.className = 'text-sm text-slate-900';
                    txt.textContent = name;

                    row.appendChild(cb);
                    row.appendChild(txt);
                    audienceModalList.appendChild(row);
                }

                if (!audienceModalList.childElementCount) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-6 text-center text-sm text-slate-500';
                    empty.textContent = 'Aucun résultat.';
                    audienceModalList.appendChild(empty);
                }
            }

            function openAudienceModal() {
                if (chatMode !== 'legacy') return;
                ensureAudienceModal();
                setAudienceModalOpen(true);
            }

            function ensureAudiencePill(rowEl, payload) {
                if (!rowEl) return;
                const text = audiencePillTextFromPayload(payload);
                if (!text) return;

                const width = rowEl.firstElementChild;
                if (!width) return;

                if (width.querySelector('[data-audience-pill]')) return;

                const senderId = Number(payload?.user?.id ?? payload?.user_id ?? rowEl?.dataset?.userId ?? 0) || 0;
                const audienceIds = normalizeUserIds(payload?.audience_user_ids || payload?.audience?.user_ids || []);

                // If this is a 1:1 targeted message involving me, make the pill open the conversation view.
                // Participants are: sender + recipients.
                let openConversationWithId = 0;
                if (currentUserId && senderId > 0 && audienceIds.length) {
                    const me = Number(currentUserId || 0) || 0;
                    const participants = new Set([senderId, ...audienceIds]);
                    if (me > 0 && participants.has(me) && participants.size === 2) {
                        for (const pid of participants) {
                            if (pid !== me) {
                                openConversationWithId = pid;
                                break;
                            }
                        }
                    }
                }

                const pillWrap = document.createElement('div');
                pillWrap.className = `mb-1 flex ${rowEl.classList.contains('justify-end') ? 'justify-end' : 'justify-start'}`;

                const pill = document.createElement(openConversationWithId > 0 || audienceIds.length > 1 ? 'button' : 'div');
                pill.dataset.audiencePill = '1';
                pill.className = 'text-[0.7rem] font-semibold text-slate-700 bg-white border border-black/10 rounded-full px-3 py-1';
                pill.textContent = text;

                if (pill.tagName === 'BUTTON') {
                    pill.type = 'button';
                    pill.className += ' hover:bg-white/80 active:scale-[0.99] transition';

                    if (openConversationWithId > 0) {
                        pill.title = 'Ouvrir la conversation';
                        pill.addEventListener('click', (ev) => {
                            ev.preventDefault();
                            navigateToConversation(openConversationWithId);
                        });
                    } else {
                        // Multi-recipient targeted message: open the targeting UI prefilled.
                        pill.title = 'Voir les destinataires';
                        pill.addEventListener('click', async (ev) => {
                            ev.preventDefault();
                            setAudienceUserIds(audienceIds);
                            await ensureRecipientsLoaded();
                            openAudienceModal();
                        });
                    }
                }

                pillWrap.appendChild(pill);
                width.insertBefore(pillWrap, width.firstChild);
            }

            function decorateExistingAudienceBadges() {
                if (!messagesEl) return;
                messagesEl.querySelectorAll('[data-message-row]').forEach((row) => {
                    const t = String(row?.dataset?.audienceType || 'all');
                    if (t !== 'subset') return;
                    let ids = [];
                    try { ids = JSON.parse(row?.dataset?.audienceUserIds || '[]'); } catch {}
                    ensureAudiencePill(row, { audience_type: 'subset', audience_user_ids: ids });
                });
            }

                        function getAnyForm() {
                return composer.mobile.form || composer.desktop.form || null;
            }

            function getCsrfToken() {
                const f = getAnyForm();
                return f?.querySelector('input[name="_token"]')?.value || null;
            }

            function getSocketId() {
                try {
                    return (window.Echo && typeof window.Echo.socketId === 'function') ? window.Echo.socketId() : null;
                } catch {
                    return null;
                }
            }

            function syncComposerKeyFromMatchMedia() {
                try {
                    const next = window.matchMedia && window.matchMedia('(min-width: 640px)').matches ? 'desktop' : 'mobile';
                    setActiveComposerKey(next);
                } catch {}
            }
            window.addEventListener('resize', syncComposerKeyFromMatchMedia);
            const attachSheet = document.getElementById('chatAttachSheet');
            const attachBackdrop = document.getElementById('chatAttachBackdrop');
            const attachCancel = document.getElementById('chatAttachCancel');
            const attachPickMedia = document.getElementById('chatAttachPickMedia');
            const attachPickVoice = document.getElementById('chatAttachPickVoice');
            const scrollToBottomBtn = document.getElementById('chatScrollToBottom');
            const searchBtn = document.getElementById('chatSearchBtn');
            const searchBar = document.getElementById('chatSearchBar');
            const searchInput = document.getElementById('chatSearchInput');
            const infoBtn = document.getElementById('chatInfoBtn');
            const infoModal = document.getElementById('chatInfoModal');
            const infoBackdrop = document.getElementById('chatInfoBackdrop');
            const infoClose = document.getElementById('chatInfoClose');
            const infoList = document.getElementById('chatInfoList');
            const infoCount = document.getElementById('chatInfoCount');

            const mediaModal = document.getElementById('chatMediaModal');
            const mediaBackdrop = document.getElementById('chatMediaBackdrop');
            const mediaClose = document.getElementById('chatMediaClose');
            const mediaTitle = document.getElementById('chatMediaTitle');
            const mediaImg = document.getElementById('chatMediaImg');
            const mediaVideo = document.getElementById('chatMediaVideo');
            const mediaOpenLink = document.getElementById('chatMediaOpenLink');
            const currentUserId = bootstrap.currentUserId;
            const currentUserName = bootstrap.currentUserName;
            const chatMode = String(bootstrap.chatMode || 'legacy');
            const backUrl = String(bootstrap.backUrl || '').trim() || null;
            const chatUrl = String(bootstrap.chatUrl || '').trim() || null;
            const pollUrl = bootstrap.pollUrl;
            const storeUrl = String(bootstrap.storeUrl || '').trim() || null;
            const dmBaseUrl = String(bootstrap.dmBaseUrl || '').trim() || '/chat/dm';
            const dmUserId = Number(bootstrap.dmUserId || 0) || 0;
            const quotaUrl = bootstrap.quotaUrl;
            const presignUrl = bootstrap.presignUrl;
            const mpInitUrl = bootstrap.mpInitUrl;
            const mpCompleteUrl = bootstrap.mpCompleteUrl;
            const finalizeUrl = bootstrap.finalizeUrl;
            let conversationUserId = Number(bootstrap.conversationWithUserId || 0) || 0;
            const conversationUser = (bootstrap && bootstrap.conversationWithUser) ? bootstrap.conversationWithUser : null;
            let lastMessageId = (bootstrap.lastMessageId ?? 0);
            const initialOnline = (bootstrap.initialOnline || []);
            const initialReactionSummaries = (bootstrap.initialReactionSummaries || []);
            const reactionSummaries = new Map();

            let mediaImgLoadToken = 0;

            // --- Scroll locking for fullscreen modals (iOS-friendly) ---
            const scrollLockState = {
                active: false,
                y: 0,
                bodyPosition: '',
                bodyTop: '',
                bodyLeft: '',
                bodyRight: '',
                bodyWidth: '',
                bodyOverflowY: '',
                bodyPaddingRight: '',
            };

            function lockPageScroll() {
                if (scrollLockState.active) return;
                const body = document.body;
                if (!body) return;

                const y = (window.scrollY || document.documentElement.scrollTop || 0);
                scrollLockState.active = true;
                scrollLockState.y = y;

                scrollLockState.bodyPosition = body.style.position;
                scrollLockState.bodyTop = body.style.top;
                scrollLockState.bodyLeft = body.style.left;
                scrollLockState.bodyRight = body.style.right;
                scrollLockState.bodyWidth = body.style.width;
                scrollLockState.bodyOverflowY = body.style.overflowY;
                scrollLockState.bodyPaddingRight = body.style.paddingRight;

                const scrollbarW = Math.max(0, (window.innerWidth || 0) - (document.documentElement?.clientWidth || 0));
                body.style.position = 'fixed';
                body.style.top = `-${y}px`;
                body.style.left = '0';
                body.style.right = '0';
                body.style.width = '100%';
                body.style.overflowY = 'scroll';
                if (scrollbarW > 0) body.style.paddingRight = `${scrollbarW}px`;
            }

            function unlockPageScroll() {
                if (!scrollLockState.active) return;
                const body = document.body;
                if (!body) return;

                const y = scrollLockState.y || 0;
                body.style.position = scrollLockState.bodyPosition;
                body.style.top = scrollLockState.bodyTop;
                body.style.left = scrollLockState.bodyLeft;
                body.style.right = scrollLockState.bodyRight;
                body.style.width = scrollLockState.bodyWidth;
                body.style.overflowY = scrollLockState.bodyOverflowY;
                body.style.paddingRight = scrollLockState.bodyPaddingRight;

                scrollLockState.active = false;
                scrollLockState.y = 0;
                try { window.scrollTo(0, y); } catch {}
            }

            function isMediaModalOpen() {
                try {
                    return !!(mediaModal && !mediaModal.classList.contains('hidden'));
                } catch {
                    return false;
                }
            }

            // Runtime wiring: make sure the forms and header reflect the current mode.
            try {
                if (storeUrl) {
                    if (composer.mobile?.form) composer.mobile.form.action = storeUrl;
                    if (composer.desktop?.form) composer.desktop.form.action = storeUrl;
                }
            } catch {}

            try {
                const titleText = String(bootstrap.chatTitle || '').trim();
                if (titleText) {
                    const titleEl = document.querySelector('.text-sm.sm\\:text-base.font-semibold.text-gray-900');
                    if (titleEl) titleEl.textContent = titleText;
                }

                // DM mode: replace the online subtitle by a simple "Privé" label.
                if (chatMode === 'dm') {
                    const onlineCount = document.getElementById('chatOnlineCount');
                    const presenceLabel = document.getElementById('chatPresenceLabel');
                    if (onlineCount) onlineCount.classList.add('hidden');
                    if (presenceLabel) presenceLabel.classList.add('hidden');
                    const subtitle = presenceLabel?.parentElement || onlineCount?.parentElement;
                    if (subtitle && !subtitle.querySelector('[data-chat-dm-label]')) {
                        const sp = document.createElement('span');
                        sp.dataset.chatDmLabel = '1';
                        sp.className = 'font-semibold text-gray-900';
                        sp.textContent = 'Privé';
                        subtitle.appendChild(sp);
                    }
                }
            } catch {}

            if (chatMode !== 'legacy') {
                // New UX: no conversation filter inside public/DM pages.
                conversationUserId = 0;
            }

            function navigateToConversation(nextUserId) {
                if (chatMode !== 'legacy') return;
                const nextId = Number(nextUserId || 0) || 0;
                try {
                    const url = new URL(window.location.href);
                    if (nextId > 0) {
                        url.searchParams.set('with_user_id', String(nextId));
                    } else {
                        url.searchParams.delete('with_user_id');
                    }
                    const nextHref = url.toString();
                    if (nextHref !== window.location.href) {
                        window.location.href = nextHref;
                    }
                } catch {
                    // Fallback: best-effort navigation
                    if (chatUrl) {
                        window.location.href = nextId > 0 ? `${chatUrl}?with_user_id=${encodeURIComponent(String(nextId))}` : chatUrl;
                    }
                }
            }

            function payloadSenderId(payload) {
                return Number(payload?.user?.id ?? payload?.user_id ?? 0) || 0;
            }

            function payloadAudienceType(payload) {
                return String(payload?.audience_type || payload?.audience?.type || 'all');
            }

            function payloadAudienceUserIds(payload) {
                return normalizeUserIds(payload?.audience_user_ids || payload?.audience?.user_ids || []);
            }

            function payloadHasParticipant(payload, userId) {
                const id = Number(userId || 0) || 0;
                if (!id) return false;
                if (payloadSenderId(payload) === id) return true;
                return payloadAudienceUserIds(payload).includes(id);
            }

            function isPayloadInConversation(payload, otherUserId) {
                const otherId = Number(otherUserId || 0) || 0;
                if (!otherId) return true;
                if (!currentUserId) return false;
                const audType = payloadAudienceType(payload);

                // Public messages: keep only those authored by me or the selected user.
                if (audType !== 'subset') {
                    const sender = payloadSenderId(payload);
                    return sender === Number(currentUserId) || sender === otherId;
                }

                // Targeted messages: keep only those where both participate (sender or recipient).
                return payloadHasParticipant(payload, currentUserId) && payloadHasParticipant(payload, otherId);
            }

            function parseJsonArray(raw) {
                if (!raw) return [];
                try {
                    const v = JSON.parse(raw);
                    return Array.isArray(v) ? v : [];
                } catch {
                    return [];
                }
            }

            function isRowInConversation(rowEl, otherUserId) {
                const otherId = Number(otherUserId || 0) || 0;
                if (!otherId) return true;
                if (!currentUserId) return false;

                const senderId = Number(rowEl?.dataset?.userId || 0) || 0;
                const audType = String(rowEl?.dataset?.audienceType || 'all');
                const audIds = normalizeUserIds(parseJsonArray(rowEl?.dataset?.audienceUserIds || '[]'));

                if (audType !== 'subset') {
                    return senderId === Number(currentUserId) || senderId === otherId;
                }

                const participants = new Set([senderId, ...audIds]);
                return participants.has(Number(currentUserId)) && participants.has(otherId);
            }

            function applyConversationFilterToDom() {
                if (!messagesEl) return;

                if (chatMode !== 'legacy') return;

                const otherId = Number(conversationUserId || 0) || 0;
                const rows = Array.from(messagesEl.querySelectorAll('[data-message-row]'));

                for (const row of rows) {
                    const show = isRowInConversation(row, otherId);
                    row.classList.toggle('hidden', !show);
                }

                // Hide day separators with no visible messages.
                try {
                    const visibleDayKeys = new Set(
                        rows
                            .filter((r) => !r.classList.contains('hidden'))
                            .map((r) => String(r?.dataset?.dayKey || ''))
                            .filter(Boolean)
                    );

                    messagesEl.querySelectorAll('[data-day-separator]').forEach((sep) => {
                        const dk = String(sep?.dataset?.dayKey || '');
                        const keep = !otherId || (dk && visibleDayKeys.has(dk));
                        sep.classList.toggle('hidden', !keep);
                    });
                } catch {}
            }

            let conversationBar = null;
            let conversationBarLabel = null;

            function ensureConversationBar() {
                if (conversationBar) return;
                if (!scrollEl || !scrollEl.parentNode) return;

                conversationBar = document.createElement('div');
                conversationBar.className = 'mx-3 sm:mx-4 mt-3 mb-2 rounded-2xl border border-black/10 bg-white/70 supports-[backdrop-filter]:bg-white/55 supports-[backdrop-filter]:backdrop-blur-xl px-3 py-2 flex items-center justify-between gap-2';
                conversationBar.dataset.conversationBar = '1';

                conversationBarLabel = document.createElement('div');
                conversationBarLabel.className = 'min-w-0 text-xs font-semibold text-slate-700 truncate';
                conversationBarLabel.textContent = 'Conversation';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'shrink-0 inline-flex items-center justify-center rounded-full border border-black/10 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700';
                btn.textContent = 'Voir tout';
                btn.addEventListener('click', () => navigateToConversation(0));

                conversationBar.appendChild(conversationBarLabel);
                conversationBar.appendChild(btn);

                scrollEl.parentNode.insertBefore(conversationBar, scrollEl);
            }

            function syncConversationUi() {
                ensureConversationBar();
                if (!conversationBar) return;

                const on = conversationUserId > 0;
                conversationBar.classList.toggle('hidden', !on);
                if (!on) return;

                const name = String(conversationUser?.name || recipientsIndex.get(conversationUserId)?.name || '—');
                conversationBarLabel.textContent = `Conversation avec ${name}`;
            }

            function toArraySummary(v) {
                if (!Array.isArray(v)) return [];
                return v
                    .map((r) => ({
                        emoji: String(r?.emoji || '').trim(),
                        count: Number(r?.count || 0),
                        reacted_by_me: Boolean(r?.reacted_by_me),
                    }))
                    .filter((r) => r.emoji && r.count > 0);
            }

            try {
                for (const [k, v] of Object.entries(initialReactionSummaries || {})) {
                    const id = Number(k);
                    if (!Number.isNaN(id) && id > 0) {
                        reactionSummaries.set(id, toArraySummary(v));
                    }
                }
            } catch {}

            const MAX_UPLOAD_BYTES = bootstrap.maxUploadBytes;
            const MULTIPART_THRESHOLD_BYTES = bootstrap.multipartThresholdBytes;

            const quotaEl = document.getElementById('chatAttachQuota');

            const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
            let recognition = null;
            let dictationActive = false;
            let dictationBase = '';
            let dictationInterim = '';

            const notifBanner = document.getElementById('chatNotifBanner');
            const notifBody = document.getElementById('chatNotifBody');
            const notifHelp = document.getElementById('chatNotifHelp');
            const notifPrimary = document.getElementById('chatNotifPrimary');
            const notifLater = document.getElementById('chatNotifLater');
            const notifClose = document.getElementById('chatNotifClose');

            const NOTIF_DISMISS_KEY = 'famille:chat:notif_dismissed_at';

            function setVoiceStatus() {}

            function parseAttachmentBody(body) {
                const prefix = '[[ATTACHMENT]]';
                const b = String(body || '');
                if (!b.startsWith(prefix)) return null;
                try {
                    const data = JSON.parse(b.slice(prefix.length));
                    if (!data || typeof data !== 'object') return null;
                    const t = String(data.media_type || '');
                    if (t !== 'image' && t !== 'video') return null;
                    return data;
                } catch (e) {
                    return null;
                }
            }

            function parseLinkCardBody(body) {
                const b = String(body || '').trim();
                if (!b) return null;

                let url = null;
                const m2 = b.match(/^(https?:\/\/\S+)\s*$/u);
                if (m2) url = m2[1];
                if (!url) return null;
                url = String(url).trim();

                let domain = '';
                try {
                    domain = (new URL(url)).host || '';
                } catch {
                    domain = url.replace(/^https?:\/\//i, '').replace(/\/+$/g, '');
                }
                return { url, domain, title: 'Lien' };
            }

            function setDictationUi(active) {
                dictationActive = !!active;
                if (!attachPickVoice) return;
                attachPickVoice.setAttribute('aria-pressed', dictationActive ? 'true' : 'false');
                attachPickVoice.classList.toggle('border-slate-900', dictationActive);
            }
            function syncVoiceAvailability() {
                if (!attachPickVoice) return;
                const supported = !!SpeechRecognitionCtor;
                attachPickVoice.disabled = !supported;
                attachPickVoice.classList.toggle('opacity-50', attachPickVoice.disabled);
                attachPickVoice.classList.toggle('cursor-not-allowed', attachPickVoice.disabled);
                attachPickVoice.title = supported ? 'Dicter' : 'Dictée vocale non supportée par ce navigateur';
            }


            const palette = [
                { chip: 'bg-indigo-50 text-indigo-700 border-indigo-200', avatar: 'bg-indigo-600 text-white' },
                { chip: 'bg-emerald-50 text-emerald-700 border-emerald-200', avatar: 'bg-emerald-600 text-white' },
                { chip: 'bg-amber-50 text-amber-800 border-amber-200', avatar: 'bg-amber-600 text-white' },
                { chip: 'bg-rose-50 text-rose-700 border-rose-200', avatar: 'bg-rose-600 text-white' },
                { chip: 'bg-sky-50 text-sky-700 border-sky-200', avatar: 'bg-sky-600 text-white' },
                { chip: 'bg-violet-50 text-violet-700 border-violet-200', avatar: 'bg-violet-600 text-white' },
            ];

            function paletteFor(userId) {
                const id = Number(userId ?? 0);
                return palette[Math.abs(id) % palette.length] ?? palette[0];
            }

            function initialsFor(name) {
                const n = String(name ?? '').trim();
                if (!n) return '—';
                const parts = n.split(/\s+/).filter(Boolean);
                const first = parts[0] ?? '';
                const last = parts[parts.length - 1] ?? '';
                let ini = first ? first.slice(0, 1) : '';
                if (last && last !== first) ini += last.slice(0, 1);
                return ini.toUpperCase();
            }

            function avatarUrlFor(u) {
                const raw = u?.avatar_url ?? u?.avatarUrl ?? u?.avatar ?? null;
                const s = String(raw ?? '').trim();
                return s ? s : '';
            }

            function buildAvatarNode({ name, colors, avatarUrl, sizeClass = 'w-9 h-9' }) {
                const wrap = document.createElement('div');
                wrap.className = `${sizeClass} rounded-full overflow-hidden flex items-center justify-center text-xs font-semibold`;

                const url = String(avatarUrl || '').trim();
                if (url) {
                    wrap.classList.add('bg-white');
                    wrap.classList.add('border');
                    wrap.classList.add('border-black/10');
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = '';
                    img.loading = 'lazy';
                    img.className = 'h-full w-full object-cover';
                    wrap.appendChild(img);
                    return wrap;
                }

                wrap.className += ` ${colors.avatar}`;
                wrap.textContent = initialsFor(name);
                return wrap;
            }

            let pinToBottomUntil = 0;

            function cancelPinToBottom() {
                pinToBottomUntil = 0;
            }

            function pinToBottom(ms = 1200) {
                const until = Date.now() + Math.max(0, Number(ms || 0));
                pinToBottomUntil = Math.max(pinToBottomUntil, until);
            }

            function isPinnedToBottom() {
                return Date.now() < pinToBottomUntil;
            }

            function getMobileBottomDockHeight() {
                const dock = document.getElementById('mobileBottomDock');
                if (!dock) return 0;
                const rect = dock.getBoundingClientRect();
                return rect && rect.height ? rect.height : (dock.offsetHeight || 0);
            }

            function syncScrollBottomPadding() {
                if (!scrollEl) return;

                let isMobile = true;
                try {
                    isMobile = !(window.matchMedia && window.matchMedia('(min-width: 640px)').matches);
                } catch {}

                const dockH = isMobile ? getMobileBottomDockHeight() : 0;
                if (dockH > 0) {
                    const pad = Math.ceil(dockH + 12);
                    scrollEl.style.paddingBottom = `${pad}px`;
                    scrollEl.style.scrollPaddingBottom = `${pad}px`;
                } else {
                    scrollEl.style.paddingBottom = '';
                    scrollEl.style.scrollPaddingBottom = '';
                }
            }

            function scrollToBottom(opts = {}) {
                const options = (opts && typeof opts === 'object') ? opts : {};
                const force = !!options.force;

                // Prevent background scroll adjustments from fighting the media modal.
                if (isMediaModalOpen()) return;

                const lastRow = messagesEl?.querySelector('[data-message-row]:last-child');
                if (!lastRow) return;

                if (!force && !isPinnedToBottom() && !isNearBottom()) return;

                syncScrollBottomPadding();

                // iOS Safari can be finicky with programmatic scrolling; do both.
                const run = () => {
                    try {
                        lastRow.scrollIntoView({ block: 'end' });
                    } catch {
                        // ignore
                    }
                    if (scrollEl) {
                        scrollEl.scrollTop = scrollEl.scrollHeight;
                    }
                };

                requestAnimationFrame(run);
                setTimeout(run, 80);
            }

            function bindInitialMediaThumbPinning() {
                if (!messagesEl) return;
                const imgs = messagesEl.querySelectorAll('[data-chat-media-open] img');
                imgs.forEach((img) => {
                    if (!(img instanceof HTMLImageElement)) return;
                    if (img.dataset.pinBound === '1') return;
                    img.dataset.pinBound = '1';

                    const card = img.closest('[data-chat-media-card]');
                    const markLoaded = () => {
                        if (card) card.classList.remove('animate-pulse');
                    };
                    if (img.complete) {
                        // Cached images might not fire 'load', but decoding can still complete later.
                        try {
                            if (typeof img.decode === 'function') {
                                img.decode().then(() => {
                                    markLoaded();
                                    pinToBottom(900);
                                    scrollToBottom({ force: true });
                                }).catch(() => {});
                            }
                        } catch {}
                        markLoaded();
                        return;
                    }

                    img.addEventListener('load', () => {
                        if (isMediaModalOpen()) return;
                        // Thumbnails can load after initial scroll, changing layout;
                        // keep the bottom pinned while this happens.
                        markLoaded();
                        pinToBottom(900);
                        scrollToBottom({ force: true });
                    }, { once: true });
                });
            }

            let ensureBottomTimers = [];
            function cancelEnsureBottomTimers() {
                for (const id of ensureBottomTimers) {
                    try { clearTimeout(id); } catch {}
                }
                ensureBottomTimers = [];
            }

            function ensureBottom(ms = 900) {
                if (isMediaModalOpen()) return;
                cancelEnsureBottomTimers();
                pinToBottom(ms);
                syncScrollBottomPadding();
                bindInitialMediaThumbPinning();
                scrollToBottom({ force: true });
                ensureBottomTimers.push(setTimeout(() => scrollToBottom({ force: true }), 120));
                ensureBottomTimers.push(setTimeout(() => scrollToBottom({ force: true }), 360));
                ensureBottomTimers.push(setTimeout(() => scrollToBottom({ force: true }), 800));
                ensureBottomTimers.push(setTimeout(() => scrollToBottom({ force: true }), 1600));
                ensureBottomTimers.push(setTimeout(() => scrollToBottom({ force: true }), 2600));
            }

            // Ensure we land at the bottom on initial load and when navigating back.
            window.addEventListener('load', () => ensureBottom(1200));
            window.addEventListener('pageshow', () => ensureBottom(1200));

            // Prevent the browser from restoring a previous scroll position (mobile/PWA can be inconsistent).
            try {
                if (window.history && 'scrollRestoration' in window.history) {
                    window.history.scrollRestoration = 'manual';
                }
            } catch {}

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) ensureBottom(1200);
            });

            window.addEventListener('resize', () => {
                syncScrollBottomPadding();
                if (!isMediaModalOpen() && isPinnedToBottom()) scrollToBottom({ force: true });
            });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    syncScrollBottomPadding();
                    if (!isMediaModalOpen() && isPinnedToBottom()) scrollToBottom({ force: true });
                });
            }

            function isNearBottom() {
                if (!scrollEl) return true;
                const threshold = 120;
                const distance = scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight;
                return distance <= threshold;
            }

            function syncScrollToBottomButton() {
                if (!scrollToBottomBtn || !scrollEl) return;
                const show = !isNearBottom();
                scrollToBottomBtn.classList.toggle('hidden', !show);
                scrollToBottomBtn.classList.toggle('flex', show);
            }

            syncScrollBottomPadding();
            ensureBottom(900);
            requestAnimationFrame(() => ensureBottom(1200));
            syncScrollToBottomButton();

            function hideEmptyState() {
                if (!emptyEl) return;
                emptyEl.classList.add('hidden');
            }

            function updatePresenceUi(count) {
                const c = Number(count || 0);
                if (presenceLabelEl) {
                    presenceLabelEl.textContent = c <= 1 ? 'en ligne' : 'en ligne';
                }

                // In public/dm mode, we don't show the "Personne en ligne" hint (not relevant)
                const hint = (chatMode === 'legacy' && c <= 1) ? 'Personne en ligne — votre message sera notifié.' : '';
                const show = hint !== '';
                [composer.mobile.soloHint, composer.desktop.soloHint].forEach((el) => {
                    if (!el) return;
                    el.textContent = hint;
                    el.classList.toggle('hidden', !show);
                });
            }

            function renderOnline(users) {
                let list = Array.isArray(users) ? [...users] : [];

                if (currentUserId) {
                    const hasMe = list.some(u => {
                        const id = userId(u);
                        return id != null && Number(id) === Number(currentUserId);
                    });

                    if (!hasMe) {
                        list.unshift({ id: currentUserId, name: currentUserName || 'Vous' });
                    }
                }

                const count = list.length;

                if (onlineCountEl) {
                    onlineCountEl.textContent = String(count);
                }

                if (infoCount) {
                    infoCount.textContent = String(count);
                }

                if (infoList) {
                    infoList.innerHTML = '';
                    list
                        .slice(0, 24)
                        .forEach(u => {
                            const id = userId(u);
                            const name = userName(u);
                            const colors = paletteFor(id);
                            const avatarUrl = avatarUrlFor(u);

                            const row = document.createElement('div');
                            row.className = 'flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-[color:rgba(14,165,160,0.08)] transition-colors';

                            if (id != null && currentUserId && Number(id) !== Number(currentUserId)) {
                                row.className += ' cursor-pointer';
                                row.title = 'Envoyer un message privé';
                                row.addEventListener('click', () => {
                                    if (chatMode === 'legacy') {
                                        navigateToConversation(id);
                                    } else {
                                        // In public/dm mode, navigate to DM page
                                        window.location.href = `${dmBaseUrl}/${id}`;
                                    }
                                });
                            }

                            const av = buildAvatarNode({ name, colors, avatarUrl, sizeClass: 'w-9 h-9' });

                            const label = document.createElement('div');
                            label.className = 'text-sm font-medium text-gray-900';
                            label.textContent = name;

                            row.appendChild(av);
                            row.appendChild(label);
                            infoList.appendChild(row);
                        });
                }

                updatePresenceUi(count);
            }

            syncVoiceAvailability();

            function dayKeyFromISO(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toISOString().slice(0, 10);
            }

            function dayLabelFromISO(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }

            function timeLabelFromISO(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            }

            function firstName(name) {
                const n = String(name ?? '').trim();
                if (!n) return '—';
                return n.split(/\s+/)[0] || n;
            }

            function shortDay(iso) {
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return '';
                return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
            }

            let lastDayKey = (function initLastDayFromDom() {
                if (!messagesEl) return '';
                const rows = messagesEl.querySelectorAll('[data-message-row]');
                const last = rows[rows.length - 1];
                return last?.dataset?.dayKey || '';
            })();

            function renderReactionsRow(rowEl, summary) {
                if (!rowEl) return;
                const el = rowEl.querySelector('[data-reactions-row]');
                if (!el) return;

                const items = toArraySummary(summary);
                el.innerHTML = '';
                if (items.length === 0) return;

                for (const r of items) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.reactionChip = '1';
                    btn.dataset.emoji = r.emoji;
                    btn.dataset.messageId = rowEl.dataset.messageId || '';
                    btn.className = 'inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-semibold shadow-sm ' + (r.reacted_by_me ? 'border-teal-300 bg-teal-50 text-teal-800' : 'border-black/10 bg-amber-50/60 text-slate-700');

                    const emo = document.createElement('span');
                    emo.className = 'text-sm leading-none';
                    emo.textContent = r.emoji;
                    const count = document.createElement('span');
                    count.className = 'text-[11px] leading-none';
                    count.textContent = String(r.count);
                    btn.appendChild(emo);
                    btn.appendChild(count);
                    el.appendChild(btn);
                }
            }

            function updateReactionSummary(messageId, summary) {
                const id = Number(messageId || 0);
                if (!id) return;
                const normalized = toArraySummary(summary);
                reactionSummaries.set(id, normalized);
                const row = messagesEl?.querySelector(`[data-message-id="${id}"]`);
                if (row) {
                    row.dataset.reactionSummary = JSON.stringify(normalized);
                    renderReactionsRow(row, normalized);
                }

                // Keep the popover coherent if a reaction changes while it's open.
                scheduleRefreshReactionUsersPopover(id);
            }

            function optimisticToggle(summary, emoji) {
                const e = String(emoji || '').trim();
                if (!e) return summary;
                const items = toArraySummary(summary);
                const idx = items.findIndex((r) => r.emoji === e);
                if (idx >= 0) {
                    const r = items[idx];
                    if (r.reacted_by_me) {
                        r.count = Math.max(0, (Number(r.count) || 0) - 1);
                        r.reacted_by_me = false;
                        if (r.count <= 0) items.splice(idx, 1);
                    } else {
                        r.count = (Number(r.count) || 0) + 1;
                        r.reacted_by_me = true;
                    }
                } else {
                    items.push({ emoji: e, count: 1, reacted_by_me: true });
                }
                return items;
            }

            const reactionsPicker = {
                root: document.getElementById('chatReactionsPicker'),
                panel: document.getElementById('chatReactionsPickerPanel'),
                more: document.getElementById('chatReactionsMore'),
                open: false,
                messageId: 0,
            };

            const reactionBar = {
                root: document.getElementById('chatReactionBar'),
                panel: document.getElementById('chatReactionBarPanel'),
                open: false,
                messageId: 0,
            };

            const actionMenu = {
                root: document.getElementById('chatActionMenu'),
                panel: document.getElementById('chatActionMenuPanel'),
                deleteAllBtn: document.getElementById('chatActionDeleteAll'),
                dmBtn: document.getElementById('chatActionDm'),
                open: false,
                messageId: 0,
            };

            const reactionUsersPopover = {
                root: document.getElementById('chatReactionUsersPopover'),
                panel: document.getElementById('chatReactionUsersPanel'),
                closeBtn: document.getElementById('chatReactionUsersClose'),
                header: document.getElementById('chatReactionUsersHeader'),
                body: document.getElementById('chatReactionUsersBody'),
                open: false,
                messageId: 0,
                emoji: '',
                reqId: 0,
                abort: null,
                refreshTimer: null,
            };

            function closeReactionUsersPopover() {
                reactionUsersPopover.open = false;
                reactionUsersPopover.messageId = 0;
                reactionUsersPopover.emoji = '';
                if (reactionUsersPopover.refreshTimer) {
                    clearTimeout(reactionUsersPopover.refreshTimer);
                    reactionUsersPopover.refreshTimer = null;
                }
                try {
                    reactionUsersPopover.abort?.abort?.();
                } catch {}
                reactionUsersPopover.abort = null;
                reactionUsersPopover.root?.classList.add('hidden');
            }

            function closeReactionsPicker() {
                reactionsPicker.open = false;
                reactionsPicker.messageId = 0;
                reactionsPicker.root?.classList.add('hidden');
            }

            function closeReactionBar() {
                reactionBar.open = false;
                reactionBar.messageId = 0;
                reactionBar.root?.classList.add('hidden');
            }

            function closeActionMenu() {
                actionMenu.open = false;
                actionMenu.messageId = 0;
                actionMenu.root?.classList.add('hidden');
            }

            function closeAllMessagePopovers() {
                closeReactionUsersPopover();
                closeReactionsPicker();
                closeReactionBar();
                closeActionMenu();
            }

            function isAnyMessagePopoverOpen() {
                return !!(reactionUsersPopover.open || reactionsPicker.open || reactionBar.open || actionMenu.open);
            }

            function rectFromPoint(x, y) {
                const px = Number(x || 0);
                const py = Number(y || 0);
                return {
                    left: px,
                    right: px,
                    top: py,
                    bottom: py,
                    width: 0,
                    height: 0,
                };
            }

            function getMessagePlainText(messageId) {
                const row = rowForMessageId(messageId);
                const bubble = bubbleForRow(row);
                if (!row || !bubble) return '';
                if (row?.dataset?.deleted === '1') return '';

                try {
                    const clone = bubble.cloneNode(true);
                    clone.querySelectorAll('button, [data-message-menu]').forEach((el) => el.remove());
                    const raw = String(clone.textContent || '');
                    return raw.replace(/\s+/g, ' ').trim();
                } catch {
                    const raw = String(bubble.textContent || '');
                    return raw.replace(/\s+/g, ' ').trim();
                }
            }

            function appendQuoteToComposer(messageId) {
                const row = rowForMessageId(messageId);
                const author = String(row?.dataset?.authorName || '').trim();
                const body = getMessagePlainText(messageId);
                if (!body) return;

                const quote = `> ${author ? (author + ': ') : ''}${body}`;
                const c = getActiveComposer();
                const ta = c?.textarea;
                if (!ta) return;
                const prev = String(ta.value || '');
                const sep = prev && !prev.endsWith('\n') ? '\n' : '';
                ta.value = prev + sep + quote + '\n';
                ta.dispatchEvent(new Event('input', { bubbles: true }));
                try {
                    ta.focus();
                    const pos = ta.value.length;
                    ta.setSelectionRange(pos, pos);
                } catch {}
            }

            function rowForMessageId(messageId) {
                const id = Number(messageId || 0);
                if (!id) return null;
                return messagesEl?.querySelector(`[data-message-id="${id}"]`) || null;
            }

            function bubbleForRow(row) {
                return row?.querySelector?.('[data-bubble]') || null;
            }

            function positionPanelNearRect(panelEl, anchorRect, preferAbove = true) {
                if (!panelEl || !anchorRect) return;

                const pad = 10;
                panelEl.style.left = '0px';
                panelEl.style.top = '0px';
                const rect = panelEl.getBoundingClientRect();

                const canAbove = anchorRect.top > (rect.height + 18);
                const canBelow = (window.innerHeight - anchorRect.bottom) > (rect.height + 18);

                const useAbove = preferAbove ? canAbove || !canBelow : (!canBelow && canAbove);

                const top = useAbove
                    ? Math.max(pad, Math.min(anchorRect.top - rect.height - 10, window.innerHeight - rect.height - pad))
                    : Math.max(pad, Math.min(anchorRect.bottom + 10, window.innerHeight - rect.height - pad));

                const left = Math.max(
                    pad,
                    Math.min(anchorRect.left + (anchorRect.width / 2) - (rect.width / 2), window.innerWidth - rect.width - pad)
                );

                panelEl.style.left = `${left}px`;
                panelEl.style.top = `${top}px`;
            }

            function syncReactionBarSelection(messageId) {
                const id = Number(messageId || 0);
                if (!id || !reactionBar.panel) return;
                const summary = reactionSummaries.get(id) || [];
                const reacted = new Set((Array.isArray(summary) ? summary : []).filter(r => r?.reacted_by_me).map(r => String(r?.emoji || '').trim()));

                reactionBar.panel.querySelectorAll('[data-reaction-bar-pick]').forEach((btn) => {
                    const emoji = String(btn?.getAttribute('data-reaction-bar-pick') || '').trim();
                    const on = emoji && reacted.has(emoji);
                    btn.classList.toggle('border-teal-300', on);
                    btn.classList.toggle('bg-teal-50', on);
                    btn.classList.toggle('text-teal-800', on);
                    btn.classList.toggle('border-transparent', !on);
                });
            }

            function openReactionBarForMessage(messageId, anchorRect) {
                const id = Number(messageId || 0);
                if (!id || !reactionBar.root || !reactionBar.panel) return;

                const row = rowForMessageId(id);
                if (row?.dataset?.deleted === '1') return;

                if (reactionBar.open && reactionBar.messageId === id) {
                    closeReactionBar();
                    return;
                }

                closeActionMenu();
                closeReactionsPicker();
                closeReactionUsersPopover();

                reactionBar.open = true;
                reactionBar.messageId = id;
                reactionBar.root.classList.remove('hidden');
                positionPanelNearRect(reactionBar.panel, anchorRect, true);
                syncReactionBarSelection(id);
            }

            function openActionMenuForMessage(messageId, anchorRect) {
                const id = Number(messageId || 0);
                if (!id || !actionMenu.root || !actionMenu.panel) return;

                const row = rowForMessageId(id);
                if (row?.dataset?.deleted === '1') return;

                if (actionMenu.open && actionMenu.messageId === id) {
                    closeActionMenu();
                    return;
                }

                closeReactionBar();
                closeReactionsPicker();
                closeReactionUsersPopover();

                actionMenu.open = true;
                actionMenu.messageId = id;
                actionMenu.root.classList.remove('hidden');

                const canDeleteAll = row && String(row.dataset.canDelete || '0') === '1';
                actionMenu.deleteAllBtn?.classList.toggle('hidden', !canDeleteAll);

                // Show "Répondre en privé" only in public mode, and only for messages from someone else.
                try {
                    const senderId = Number(row?.dataset?.userId || 0) || 0;
                    const showDm = chatMode === 'public' && senderId > 0 && (!currentUserId || senderId !== Number(currentUserId));
                    actionMenu.dmBtn?.classList.toggle('hidden', !showDm);
                } catch {
                    actionMenu.dmBtn?.classList.add('hidden');
                }

                positionPanelNearRect(actionMenu.panel, anchorRect, false);
            }

            function positionReactionUsersPopover(anchorRect) {
                if (!reactionUsersPopover.panel || !anchorRect) return;

                positionPanelNearRect(reactionUsersPopover.panel, anchorRect, true);
            }

            function renderReactionUsersPopoverLoading(emoji) {
                if (reactionUsersPopover.header) reactionUsersPopover.header.textContent = `${emoji} …`;
                if (reactionUsersPopover.body) reactionUsersPopover.body.innerHTML = '<div class="text-sm text-slate-600">Chargement…</div>';
            }

            function renderReactionUsersPopoverError(emoji) {
                if (reactionUsersPopover.header) reactionUsersPopover.header.textContent = `${emoji}`;
                if (!reactionUsersPopover.body) return;
                reactionUsersPopover.body.innerHTML = '';

                const wrap = document.createElement('div');
                wrap.className = 'space-y-2';

                const txt = document.createElement('div');
                txt.className = 'text-sm text-red-600';
                txt.textContent = 'Impossible de charger';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-[color:rgba(14,165,160,0.08)]';
                btn.textContent = 'Réessayer';
                btn.addEventListener('click', () => {
                    if (!reactionUsersPopover.open) return;
                    loadReactionUsers(reactionUsersPopover.messageId, reactionUsersPopover.emoji);
                });

                wrap.appendChild(txt);
                wrap.appendChild(btn);
                reactionUsersPopover.body.appendChild(wrap);
            }

            function renderReactionUsersPopover(emoji, users) {
                const list = Array.isArray(users) ? users : [];
                const count = list.length;
                if (reactionUsersPopover.header) reactionUsersPopover.header.textContent = `${emoji} ${count}`;
                if (!reactionUsersPopover.body) return;

                reactionUsersPopover.body.innerHTML = '';

                if (count === 0) {
                    reactionUsersPopover.body.innerHTML = '<div class="text-sm text-slate-600">Aucune réaction</div>';
                    return;
                }

                const isTouch = !!window.matchMedia && window.matchMedia('(hover: none)').matches;

                const grid = document.createElement('div');
                grid.className = 'flex flex-wrap gap-2';

                for (const u of list) {
                    const name = String(u?.name || '—');
                    const url = String(u?.avatar_url || '');

                    const wrap = document.createElement('div');
                    wrap.className = 'flex flex-col items-center gap-1';

                    const avatar = document.createElement('div');
                    avatar.className = 'w-7 h-7 rounded-full overflow-hidden bg-slate-100 border border-black/10 flex items-center justify-center';
                    avatar.title = firstName(name);

                    if (url) {
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = '';
                        img.className = 'w-full h-full object-cover';
                        img.loading = 'lazy';
                        avatar.appendChild(img);
                    } else {
                        const t = document.createElement('div');
                        t.className = 'text-[11px] font-extrabold text-slate-700';
                        t.textContent = initialsFor(name);
                        avatar.appendChild(t);
                    }

                    wrap.appendChild(avatar);

                    if (isTouch) {
                        const label = document.createElement('div');
                        label.className = 'text-[11px] text-slate-700 max-w-[64px] truncate';
                        label.textContent = firstName(name);
                        wrap.appendChild(label);
                    }

                    grid.appendChild(wrap);
                }

                reactionUsersPopover.body.appendChild(grid);
            }

            async function loadReactionUsers(messageId, emoji) {
                const id = Number(messageId || 0);
                const e = String(emoji || '').trim();
                if (!id || !e) return;
                if (!reactionUsersPopover.open || reactionUsersPopover.messageId !== id || reactionUsersPopover.emoji !== e) return;

                try {
                    reactionUsersPopover.abort?.abort?.();
                } catch {}

                const controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                reactionUsersPopover.abort = controller;

                const myReqId = ++reactionUsersPopover.reqId;
                renderReactionUsersPopoverLoading(e);

                const url = `/chat/messages/${id}/reactions/${encodeURIComponent(e)}`;
                try {
                    const resp = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        ...(controller ? { signal: controller.signal } : {}),
                    });

                    if (myReqId !== reactionUsersPopover.reqId) return;
                    if (!reactionUsersPopover.open || reactionUsersPopover.messageId !== id || reactionUsersPopover.emoji !== e) return;

                    if (!resp.ok) {
                        renderReactionUsersPopoverError(e);
                        return;
                    }

                    const json = await resp.json();
                    if (myReqId !== reactionUsersPopover.reqId) return;

                    renderReactionUsersPopover(e, json?.users || []);
                } catch {
                    if (myReqId !== reactionUsersPopover.reqId) return;
                    if (!reactionUsersPopover.open) return;
                    renderReactionUsersPopoverError(e);
                }
            }

            function openReactionUsersPopover(messageId, emoji, anchorRect) {
                const id = Number(messageId || 0);
                const e = String(emoji || '').trim();
                if (!reactionUsersPopover.root || !reactionUsersPopover.panel || !id || !e) return;

                const row = messagesEl?.querySelector(`[data-message-id="${id}"]`);
                if (row?.dataset?.deleted === '1') return;

                if (reactionUsersPopover.open && reactionUsersPopover.messageId === id && reactionUsersPopover.emoji === e) {
                    closeReactionUsersPopover();
                    return;
                }

                closeReactionBar();
                closeActionMenu();
                closeReactionsPicker();

                reactionUsersPopover.open = true;
                reactionUsersPopover.messageId = id;
                reactionUsersPopover.emoji = e;
                reactionUsersPopover.root.classList.remove('hidden');
                positionReactionUsersPopover(anchorRect);
                loadReactionUsers(id, e);
            }

            function scheduleRefreshReactionUsersPopover(messageId) {
                const id = Number(messageId || 0);
                if (!reactionUsersPopover.open || reactionUsersPopover.messageId !== id) return;
                if (reactionUsersPopover.refreshTimer) clearTimeout(reactionUsersPopover.refreshTimer);
                reactionUsersPopover.refreshTimer = setTimeout(() => {
                    reactionUsersPopover.refreshTimer = null;
                    if (!reactionUsersPopover.open) return;
                    loadReactionUsers(reactionUsersPopover.messageId, reactionUsersPopover.emoji);
                }, 350);
            }

            function openReactionsPicker(messageId, x, y) {
                const id = Number(messageId || 0);
                if (!reactionsPicker.root || !reactionsPicker.panel || !id) return;

                const row = messagesEl?.querySelector(`[data-message-id="${id}"]`);
                if (row?.dataset?.deleted === '1') {
                    return;
                }

                closeReactionBar();
                closeActionMenu();
                closeReactionUsersPopover();

                reactionsPicker.messageId = id;
                reactionsPicker.open = true;
                reactionsPicker.more?.classList.add('hidden');
                reactionsPicker.root.classList.remove('hidden');

                const pad = 10;
                reactionsPicker.panel.style.left = '0px';
                reactionsPicker.panel.style.top = '0px';
                const rect = reactionsPicker.panel.getBoundingClientRect();

                const left = Math.max(pad, Math.min(Number(x || 0) - rect.width / 2, window.innerWidth - rect.width - pad));
                const top = Math.max(pad, Math.min(Number(y || 0) - rect.height - 12, window.innerHeight - rect.height - pad));

                reactionsPicker.panel.style.left = `${left}px`;
                reactionsPicker.panel.style.top = `${top}px`;
            }

            reactionUsersPopover.closeBtn?.addEventListener('click', closeReactionUsersPopover);

            async function postToggleReaction(messageId, emoji) {
                const id = Number(messageId || 0);
                const e = String(emoji || '').trim();
                if (!id || !e) return null;

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const url = `/chat/messages/${id}/reactions`;

                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({ emoji: e }),
                    credentials: 'same-origin',
                });

                if (!resp.ok) {
                    const txt = await resp.text().catch(() => '');
                    throw new Error(`toggle failed ${resp.status} ${txt}`);
                }

                return await resp.json();
            }

            reactionsPicker.panel?.addEventListener('click', async (e) => {
                const btn = e.target?.closest('[data-reaction-pick], [data-reaction-more]');
                if (!btn) return;
                if (btn.hasAttribute('data-reaction-more')) {
                    reactionsPicker.more?.classList.toggle('hidden');
                    return;
                }

                const emoji = btn.getAttribute('data-reaction-pick') || '';
                const mid = reactionsPicker.messageId;
                if (!mid || !emoji) return;

                const prev = reactionSummaries.get(mid) || [];
                const optimistic = optimisticToggle(prev, emoji);
                updateReactionSummary(mid, optimistic);
                closeReactionsPicker();

                try {
                    const json = await postToggleReaction(mid, emoji);
                    if (json?.reaction_summary) updateReactionSummary(mid, json.reaction_summary);
                } catch {
                    updateReactionSummary(mid, prev);
                }
            });

            reactionBar.panel?.addEventListener('click', async (e) => {
                const btn = e.target?.closest('[data-reaction-bar-pick], [data-reaction-bar-more]');
                if (!btn) return;
                const mid = reactionBar.messageId;
                if (!mid) return;

                if (btn.hasAttribute('data-reaction-bar-more')) {
                    const row = rowForMessageId(mid);
                    const bubble = bubbleForRow(row);
                    const rect = bubble ? bubble.getBoundingClientRect() : reactionBar.panel.getBoundingClientRect();
                    closeReactionBar();
                    openReactionsPicker(mid, rect.left + rect.width / 2, rect.top);
                    return;
                }

                const emoji = String(btn.getAttribute('data-reaction-bar-pick') || '').trim();
                if (!emoji) return;

                const prev = reactionSummaries.get(mid) || [];
                const optimistic = optimisticToggle(prev, emoji);
                updateReactionSummary(mid, optimistic);
                syncReactionBarSelection(mid);

                try {
                    const json = await postToggleReaction(mid, emoji);
                    if (json?.reaction_summary) updateReactionSummary(mid, json.reaction_summary);
                } catch {
                    updateReactionSummary(mid, prev);
                    syncReactionBarSelection(mid);
                }
            });

            actionMenu.panel?.addEventListener('click', async (e) => {
                const btn = e.target?.closest('[data-action-menu]');
                if (!btn) return;
                const action = String(btn.getAttribute('data-action-menu') || '').trim();
                const mid = actionMenu.messageId;
                if (!mid) return;

                if (action === 'dm') {
                    closeActionMenu();
                    const row = rowForMessageId(mid);
                    const uid = Number(row?.dataset?.userId || 0) || 0;
                    if (uid > 0 && (!currentUserId || uid !== Number(currentUserId))) {
                        window.location.href = `${dmBaseUrl}/${encodeURIComponent(String(uid))}`;
                    }
                    return;
                }

                if (action === 'copy') {
                    const txt = getMessagePlainText(mid);
                    await copyToClipboard(txt);
                    closeActionMenu();
                    return;
                }

                if (action === 'reply') {
                    closeActionMenu();
                    if (chatMode === 'legacy') {
                        try {
                            const row = rowForMessageId(mid);
                            const t = String(row?.dataset?.audienceType || 'all');
                            if (t === 'subset') {
                                let ids = [];
                                try { ids = JSON.parse(row?.dataset?.audienceUserIds || '[]'); } catch {}
                                setAudienceUserIds(ids);
                            } else {
                                resetAudience();
                            }
                        } catch {
                            resetAudience();
                        }
                    }
                    appendQuoteToComposer(mid);
                    return;
                }

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const headers = {
                    'Accept': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                };

                if (action === 'delete_me') {
                    const ok = confirm('Supprimer ce message pour vous ?');
                    if (!ok) return;
                    closeActionMenu();
                    try {
                        const resp = await fetch(`/chat/messages/${mid}/me`, { method: 'DELETE', headers, credentials: 'same-origin' });
                        if (resp.ok) {
                            const row = messagesEl?.querySelector(`[data-message-id="${mid}"]`);
                            if (row) row.style.display = 'none';
                        }
                    } catch {}
                    return;
                }

                if (action === 'delete_all') {
                    const ok = confirm('Supprimer ce message pour tout le monde ?');
                    if (!ok) return;
                    closeActionMenu();
                    try {
                        const resp = await fetch(`/chat/messages/${mid}`, { method: 'DELETE', headers, credentials: 'same-origin' });
                        if (resp.ok) {
                            markMessageDeletedForAll(mid);
                        }
                    } catch {}
                    return;
                }
            });

            

            // Bubble triggers: right-click (desktop) + long-press (mobile)
            messagesEl?.addEventListener('contextmenu', (e) => {
                const bubble = e.target?.closest('[data-bubble]');
                if (!bubble) return;
                e.preventDefault();
                const row = bubble.closest('[data-message-row]');
                const mid = row?.dataset?.messageId;
                openActionMenuForMessage(mid, rectFromPoint(e.clientX, e.clientY));
            });

            let longPressTimer = null;
            let longPressStart = null;

            messagesEl?.addEventListener('pointerdown', (e) => {
                if (e.pointerType !== 'touch') return;
                const bubble = e.target?.closest('[data-bubble]');
                if (!bubble) return;
                longPressStart = { x: e.clientX, y: e.clientY };
                if (longPressTimer) clearTimeout(longPressTimer);
                longPressTimer = setTimeout(() => {
                    const row = bubble.closest('[data-message-row]');
                    const mid = row?.dataset?.messageId;
                    const rect = bubble.getBoundingClientRect();
                    openActionMenuForMessage(mid, rect);
                }, 480);
            });

            messagesEl?.addEventListener('pointermove', (e) => {
                if (!longPressStart || !longPressTimer) return;
                const dx = Math.abs(e.clientX - longPressStart.x);
                const dy = Math.abs(e.clientY - longPressStart.y);
                if (dx > 10 || dy > 10) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }
            });

            const cancelLongPress = () => {
                if (longPressTimer) clearTimeout(longPressTimer);
                longPressTimer = null;
                longPressStart = null;
            };

            messagesEl?.addEventListener('pointerup', cancelLongPress);
            messagesEl?.addEventListener('pointercancel', cancelLongPress);

            // Message clicks
            messagesEl?.addEventListener('click', (e) => {
                const menuBtn = e.target?.closest('[data-message-menu]');
                if (menuBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const mid = menuBtn.getAttribute('data-message-id') || menuBtn.closest('[data-message-row]')?.dataset?.messageId;
                    const rect = menuBtn.getBoundingClientRect();
                    openActionMenuForMessage(mid, rect);
                    return;
                }

                const trigger = e.target?.closest('[data-reaction-trigger]');
                if (trigger) {
                    e.preventDefault();
                    e.stopPropagation();
                    const mid = trigger.getAttribute('data-message-id') || trigger.closest('[data-message-row]')?.dataset?.messageId;
                    const row = trigger.closest('[data-message-row]') || rowForMessageId(mid);
                    const bubble = bubbleForRow(row);
                    const rect = bubble ? bubble.getBoundingClientRect() : trigger.getBoundingClientRect();
                    openReactionBarForMessage(mid, rect);
                    return;
                }

                const chip = e.target?.closest('[data-reaction-chip]');
                if (chip) {
                    const mid = chip.getAttribute('data-message-id') || chip.closest('[data-message-row]')?.dataset?.messageId;
                    const emoji = chip.getAttribute('data-emoji') || '';
                    const rect = chip.getBoundingClientRect();
                    openReactionUsersPopover(mid, emoji, rect);
                    return;
                }

                const bubble = e.target?.closest('[data-bubble]');
                if (!bubble) return;

                // Avoid stealing clicks from interactive elements inside bubbles.
                if (e.target?.closest('a, button, input, textarea, select, [role="button"], [data-chat-media-open]')) return;

                const row = bubble.closest('[data-message-row]');
                const mid = row?.dataset?.messageId;
                const rect = bubble.getBoundingClientRect();
                openReactionBarForMessage(mid, rect);
            });

            // Global close behaviors (non-blocking popovers)
            document.addEventListener('pointerdown', (e) => {
                if (!isAnyMessagePopoverOpen()) return;
                const t = e.target;
                const inside = (
                    (reactionUsersPopover.open && reactionUsersPopover.panel?.contains(t)) ||
                    (reactionsPicker.open && reactionsPicker.panel?.contains(t)) ||
                    (reactionBar.open && reactionBar.panel?.contains(t)) ||
                    (actionMenu.open && actionMenu.panel?.contains(t))
                );
                if (inside) return;
                closeAllMessagePopovers();
            }, true);

            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (!isAnyMessagePopoverOpen()) return;
                e.preventDefault();
                closeAllMessagePopovers();
            });

            const closeOnScroll = () => {
                if (!isAnyMessagePopoverOpen()) return;
                closeAllMessagePopovers();
            };
            window.addEventListener('scroll', closeOnScroll, { passive: true, capture: true });
            messagesEl?.addEventListener('scroll', closeOnScroll, { passive: true });

            // Render existing DOM reaction summaries (server-rendered)
            try {
                messagesEl?.querySelectorAll('[data-message-row]').forEach((row) => {
                    const mid = Number(row?.dataset?.messageId || 0);
                    let rs = [];
                    try {
                        rs = JSON.parse(row?.dataset?.reactionSummary || '[]');
                    } catch {
                        rs = reactionSummaries.get(mid) || [];
                    }
                    if (mid) reactionSummaries.set(mid, toArraySummary(rs));
                    renderReactionsRow(row, reactionSummaries.get(mid) || []);
                });
            } catch {}

            function appendDaySeparator(dayKey, label) {
                if (!messagesEl || !dayKey || dayKey === lastDayKey) return;

                const sep = document.createElement('div');
                sep.className = 'py-2 flex justify-center';
                sep.dataset.daySeparator = '1';
                sep.dataset.dayKey = dayKey;

                const pill = document.createElement('div');
                pill.className = 'text-xs text-slate-500 bg-white border border-black/10 rounded-full px-3 py-1';
                pill.textContent = label;

                sep.appendChild(pill);
                messagesEl.appendChild(sep);
                lastDayKey = dayKey;
            }

            function appendMessage(payload) {
                if (!messagesEl) return;

                if (conversationUserId > 0 && !isPayloadInConversation(payload, conversationUserId)) {
                    return false;
                }

                const id = payload?.id ?? null;
                if (id != null && messagesEl.querySelector(`[data-message-id="${id}"]`)) {
                    return false;
                }

                const wasAtBottom = isNearBottom();
                const uid = payload?.user?.id ?? payload?.user_id ?? null;
                const name = payload?.user?.name ?? '—';
                const avatarUrl = avatarUrlFor(payload?.user || payload || {});
                const isDeleted = !!payload?.is_deleted || !!payload?.deleted_for_all || !!payload?.deleted_for_all_at;
                const body = isDeleted ? '' : (payload?.body ?? '');
                const att = isDeleted ? null : parseAttachmentBody(body);
                const createdISO = payload?.created_at ?? null;
                const whenTime = createdISO ? timeLabelFromISO(createdISO) : '';

                const dk = createdISO ? dayKeyFromISO(createdISO) : '';
                const dl = createdISO ? dayLabelFromISO(createdISO) : '';
                appendDaySeparator(dk, dl);

                const isMe = currentUserId && uid && Number(uid) === Number(currentUserId);
                const colors = paletteFor(uid);
                const initials = initialsFor(name);

                hideEmptyState();

                // Grouping: same author as previous message => hide meta + move avatar to new last message.
                const rows = messagesEl.querySelectorAll('[data-message-row]');
                const lastRow = rows[rows.length - 1] || null;
                const lastUserId = lastRow ? Number(lastRow.dataset.userId || 0) : null;
                const sameAuthorAsPrev = lastRow && uid != null && Number(uid) === Number(lastUserId);

                if (sameAuthorAsPrev) {
                    const lastAvatar = lastRow.querySelector('[data-avatar]');
                    if (lastAvatar) {
                        lastAvatar.classList.add('invisible');
                    }
                }

                const outer = document.createElement('div');
                outer.className = `flex ${isMe ? 'justify-end' : 'justify-start'} group`;
                outer.dataset.messageRow = '1';
                outer.dataset.userId = uid != null ? String(uid) : '';
                outer.dataset.dayKey = dk;
                outer.dataset.deleted = isDeleted ? '1' : '0';
                if (id != null) outer.dataset.messageId = String(id);

                const audienceType = String(payload?.audience_type || payload?.audience?.type || 'all');
                const audienceIds = normalizeUserIds(payload?.audience_user_ids || payload?.audience?.user_ids || []);
                outer.dataset.audienceType = audienceType;
                outer.dataset.audienceUserIds = JSON.stringify(audienceIds);

                const initialRs = isDeleted ? [] : toArraySummary(payload?.reaction_summary || []);
                outer.dataset.reactionSummary = JSON.stringify(initialRs);
                if (id != null) {
                    reactionSummaries.set(Number(id), initialRs);
                }

                const width = document.createElement('div');
                width.className = att
                    ? 'w-[clamp(240px,72vw,420px)] max-w-[92vw] sm:w-[clamp(320px,48vw,520px)] sm:max-w-[520px]'
                    : 'max-w-[72%] sm:max-w-[68%]';

                if (!sameAuthorAsPrev) {
                    const meta = document.createElement('div');
                    meta.className = `mb-1 flex items-center gap-1.5 text-xs ${isMe ? 'justify-end' : ''}`;

                    const nameEl = document.createElement('span');
                    nameEl.className = 'font-semibold text-[color:var(--chat-text)]/90';
                    nameEl.textContent = firstName(name);

                    const dotEl = document.createElement('span');
                    dotEl.className = 'text-[color:var(--chat-meta)]';
                    dotEl.textContent = '·';

                    const timeEl = document.createElement('span');
                    timeEl.className = 'text-[0.7rem] font-semibold text-[color:var(--chat-meta)]';
                    timeEl.textContent = whenTime;

                    meta.appendChild(nameEl);
                    meta.appendChild(dotEl);
                    meta.appendChild(timeEl);
                    width.appendChild(meta);
                }

                ensureAudiencePill(outer, payload);

                const row = document.createElement('div');
                row.className = `flex items-end gap-2 ${isMe ? 'flex-row-reverse' : ''}`;

                const avatarWrap = document.createElement('div');
                avatarWrap.className = 'shrink-0';
                avatarWrap.dataset.avatar = '1';
                const avatarNode = buildAvatarNode({ name, colors, avatarUrl, sizeClass: 'w-8 h-8 sm:w-9 sm:h-9' });
                avatarWrap.appendChild(avatarNode);

                const wrapper = document.createElement('div');
                if (att) {
                    wrapper.className = 'relative p-0 border-0 bg-transparent';
                } else {
                    if (isMe) {
                        wrapper.className = 'relative px-4 py-3 border text-white rounded-2xl rounded-br-md';
                        wrapper.style.backgroundColor = '#0F172A';
                        wrapper.style.borderColor = 'rgba(15,23,42,0.35)';
                        wrapper.style.boxShadow = '0 8px 18px rgba(15,23,42,0.22)';
                    } else {
                        wrapper.className = 'relative px-4 py-3 border rounded-2xl rounded-bl-md';
                        wrapper.style.backgroundColor = '#F3F1EA';
                        wrapper.style.color = '#1B140B';
                        wrapper.style.borderColor = '#E4DED2';
                        wrapper.style.boxShadow = '0 1px 0 rgba(0,0,0,.03), 0 6px 18px rgba(0,0,0,.04)';
                    }
                }
                wrapper.dataset.bubble = '1';

                if (!isDeleted) {
                    // Desktop discoverability: hover button 🙂
                    const reactBtn = document.createElement('button');
                    reactBtn.type = 'button';
                    reactBtn.className = `hidden sm:inline-flex absolute -top-3 ${isMe ? '-left-3' : '-right-3'} w-8 h-8 items-center justify-center rounded-full border border-black/10 bg-white text-slate-700 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity`;
                    reactBtn.dataset.reactionTrigger = '1';
                    if (id != null) reactBtn.dataset.messageId = String(id);
                    reactBtn.setAttribute('aria-label', 'Réagir');
                    reactBtn.title = 'Réagir';
                    reactBtn.textContent = '🙂';
                    wrapper.appendChild(reactBtn);
                }
                const bodyEl = document.createElement('div');

                if (isDeleted) {
                    bodyEl.className = `text-sm italic ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                    bodyEl.textContent = 'Message supprimé';
                } else if (att) {
                    bodyEl.className = 'text-sm';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'group block w-full text-left rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/10 focus-visible:ring-offset-2 focus-visible:ring-offset-white';
                    btn.style.webkitTapHighlightColor = 'transparent';
                    btn.dataset.chatMediaOpen = '1';
                    btn.dataset.url = String(att.url || '#');
                    btn.dataset.openUrl = String(att.open_url || '');
                    btn.dataset.type = String(att.media_type || '');

                    const rawW = Number(att.width || 0);
                    const rawH = Number(att.height || 0);
                    btn.dataset.mediaW = String(Number.isFinite(rawW) ? rawW : 0);
                    btn.dataset.mediaH = String(Number.isFinite(rawH) ? rawH : 0);
                    const isLandscape = rawW > 0 && rawH > 0 ? rawW > rawH : false;
                    const isVideo = String(att.media_type) === 'video';
                    const mediaH = isVideo
                        ? (isLandscape ? 'h-[clamp(10rem,30vh,36vh)]' : 'h-[clamp(14rem,46vh,52vh)]')
                        : (isLandscape ? 'h-[clamp(10rem,28vh,36vh)]' : 'h-[clamp(14rem,40vh,52vh)]');

                    const card = document.createElement('div');
                    card.className = 'overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm';

                    const thumb = String(att.thumb_url || '');
                    const nameLabel = String(att.name || (att.media_type === 'video' ? 'Vidéo' : 'Photo'));
                    btn.dataset.name = nameLabel;
                    btn.dataset.thumb = thumb;

                    const mediaBox = document.createElement('div');
                    mediaBox.className = `relative w-full ${mediaH} bg-slate-100 animate-pulse`;
                    mediaBox.dataset.chatMediaCard = '1';

                    if (thumb) {
                        const img = document.createElement('img');
                        img.src = thumb;
                        img.alt = nameLabel;
                        img.loading = 'lazy';
                        img.className = 'block w-full h-full object-cover';
                        img.dataset.chatMediaThumb = '1';
                        // When the image loads, the bubble height changes; if we're at the bottom,
                        // keep it pinned so the new upload looks "properly placed".
                        img.addEventListener('load', () => {
                            mediaBox.classList.remove('animate-pulse');
                            if (isPinnedToBottom() || isNearBottom()) scrollToBottom({ force: true });
                        }, { once: true });
                        mediaBox.appendChild(img);
                    } else {
                        const ph = document.createElement('div');
                        ph.className = `w-full h-full flex items-center justify-center text-xs ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                        ph.textContent = nameLabel;
                        mediaBox.appendChild(ph);
                    }

                    const expand = document.createElement('div');
                    expand.className = 'absolute top-2 right-2 pointer-events-none opacity-60 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity';
                    expand.innerHTML = '<div class="w-9 h-9 rounded-full bg-black/35 backdrop-blur flex items-center justify-center text-white"><i class="ph ph-arrows-out" aria-hidden="true"></i></div>';
                    mediaBox.appendChild(expand);

                    if (String(att.media_type) === 'video') {
                        const overlay = document.createElement('div');
                        overlay.className = 'absolute inset-0 flex items-center justify-center pointer-events-none';
                        const pill = document.createElement('div');
                        pill.className = 'w-11 h-11 rounded-full bg-black/35 backdrop-blur-sm flex items-center justify-center text-white text-lg';
                        pill.textContent = '▶';
                        overlay.appendChild(pill);
                        mediaBox.appendChild(overlay);
                    }

                    card.appendChild(mediaBox);

                    const caption = String(att.caption || att.text || '').trim();
                    if (caption) {
                        const cap = document.createElement('div');
                        cap.className = 'px-4 py-3 text-sm text-slate-600 bg-white';
                        cap.textContent = caption;
                        card.appendChild(cap);
                    }

                    btn.appendChild(card);
                    bodyEl.appendChild(btn);
                } else {
                    const link = parseLinkCardBody(body);
                    if (link) {
                        const card = document.createElement('div');
                        card.className = `rounded-xl border border-black/10 ${isMe ? 'bg-white/10' : 'bg-white'} p-3`;

                        const t = document.createElement('div');
                        t.className = `text-sm font-semibold ${isMe ? 'text-white' : 'text-gray-900'}`;
                        t.textContent = String(link.title || 'Lien');

                        const d = document.createElement('div');
                        d.className = `mt-0.5 text-xs ${isMe ? 'text-white/80' : 'text-slate-500'}`;
                        d.textContent = String(link.domain || '');

                        const actions = document.createElement('div');
                        actions.className = 'mt-3 flex items-center gap-2';

                        const join = document.createElement('a');
                        join.href = String(link.url || '#');
                        join.target = '_blank';
                        join.rel = 'noopener';
                        join.className = 'inline-flex items-center justify-center rounded-full bg-slate-900 text-white px-3 py-1.5 text-xs font-semibold';
                        join.textContent = 'Rejoindre';

                        const copy = document.createElement('button');
                        copy.type = 'button';
                        copy.className = 'inline-flex items-center justify-center rounded-full border border-black/10 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700';
                        copy.textContent = 'Copier le lien';
                        copy.dataset.copyLink = String(link.url || '');

                        actions.appendChild(join);
                        actions.appendChild(copy);
                        card.appendChild(t);
                        card.appendChild(d);
                        card.appendChild(actions);

                        bodyEl.appendChild(card);
                    } else {
                        bodyEl.className = 'text-sm whitespace-pre-wrap';
                        bodyEl.textContent = body;
                    }
                }
                wrapper.appendChild(bodyEl);

                row.appendChild(avatarWrap);
                row.appendChild(wrapper);
                width.appendChild(row);

                const reactionsRow = document.createElement('div');
                reactionsRow.className = `mt-1 flex flex-wrap gap-1.5 ${isMe ? 'justify-end' : 'justify-start'}`;
                reactionsRow.dataset.reactionsRow = '1';
                width.appendChild(reactionsRow);

                outer.appendChild(width);
                messagesEl.appendChild(outer);

                renderReactionsRow(outer, reactionSummaries.get(Number(id)) || []);

                if (wasAtBottom || isPinnedToBottom()) {
                    scrollToBottom({ force: true });
                }
                syncScrollToBottomButton();
                return true;
            }

            function markMessageDeletedForAll(messageId) {
                const id = Number(messageId || 0);
                if (!id || !messagesEl) return;
                const row = messagesEl.querySelector(`[data-message-row][data-message-id="${id}"]`);
                if (!row) return;
                row.dataset.deleted = '1';
                reactionSummaries.set(id, []);

                const isMe = currentUserId && Number(row.dataset.userId || 0) === Number(currentUserId);
                const bubble = row.querySelector('[data-bubble]');
                if (bubble) {
                    bubble.className = `relative px-4 py-3 border ${isMe
                        ? 'bg-[color:var(--chat-primary)] text-white border-[color:rgba(14,165,160,0.35)] shadow-[0_8px_18px_rgba(14,165,160,0.22)] rounded-2xl rounded-br-md'
                        : 'bg-[color:var(--chat-bubble-other-bg)] text-[color:var(--chat-text)] border-[color:var(--chat-bubble-other-border)] shadow-[var(--chat-bubble-shadow)] rounded-2xl rounded-bl-md'}`;
                    bubble.innerHTML = '';
                    const txt = document.createElement('div');
                    txt.className = `text-sm italic ${isMe ? 'text-white/80' : 'text-[color:var(--chat-meta)]'}`;
                    txt.textContent = 'Message supprimé';
                    bubble.appendChild(txt);
                }

                const rr = row.querySelector('[data-reactions-row]');
                if (rr) rr.innerHTML = '';
            }

            function effectiveAudience() {
                if (chatMode === 'dm') {
                    return {
                        type: 'subset',
                        userIds: dmUserId > 0 ? [dmUserId] : [],
                    };
                }
                if (chatMode === 'public') {
                    return {
                        type: 'all',
                        userIds: [],
                    };
                }
                return {
                    type: audienceState.type,
                    userIds: audienceState.type === 'subset' ? audienceState.userIds : [],
                };
            }

            function appendLocalMessage(tempId, body) {
                const a = effectiveAudience();
                const payload = {
                    id: tempId,
                    body,
                    created_at: new Date().toISOString(),
                    user: { id: currentUserId, name: currentUserName || 'Vous' },
                    audience_type: a.type,
                    audience_user_ids: a.userIds,
                };
                const ok = appendMessage(payload);
                if (!ok) return;
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                if (row) {
                    row.dataset.localBody = String(body || '');
                }
                const bubble = row?.querySelector('[data-bubble]');
                if (bubble) {
                    const status = document.createElement('div');
                    status.className = 'mt-1 text-right text-xs opacity-70';
                    status.dataset.localStatus = '1';
                    status.textContent = 'Envoi…';
                    bubble.appendChild(status);
                }
            }

            function markLocalFailed(tempId, errorMessage) {
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                const bubble = row?.querySelector('[data-bubble]');
                if (!bubble) return;

                bubble.classList.add('border-red-300');

                const status = bubble.querySelector('[data-local-status]');
                if (status) {
                    status.textContent = 'Échec';
                    status.classList.add('text-red-200');
                }

                const actions = document.createElement('div');
                actions.className = 'mt-2 flex items-center justify-end gap-2';

                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'inline-flex items-center justify-center rounded-full bg-white text-slate-900 px-3 py-1.5 text-xs font-semibold';
                retry.textContent = 'Réessayer';
                retry.dataset.retryTempId = tempId;

                const copy = document.createElement('button');
                copy.type = 'button';
                copy.className = 'inline-flex items-center justify-center rounded-full border border-white/30 bg-transparent text-white px-3 py-1.5 text-xs font-semibold';
                copy.textContent = 'Copier';
                copy.dataset.copyText = String(row?.dataset?.localBody || '');

                actions.appendChild(retry);
                actions.appendChild(copy);
                bubble.appendChild(actions);

                if (errorMessage) {
                    const hint = document.createElement('div');
                    hint.className = 'mt-1 text-right text-xs text-red-200/80';
                    hint.textContent = String(errorMessage);
                    bubble.appendChild(hint);
                }
            }

            let attachSheetCloseTimer = null;

            function setAttachSheetOpen(open) {
                if (!attachSheet) return;

                const panel = document.getElementById('chatAttachPanel');
                const reduceMotion = (() => {
                    try { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
                    catch { return false; }
                })();
                const DURATION_MS = 220;

                if (attachSheetCloseTimer) {
                    clearTimeout(attachSheetCloseTimer);
                    attachSheetCloseTimer = null;
                }

                if (open) {
                    attachSheet.classList.remove('hidden');
                    attachSheet.setAttribute('aria-hidden', 'false');

                    // Ensure initial state then animate in.
                    if (attachBackdrop) {
                        attachBackdrop.classList.add('opacity-0');
                        attachBackdrop.classList.remove('opacity-100');
                    }
                    if (panel) {
                        panel.classList.add('opacity-0', 'translate-y-6');
                        panel.classList.remove('opacity-100', 'translate-y-0');
                    }

                    requestAnimationFrame(() => {
                        if (attachBackdrop) {
                            attachBackdrop.classList.remove('opacity-0');
                            attachBackdrop.classList.add('opacity-100');
                        }
                        if (panel) {
                            panel.classList.remove('opacity-0', 'translate-y-6');
                            panel.classList.add('opacity-100', 'translate-y-0');
                        }
                    });

                    refreshQuota().catch(() => {});
                    return;
                }

                attachSheet.setAttribute('aria-hidden', 'true');
                if (attachBackdrop) {
                    attachBackdrop.classList.add('opacity-0');
                    attachBackdrop.classList.remove('opacity-100');
                }
                if (panel) {
                    panel.classList.add('opacity-0', 'translate-y-6');
                    panel.classList.remove('opacity-100', 'translate-y-0');
                }

                if (reduceMotion) {
                    attachSheet.classList.add('hidden');
                } else {
                    attachSheetCloseTimer = setTimeout(() => {
                        attachSheet.classList.add('hidden');
                        attachSheetCloseTimer = null;
                    }, DURATION_MS);
                }
            }

            function formatBytes(bytes) {
                const b = Number(bytes || 0);
                if (!Number.isFinite(b) || b <= 0) return '0 B';
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                let v = b;
                let i = 0;
                while (v >= 1024 && i < units.length - 1) {
                    v /= 1024;
                    i++;
                }
                const txt = (v >= 10 || i === 0) ? v.toFixed(0) : v.toFixed(1);
                return `${txt} ${units[i]}`;
            }

            async function refreshQuota() {
                if (!quotaEl) return;
                const token = getCsrfToken();
                if (!token) return;

                const res = await fetch(quotaUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                });
                if (!res.ok) return;
                const data = await res.json();
                const remaining = Number(data?.remaining_bytes ?? 0);
                quotaEl.textContent = `Espace restant : ${formatBytes(remaining)}`;
            }

            async function postJson(url, payload) {
                const token = getCsrfToken();
                if (!token) throw new Error('missing_csrf');
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(payload || {}),
                });
                const txt = await res.text();
                let json = null;
                try { json = txt ? JSON.parse(txt) : null; } catch (e) {}
                if (!res.ok) {
                    const msg = (json && json.message) ? String(json.message) : `Erreur upload (${res.status})`;
                    const err = new Error(msg);
                    err.status = res.status;
                    err.data = json;
                    throw err;
                }
                return json;
            }

            async function postForm(url, formData) {
                const token = getCsrfToken();
                if (!token) throw new Error('missing_csrf');
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: formData,
                });
                const txt = await res.text();
                let json = null;
                try { json = txt ? JSON.parse(txt) : null; } catch (e) {}
                if (!res.ok) {
                    const msg = (json && json.message) ? String(json.message) : `Erreur upload (${res.status})`;
                    const err = new Error(msg);
                    err.status = res.status;
                    err.data = json;
                    throw err;
                }
                return json;
            }

            function waitMedia(el, eventName, timeoutMs) {
                return new Promise((resolve, reject) => {
                    const t = setTimeout(() => {
                        try { el.removeEventListener(eventName, on); } catch {}
                        reject(new Error('timeout:' + eventName));
                    }, timeoutMs);
                    const on = () => {
                        clearTimeout(t);
                        resolve();
                    };
                    el.addEventListener(eventName, on, { once: true });
                });
            }

            async function buildVideoPosterBlob(file) {
                try {
                    if (!file) return null;
                    if (!('URL' in window) || !('createObjectURL' in URL)) return null;

                    const url = URL.createObjectURL(file);
                    try {
                        const video = document.createElement('video');
                        video.preload = 'metadata';
                        video.muted = true;
                        video.playsInline = true;
                        video.src = url;

                        await waitMedia(video, 'loadedmetadata', 8000);

                        const duration = Number(video.duration || 0);
                        const target = (Number.isFinite(duration) && duration > 2) ? 1 : 0;
                        try { video.currentTime = target; } catch (e) { /* ignore */ }
                        try {
                            await waitMedia(video, 'seeked', 8000);
                        } catch {
                            // Some browsers/devices don't fire seeked reliably for blobs.
                            try { await waitMedia(video, 'loadeddata', 8000); } catch {}
                        }

                        const w = video.videoWidth || 0;
                        const h = video.videoHeight || 0;
                        if (!w || !h) return null;

                        const maxW = 640;
                        const scale = Math.min(1, maxW / w);
                        const cw = Math.max(1, Math.round(w * scale));
                        const ch = Math.max(1, Math.round(h * scale));

                        const canvas = document.createElement('canvas');
                        canvas.width = cw;
                        canvas.height = ch;
                        const ctx = canvas.getContext('2d');
                        if (!ctx) return null;
                        ctx.drawImage(video, 0, 0, cw, ch);

                        const blob = await new Promise((resolve) => {
                            canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75);
                        });

                        const out = blob || null;
                        if (!out) return null;
                        return { blob: out, width: w, height: h };
                    } finally {
                        try { URL.revokeObjectURL(url); } catch (e) {}
                    }
                } catch {
                    return null;
                }
            }

            async function buildImageMeta(file) {
                try {
                    if (!file) return null;
                    if (!('URL' in window) || !('createObjectURL' in URL)) return null;

                    const url = URL.createObjectURL(file);
                    try {
                        const img = new Image();
                        img.decoding = 'async';
                        img.loading = 'eager';
                        img.src = url;

                        await new Promise((resolve, reject) => {
                            const t = setTimeout(() => reject(new Error('timeout:image_meta')), 6000);
                            img.onload = () => {
                                clearTimeout(t);
                                resolve();
                            };
                            img.onerror = () => {
                                clearTimeout(t);
                                reject(new Error('error:image_meta'));
                            };
                        });

                        const w = Number(img.naturalWidth || 0);
                        const h = Number(img.naturalHeight || 0);
                        if (!w || !h) return null;
                        return { width: w, height: h };
                    } finally {
                        try { URL.revokeObjectURL(url); } catch {}
                    }
                } catch {
                    return null;
                }
            }

            function putWithProgress(url, blobOrFile, contentType, onProgress) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', url, true);
                    if (contentType) xhr.setRequestHeader('Content-Type', contentType);

                    xhr.upload.onprogress = (evt) => {
                        if (!evt.lengthComputable) return;
                        if (typeof onProgress === 'function') {
                            onProgress(evt.loaded, evt.total);
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve({
                                status: xhr.status,
                                etag: xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag') || null,
                            });
                        } else {
                            reject(new Error(`Upload failed (${xhr.status})`));
                        }
                    };
                    xhr.onerror = () => reject(new Error('network_error'));
                    xhr.send(blobOrFile);
                });
            }

            function appendUploadPlaceholder(name) {
                const tempId = `upload-${Date.now()}`;
                const payload = {
                    id: tempId,
                    body: `⏳ Envoi de ${name}… 0%`,
                    created_at: new Date().toISOString(),
                    user: { id: currentUserId, name: currentUserName || 'Vous' },
                };
                appendMessage(payload);
                return tempId;
            }

            function updateUploadPlaceholder(tempId, pct) {
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                if (!row) return;
                const b = row.querySelector('[data-bubble] .text-sm') || row.querySelector('[data-bubble]');
                const bodyEl = row.querySelector('[data-bubble] .text-sm');
                const bubble = row.querySelector('[data-bubble]');
                // find the first body element inside wrapper
                const bodyDiv = bubble?.querySelector('div');
                if (bodyDiv) bodyDiv.textContent = `⏳ Envoi… ${pct}%`;
            }

            function removeUploadPlaceholder(tempId) {
                const row = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                if (row) row.remove();
            }

            function uploadAttachment(file) {
                if (!file) return;

                const token = getCsrfToken();
                if (!token) {
                    alert('Session expirée. Recharge la page.');
                    return;
                }

                const size = Number(file.size || 0);
                if (size > MAX_UPLOAD_BYTES) {
                    alert('Fichier trop volumineux (max 2 Go).');
                    return;
                }

                const mime = String(file.type || 'application/octet-stream');
                const kind = mime.startsWith('video/') ? 'video' : 'photo';

                const effAud = effectiveAudience();

                const tempId = appendUploadPlaceholder(file.name || 'fichier');
                setAttachSheetOpen(false);

                (async () => {
                    try {
                        let finalized = null;
                        const posterPromise = (kind === 'video') ? buildVideoPosterBlob(file) : Promise.resolve(null);
                        const imageMetaPromise = (kind === 'photo') ? buildImageMeta(file) : Promise.resolve(null);

                        if (size > MULTIPART_THRESHOLD_BYTES) {
                            let init = null;
                            try {
                                init = await postJson(mpInitUrl, {
                                    filename: file.name || 'file',
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                });
                            } catch (e) {
                                init = null;
                            }

                            if (!init || !init.upload_id || !init.key || !init.part_size || !Array.isArray(init.parts) || init.parts.length === 0) {
                                const presign = await postJson(presignUrl, {
                                    filename: file.name || 'file',
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                });

                                const uploadUrl = String(presign?.upload_url || '');
                                const key = String(presign?.key || '');
                                const storageDisk = String(presign?.storage_disk || 'r2');
                                if (!uploadUrl || !key) throw new Error('Presign invalide.');

                                await putWithProgress(uploadUrl, file, mime, (loaded, total) => {
                                    const pct = Math.max(0, Math.min(100, Math.round((loaded / (total || size)) * 100)));
                                    updateUploadPlaceholder(tempId, pct);
                                });

                                const finPublicUrl = presign?.public_url || null;
                                if (kind === 'video') {
                                    const fd = new FormData();
                                    fd.append('key', key);
                                    if (finPublicUrl) fd.append('public_url', String(finPublicUrl));
                                    fd.append('mime', mime);
                                    fd.append('size', String(size));
                                    fd.append('kind', kind);
                                    fd.append('context', 'chat');
                                    fd.append('storage_disk', storageDisk);
                                    if (file.name) fd.append('filename', String(file.name));
                                    fd.append('chat_thread_id', 'default');

                                    fd.append('audience_type', String(effAud.type || 'all'));
                                    (Array.isArray(effAud.userIds) ? effAud.userIds : []).forEach((id) => {
                                        const v = Number(id || 0) || 0;
                                        if (v > 0) fd.append('audience_user_ids[]', String(v));
                                    });

                                    const posterInfo = await posterPromise;
                                    const posterBlob = posterInfo && typeof posterInfo === 'object' ? posterInfo.blob : null;
                                    if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');

                                    finalized = await postForm(finalizeUrl, fd);
                                } else {
                                    finalized = await postJson(finalizeUrl, {
                                        key,
                                        public_url: finPublicUrl,
                                        mime,
                                        size,
                                        kind,
                                        context: 'chat',
                                        storage_disk: storageDisk,
                                        filename: file.name || null,
                                        chat_thread_id: 'default',
                                        audience_type: effAud.type,
                                        audience_user_ids: effAud.type === 'subset' ? (effAud.userIds || []) : [],
                                    });
                                }
                            } else {

                            const partSize = Number(init?.part_size || 0);
                            const parts = Array.isArray(init?.parts) ? init.parts : [];
                            const uploadId = String(init?.upload_id || '');
                            const key = String(init?.key || '');

                            if (!uploadId || !key || !partSize || parts.length === 0) {
                                throw new Error('Multipart init invalide.');
                            }

                            const etags = [];

                            const partBytesArr = parts.map((p) => {
                                const partNumber = Number(p?.part_number || 0);
                                const start = (partNumber - 1) * partSize;
                                const end = Math.min(size, start + partSize);
                                return Math.max(0, end - start);
                            });
                            const partLoadedArr = parts.map(() => 0);

                            const updateOverallProgress = () => {
                                const loaded = partLoadedArr.reduce((a, b) => a + Number(b || 0), 0);
                                const pct = Math.max(0, Math.min(100, Math.round((loaded / size) * 100)));
                                updateUploadPlaceholder(tempId, pct);
                            };

                            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
                            const maxConc = isMobile ? 2 : 4;
                            const concurrency = Math.max(1, Math.min(maxConc, parts.length));

                            const uploadPartAtIndex = async (idx) => {
                                const p = parts[idx];
                                const partNumber = Number(p?.part_number || 0);
                                const uploadUrl = String(p?.upload_url || '');
                                if (!partNumber || !uploadUrl) throw new Error('Part invalide.');

                                const start = (partNumber - 1) * partSize;
                                const end = Math.min(size, start + partSize);
                                const blob = file.slice(start, end);
                                const partBytes = partBytesArr[idx];

                                let attempt = 0;
                                while (true) {
                                    try {
                                        const res = await putWithProgress(uploadUrl, blob, mime, (loaded) => {
                                            const v = Math.max(0, Math.min(partBytes, Number(loaded || 0)));
                                            partLoadedArr[idx] = v;
                                            updateOverallProgress();
                                        });
                                        const etag = String(res?.etag || '').trim();
                                        if (!etag) throw new Error('ETag manquant (R2).');
                                        partLoadedArr[idx] = partBytes;
                                        updateOverallProgress();
                                        etags.push({ part_number: partNumber, etag });
                                        return;
                                    } catch (e) {
                                        attempt++;
                                        if (attempt >= 3) throw e;
                                        await new Promise(r => setTimeout(r, 750 * attempt));
                                    }
                                }
                            };

                            let nextIndex = 0;
                            const workers = Array.from({ length: concurrency }, () => (async () => {
                                while (true) {
                                    const idx = nextIndex;
                                    nextIndex++;
                                    if (idx >= parts.length) return;
                                    await uploadPartAtIndex(idx);
                                }
                            })());

                            await Promise.all(workers);
                            etags.sort((a, b) => Number(a.part_number) - Number(b.part_number));

                            const complete = await postJson(mpCompleteUrl, {
                                key,
                                upload_id: uploadId,
                                parts: etags,
                                mime,
                                size,
                                kind,
                                context: 'chat',
                            });

                            const finPublicUrl = complete?.public_url || init?.public_url || null;
                            if (kind === 'video') {
                                const fd = new FormData();
                                fd.append('key', key);
                                if (finPublicUrl) fd.append('public_url', String(finPublicUrl));
                                fd.append('mime', mime);
                                fd.append('size', String(size));
                                fd.append('kind', kind);
                                fd.append('context', 'chat');
                                fd.append('storage_disk', 'r2');
                                if (file.name) fd.append('filename', String(file.name));
                                fd.append('chat_thread_id', 'default');

                                fd.append('audience_type', String(effAud.type || 'all'));
                                (Array.isArray(effAud.userIds) ? effAud.userIds : []).forEach((id) => {
                                    const v = Number(id || 0) || 0;
                                    if (v > 0) fd.append('audience_user_ids[]', String(v));
                                });

                                const posterInfo = await posterPromise;
                                const posterBlob = posterInfo && typeof posterInfo === 'object' ? posterInfo.blob : null;
                                if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');

                                finalized = await postForm(finalizeUrl, fd);
                            } else {
                                finalized = await postJson(finalizeUrl, {
                                    key,
                                    public_url: finPublicUrl,
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                    storage_disk: 'r2',
                                    filename: file.name || null,
                                    chat_thread_id: 'default',
                                    audience_type: effAud.type,
                                    audience_user_ids: effAud.type === 'subset' ? (effAud.userIds || []) : [],
                                });
                            }

                            }
                        } else {
                            const presign = await postJson(presignUrl, {
                                filename: file.name || 'file',
                                mime,
                                size,
                                kind,
                                context: 'chat',
                            });

                            const uploadUrl = String(presign?.upload_url || '');
                            const key = String(presign?.key || '');
                            const storageDisk = String(presign?.storage_disk || 'r2');
                            if (!uploadUrl || !key) throw new Error('Presign invalide.');

                            await putWithProgress(uploadUrl, file, mime, (loaded, total) => {
                                const pct = Math.max(0, Math.min(100, Math.round((loaded / (total || size)) * 100)));
                                updateUploadPlaceholder(tempId, pct);
                            });

                            const finPublicUrl = presign?.public_url || null;
                            if (kind === 'video') {
                                const fd = new FormData();
                                fd.append('key', key);
                                if (finPublicUrl) fd.append('public_url', String(finPublicUrl));
                                fd.append('mime', mime);
                                fd.append('size', String(size));
                                fd.append('kind', kind);
                                fd.append('context', 'chat');
                                fd.append('storage_disk', storageDisk);
                                if (file.name) fd.append('filename', String(file.name));
                                fd.append('chat_thread_id', 'default');

                                fd.append('audience_type', String(effAud.type || 'all'));
                                (Array.isArray(effAud.userIds) ? effAud.userIds : []).forEach((id) => {
                                    const v = Number(id || 0) || 0;
                                    if (v > 0) fd.append('audience_user_ids[]', String(v));
                                });

                                const posterInfo = await posterPromise;
                                const posterBlob = posterInfo && typeof posterInfo === 'object' ? posterInfo.blob : null;
                                if (posterBlob) fd.append('poster_file', posterBlob, 'poster.jpg');

                                finalized = await postForm(finalizeUrl, fd);
                            } else {
                                finalized = await postJson(finalizeUrl, {
                                    key,
                                    public_url: finPublicUrl,
                                    mime,
                                    size,
                                    kind,
                                    context: 'chat',
                                    storage_disk: storageDisk,
                                    filename: file.name || null,
                                    chat_thread_id: 'default',
                                    audience_type: effAud.type,
                                    audience_user_ids: effAud.type === 'subset' ? (effAud.userIds || []) : [],
                                });
                            }
                        }

                        removeUploadPlaceholder(tempId);
                        refreshQuota().catch(() => {});

                        // Show attachment immediately in the chat (without waiting for realtime/polling).
                        const chatMessageId = Number(finalized?.chat_message_id || 0);
                        const openUrl = String(finalized?.open_url || '');
                        const mediaUrl = String(finalized?.media_url || finalized?.stream_url || '');
                        if (chatMessageId && (mediaUrl || openUrl)) {
                            const posterInfo = await posterPromise;
                            const imageMeta = await imageMetaPromise;
                            const dims = (kind === 'video')
                                ? (posterInfo && typeof posterInfo === 'object' ? { width: Number(posterInfo.width || 0), height: Number(posterInfo.height || 0) } : null)
                                : (imageMeta && typeof imageMeta === 'object' ? { width: Number(imageMeta.width || 0), height: Number(imageMeta.height || 0) } : null);

                            const attachment = {
                                media_type: kind === 'video' ? 'video' : 'image',
                                media_id: Number(finalized?.media_id || 0) || null,
                                name: String((kind === 'video' ? (file.name || 'Vidéo') : (file.name || 'Photo'))),
                                url: mediaUrl || openUrl,
                                open_url: openUrl || undefined,
                                thumb_url: String(finalized?.thumb_url || ''),
                                public_url: String(finalized?.public_url || ''),
                                width: dims && dims.width ? dims.width : undefined,
                                height: dims && dims.height ? dims.height : undefined,
                            };

                            appendMessage({
                                id: chatMessageId,
                                body: '[[ATTACHMENT]]' + JSON.stringify(attachment),
                                created_at: new Date().toISOString(),
                                user: { id: currentUserId, name: currentUserName || 'Vous' },
                                audience_type: effAud.type,
                                audience_user_ids: effAud.type === 'subset' ? effAud.userIds : [],
                            });

                            lastMessageId = Math.max(lastMessageId, chatMessageId);
                            ensureBottom(2000);
                        } else {
                            // Fallback: force a poll so the new message appears quickly.
                            pollOnce().catch(() => {});
                        }
                    } catch (e) {
                        removeUploadPlaceholder(tempId);
                        alert(String(e?.message || 'Upload impossible.'));
                    }
                })();
            }

            const online = new Map();

            function normalizeUsers(users) {
                if (!users) return [];
                if (Array.isArray(users)) return users;
                if (typeof users === 'object') return Object.values(users);
                return [];
            }

            function userId(u) {
                return u?.id ?? u?.user_id ?? u?.user?.id ?? null;
            }

            function userName(u) {
                return u?.name ?? u?.user?.name ?? '—';
            }

            let realtimeStarted = false;

            function startRealtime() {
                if (realtimeStarted) return;
                if (!window.Echo) return;

                realtimeStarted = true;
                console.log('[chat] Echo ready, joining presence channel chat');

                const handleSent = (e) => {
                    console.log('[chat] message.sent', e);
                    const appended = appendMessage(e);
                    if (e?.id) lastMessageId = Math.max(lastMessageId, Number(e.id));
                };

                window.Echo.join('chat')
                    .here((users) => {
                        console.log('[chat] here(users)=', users);
                        online.clear();
                        normalizeUsers(users).forEach(u => {
                            const id = userId(u);
                            if (id != null) {
                                online.set(id, { id, name: userName(u) });
                            }
                        });
                        renderOnline(Array.from(online.values()));
                    })
                    .joining((user) => {
                        console.log('[chat] joining(user)=', user);
                        const id = userId(user);
                        if (id != null) online.set(id, { id, name: userName(user) });
                        renderOnline(Array.from(online.values()));
                    })
                    .leaving((user) => {
                        console.log('[chat] leaving(user)=', user);
                        const id = userId(user);
                        if (id != null) online.delete(id);
                        renderOnline(Array.from(online.values()));
                    })
                    .listen('.message.sent', (e) => {
                        handleSent(e);
                    })
                    .listen('.message.deleted', (e) => {
                        if (!e?.id) return;
                        markMessageDeletedForAll(Number(e.id));
                        if (reactionUsersPopover.open && Number(reactionUsersPopover.messageId || 0) === Number(e.id)) {
                            closeReactionUsersPopover();
                        }
                    })
                    .listen('.message.reactions.updated', (e) => {
                        if (!e?.message_id) return;
                        updateReactionSummary(Number(e.message_id), e.reaction_summary || []);
                    });

                // Targeted messages: listen on private channels.
                if (currentUserId) {
                    try {
                        window.Echo.private(`chat.user.${currentUserId}`)
                            .listen('.message.sent', handleSent);
                    } catch {}
                }

                if (isAdmin) {
                    try {
                        window.Echo.private('chat.admin')
                            .listen('.message.sent', handleSent);
                    } catch {}
                }
            }

            function bindAttachFor(key) {
                const c = composer[key];
                const fd = focusDock[key];
                if (!c) return;

                // Focus Dock toggle
                if (c.attachBtn) {
                    c.attachBtn.addEventListener('click', () => {
                        setActiveComposerKey(key);
                        setFocusDockOpen(!focusDockOpen);
                    });
                }

                // Textarea focus
                if (c.textarea) {
                    c.textarea.addEventListener('focus', () => setActiveComposerKey(key));
                }

                // Photo input
                if (c.photoInput) {
                    c.photoInput.addEventListener('change', () => {
                        const f = c.photoInput.files && c.photoInput.files[0];
                        c.photoInput.value = '';
                        if (f) uploadAttachment(f);
                    });
                }

                // Video input
                if (c.videoInput) {
                    c.videoInput.addEventListener('change', () => {
                        const f = c.videoInput.files && c.videoInput.files[0];
                        c.videoInput.value = '';
                        if (f) uploadAttachment(f);
                    });
                }

                // Focus Dock actions
                if (fd) {
                    if (fd.photo) {
                        fd.photo.addEventListener('click', () => {
                            c.photoInput?.click();
                            setFocusDockOpen(false);
                        });
                    }

                    if (fd.video) {
                        fd.video.addEventListener('click', () => {
                            c.videoInput?.click();
                            setFocusDockOpen(false);
                        });
                    }

                    if (fd.micro) {
                        fd.micro.addEventListener('click', () => {
                            setActiveComposerKey(key);
                            setFocusDockOpen(false);
                            // Delay dictation start slightly to let dock close animation finish
                            setTimeout(() => startDictation(), 100);
                        });
                    }

                    if (fd.close) {
                        fd.close.addEventListener('click', () => setFocusDockOpen(false));
                    }

                    if (fd.backdrop) {
                        fd.backdrop.addEventListener('click', () => setFocusDockOpen(false));
                    }
                }
            }

            bindAttachFor('mobile');
            bindAttachFor('desktop');

            // Global ESC key to close Focus Dock
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && focusDockOpen) {
                    setFocusDockOpen(false);
                    e.preventDefault();
                }
            });

            // DEPRECATED: Old attach sheet (remove if no longer needed)
            if (attachPickMedia) {
                attachPickMedia.addEventListener('click', () => {
                    const c = getActiveComposer();
                    c?.photoInput?.click();
                });
            }

            if (attachBackdrop) {
                attachBackdrop.addEventListener('click', () => setAttachSheetOpen(false));
            }
            if (attachCancel) {
                attachCancel.addEventListener('click', () => setAttachSheetOpen(false));
            }

            let pollingTimer = null;
            let pollingInFlight = false;

            async function pollOnce() {
                if (pollingInFlight) return;
                pollingInFlight = true;

                try {
                    const url = new URL(pollUrl, window.location.origin);
                    if (lastMessageId) url.searchParams.set('since_id', String(lastMessageId));
                    if (conversationUserId > 0) url.searchParams.set('with_user_id', String(conversationUserId));

                    const res = await fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;

                    const ct = (res.headers.get('content-type') || '').toLowerCase();
                    if (!ct.includes('application/json')) {
                        // Can happen if the session expired (HTML login), offline fallback, proxy error page, etc.
                        console.warn('[chat] poll: non-json response', { status: res.status, contentType: ct });
                        return;
                    }

                    const data = await res.json();
                    const onlineUsers = Array.isArray(data?.online) ? data.online : [];
                    renderOnline(onlineUsers);
                    // updateGate(onlineUsers.length); // TODO: implement or remove

                    const msgs = Array.isArray(data?.messages) ? data.messages : [];
                    let appendedAny = false;
                    msgs.forEach(m => {
                        const ok = appendMessage(m);
                        if (ok) appendedAny = true;
                    });
                    if (appendedAny) {
                        focusLastMessage();
                    }

                    const newLast = Number(data?.last_id ?? lastMessageId);
                    if (!Number.isNaN(newLast)) lastMessageId = Math.max(lastMessageId, newLast);
                } catch (e) {
                    console.warn('[chat] poll failed', e);
                } finally {
                    pollingInFlight = false;
                }
            }

            function startPolling(options) {
                if (pollingTimer) return;
                console.log('[chat] starting polling fallback');

                const useInitial = options?.useInitial !== false;
                if (useInitial) {
                    renderOnline(initialOnline);
                }

                pollOnce();
                pollingTimer = setInterval(pollOnce, 5000);
            }

            // iOS/Safari can throttle timers heavily in background.
            // When the user comes back, do an immediate sync.
            window.addEventListener('focus', () => pollOnce());
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) pollOnce();
            });

            // Vite's module scripts load after this inline script, so Echo may appear a bit later.
            if (window.Echo) {
                startRealtime();
                // Safety net: even with Echo present, polling keeps messages in sync
                // on hosts where websockets/broadcasting are unavailable.
                startPolling({ useInitial: false });
            } else {
                console.log('[chat] waiting for Echo…');
                window.addEventListener('echo:ready', () => {
                    startRealtime();
                    startPolling({ useInitial: false });
                }, { once: true });

                let attempts = 0;
                const timer = setInterval(() => {
                    attempts++;
                    if (window.Echo) {
                        clearInterval(timer);
                        startRealtime();
                        startPolling({ useInitial: false });
                        return;
                    }
                    if (attempts >= 30) {
                        clearInterval(timer);
                        console.warn('[chat] Echo still not present after waiting (no realtime)');
                        startPolling({ useInitial: true });
                    }
                }, 100);
            }

            let isSending = false;

            function autoGrowTextarea(ta) {
                if (!ta) return;
                ta.style.height = 'auto';
                const styles = window.getComputedStyle(ta);
                const lineHeight = parseFloat(styles.lineHeight || '20') || 20;
                const max = Math.round(lineHeight * 4);
                ta.style.height = Math.min(ta.scrollHeight, max) + 'px';
            }

            function syncSendButtonFor(c) {
                if (!c?.sendBtn || !c?.textarea) return;
                const body = String(c.textarea.value || '').trim();
                c.sendBtn.disabled = body.length === 0 || isSending;
            }

            function bindComposerHandlers(c) {
                if (!c?.form || !c?.textarea) return;

                autoGrowTextarea(c.textarea);
                syncSendButtonFor(c);

                c.textarea.addEventListener('input', () => {
                    setActiveComposerKey(c.key);
                    autoGrowTextarea(c.textarea);
                    syncSendButtonFor(c);
                    // On mobile, the dock height changes as the textarea grows.
                    requestAnimationFrame(() => {
                        syncScrollBottomPadding();
                        if (isPinnedToBottom()) scrollToBottom({ force: true });
                    });
                });

                c.textarea.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter' && !ev.shiftKey) {
                        ev.preventDefault();
                        setActiveComposerKey(c.key);
                        c.form.requestSubmit?.();
                    }
                });

                c.form.addEventListener('submit', async (ev) => {
                    ev.preventDefault();

                    setActiveComposerKey(c.key);
                    if (isSending) return;

                    const body = c.textarea.value.trim();
                    if (!body) return;

                    isSending = true;
                    syncSendButtonFor(c);

                    const tempId = `temp-${Date.now()}`;
                    appendLocalMessage(tempId, body);
                    ensureBottom(1600);

                    const token = c.form.querySelector('input[name="_token"]')?.value || getCsrfToken();
                    const socketId = getSocketId();

                    try {
                        const res = await fetch(c.form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                                ...(socketId ? { 'X-Socket-Id': socketId } : {}),
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                body,
                                audience_user_ids: audienceState.type === 'subset' ? audienceState.userIds : [],
                            }),
                        });

                        const ct = (res.headers.get('content-type') || '').toLowerCase();
                        if (!res.ok) {
                            const errMsg = ct.includes('application/json') ? (await res.json())?.message : 'Envoi impossible.';
                            markLocalFailed(tempId, errMsg);
                            return;
                        }

                        const json = ct.includes('application/json') ? await res.json() : null;
                        const tempRow = messagesEl?.querySelector(`[data-message-id="${tempId}"]`);
                        if (tempRow) tempRow.remove();

                        if (json) {
                            appendMessage(json);
                            if (json?.id) lastMessageId = Math.max(lastMessageId, Number(json.id));
                        }

                        ensureBottom(1200);

                        c.textarea.value = '';
                        autoGrowTextarea(c.textarea);
                        syncSendButtonFor(c);
                        // Avoid re-opening the mobile virtual keyboard after send.
                        // Keep focus on desktop for fast consecutive messages.
                        if (c.key === 'desktop') {
                            c.textarea.focus();
                        } else {
                            try { c.textarea.blur(); } catch {}
                        }

                        // Safety: reset to "Tout le monde" after send (except in conversation view).
                        if (!(conversationUserId > 0)) {
                            resetAudience();
                        }
                    } catch (e) {
                        markLocalFailed(tempId, e?.message || 'Envoi impossible.');
                    } finally {
                        isSending = false;
                        syncSendButtonFor(c);
                    }
                });
            }

            bindComposerHandlers(composer.mobile);
            bindComposerHandlers(composer.desktop);

            ensureAudienceUiFor('mobile');
            ensureAudienceUiFor('desktop');
            syncAudienceUi();
            ensureRecipientsLoaded();

            // Conversation view (filter + auto-target)
            if (conversationUser && Number(conversationUser?.id || 0) > 0) {
                const id = Number(conversationUser.id);
                if (!recipientsIndex.has(id)) {
                    recipientsIndex.set(id, {
                        id,
                        name: String(conversationUser?.name || '—'),
                        avatar_url: String(conversationUser?.avatar_url || ''),
                    });
                }
            }

            if (conversationUserId > 0) {
                setAudienceUserIds([conversationUserId]);
            }
            syncConversationUi();

            // Voice dictation (Web Speech API)
            if (attachPickVoice) {
                syncVoiceAvailability();
                if (SpeechRecognitionCtor) {
                    recognition = new SpeechRecognitionCtor();
                    recognition.lang = 'fr-FR';
                    recognition.interimResults = true;
                    recognition.continuous = true;
                    recognition.maxAlternatives = 1;

                    recognition.onresult = (event) => {
                        const ta = getActiveComposer()?.textarea;
                        if (!ta) return;

                        let finalText = '';
                        let interimText = '';

                        for (let i = event.resultIndex; i < event.results.length; i++) {
                            const res = event.results[i];
                            const chunk = String(res?.[0]?.transcript ?? '').trim();
                            if (!chunk) continue;
                            if (res.isFinal) {
                                finalText += (finalText ? ' ' : '') + chunk;
                            } else {
                                interimText += (interimText ? ' ' : '') + chunk;
                            }
                        }

                        if (finalText) {
                            dictationBase = (dictationBase || '').trim();
                            dictationBase = dictationBase
                                ? (dictationBase + ' ' + finalText).trim()
                                : finalText;
                        }

                        dictationInterim = interimText;
                        const composed = [dictationBase, dictationInterim].filter(Boolean).join(' ').trim();
                        ta.value = composed;
                        ta.selectionStart = ta.selectionEnd = ta.value.length;
                        autoGrowTextarea(ta);
                        syncSendButtonFor(getActiveComposer());
                    };

                    recognition.onerror = () => {
                        setDictationUi(false);
                    };

                    recognition.onend = () => {
                        if (dictationActive) {
                            setDictationUi(false);
                            dictationInterim = '';
                        }
                    };
                }

                attachPickVoice.addEventListener('click', () => {
                    if (!SpeechRecognitionCtor || !recognition) return;

                    const c = getActiveComposer();
                    setAttachSheetOpen(false);
                    c?.textarea?.focus();

                    if (dictationActive) {
                        try { recognition.stop(); } catch {}
                        setDictationUi(false);
                        dictationInterim = '';
                        return;
                    }

                    dictationBase = String(c?.textarea?.value || '').trim();
                    dictationInterim = '';
                    setDictationUi(true);
                    try {
                        recognition.start();
                    } catch {
                        setDictationUi(false);
                    }
                });
            }

            if (scrollEl) {
                scrollEl.addEventListener('scroll', () => {
                    if (isNearBottom()) {
                        pinToBottom(600);
                    } else {
                        pinToBottomUntil = 0;
                    }
                    syncScrollToBottomButton();
                });
            }
            if (scrollToBottomBtn) {
                scrollToBottomBtn.addEventListener('click', () => {
                    ensureBottom(900);
                    syncScrollToBottomButton();
                });
            }

            if (messagesEl) {
                messagesEl.addEventListener('click', async (ev) => {
                    const target = ev.target;
                    if (!(target instanceof HTMLElement)) return;

                    const copyLink = target.closest('[data-copy-link]');
                    if (copyLink instanceof HTMLElement) {
                        const url = String(copyLink.dataset.copyLink || '').trim();
                        if (!url) return;
                        try {
                            await navigator.clipboard.writeText(url);
                            copyLink.textContent = 'Copié';
                            setTimeout(() => { copyLink.textContent = 'Copier le lien'; }, 1200);
                        } catch {
                            alert(url);
                        }
                        return;
                    }

                    const retryBtn = target.closest('[data-retry-temp-id]');
                    if (retryBtn instanceof HTMLElement) {
                        const tempId = String(retryBtn.dataset.retryTempId || '');
                        if (!tempId) return;
                        const row = messagesEl.querySelector(`[data-message-id="${tempId}"]`);
                        const body = String(row?.dataset?.localBody || '').trim();
                        if (textareaEl && body) {
                            textareaEl.value = body;
                            textareaEl.focus();
                            if (formEl) formEl.requestSubmit?.();
                        }
                        return;
                    }

                    const copyTextBtn = target.closest('[data-copy-text]');
                    if (copyTextBtn instanceof HTMLElement) {
                        const txt = String(copyTextBtn.dataset.copyText || '').trim();
                        if (!txt) return;
                        try {
                            await navigator.clipboard.writeText(txt);
                            copyTextBtn.textContent = 'Copié';
                            setTimeout(() => { copyTextBtn.textContent = 'Copier'; }, 1200);
                        } catch {
                            alert(txt);
                        }
                        return;
                    }
                });
            }

            // Header menu: Voir les conversations (remplace la flèche retour)
            const headerMenuConversations = document.getElementById('chatHeaderMenuConversations');
            if (headerMenuConversations) {
                headerMenuConversations.addEventListener('click', () => {
                    closeHeaderMenu();
                    if (backUrl) {
                        window.location.href = backUrl;
                        return;
                    }
                    if (window.history.length > 1) {
                        window.history.back();
                        return;
                    }
                    window.location.href = bootstrap.dashboardUrl;
                });
            }

            // Header overflow menu
            const menuBtn = document.getElementById('chatMenuBtn');
            const headerMenu = document.getElementById('chatHeaderMenu');
            const headerMenuBackdrop = document.getElementById('chatHeaderMenuBackdrop');
            const headerMenuPanel = document.getElementById('chatHeaderMenuPanel');
            const headerMenuSearch = document.getElementById('chatHeaderMenuSearch');
            const headerMenuInfo = document.getElementById('chatHeaderMenuInfo');

            function openHeaderMenu() {
                if (!headerMenu || !headerMenuPanel || !menuBtn) return;
                headerMenu.classList.remove('hidden');
                headerMenu.setAttribute('aria-hidden', 'false');
                
                const btn = menuBtn.getBoundingClientRect();
                const panel = headerMenuPanel;
                const vw = window.innerWidth || document.documentElement.clientWidth;
                const vh = window.innerHeight || document.documentElement.clientHeight;
                const pad = 8;
                
                requestAnimationFrame(() => {
                    const rect = panel.getBoundingClientRect();
                    const w = rect.width || 220;
                    const h = rect.height || 120;
                    
                    const right = Math.max(pad, vw - btn.right);
                    const top = Math.max(pad, Math.min(vh - h - pad, btn.bottom + 4));
                    
                    panel.style.right = `${right}px`;
                    panel.style.top = `${top}px`;
                });
            }

            function closeHeaderMenu() {
                if (!headerMenu) return;
                headerMenu.classList.add('hidden');
                headerMenu.setAttribute('aria-hidden', 'true');
            }

            if (menuBtn) {
                menuBtn.addEventListener('click', () => {
                    const isOpen = !headerMenu.classList.contains('hidden');
                    if (isOpen) {
                        closeHeaderMenu();
                    } else {
                        openHeaderMenu();
                    }
                });
            }

            if (headerMenuBackdrop) {
                headerMenuBackdrop.addEventListener('click', closeHeaderMenu);
            }

            if (headerMenuSearch && searchBar && searchInput) {
                headerMenuSearch.addEventListener('click', () => {
                    closeHeaderMenu();
                    const open = searchBar.classList.contains('hidden');
                    searchBar.classList.toggle('hidden', !open);
                    if (open) {
                        searchInput.focus();
                    } else {
                        searchInput.value = '';
                        messagesEl?.querySelectorAll('[data-message-row]').forEach(el => el.classList.remove('hidden'));
                    }
                });
            }

            if (headerMenuInfo && infoModal) {
                headerMenuInfo.addEventListener('click', () => {
                    closeHeaderMenu();
                    setInfoModalOpen(true);
                });
            }

            // Visibility Sheet
            const visibilityChip = document.getElementById('chatVisibilityChip');
            const visibilityLabel = document.getElementById('chatVisibilityLabel');
            const visibilitySheet = document.getElementById('chatVisibilitySheet');
            const visibilityBackdrop = document.getElementById('chatVisibilityBackdrop');
            const visibilityPanel = document.getElementById('chatVisibilityPanel');
            const visibilityCancel = document.getElementById('chatVisibilityCancel');
            const visibilityConfirm = document.getElementById('chatVisibilityConfirm');
            const visibilityWarning = document.getElementById('chatVisibilityWarning');
            const visibilityParticipants = document.getElementById('chatVisibilityParticipants');
            const visibilityPills = document.getElementById('chatVisibilityPills');
            const visibilityHelper = document.getElementById('chatVisibilityHelper');
            const visibilityToggleList = document.getElementById('chatVisibilityToggleList');
            const visibilityToggleIcon = document.getElementById('chatVisibilityToggleIcon');
            const visibilityMemberList = document.getElementById('chatVisibilityMemberList');
            const visibilityRadios = document.querySelectorAll('input[name="chatVisibilityMode"]');
            
            let visibilityOpen = false;
            let selectedUserIds = new Set();
            let allMembers = [];
            
            function openVisibilitySheet() {
                console.log('openVisibilitySheet called', { visibilitySheet, visibilityPanel, isAdmin });
                if (!visibilitySheet || !visibilityPanel) {
                    console.log('Missing elements, aborting');
                    return;
                }
                console.log('Opening visibility sheet...');
                visibilityOpen = true;
                visibilitySheet.classList.remove('hidden');
                visibilitySheet.setAttribute('aria-hidden', 'false');
                console.log('Sheet unhidden, classes:', visibilitySheet.className);
                
                // Init state
                const currentMode = visibilityLabel?.textContent.trim().toLowerCase() === 'privé' ? 'private' : 'public';
                selectedUserIds = new Set([currentUserId]);
                console.log('Current mode:', currentMode, 'selectedUserIds:', selectedUserIds);
                
                visibilityRadios.forEach(r => {
                    r.checked = r.value === currentMode;
                });
                
                updateVisibilityUI();
                console.log('UI updated, triggering animation...');
                
                requestAnimationFrame(() => {
                    visibilityBackdrop?.classList.remove('opacity-0');
                    visibilityPanel?.classList.remove('opacity-0', 'scale-95');
                    console.log('Animation classes removed, panel should be visible now');
                });
            }
            
            function closeVisibilitySheet() {
                if (!visibilitySheet) return;
                visibilityBackdrop?.classList.add('opacity-0');
                visibilityPanel?.classList.add('opacity-0', 'scale-95');
                
                setTimeout(() => {
                    visibilitySheet.classList.add('hidden');
                    visibilitySheet.setAttribute('aria-hidden', 'true');
                    visibilityOpen = false;
                }, 200);
            }
            
            function updateVisibilityUI() {
                const isPrivate = Array.from(visibilityRadios).find(r => r.checked)?.value === 'private';
                
                visibilityWarning?.classList.toggle('hidden', !isPrivate);
                visibilityParticipants?.classList.toggle('hidden', !isPrivate);
                
                if (isPrivate) {
                    renderPills();
                    renderMemberList();
                    validateSelection();
                }
            }
            
            function renderPills() {
                if (!visibilityPills) return;
                visibilityPills.innerHTML = '';
                
                selectedUserIds.forEach(uid => {
                    const isCurrentUser = uid === currentUserId;
                    const member = allMembers.find(m => m.id === uid);
                    const name = isCurrentUser ? 'Vous' : (member?.name || `User ${uid}`);
                    
                    const pill = document.createElement('div');
                    pill.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-teal-600/10 text-teal-700 text-xs font-medium border border-teal-600/20';
                    pill.innerHTML = `
                        <span>${name}${isCurrentUser ? ' 🔒' : ''}</span>
                        ${!isCurrentUser ? `<button type="button" class="hover:text-teal-900 transition-colors" data-remove-user="${uid}">×</button>` : ''}
                    `;
                    
                    visibilityPills.appendChild(pill);
                });
                
                // Attach remove handlers
                visibilityPills.querySelectorAll('[data-remove-user]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const uid = Number(btn.dataset.removeUser);
                        if (uid && uid !== currentUserId) {
                            selectedUserIds.delete(uid);
                            updateVisibilityUI();
                        }
                    });
                });
            }
            
            function renderMemberList() {
                try {
                    console.log('[Accordion] renderMemberList() CALLED');
                if (!visibilityMemberList || !recipientsUrl) return;
                
                if (allMembers.length === 0) {
                    // Fetch members
                    fetch(recipientsUrl, { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(data => {
                            allMembers = (data || []).filter(m => m.id !== currentUserId);
                            renderMemberListHTML();
                        })
                        .catch(() => {});
                } else {
                    renderMemberListHTML();
                }
                } catch (err) {
                    console.error('[Accordion] CRASH:', err);
                }
            }
            
            function renderMemberListHTML() {
                if (!visibilityMemberList) {
                    console.log('[Accordion] Cannot render HTML - missing element');
                    return;
                }
                console.log('[Accordion] Rendering HTML for', allMembers.length, 'members');
                visibilityMemberList.innerHTML = '';
                
                allMembers.forEach(member => {
                    const isSelected = selectedUserIds.has(member.id);
                    const item = document.createElement('label');
                    item.className = 'flex items-center justify-between gap-3 px-3 py-2.5 hover:bg-slate-50 cursor-pointer transition-colors';
                    item.innerHTML = `
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-teal-600/10 flex items-center justify-center text-xs font-semibold text-teal-700">
                                ${member.name.charAt(0).toUpperCase()}
                            </div>
                            <div class="text-sm font-medium text-slate-900 truncate">${member.name}</div>
                        </div>
                        <input type="checkbox" ${isSelected ? 'checked' : ''} class="h-4 w-4 text-teal-600 focus:ring-teal-600 rounded" data-member-id="${member.id}" />
                    `;
                    
                    visibilityMemberList.appendChild(item);
                });
                
                // Attach checkbox handlers
                visibilityMemberList.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.addEventListener('change', () => {
                        const uid = Number(cb.dataset.memberId);
                        if (cb.checked) {
                            selectedUserIds.add(uid);
                        } else {
                            selectedUserIds.delete(uid);
                        }
                        updateVisibilityUI();
                    });
                });
            }
            
            function validateSelection() {
                const isPrivate = Array.from(visibilityRadios).find(r => r.checked)?.value === 'private';
                const hasOtherUsers = Array.from(selectedUserIds).filter(uid => uid !== currentUserId).length > 0;
                const isValid = !isPrivate || (isPrivate && hasOtherUsers);
                
                if (visibilityConfirm) {
                    visibilityConfirm.disabled = !isValid;
                }
                
                if (visibilityHelper) {
                    visibilityHelper.classList.toggle('hidden', isValid);
                }
            }
            
            if (visibilityChip) {
                console.log('Visibility chip found, attaching click handler');
                visibilityChip.addEventListener('click', () => {
                    console.log('Visibility chip clicked!');
                    openVisibilitySheet();
                });
            } else {
                console.log('Visibility chip NOT found!');
            }
            
            if (visibilityBackdrop) {
                visibilityBackdrop.addEventListener('click', closeVisibilitySheet);
            }
            
            if (visibilityCancel) {
                visibilityCancel.addEventListener('click', closeVisibilitySheet);
            }
            
            if (visibilityConfirm) {
                visibilityConfirm.addEventListener('click', () => {
                    const mode = Array.from(visibilityRadios).find(r => r.checked)?.value || 'public';
                    const participants = mode === 'private' ? Array.from(selectedUserIds) : [];
                    
                    // TODO: Send to backend
                    console.log('Saving visibility:', { mode, participants });
                    
                    // Update UI
                    if (visibilityLabel) {
                        visibilityLabel.textContent = mode === 'private' ? 'Privé' : 'Public';
                    }
                    
                    closeVisibilitySheet();
                    showToast(`Visibilité changée : ${mode === 'private' ? 'Privé' : 'Public'}`);
                });
            }
            
            if (visibilityRadios.length) {
                visibilityRadios.forEach(radio => {
                    radio.addEventListener('change', updateVisibilityUI);
                });
            }
            
            if (visibilityToggleList) {
                console.log('[Accordion] Attaching click handler...');
                visibilityToggleList.addEventListener('click', () => {
                    const isClosed = visibilityMemberList?.classList.contains('hidden');
                    console.log('[Accordion] Click - Is closed:', isClosed);
                    
                    if (isClosed) {
                        // OPEN: Show immediately and load members
                        console.log('[Accordion] Opening...');
                        visibilityMemberList?.classList.remove('hidden');
                        visibilityToggleIcon?.classList.add('rotate-180');
                        
                        // Load members (will populate the now-visible list)
                        console.log('[Accordion] Loading members...');
                        if (allMembers.length === 0) {
                            visibilityMemberList.innerHTML = '<div class="px-3 py-4 text-center text-sm text-slate-500">Chargement...</div>';
                        }
                        renderMemberList();
                    } else {
                        // CLOSE: Add hidden, remove rotate-180
                        console.log('[Accordion] Closing...');
                        visibilityMemberList?.classList.add('hidden');
                        visibilityToggleIcon?.classList.remove('rotate-180');
                    }
                    
                    // Debug: Verify final state
                    const stillHidden = visibilityMemberList?.classList.contains('hidden');
                    console.log('[Accordion] After toggle - Hidden:', stillHidden, 'Classes:', visibilityMemberList?.className);
                });
            } else {
                console.log('Toggle list button NOT found!');
            }

            if (searchBtn && searchBar && searchInput) {
                searchBtn.addEventListener('click', () => {
                    const open = searchBar.classList.contains('hidden');
                    searchBar.classList.toggle('hidden', !open);
                    if (open) {
                        searchInput.focus();
                    } else {
                        searchInput.value = '';
                        // reset
                        messagesEl?.querySelectorAll('[data-message-row]').forEach(el => el.classList.remove('hidden'));
                    }
                });
                searchInput.addEventListener('input', () => {
                    const q = String(searchInput.value || '').trim().toLowerCase();
                    const rows = messagesEl?.querySelectorAll('[data-message-row]') || [];
                    rows.forEach((row) => {
                        const txt = String(row.textContent || '').toLowerCase();
                        row.classList.toggle('hidden', q !== '' && !txt.includes(q));
                    });
                });
            }

            function notifDismissedRecently() {
                try {
                    const raw = localStorage.getItem(NOTIF_DISMISS_KEY);
                    const ts = raw ? Number(raw) : 0;
                    if (!ts) return false;
                    const ageMs = Date.now() - ts;
                    return ageMs < 1000 * 60 * 60 * 24 * 7; // 7 days
                } catch {
                    return false;
                }
            }

            function dismissNotifBanner() {
                try { localStorage.setItem(NOTIF_DISMISS_KEY, String(Date.now())); } catch {}
                if (notifBanner) notifBanner.classList.add('hidden');
            }

            async function refreshNotifBanner() {
                if (!notifBanner || !notifPrimary || !notifLater || !notifClose) return;
                if (!('Notification' in window) || !window.famillePush) return;
                if (notifDismissedRecently()) return;

                const perm = Notification.permission;
                const active = await window.famillePush.hasActive();
                if (active && perm === 'granted') {
                    notifBanner.classList.add('hidden');
                    return;
                }

                notifBanner.classList.remove('hidden');
                notifHelp.textContent = '';

                if (perm === 'denied') {
                    if (notifBody) notifBody.textContent = 'Les notifications sont désactivées pour ce site dans le navigateur.';
                    notifPrimary.textContent = 'Ouvrir les réglages';
                } else {
                    if (notifBody) notifBody.textContent = 'Recevez un push quand un message arrive.';
                    notifPrimary.textContent = 'Activer';
                }

                notifClose.addEventListener('click', dismissNotifBanner);
                notifLater.addEventListener('click', dismissNotifBanner);
                notifPrimary.addEventListener('click', async () => {
                    if (Notification.permission === 'denied') {
                        if (notifHelp) {
                            notifHelp.textContent = 'Ouvre les autorisations du site (icône cadenas) et autorise les notifications.';
                        }
                        return;
                    }
                    notifPrimary.disabled = true;
                    try {
                        const res = await window.famillePush.enable();
                        if (!res?.ok) {
                            if (notifHelp) notifHelp.textContent = 'Impossible d’activer les notifications.';
                        } else {
                            dismissNotifBanner();
                        }
                    } finally {
                        notifPrimary.disabled = false;
                    }
                });
            }

            refreshNotifBanner().catch(() => {});

            function getMediaStageRect() {
                // The center stage that contains the image/video in the modal.
                const stage = (mediaImg && mediaImg.parentElement) ? mediaImg.parentElement : null;
                try {
                    if (stage && stage.getBoundingClientRect) return stage.getBoundingClientRect();
                } catch {}
                return null;
            }

            function computeContainRect(stageRect, mediaW, mediaH) {
                if (!stageRect) return null;
                const sw = Math.max(0, Number(stageRect.width || 0));
                const sh = Math.max(0, Number(stageRect.height || 0));
                if (sw < 2 || sh < 2) return null;

                const w = Number(mediaW || 0);
                const h = Number(mediaH || 0);
                if (!(w > 0 && h > 0)) {
                    // Fallback: animate to the stage itself.
                    return {
                        top: stageRect.top,
                        left: stageRect.left,
                        width: stageRect.width,
                        height: stageRect.height,
                    };
                }

                const r = w / h;
                let tw = sw;
                let th = tw / r;
                if (th > sh) {
                    th = sh;
                    tw = th * r;
                }

                const left = stageRect.left + (sw - tw) / 2;
                const top = stageRect.top + (sh - th) / 2;
                return { top, left, width: tw, height: th };
            }

            function setMediaOpen(open, opts) {
                if (!mediaModal || !mediaImg || !mediaVideo || !mediaTitle || !mediaOpenLink) return;
                mediaModal.classList.toggle('hidden', !open);

                if (!open) {
                    unlockPageScroll();
                    mediaImgLoadToken++;
                    mediaImg.classList.add('hidden');
                    mediaVideo.classList.add('hidden');
                    mediaImg.src = '';
                    mediaImg.alt = '';
                    try { mediaImg.style.opacity = ''; } catch {}
                    try { mediaVideo.style.opacity = ''; } catch {}
                    try { mediaBackdrop && (mediaBackdrop.style.opacity = ''); } catch {}
                    try { mediaVideo.pause(); } catch {}
                    mediaVideo.removeAttribute('src');
                    mediaVideo.load();
                    mediaTitle.textContent = '';
                    mediaOpenLink.href = '#';
                    return;
                }

                lockPageScroll();

                // Fade-in the backdrop to avoid harsh flashes on mobile engines.
                try {
                    if (mediaBackdrop) {
                        mediaBackdrop.style.transition = 'opacity 160ms ease-out';
                        mediaBackdrop.style.opacity = '0';
                        requestAnimationFrame(() => {
                            if (isMediaModalOpen()) mediaBackdrop.style.opacity = '1';
                        });
                    }
                } catch {}

                // Once the media modal is open, stop any background bottom-pinning loops.
                cancelEnsureBottomTimers();
                cancelPinToBottom();

                const type = String(opts?.type || '');
                const rawUrl = String(opts?.url || '');
                const rawOpenUrl = String(opts?.openUrl || opts?.open_url || '');
                const thumb = String(opts?.thumb || opts?.thumb_url || '');
                const name = String(opts?.name || (type === 'video' ? 'Vidéo' : 'Photo'));

                const normalize = (() => {
                    const stripTrailingSlash = (u) => u.endsWith('/') ? u.slice(0, -1) : u;

                    const toSameOriginPath = (u) => {
                        try {
                            const x = new URL(u, window.location.origin);
                            if (x.origin !== window.location.origin) return null;
                            return x.pathname + x.search + x.hash;
                        } catch {
                            return null;
                        }
                    };

                    const rewriteVideoShowToStream = (u) => {
                        const p = toSameOriginPath(u);
                        if (!p) return null;
                        const m = p.match(/^\/videos\/(\d+)$/);
                        if (!m) return null;
                        return `/videos/${m[1]}/stream`;
                    };

                    const rewritePhotoViewerToImage = (u) => {
                        const p = toSameOriginPath(u);
                        if (!p) return null;
                        const m = p.match(/^\/media\/photos\/(\d+)$/);
                        if (!m) return null;
                        return `/galerie/${m[1]}`;
                    };

                    const rewriteStreamToShow = (u) => {
                        const p = toSameOriginPath(u);
                        if (!p) return null;
                        const m = p.match(/^\/videos\/(\d+)\/stream$/);
                        if (!m) return null;
                        return `/videos/${m[1]}`;
                    };

                    return { stripTrailingSlash, rewriteVideoShowToStream, rewritePhotoViewerToImage, rewriteStreamToShow };
                })();

                let url = rawUrl;
                let openUrl = rawOpenUrl;

                if (!openUrl && type === 'video') {
                    const show = normalize.rewriteStreamToShow(rawUrl);
                    if (show) openUrl = show;
                }

                if (!openUrl && type !== 'video') {
                    // For legacy image attachments where url was the viewer page, keep it as openUrl.
                    const maybeViewer = normalize.rewritePhotoViewerToImage(rawUrl);
                    if (maybeViewer) openUrl = rawUrl;
                }

                // Backward-compat: some older payloads used a page URL as the media src.
                if (type === 'video') {
                    const stream = normalize.rewriteVideoShowToStream(normalize.stripTrailingSlash(url));
                    if (stream) url = stream;
                } else {
                    const img = normalize.rewritePhotoViewerToImage(normalize.stripTrailingSlash(url));
                    if (img) url = img;
                }

                mediaTitle.textContent = name;
                mediaOpenLink.href = (openUrl || url) || '#';

                if (type === 'video') {
                    mediaImg.classList.add('hidden');
                    mediaVideo.classList.remove('hidden');
                    try {
                        mediaVideo.style.transition = 'opacity 160ms ease-out';
                        mediaVideo.style.opacity = '0';
                    } catch {}
                    if (thumb) {
                        mediaVideo.setAttribute('poster', thumb);
                    } else {
                        mediaVideo.removeAttribute('poster');
                    }
                    mediaVideo.src = url;
                    mediaVideo.load();

                    const token = ++mediaImgLoadToken;
                    const revealVideoIfCurrent = () => {
                        if (token !== mediaImgLoadToken) return;
                        try { mediaVideo.style.opacity = '1'; } catch {}
                    };

                    try {
                        mediaVideo.addEventListener('loadedmetadata', revealVideoIfCurrent, { once: true });
                        mediaVideo.addEventListener('canplay', revealVideoIfCurrent, { once: true });
                        mediaVideo.addEventListener('error', revealVideoIfCurrent, { once: true });
                    } catch {}
                    setTimeout(revealVideoIfCurrent, 120);
                } else {
                    mediaVideo.classList.add('hidden');
                    try { mediaVideo.pause(); } catch {}
                    mediaVideo.removeAttribute('src');
                    mediaVideo.load();
                    // Avoid showing progressive/intermediate image paints: keep hidden until loaded/decoded.
                    const token = ++mediaImgLoadToken;
                    mediaImg.classList.remove('hidden');
                    try {
                        mediaImg.style.transition = 'opacity 160ms ease-out';
                        mediaImg.style.opacity = '0';
                    } catch {}
                    try { mediaImg.decoding = 'async'; } catch {}
                    try { mediaImg.loading = 'eager'; } catch {}
                    // Reset first to avoid some browsers briefly reusing the previous frame.
                    try { mediaImg.src = ''; } catch {}
                    mediaImg.src = url;
                    mediaImg.alt = name;

                    const revealIfCurrent = () => {
                        if (token !== mediaImgLoadToken) return;
                        try {
                            if (mediaImg.complete && mediaImg.naturalWidth > 0) {
                                mediaImg.style.opacity = '1';
                            }
                        } catch {}
                    };

                    const onLoad = () => {
                        // decode() prevents some mid-paint flashes on a few engines.
                        try {
                            if (typeof mediaImg.decode === 'function') {
                                mediaImg.decode().then(revealIfCurrent).catch(revealIfCurrent);
                                return;
                            }
                        } catch {}
                        revealIfCurrent();
                    };

                    const onErr = () => {
                        if (token !== mediaImgLoadToken) return;
                        // Fallback: show the element so the user sees the broken-state instead of a blank.
                        try { mediaImg.style.opacity = '1'; } catch {}
                    };

                    try { mediaImg.addEventListener('load', onLoad, { once: true }); } catch {}
                    try { mediaImg.addEventListener('error', onErr, { once: true }); } catch {}
                    // If cached, load may not fire.
                    setTimeout(onLoad, 0);
                }
            }

            if (messagesEl) {
                messagesEl.addEventListener('click', (e) => {
                    const el = e.target && e.target.closest ? e.target.closest('[data-chat-media-open]') : null;
                    if (!el) return;
                    e.preventDefault();
                    const url = String(el.dataset.url || '');
                    const openUrl = String(el.dataset.openUrl || '');
                    const type = String(el.dataset.type || '');
                    const name = String(el.dataset.name || (type === 'video' ? 'Vidéo' : 'Photo'));
                    const thumb = String(el.dataset.thumb || '');
                    setMediaOpen(true, { url, openUrl, type, name, thumb });
                });
            }

            if (mediaBackdrop) {
                mediaBackdrop.addEventListener('click', () => setMediaOpen(false));
            }
            if (mediaClose) {
                mediaClose.addEventListener('click', () => setMediaOpen(false));
            }
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (!mediaModal || mediaModal.classList.contains('hidden')) return;
                setMediaOpen(false);
            });

            function setInfoOpen(open) {
                if (!infoModal) return;
                infoModal.classList.toggle('hidden', !open);
            }

            if (infoBtn) {
                infoBtn.addEventListener('click', () => setInfoOpen(true));
            }
            if (infoBackdrop) {
                infoBackdrop.addEventListener('click', () => setInfoOpen(false));
            }
            if (infoClose) {
                infoClose.addEventListener('click', () => setInfoOpen(false));
            }

            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, { once: true });
            } else {
                start();
            }
        })();
    
