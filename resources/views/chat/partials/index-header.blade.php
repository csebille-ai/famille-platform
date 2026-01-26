            <div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-100">
                <div style="padding-top: calc(env(safe-area-inset-top) + 0.5rem)">
                    <div class="h-14 px-4 sm:px-6 pb-2 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        id="chatBackBtn"
                        class="inline-flex items-center gap-1.5 px-2 py-2 min-h-[44px] -ml-2 text-slate-600 hover:text-slate-900 transition-colors active:scale-[0.98]"
                        aria-label="Retour aux conversations"
                        title="Retour aux conversations"
                    >
                        <i class="ph ph-arrow-left text-xl" aria-hidden="true"></i>
                        <span class="text-sm font-semibold">Conversations</span>
                    </button>

                    <div class="min-w-0 flex-1 text-center">
                        <div class="text-sm sm:text-base font-semibold text-gray-900 leading-tight">Famille</div>
                        <div class="text-xs text-slate-500 leading-tight">
                            <span class="text-emerald-600">●</span>
                            <span id="chatOnlineCount" class="font-semibold text-gray-900">{{ ($onlineList ?? collect())->count() }}</span>
                            <span id="chatPresenceLabel">en ligne</span>
                        </div>
                    </div>

                    <button
                        type="button"
                        id="chatMenuBtn"
                        class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)] transition-colors"
                        aria-label="Menu"
                        title="Menu"
                    >
                        <i class="ph ph-dots-three" aria-hidden="true"></i>
                    </button>
                    </div>
                </div>

                <div id="chatSearchBar" class="hidden px-4 sm:px-6 pb-3">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2">
                        <input
                            type="search"
                            id="chatSearchInput"
                            class="w-full border-0 p-0 focus:ring-0 text-sm"
                            placeholder="Rechercher dans la conversation…"
                        />
                    </div>
                </div>
            </div>
