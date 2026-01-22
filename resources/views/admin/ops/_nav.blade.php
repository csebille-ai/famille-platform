@php
    $items = [
        ['route' => 'admin.overview', 'label' => 'Aperçu'],
        ['route' => 'admin.users.index', 'label' => 'Utilisateurs'],
        ['route' => 'admin.activity', 'label' => 'Journal'],
        ['route' => 'admin.errors', 'label' => 'Erreurs'],
    ];
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-2">
    <div class="flex flex-wrap gap-2">
        @foreach($items as $it)
            @php $active = request()->routeIs($it['route']); @endphp
            <a href="{{ route($it['route']) }}"
               class="inline-flex items-center h-9 px-3 rounded-xl text-sm font-semibold border {{ $active ? 'bg-teal-600 text-white border-transparent' : 'bg-white text-slate-700 border-black/10 hover:bg-teal-50 hover:text-slate-900' }}">
                {{ $it['label'] }}
            </a>
        @endforeach
    </div>
</div>
