@php
    $spreadLabel = fn (string $s) => $s === 'three' ? '3 cartes' : '1 carte';
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-3xl mx-auto px-6 py-6 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tarot</h1>
            <div class="text-sm text-slate-500 mt-1">Tirage fun et bienveillant (aide à la réflexion).</div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <form method="POST" action="{{ route('tarot.draw') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="question" class="block text-sm font-semibold text-gray-900">Ta question</label>
                    <textarea id="question" name="question" rows="3" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" placeholder="Ex: Comment aborder sereinement la semaine à venir ?">{{ old('question') }}</textarea>
                    <div class="mt-1 text-xs text-slate-500">Max 500 caractères.</div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-sm font-semibold text-gray-900">Tirage</div>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="spread" value="one" class="rounded" {{ old('spread', 'one') === 'one' ? 'checked' : '' }}>
                        <span>1 carte</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="spread" value="three" class="rounded" {{ old('spread') === 'three' ? 'checked' : '' }}>
                        <span>3 cartes</span>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button>
                        Tirer
                    </x-primary-button>

                    <a href="{{ route('tarot.history') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">Voir l’historique</a>
                </div>
            </form>
        </div>

        @if (is_array($draft ?? null))
            <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-sm text-slate-500">Résultat ({{ $spreadLabel((string) ($draft['spread'] ?? 'one')) }})</div>
                        <div class="mt-1 text-base font-semibold text-gray-900 truncate">{{ (string) ($draft['question'] ?? '') }}</div>
                    </div>
                    <form method="POST" action="{{ route('tarot.reset') }}" class="shrink-0">
                        @csrf
                        <x-secondary-button type="submit">Relancer</x-secondary-button>
                    </form>
                </div>

                <div class="grid grid-cols-{{ ((string) ($draft['spread'] ?? 'one')) === 'three' ? '3' : '1' }} gap-3">
                    @foreach ((array) ($draft['cards'] ?? []) as $c)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                            @if (!empty($c['file']))
                                <div class="flex justify-center">
                                    <img
                                        src="{{ 'https://opanoma.fr/tarot/' . $c['file'] }}"
                                        alt="{{ $c['name'] ?? '' }}"
                                        class="h-56 w-auto max-w-full rounded-lg border border-slate-200 bg-white object-contain"
                                        loading="lazy"
                                    />
                                </div>
                            @endif
                            <div class="mt-3 text-sm font-semibold text-gray-900">{{ $c['name'] ?? '' }}</div>
                            @if (!empty($c['reversed']))
                                <div class="mt-1 text-xs text-slate-500">Renversée</div>
                            @endif
                            <div class="mt-1 text-xs text-slate-500">{{ $c['keywords'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm text-gray-900 whitespace-pre-wrap">
                    {{ (string) ($draft['interpretation'] ?? '') }}
                </div>

                <form method="POST" action="{{ route('tarot.save') }}" class="flex items-center gap-3">
                    @csrf
                    <x-primary-button type="submit">Enregistrer</x-primary-button>
                    <div class="text-xs text-slate-500">Stocké en privé dans ton historique.</div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
