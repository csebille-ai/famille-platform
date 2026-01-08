@php
    $short = function (?string $s, int $max = 80): string {
        $s = trim((string) $s);
        if ($s === '') return '';
        if (mb_strlen($s) <= $max) return $s;
        return mb_substr($s, 0, $max - 1) . '…';
    };

    $spreadLabel = fn (string $s) => $s === 'three' ? '3 cartes' : '1 carte';
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-3xl mx-auto px-6 py-6 space-y-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Historique Tarot</h1>
                <div class="text-sm text-slate-500 mt-1">Privé (visible uniquement par toi).</div>
            </div>
            <a href="{{ route('tarot.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Nouveau tirage</a>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-6">
            @if (($readings ?? null) && $readings->count())
                <div class="space-y-3">
                    @foreach ($readings as $r)
                        <a href="{{ route('tarot.history.show', $r) }}" class="block rounded-xl border border-slate-200 bg-white px-4 py-3 hover:bg-slate-50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 truncate">{{ $short($r->question, 90) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $spreadLabel((string) $r->spread) }}
                                        <span class="text-slate-400">·</span>
                                        {{ $r->created_at?->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="shrink-0 text-xs text-slate-500">
                                    {{ is_array($r->cards) ? count($r->cards) : 0 }} cartes
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $readings->links() }}
                </div>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                    Aucun tirage enregistré pour l’instant.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
