<div class="hidden sm:block border-t border-slate-100 bg-white sticky bottom-0 z-40">
                <div class="px-4 sm:px-6 py-3">
                    <div id="chatSoloHintDesktop" class="hidden mb-2 text-xs text-slate-500"></div>
                    <form id="chatFormDesktop" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                        @csrf
                        <button
                            type="button"
                            id="chatAttachBtnDesktop"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]"
                            aria-label="Ajouter"
                            title="Ajouter"
                        >
                            ＋
                        </button>

                        <div class="flex-1 min-w-0">
                            <div id="chatQuickTypeDesktop" class="hidden mb-2">
                                <div id="chatQuickTypeListDesktop" role="listbox" aria-label="Suggestions" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1"></div>
                            </div>

                            <div class="rounded-full border border-slate-200 bg-white px-4 py-2">
                                <textarea
                                    id="bodyDesktop"
                                    name="body"
                                    rows="1"
                                    class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6"
                                    placeholder="Écrire un message…"
                                    required
                                >{{ old('body') }}</textarea>
                            </div>
                        </div>

                        <input type="file" id="chatAttachInputDesktop" class="hidden" accept="image/*,video/*" />

                        <button
                            type="submit"
                            id="chatSendBtnDesktop"
                            class="w-11 h-11 rounded-full inline-flex items-center justify-center bg-slate-900 text-white font-semibold disabled:opacity-50"
                            aria-label="Envoyer"
                            title="Envoyer"
                            disabled
                        >
                            <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                        </button>

                        <x-input-error class="mt-2" :messages="$errors->get('body')" />
                    </form>
                </div>
            </div>
        </div>
