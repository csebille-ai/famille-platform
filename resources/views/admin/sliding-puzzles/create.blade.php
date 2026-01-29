<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">Nouveau taquin</h2>
            <div class="mt-1 text-sm text-slate-600">Créer un puzzle et choisir l’image</div>
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

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <form method="POST" action="{{ route('admin.sliding-puzzles.store') }}" class="space-y-4">
                    @csrf

                    @include('admin.sliding-puzzles._form', ['puzzle' => $puzzle, 'recentImages' => $recentImages])

                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('admin.sliding-puzzles.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">← Retour</a>

                        <button type="submit" class="inline-flex items-center h-10 px-4 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
                            Créer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
