<div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-100">
                <div style="padding-top: calc(env(safe-area-inset-top) + 0.5rem)">
                    <div class="h-14 px-4 sm:px-6 pb-2 grid grid-cols-[44px_1fr_44px] items-center gap-3">
                        <!-- Left: Logo -->
                        <a href="{{ url('/') }}" class="flex items-center justify-center" aria-label="Accueil">
                            <img src="{{ asset('icon-192.png') }}" alt="Logo" class="w-8 h-8 rounded-full" />
                        </a>

                        <!-- Center content -->
                        <div class="flex flex-col items-center justify-center leading-tight">
                            <div class="flex items-center gap-2">
                                <div class="text-sm sm:text-base font-semibold text-gray-900">Famille</div>
                                
                                @can('manage-users')
                                    <button
                                        type="button"
                                        id="chatVisibilityChip"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-600/10 text-teal-700 border border-teal-600/20 hover:bg-teal-600/15 transition-colors"
                                        aria-label="Changer la visibilité du salon"
                                    >
                                        <span id="chatVisibilityLabel">Public</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 256 256"><path d="M213.66,101.66l-80,80a8,8,0,0,1-11.32,0l-80-80A8,8,0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8,0,0,1,11.32,11.32Z"></path></svg>
                                    </button>
                                @else
                                    <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-600/10 text-teal-700 border border-teal-600/20 opacity-80 pointer-events-none">
                                        <span id="chatVisibilityLabel">Public</span>
                                    </div>
                                @endcan
                            </div>
                            
                            <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-1">
                                <span class="text-emerald-600">●</span>
                                <span id="chatOnlineCount" class="font-semibold text-gray-900">{{ ($onlineList ?? collect())->count() }}</span>
                                <span id="chatPresenceLabel">en ligne</span>
                            </div>
                        </div>

                        <!-- Right menu button -->
                        <button
                            type="button"
                            id="chatMenuBtn"
                            class="w-10 h-10 rounded-full inline-flex items-center justify-center border border-black/10 bg-white text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)] transition-colors justify-self-end"
                            aria-label="Menu"
                            title="Menu"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256"><path d="M144,128a16,16,0,1,1-16-16A16,16,0,0,1,144,128ZM60,112a16,16,0,1,0,16,16A16,16,0,0,0,60,112Zm136,0a16,16,0,1,0,16,16A16,16,0,0,0,196,112Z"></path></svg>
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