<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Prochains moments</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if(($moments ?? collect())->count() === 0)
                        <div class="text-sm text-gray-600">Aucun moment à venir.</div>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach($moments as $m)
                                <div class="py-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ $m['title'] }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ $m['date_label'] }}{{ $m['subtitle'] ? ' · '.$m['subtitle'] : '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
