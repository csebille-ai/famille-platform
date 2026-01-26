// Focus Dock Management for Chat Composer
// This code will be integrated into chat-page.js

export function setupFocusDock() {
    // Elements - Mobile
    const focusDock = document.getElementById('chatFocusDock');
    const focusDockBackdrop = document.getElementById('chatFocusDockBackdrop');
    const focusDockPanel = document.getElementById('chatFocusDockPanel');
    const focusPhoto = document.getElementById('chatFocusPhoto');
    const focusVideo = document.getElementById('chatFocusVideo');
    const focusMicro = document.getElementById('chatFocusMicro');
    const focusClose = document.getElementById('chatFocusClose');
    const photoInput = document.getElementById('chatPhotoInput');
    const videoInput = document.getElementById('chatVideoInput');
    const attachBtn = document.getElementById('chatAttachBtn');
    const textarea = document.getElementById('body');

    // Elements - Desktop
    const focusDockDesktop = document.getElementById('chatFocusDockDesktop');
    const focusDockPanelDesktop = document.getElementById('chatFocusDockPanelDesktop');
    const focusPhotoDesktop = document.getElementById('chatFocusPhotoDesktop');
    const focusVideoDesktop = document.getElementById('chatFocusVideoDesktop');
    const focusMicroDesktop = document.getElementById('chatFocusMicroDesktop');
    const focusCloseDesktop = document.getElementById('chatFocusCloseDesktop');
    const photoInputDesktop = document.getElementById('chatPhotoInputDesktop');
    const videoInputDesktop = document.getElementById('chatVideoInputDesktop');
    const attachBtnDesktop = document.getElementById('chatAttachBtnDesktop');
    const textareaDesktop = document.getElementById('bodyDesktop');

    let isDockOpen = false;
    let activeMode = 'mobile'; // 'mobile' or 'desktop'
    let recognition = null;
    let isDictating = false;

    const DURATION_MS = 220;

    function isReducedMotion() {
        try {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch {
            return false;
        }
    }

    function isMobile() {
        try {
            return !window.matchMedia || !window.matchMedia('(min-width: 640px)').matches;
        } catch {
            return true;
        }
    }

    function updateActiveMode() {
        activeMode = isMobile() ? 'mobile' : 'desktop';
    }

    function getElements() {
        updateActiveMode();
        if (activeMode === 'desktop') {
            return {
                dock: focusDockDesktop,
                panel: focusDockPanelDesktop,
                photo: focusPhotoDesktop,
                video: focusVideoDesktop,
                micro: focusMicroDesktop,
                close: focusCloseDesktop,
                photoInput: photoInputDesktop,
                videoInput: videoInputDesktop,
                attachBtn: attachBtnDesktop,
                textarea: textareaDesktop,
            };
        }
        return {
            dock: focusDock,
            backdrop: focusDockBackdrop,
            panel: focusDockPanel,
            photo: focusPhoto,
            video: focusVideo,
            micro: focusMicro,
            close: focusClose,
            photoInput,
            videoInput,
            attachBtn,
            textarea,
        };
    }

    function openDock() {
        const els = getElements();
        if (!els.dock) return;

        isDockOpen = true;

        // Update aria-expanded
        if (els.attachBtn) {
            els.attachBtn.setAttribute('aria-expanded', 'true');
        }

        els.dock.classList.remove('hidden');
        els.dock.setAttribute('aria-hidden', 'false');

        if (activeMode === 'mobile') {
            // Mobile: backdrop + slide up
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
            // Desktop: scale up
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

        // Focus first action
        setTimeout(() => {
            els.photo?.focus();
        }, DURATION_MS);
    }

    function closeDock() {
        const els = getElements();
        if (!els.dock) return;

        isDockOpen = false;

        // Update aria-expanded
        if (els.attachBtn) {
            els.attachBtn.setAttribute('aria-expanded', 'false');
        }

        els.dock.setAttribute('aria-hidden', 'true');

        if (activeMode === 'mobile') {
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
            setTimeout(() => {
                els.dock.classList.add('hidden');
            }, DURATION_MS);
        } else {
            els.dock.classList.add('hidden');
        }

        // Stop dictation if active
        stopDictation();
    }

    function toggleDock() {
        if (isDockOpen) {
            closeDock();
        } else {
            openDock();
        }
    }

    // === DICTATION (Web Speech API) ===

    function showToast(message) {
        // Implement toast notification
        // For now, use console + alert fallback
        console.log('[Focus Dock]', message);
        
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-[100] bg-slate-900 text-white px-4 py-2 rounded-xl shadow-2xl text-sm font-medium';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 200ms';
            setTimeout(() => toast.remove(), 200);
        }, 2500);
    }

    function startDictation() {
        const els = getElements();
        
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            showToast('Dictée non supportée sur cet appareil');
            return;
        }

        if (isDictating) {
            stopDictation();
            return;
        }

        try {
            recognition = new SpeechRecognition();
            recognition.lang = 'fr-FR';
            recognition.continuous = false;
            recognition.interimResults = true;
            recognition.maxAlternatives = 1;

            let finalTranscript = '';

            recognition.onstart = () => {
                isDictating = true;
                if (els.micro) {
                    els.micro.setAttribute('data-dictating', 'true');
                    const span = els.micro.querySelector('span');
                    if (span) span.textContent = 'Stop';
                    
                    // Add pulsing effect
                    const iconDiv = els.micro.querySelector('div');
                    if (iconDiv) {
                        iconDiv.style.animation = 'pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite';
                    }
                }
            };

            recognition.onresult = (event) => {
                let interimTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript + ' ';
                    } else {
                        interimTranscript += transcript;
                    }
                }

                // Update textarea with interim results (visual feedback)
                if (els.textarea) {
                    const current = els.textarea.value.trim();
                    const preview = (current ? current + ' ' : '') + (finalTranscript + interimTranscript).trim();
                    els.textarea.value = preview;
                    
                    // Auto-resize textarea
                    els.textarea.style.height = 'auto';
                    els.textarea.style.height = els.textarea.scrollHeight + 'px';
                }
            };

            recognition.onend = () => {
                if (isDictating && els.textarea) {
                    // Finalize text
                    const current = els.textarea.value.trim();
                    const final = (current ? current + ' ' : '') + finalTranscript.trim();
                    els.textarea.value = final;
                    
                    // Trigger input event for send button enable logic
                    els.textarea.dispatchEvent(new Event('input', { bubbles: true }));
                }
                stopDictation();
            };

            recognition.onerror = (event) => {
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

            recognition.start();
        } catch (e) {
            console.error('[Dictation] Failed to start:', e);
            showToast('Impossible de démarrer la dictée');
            stopDictation();
        }
    }

    function stopDictation() {
        if (recognition) {
            try {
                recognition.stop();
            } catch {}
            recognition = null;
        }

        isDictating = false;

        // Reset UI for both mobile and desktop
        [focusMicro, focusMicroDesktop].forEach(btn => {
            if (btn) {
                btn.setAttribute('data-dictating', 'false');
                const span = btn.querySelector('span');
                if (span) span.textContent = 'Micro';
                
                const iconDiv = btn.querySelector('div');
                if (iconDiv) {
                    iconDiv.style.animation = '';
                }
            }
        });
    }

    // === EVENT LISTENERS ===

    // Mobile
    if (attachBtn) {
        attachBtn.addEventListener('click', toggleDock);
    }

    if (focusPhoto) {
        focusPhoto.addEventListener('click', () => {
            photoInput?.click();
            closeDock();
        });
    }

    if (focusVideo) {
        focusVideo.addEventListener('click', () => {
            videoInput?.click();
            closeDock();
        });
    }

    if (focusMicro) {
        focusMicro.addEventListener('click', () => {
            startDictation();
            // Don't close dock immediately - let user see "Stop" button
        });
    }

    if (focusClose) {
        focusClose.addEventListener('click', closeDock);
    }

    if (focusDockBackdrop) {
        focusDockBackdrop.addEventListener('click', closeDock);
    }

    // Desktop
    if (attachBtnDesktop) {
        attachBtnDesktop.addEventListener('click', toggleDock);
    }

    if (focusPhotoDesktop) {
        focusPhotoDesktop.addEventListener('click', () => {
            photoInputDesktop?.click();
            closeDock();
        });
    }

    if (focusVideoDesktop) {
        focusVideoDesktop.addEventListener('click', () => {
            videoInputDesktop?.click();
            closeDock();
        });
    }

    if (focusMicroDesktop) {
        focusMicroDesktop.addEventListener('click', () => {
            startDictation();
        });
    }

    if (focusCloseDesktop) {
        focusCloseDesktop.addEventListener('click', closeDock);
    }

    // Keyboard: ESC to close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isDockOpen) {
            closeDock();
            e.preventDefault();
        }
    });

    // Window resize: update mode
    window.addEventListener('resize', updateActiveMode);

    return {
        open: openDock,
        close: closeDock,
        toggle: toggleDock,
        isOpen: () => isDockOpen,
    };
}
