<div class="px-4 py-2">
            <div id="chatSoloHint" class="hidden mb-2 text-xs text-slate-500"></div>
            <form id="chatForm" method="POST" action="{{ route('chat.store') }}" class="flex items-end gap-2">
                @csrf
                <button
                    type="button"
                    id="chatAttachBtn"
                    class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]"
                    aria-label="Ajouter"
                    title="Ajouter"
                >
                    ＋
                </button>

                <div class="flex-1 min-w-0">
                    <div id="chatQuickType" class="hidden mb-2">
                        <div id="chatQuickTypeList" role="listbox" aria-label="Suggestions" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1"></div>
                    </div>

                    <div class="rounded-full border border-slate-200 bg-white px-4 py-2">
                        <textarea
                            id="body"
                            name="body"
                            rows="1"
                            class="block w-full resize-none border-0 p-0 focus:ring-0 text-sm leading-6 max-h-28"
                            placeholder="Écrire un message…"
                            required
                        >{{ old('body') }}</textarea>
                    </div>
                </div>

                <input type="file" id="chatAttachInput" class="hidden" accept="image/*,video/*" />

                <button
                    type="submit"
                    id="chatSendBtn"
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
