        <div id="chatAttachSheet" class="fixed inset-0 z-50 hidden" aria-hidden="true">
            <div id="chatAttachBackdrop" class="absolute inset-0 bg-black/30 backdrop-blur-sm opacity-0 transition-opacity duration-[220ms] ease-out motion-reduce:transition-none"></div>
            <div class="absolute inset-x-0 bottom-0 flex justify-center">
                <div
                    id="chatAttachPanel"
                    class="w-full max-w-[560px] rounded-t-3xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)] shadow-[0_-18px_55px_rgba(15,23,42,0.18)] p-4 pb-[calc(env(safe-area-inset-bottom)+16px)] opacity-0 translate-y-6 transition-[transform,opacity] duration-[220ms] ease-out motion-reduce:transition-none motion-reduce:transform-none"
                    role="dialog"
                    aria-label="Ajouter"
                >
                    <div class="mx-auto h-1 w-9 rounded-full bg-black/10"></div>

                    <div class="mt-3">
                        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Ajouter</div>
                        <div id="chatAttachQuota" class="mt-1 text-xs text-slate-600"></div>
                    </div>

                    <div class="mt-3 grid gap-2">
                        <button
                            type="button"
                            id="chatAttachPickMedia"
                            class="group w-full h-14 inline-flex items-center justify-between rounded-2xl border border-[color:var(--fam-border)] bg-white px-4 text-sm font-semibold text-[color:var(--fam-text)] transition-[transform,background-color,border-color] hover:bg-[color:var(--fam-tint)] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--fam-surface-alt)]"
                        >
                            <span>Photo / Vidéo</span>
                            <i class="ph ph-image text-[22px] text-slate-500 transition-colors group-active:text-[color:var(--fam-primary)] group-focus-visible:text-[color:var(--fam-primary)]" aria-hidden="true"></i>
                        </button>

                        <button
                            type="button"
                            id="chatAttachPickVoice"
                            class="group w-full h-14 inline-flex items-center justify-between rounded-2xl border border-[color:var(--fam-border)] bg-white px-4 text-sm font-semibold text-[color:var(--fam-text)] transition-[transform,background-color,border-color] hover:bg-[color:var(--fam-tint)] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--fam-surface-alt)]"
                        >
                            <span>Dicter</span>
                            <i class="ph ph-microphone text-[22px] text-slate-500 transition-colors group-active:text-[color:var(--fam-primary)] group-focus-visible:text-[color:var(--fam-primary)]" aria-hidden="true"></i>
                        </button>
                    </div>

                    <button
                        type="button"
                        id="chatAttachCancel"
                        class="mt-3 w-full h-11 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-white/60 active:bg-white/75 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.30)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--fam-surface-alt)]"
                    >
                        Annuler
                    </button>
                </div>
            </div>
        </div>

        <div id="chatInfoModal" class="fixed inset-0 z-50 hidden">
            <div id="chatInfoBackdrop" class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-x-0 bottom-0 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:top-24 sm:bottom-auto w-full sm:w-[420px] bg-white rounded-t-3xl sm:rounded-3xl p-4 shadow-2xl">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-900">Participants</div>
                    <button type="button" id="chatInfoClose" class="w-9 h-9 rounded-full inline-flex items-center justify-center text-slate-600 hover:bg-[color:rgba(14,165,160,0.10)]" aria-label="Fermer" title="Fermer">✕</button>
                </div>
                <div class="mt-1 text-xs text-slate-500"><span id="chatInfoCount">0</span> en ligne</div>
                <div id="chatInfoList" class="mt-3 space-y-2"></div>
            </div>
        </div>

        <div id="chatHeaderMenu" class="fixed inset-0 z-[60] hidden pointer-events-none" aria-hidden="true">
            <div id="chatHeaderMenuBackdrop" class="absolute inset-0"></div>
            <div id="chatHeaderMenuPanel" class="absolute pointer-events-auto min-w-[14rem] rounded-2xl border border-slate-200 bg-white shadow-2xl p-1">
                <button type="button" id="chatHeaderMenuSearch" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)] flex items-center gap-2">
                    <i class="ph ph-magnifying-glass text-base" aria-hidden="true"></i>
                    <span>Rechercher</span>
                </button>
                <button type="button" id="chatHeaderMenuInfo" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)] flex items-center gap-2">
                    <i class="ph ph-info text-base" aria-hidden="true"></i>
                    <span>Infos & participants</span>
                </button>
            </div>
        </div>

        <div id="chatMediaModal" class="fixed inset-0 z-[60] hidden">
            <div id="chatMediaBackdrop" class="absolute inset-0 bg-black/80"></div>
            <div class="absolute inset-0 flex flex-col">
                <div class="shrink-0 flex items-center justify-between gap-3 p-3 sm:p-4 text-white">
                    <div id="chatMediaTitle" class="text-sm font-semibold truncate"></div>
                    <div class="flex items-center gap-2">
                        <a id="chatMediaOpenLink" href="#" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold hover:bg-white/15">Ouvrir</a>
                        <button type="button" id="chatMediaClose" class="w-9 h-9 rounded-full inline-flex items-center justify-center bg-white/10 hover:bg-white/15" aria-label="Fermer" title="Fermer">✕</button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 flex items-center justify-center p-3 sm:p-6">
                    <img id="chatMediaImg" class="hidden max-h-full max-w-full object-contain rounded-2xl bg-black/20" alt="" />
                    <video id="chatMediaVideo" class="hidden max-h-full max-w-full rounded-2xl bg-black/20" controls playsinline></video>
                </div>
            </div>
        </div>

        <div id="chatQuickTypeMenu" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
            <div id="chatQuickTypeMenuBackdrop" class="absolute inset-0"></div>
            <div id="chatQuickTypeMenuPanel" role="menu" aria-label="Actions suggestion" class="absolute min-w-[14rem] rounded-2xl border border-slate-200 bg-white shadow-xl p-1">
                <div id="chatQuickTypeMenuMain">
                    <button type="button" data-qt-action="insert" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]">Insérer</button>
                    <button type="button" data-qt-action="copy" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]">Copier</button>
                    <button type="button" data-qt-action="pin" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]">Épingler</button>
                    <button type="button" data-qt-action="unpin" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]">Désépingler</button>
                    <div class="h-px bg-slate-100 my-1"></div>
                    <button type="button" data-qt-action="remove_recent" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]">Retirer des récents</button>
                    <button type="button" data-qt-action="hide" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Masquer</button>
                    <div class="h-px bg-slate-100 my-1"></div>
                    <button type="button" data-qt-action="manage_hidden" role="menuitem" class="w-full text-left rounded-xl px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]">Gérer les masqués…</button>
                </div>

                <div id="chatQuickTypeMenuHidden" class="hidden">
                    <div class="flex items-center justify-between gap-2 px-2 py-2">
                        <button type="button" data-qt-action="hidden_back" class="rounded-xl px-2 py-1 text-sm font-semibold text-slate-700 hover:bg-[color:rgba(14,165,160,0.10)]">← Retour</button>
                        <div class="text-sm font-semibold text-slate-900">Masqués</div>
                        <button type="button" data-qt-action="hidden_clear" class="rounded-xl px-2 py-1 text-sm font-semibold text-red-600 hover:bg-red-50">Tout rétablir</button>
                    </div>
                    <div class="h-px bg-slate-100 my-1"></div>
                    <div id="chatQuickTypeHiddenList" class="max-h-64 overflow-auto"></div>
                </div>
            </div>
        </div>

        <div id="chatQuickTypeToast" class="fixed inset-x-0 bottom-[calc(var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom)+0.75rem)] sm:bottom-6 z-[70] pointer-events-none hidden">
            <div class="mx-auto w-fit rounded-full bg-slate-900 text-white px-3 py-1.5 text-xs font-semibold shadow-lg">Copié</div>
        </div>

        <div id="chatReactionBar" class="fixed inset-0 z-[78] hidden pointer-events-none" aria-hidden="true">
            <div id="chatReactionBarPanel" class="absolute pointer-events-auto rounded-2xl border border-slate-200 bg-white shadow-2xl px-2 py-1.5">
                <div class="flex items-center gap-1.5">
                    @foreach(\App\Services\ChatReactions::BASE_EMOJIS as $e)
                        <button type="button" class="w-10 h-10 rounded-xl border border-transparent hover:bg-[color:rgba(14,165,160,0.10)] text-xl" data-reaction-bar-pick="{{ $e }}" aria-label="Réagir {{ $e }}">{{ $e }}</button>
                    @endforeach
                    <button type="button" class="w-10 h-10 rounded-xl hover:bg-[color:rgba(14,165,160,0.10)] text-sm font-bold text-slate-700" data-reaction-bar-more aria-label="Plus">＋</button>
                </div>
            </div>
        </div>

        <div id="chatActionMenu" class="fixed inset-0 z-[79] hidden pointer-events-none" aria-hidden="true">
            <div id="chatActionMenuPanel" class="absolute pointer-events-auto w-[min(92vw,280px)] rounded-2xl border border-slate-200 bg-white shadow-2xl p-1">
                <button type="button" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]" data-action-menu="copy">Copier</button>
                <button type="button" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]" data-action-menu="reply">Répondre</button>
                <button type="button" id="chatActionDm" class="hidden w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]" data-action-menu="dm">Répondre en privé</button>
                <div class="h-px bg-slate-100 my-1"></div>
                <button type="button" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)]" data-action-menu="delete_me">Supprimer pour moi</button>
                <button type="button" id="chatActionDeleteAll" class="hidden w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50" data-action-menu="delete_all">Supprimer pour tout le monde</button>
            </div>
        </div>

        <div id="chatReactionsPicker" class="fixed inset-0 z-[80] hidden pointer-events-none" aria-hidden="true">
            <div id="chatReactionsPickerPanel" class="absolute pointer-events-auto rounded-2xl border border-slate-200 bg-white shadow-2xl px-2 py-2">
                <div class="flex items-center gap-1.5">
                    @foreach(\App\Services\ChatReactions::BASE_EMOJIS as $e)
                        <button type="button" class="w-10 h-10 rounded-xl hover:bg-[color:rgba(14,165,160,0.10)] text-xl" data-reaction-pick="{{ $e }}" aria-label="Réagir {{ $e }}">{{ $e }}</button>
                    @endforeach
                    <button type="button" class="w-10 h-10 rounded-xl hover:bg-[color:rgba(14,165,160,0.10)] text-sm font-bold text-slate-700" data-reaction-more aria-label="Plus">＋</button>
                </div>
                <div id="chatReactionsMore" class="hidden mt-2 pt-2 border-t border-slate-100">
                    <div class="grid grid-cols-8 gap-1">
                        @foreach(['🎉','🔥','😍','🤩','😎','🤔','😅','😭','👏','✅','❌','💯','💪','✨','🫶','🤝'] as $e)
                            <button type="button" class="w-9 h-9 rounded-xl hover:bg-[color:rgba(14,165,160,0.10)] text-lg" data-reaction-pick="{{ $e }}" aria-label="Réagir {{ $e }}">{{ $e }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div id="chatReactionUsersPopover" class="fixed inset-0 z-[85] hidden pointer-events-none" aria-hidden="true">
            <div id="chatReactionUsersPanel" class="absolute pointer-events-auto w-[min(92vw,340px)] rounded-2xl border border-slate-200 bg-white shadow-2xl p-3">
                <div class="flex items-center justify-between gap-3">
                    <div id="chatReactionUsersHeader" class="text-sm font-semibold text-slate-900"></div>
                    <button type="button" id="chatReactionUsersClose" class="w-8 h-8 rounded-full inline-flex items-center justify-center text-slate-600 hover:bg-[color:rgba(14,165,160,0.10)]" aria-label="Fermer" title="Fermer">✕</button>
                </div>
                <div id="chatReactionUsersBody" class="mt-2"></div>
            </div>
        </div>

        
