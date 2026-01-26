<div class="px-4 py-2">
            <div id="chatSoloHint" class="hidden mb-2 text-xs text-slate-500"></div>
            <form id="chatForm" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                @csrf
                <button
                    type="button"
                    id="chatAttachBtn"
                    class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)] transition-colors"
                    aria-label="Ouvrir actions"
                    aria-expanded="false"
                    aria-controls="chatFocusDock"
                    title="Actions"
                >
                    ＋
                </button>

                <div class="flex-1 min-w-0">
                    <div class="rounded-full border border-slate-200 bg-white px-4 py-2">
                        <textarea
                            id="body"
                            name="body"
                            rows="1"
                            class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6 max-h-28"
                            placeholder="Votre message…"
                            spellcheck="false"
                            autocomplete="off"
                            required
                        >{{ old('body') }}</textarea>
                    </div>
                </div>

                <input type="file" id="chatPhotoInput" class="hidden" accept="image/*" />
                <input type="file" id="chatVideoInput" class="hidden" accept="video/*" />

                <button
                    type="submit"
                    id="chatSendBtn"
                    class="w-11 h-11 rounded-full inline-flex items-center justify-center bg-slate-900 text-white font-semibold disabled:opacity-50 transition-opacity"
                    aria-label="Envoyer"
                    title="Envoyer"
                    disabled
                >
                    <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                </button>

                <x-input-error class="mt-2" :messages="$errors->get('body')" />
            </form>

            <!-- Focus Dock (3 actions) -->
            <div id="chatFocusDock" class="hidden fixed inset-x-0 bottom-0 z-[45]" role="dialog" aria-label="Actions">
                <div id="chatFocusDockBackdrop" class="absolute inset-0 bg-black/20 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
                <div id="chatFocusDockPanel" class="relative bg-gradient-to-br from-white to-slate-50/80 rounded-t-3xl border-t border-slate-200/60 shadow-[0_-8px_32px_rgba(15,23,42,0.12)] p-4 pb-[calc(env(safe-area-inset-bottom)+16px)] opacity-0 translate-y-6 transition-[opacity,transform] duration-220 ease-out">
                    <div class="mx-auto h-1 w-9 rounded-full bg-slate-300/60 mb-4"></div>
                    
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <button
                            type="button"
                            id="chatFocusPhoto"
                            class="flex flex-col items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-blue-50 to-cyan-50 hover:from-blue-100 hover:to-cyan-100 active:scale-[0.97] border border-blue-200/40 p-4 min-h-[72px] transition-[transform,background] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400/50 focus-visible:ring-offset-2"
                        >
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center shadow-sm">
                                <i class="ph-fill ph-image text-[22px] text-white" aria-hidden="true"></i>
                            </div>
                            <span class="text-sm font-semibold text-slate-900">Photo</span>
                        </button>

                        <button
                            type="button"
                            id="chatFocusVideo"
                            class="flex flex-col items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 active:scale-[0.97] border border-purple-200/40 p-4 min-h-[72px] transition-[transform,background] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/50 focus-visible:ring-offset-2"
                        >
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-sm">
                                <i class="ph-fill ph-video-camera text-[22px] text-white" aria-hidden="true"></i>
                            </div>
                            <span class="text-sm font-semibold text-slate-900">Vidéo</span>
                        </button>

                        <button
                            type="button"
                            id="chatFocusMicro"
                            class="flex flex-col items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-50 to-emerald-50 hover:from-teal-100 hover:to-emerald-100 active:scale-[0.97] border border-teal-200/40 p-4 min-h-[72px] transition-[transform,background] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-400/50 focus-visible:ring-offset-2"
                            data-dictating="false"
                        >
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-500 to-emerald-500 flex items-center justify-center shadow-sm">
                                <i class="ph-fill ph-microphone text-[22px] text-white" aria-hidden="true"></i>
                            </div>
                            <span class="text-sm font-semibold text-slate-900">Micro</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        id="chatFocusClose"
                        class="w-full h-11 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-white/60 active:bg-white/80 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400/30 focus-visible:ring-offset-2"
                    >
                        Fermer
                    </button>
                </div>
            </div>
        </div>
