<x-app-layout pageBgClass="fam-page-bg">
    <div class="px-4 sm:px-6 py-4">
        <div class="max-w-2xl mx-auto space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <div class="text-lg font-extrabold text-[color:var(--fam-text)]">Conversations</div>
                    <div class="text-sm text-slate-600">Public et messages privés</div>
                </div>
            </div>

            <a href="{{ route('chat.index') }}" class="block rounded-2xl border border-[color:var(--fam-border-soft)] bg-white shadow-sm p-4 hover:bg-[color:var(--fam-tint)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-slate-900">Famille</div>
                        <div class="mt-0.5 text-xs text-slate-600">Public</div>
                    </div>
                    <div class="shrink-0 text-slate-500">→</div>
                </div>
            </a>

            <div class="rounded-2xl border border-[color:var(--fam-border-soft)] bg-gradient-to-br from-slate-50 to-slate-100/50 shadow-sm">
                <div class="px-4 py-3 border-b border-slate-200/60">
                    <div class="text-sm font-bold text-slate-900">Privé</div>
                    <div class="text-xs text-slate-600">Discussions 1:1</div>
                </div>

                <div class="divide-y divide-slate-200/40">
                    @if(($dmThreads ?? collect())->count() === 0)
                        <div class="px-4 py-6 text-sm text-slate-600">Aucune discussion privée pour l’instant.</div>
                    @else
                        @foreach($dmThreads as $t)
                            <a href="{{ route('chat.dm', ['user' => (int) $t['other_id']]) }}" class="block px-4 py-3 hover:bg-white/70 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full overflow-hidden bg-white border border-slate-200 flex items-center justify-center shrink-0 shadow-sm">
                                        @if(!empty($t['other_avatar_url']))
                                            <img src="{{ $t['other_avatar_url'] }}" alt="" class="w-full h-full object-cover" loading="lazy" />
                                        @else
                                            <div class="text-xs font-extrabold text-slate-700">DM</div>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-slate-900 truncate">{{ $t['other_name'] }}</div>
                                        @php
                                            $preview = trim((string) ($t['last_message_body'] ?? ''));
                                            $preview = preg_replace('/^\\[\\[ATTACHMENT\\]\\].*$/', '📎 Pièce jointe', $preview);
                                        @endphp
                                        <div class="mt-0.5 text-xs text-slate-600 truncate">{{ $preview }}</div>
                                    </div>

                                    <div class="shrink-0 text-slate-500">→</div>
                                </div>
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>

            <a href="{{ route('chat.legacy') }}" class="block text-center text-xs text-slate-600 hover:text-slate-900">
                Ouvrir le mode ancien (ciblage / groupes)
            </a>
        </div>
    </div>
</x-app-layout>
