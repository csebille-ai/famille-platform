        <div id="chatAttachSheet" class="fixed inset-0 z-50 hidden" aria-hidden="true">
            <div id="chatAttachBackdrop" class="absolute inset-0 bg-black/30 backdrop-blur-sm opacity-0 transition-opacity duration-[220ms] ease-out motion-reduce:transition-none"></div>
            <div class="absolute inset-x-0 bottom-0 flex justify-center">
                <div
                    id="chatAttachPanel"
                    class="w-full max-w-[560px] rounded-t-3xl bg-[color:var(--fam-surface-alt)] border border-[color:var(--fam-border-soft)] shadow-[0_-18px_55px_rgba(15,23,42,0.18)] p-4 pb-[calc(env(safe-area-inset-bottom)+16px)] opacity-0 translate-y-6 transition-[transform,opacity] duration-[220ms] ease-out motion-reduce:transition-none motion-reduce:transform-none"
                    role="dialog"
                    aria-label="Partager un fichier"
                >
                    <div class="mx-auto h-1 w-9 rounded-full bg-black/10"></div>

                    <div class="mt-3 flex items-baseline justify-between gap-3">
                        <div class="text-base font-semibold text-[color:var(--fam-text)]">Partager un fichier</div>
                        <div id="chatAttachQuota" class="text-xs text-slate-500 font-medium"></div>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <button
                            type="button"
                            id="chatAttachPickMedia"
                            class="group w-full inline-flex items-center gap-4 rounded-2xl border border-[color:var(--fam-border)] bg-gradient-to-br from-blue-50 to-cyan-50 hover:from-blue-100 hover:to-cyan-100 p-4 text-left transition-[transform,background] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--fam-surface-alt)]"
                        >
                            <div class="shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center shadow-sm">
                                <i class="ph-fill ph-image text-[26px] text-white" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-slate-900">Photo ou vidéo</div>
                                <div class="mt-0.5 text-xs text-slate-600">Depuis votre galerie</div>
                            </div>
                            <i class="ph ph-caret-right text-lg text-slate-400 group-hover:text-slate-500" aria-hidden="true"></i>
                        </button>

                        <button
                            type="button"
                            id="chatAttachPickVoice"
                            class="group w-full inline-flex items-center gap-4 rounded-2xl border border-[color:var(--fam-border)] bg-gradient-to-br from-purple-50 to-pink-50 hover:from-purple-100 hover:to-pink-100 p-4 text-left transition-[transform,background] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.35)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--fam-surface-alt)]"
                        >
                            <div class="shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center shadow-sm">
                                <i class="ph-fill ph-microphone text-[26px] text-white" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-slate-900">Message vocal</div>
                                <div class="mt-0.5 text-xs text-slate-600">Enregistrer un audio</div>
                            </div>
                            <i class="ph ph-caret-right text-lg text-slate-400 group-hover:text-slate-500" aria-hidden="true"></i>
                        </button>
                    </div>

                    <button
                        type="button"
                        id="chatAttachCancel"
                        class="mt-4 w-full h-11 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-white/60 active:bg-white/75 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:rgba(14,165,160,0.30)] focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--fam-surface-alt)]"
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
                <button type="button" id="chatHeaderMenuConversations" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M224,48H32a8,8,0,0,0-8,8V192a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A8,8,0,0,0,224,48Zm-96,85.15L52.57,64H203.43ZM98.71,128,40,181.81V74.19Zm11.84,10.85,12,11.05a8,8,0,0,0,10.82,0l12-11.05,58,53.15H52.57ZM157.29,128,216,74.18V181.82Z"></path></svg>
                    <span>Voir les conversations</span>
                </button>
                <div class="h-px bg-slate-100 my-1"></div>
                <button type="button" id="chatHeaderMenuSearch" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M229.66,218.34l-50.07-50.06a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z"></path></svg>
                    <span>Rechercher</span>
                </button>
                <button type="button" id="chatHeaderMenuInfo" class="w-full text-left rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-900 hover:bg-[color:rgba(14,165,160,0.10)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm16-40a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176ZM112,84a12,12,0,1,1,12,12A12,12,0,0,1,112,84Z"></path></svg>
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
                    <video id="chatMediaVideo" class="hidden max-h-full max-w-full rounded-2xl bg-black/20" controls playsinline autoplay></video>
                </div>
            </div>
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

        <!-- Visibility Sheet -->
        <div id="chatVisibilitySheet" class="fixed inset-0 z-50 hidden" aria-hidden="true">
            <div id="chatVisibilityBackdrop" class="absolute inset-0 bg-black/30 backdrop-blur-sm opacity-0 transition-opacity duration-200"></div>
            <div class="absolute inset-x-0 bottom-0 flex justify-center">
                <div
                    id="chatVisibilityPanel"
                    class="w-full max-w-[560px] rounded-t-3xl bg-white border border-slate-200 shadow-[0_-18px_55px_rgba(15,23,42,0.18)] px-4 pt-2 pb-[calc(env(safe-area-inset-bottom)+16px)] max-h-[85vh] flex flex-col opacity-0 translate-y-6 transition-[transform,opacity] duration-200 ease-out"
                    role="dialog"
                    aria-label="Visibilité du salon"
                >
                    <div class="mx-auto h-1 w-10 rounded-full bg-slate-300 mb-3"></div>
                    <div class="text-lg font-semibold text-slate-900 mb-4">Visibilité du salon</div>

                    <div class="flex-1 min-h-0 overflow-y-auto">
                        <!-- Radio Public -->
                        <label class="flex items-start gap-3 p-3 rounded-2xl border-2 border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors">
                            <input type="radio" name="chatVisibilityMode" value="public" checked class="mt-0.5 h-5 w-5 text-teal-600 focus:ring-teal-600" />
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-slate-900">Public</div>
                                <div class="text-xs text-slate-600 mt-0.5">Visible et accessible à tous les membres.</div>
                            </div>
                        </label>

                        <!-- Radio Privé -->
                        <label class="mt-3 flex items-start gap-3 p-3 rounded-2xl border-2 border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors">
                            <input type="radio" name="chatVisibilityMode" value="private" class="mt-0.5 h-5 w-5 text-teal-600 focus:ring-teal-600" />
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-slate-900">Privé (participants)</div>
                                <div class="text-xs text-slate-600 mt-0.5">Visible uniquement pour les membres sélectionnés.</div>
                            </div>
                        </label>

                        <!-- Warning si privé -->
                        <div id="chatVisibilityWarning" class="hidden mt-3 p-3 rounded-2xl bg-amber-50 border border-amber-200">
                            <div class="flex gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="text-amber-600 shrink-0" viewBox="0 0 256 256"><path d="M236.8,188.09,149.35,36.22h0a24.76,24.76,0,0,0-42.7,0L19.2,188.09a23.51,23.51,0,0,0,0,23.72A24.35,24.35,0,0,0,40.55,224h174.9a24.35,24.35,0,0,0,21.33-12.19A23.51,23.51,0,0,0,236.8,188.09ZM120,104a8,8,0,0,1,16,0v40a8,8,0,0,1-16,0Zm8,88a12,12,0,1,1,12-12A12,12,0,0,1,128,192Z"></path></svg>
                                <div class="text-xs text-amber-800">Les membres non sélectionnés ne verront plus ce salon.</div>
                            </div>
                        </div>

                        <!-- Participants (inline si privé) -->
                        <div id="chatVisibilityParticipants" class="hidden mt-4">
                            <div class="text-sm font-medium text-slate-900 mb-2">Participants</div>
                            
                            <!-- Pills sélectionnés -->
                            <div id="chatVisibilityPills" class="flex flex-wrap gap-2 mb-3 empty:hidden">
                                <!-- Pills générées dynamiquement -->
                            </div>

                            <!-- Message helper si pas assez de participants -->
                            <div id="chatVisibilityHelper" class="hidden text-xs text-red-600 mb-3">
                                Ajoute au moins 1 autre personne.
                            </div>

                            <!-- Accordéon "Ajouter un membre…" -->
                            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                <button
                                    type="button"
                                    id="chatVisibilityToggleList"
                                    class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                                >
                                    <span>Ajouter un membre…</span>
                                    <svg id="chatVisibilityToggleIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256" class="transition-transform duration-200"><path d="M213.66,101.66l-80,80a8,8,0,0,1-11.32,0l-80-80A8,8,0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8,0,0,1,11.32,11.32Z"></path></svg>
                                </button>
                                
                                <div id="chatVisibilityMemberList" class="hidden border-t border-slate-200 max-h-64 overflow-y-auto">
                                    <!-- Liste avec checkboxes générée dynamiquement -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div class="shrink-0 flex gap-2 mt-4 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            id="chatVisibilityCancel"
                            class="flex-1 h-11 rounded-2xl border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            id="chatVisibilityConfirm"
                            class="flex-1 h-11 rounded-2xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Confirmer
                        </button>
                    </div>
                </div>
            </div>
        </div>
