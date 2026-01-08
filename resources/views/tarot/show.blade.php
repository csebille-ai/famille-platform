@php
    $spreadLabel = fn (string $s) => $s === 'three' ? '3 cartes' : '1 carte';
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-3xl mx-auto px-6 py-6 space-y-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tirage Tarot</h1>
                <div class="text-sm text-slate-500 mt-1">
                    {{ $spreadLabel((string) $reading->spread) }}
                    <span class="text-slate-400">·</span>
                    {{ $reading->created_at?->diffForHumans() }}
                </div>
            </div>
            <a href="{{ route('tarot.history') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Retour historique</a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
            <div>
                <div class="text-sm text-slate-500">Question</div>
                <div class="mt-1 text-base font-semibold text-gray-900">{{ $reading->question }}</div>
            </div>

            <div class="grid grid-cols-{{ ((string) $reading->spread) === 'three' ? '3' : '1' }} gap-3">
                @foreach ((array) ($reading->cards ?? []) as $c)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-sm font-semibold text-gray-900">{{ $c['name'] ?? '' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $c['keywords'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>

            <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 whitespace-pre-wrap">
                {{ $reading->interpretation }}
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('tarot.index') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Nouveau tirage</a>
            </div>
        </div>
    </div>
</x-app-layout>
