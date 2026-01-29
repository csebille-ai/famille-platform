<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-slate-900 leading-tight">Modifier taquin</h2>
                <div class="mt-1 text-sm text-slate-600">{{ $puzzle->title }}</div>
            </div>

            <div class="flex items-center gap-2">
                @if((bool) $puzzle->is_active)
                    <a href="{{ route('games.sliding-puzzles.show', $puzzle) }}" class="inline-flex items-center h-10 px-4 rounded-xl border border-black/10 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">
                        Jouer
                    </a>
                @endif

                <a href="{{ route('admin.sliding-puzzles.create') }}" class="inline-flex items-center h-10 px-4 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
                    Nouveau
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin.ops._nav')

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
                    <div class="text-sm font-semibold">{{ __('Something went wrong') }}</div>
                    <ul class="mt-2 text-sm list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-900">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <form method="POST" action="{{ route('admin.sliding-puzzles.update', $puzzle) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    @include('admin.sliding-puzzles._form', ['puzzle' => $puzzle, 'recentImages' => $recentImages])

                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('admin.sliding-puzzles.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">← Retour</a>

                        <button type="submit" class="inline-flex items-center h-10 px-4 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <div class="text-sm font-semibold text-rose-900">Suppression</div>
                <div class="mt-1 text-sm text-rose-800">Supprime ce puzzle (le classement lié restera en base si tu ne supprimes pas les tentatives).</div>

                <form method="POST" action="{{ route('admin.sliding-puzzles.destroy', $puzzle) }}" class="mt-3" onsubmit="return confirm('Supprimer ce taquin ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center h-10 px-4 rounded-xl bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
