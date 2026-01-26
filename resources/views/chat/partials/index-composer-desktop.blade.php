<div class="hidden sm:block border-t border-slate-100 bg-white sticky bottom-0 z-40">
                <div class="px-4 sm:px-6 py-3">
                    <div id="chatSoloHintDesktop" class="hidden mb-2 text-xs text-slate-500"></div>
                    <form id="chatFormDesktop" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                        @csrf
                        <button
                            type="button"
                            id="chatAttachBtnDesktop"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)] transition-colors"
                            aria-label="Ouvrir actions"
                            aria-expanded="false"
                            aria-controls="chatFocusDockDesktop"
                            title="Actions"
                        >
                            ＋
                        </button>

                        <div class="flex-1 min-w-0">
                            <div class="rounded-full border border-slate-200 bg-white px-4 py-2">
                                <textarea
                                    id="bodyDesktop"
                                    name="body"
                                    rows="1"
                                    class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6"
                                    placeholder="Votre message…"
                                    spellcheck="false"
                                    autocomplete="off"
                                    required
                                >{{ old('body') }}</textarea>
                            </div>
                        </div>

                        <input type="file" id="chatPhotoInputDesktop" class="hidden" accept="image/*" />
                        <input type="file" id="chatVideoInputDesktop" class="hidden" accept="video/*" />

                        <button
                            type="submit"
                            id="chatSendBtnDesktop"
                            class="w-11 h-11 rounded-full inline-flex items-center justify-center bg-slate-900 text-white font-semibold disabled:opacity-50 transition-opacity"
                            aria-label="Envoyer"
                            title="Envoyer"
                            disabled
                        >
                            <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                        </button>

                        <x-input-error class="mt-2" :messages="$errors->get('body')" />
                    </form>

                    <!-- Focus Dock Desktop (3 actions) -->
                    <div id="chatFocusDockDesktop" class="hidden absolute bottom-full left-4 right-4 mb-2" role="dialog" aria-label="Actions">
                        <div id="chatFocusDockPanelDesktop" class="bg-[color:var(--fam-surface-alt)]/95 backdrop-blur-md rounded-2xl border border-[color:var(--fam-border)] shadow-[0_20px_50px_rgba(33,24,16,0.12)] p-3 opacity-0 scale-95 transition-[opacity,transform] duration-200 origin-bottom">
                            <div class="grid grid-cols-3 gap-3 mb-3">
                                <button
                                    type="button"
                                    id="chatFocusPhotoDesktop"
                                    class="group w-full rounded-2xl bg-white border border-slate-200/60 px-3 py-3.5 text-center active:scale-[0.99] transition hover:shadow-md hover:border-teal-600/20 focus:outline-none focus:ring-2 focus:ring-teal-600/30"
                                >
                                    <div class="mb-1.5 mx-auto inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600/10 text-teal-600 group-hover:bg-teal-600/15">
                                        <i class="ph-fill ph-image text-[20px]" aria-hidden="true"></i>
                                    </div>
                                    <div class="text-[13px] font-medium text-slate-900">Photo</div>
                                </button>

                                <button
                                    type="button"
                                    id="chatFocusVideoDesktop"
                                    class="group w-full rounded-2xl bg-white border border-slate-200/60 px-3 py-3.5 text-center active:scale-[0.99] transition hover:shadow-md hover:border-teal-600/20 focus:outline-none focus:ring-2 focus:ring-teal-600/30"
                                >
                                    <div class="mb-1.5 mx-auto inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600/10 text-teal-600 group-hover:bg-teal-600/15">
                                        <i class="ph-fill ph-video-camera text-[20px]" aria-hidden="true"></i>
                                    </div>
                                    <div class="text-[13px] font-medium text-slate-900">Vidéo</div>
                                </button>

                                <button
                                    type="button"
                                    id="chatFocusMicroDesktop"
                                    class="group w-full rounded-2xl bg-white border border-slate-200/60 px-3 py-3.5 text-center active:scale-[0.99] transition hover:shadow-md hover:border-teal-600/20 focus:outline-none focus:ring-2 focus:ring-teal-600/30"
                                    data-dictating="false"
                                >
                                    <div class="mb-1.5 mx-auto inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600/10 text-teal-600 group-hover:bg-teal-600/15">
                                        <i class="ph-fill ph-microphone text-[20px]" aria-hidden="true"></i>
                                    </div>
                                    <div class="text-[13px] font-medium text-slate-900">Micro</div>
                                </button>
                            </div>

                            <button
                                type="button"
                                id="chatFocusCloseDesktop"
                                class="w-full rounded-2xl border border-[color:var(--fam-border)] bg-transparent py-2 text-sm font-medium text-[color:var(--fam-muted)] hover:bg-[color:var(--fam-primary)]/6 transition-colors focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/30"
                            >
                                Fermer
                            </button>
                        </div>
                    </div>
