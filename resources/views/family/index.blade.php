@php
    /** @var \Illuminate\Database\Eloquent\Collection<int,\App\Models\Person> $adults */
    /** @var \Illuminate\Database\Eloquent\Collection<int,\App\Models\Person> $children */
    $adults = $adults ?? collect();
    $children = $children ?? collect();

    $meId = (int) (auth()->id() ?? 0);
@endphp

<x-app-layout pageBgClass="bg-slate-50">
    <div class="max-w-4xl mx-auto px-6 py-6 space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-gray-900">Famille</h1>
            <a href="{{ route('family.children.create') }}" class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Ajouter un enfant</a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm font-semibold text-gray-900">Adultes</div>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse($adults as $p)
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-slate-900 text-white flex items-center justify-center text-sm font-bold">
                                {{ $p->initials() }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 truncate">{{ $p->displayName() }}</div>
                                <div class="text-sm text-slate-600">
                                    @if($p->birth_date)
                                        Né(e) le {{ $p->birth_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    @else
                                        Date de naissance non renseignée
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-600">Aucun adulte.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-sm font-semibold text-gray-900">Enfants</div>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse($children as $c)
                    @php
                        $mine = $c->guardians->firstWhere('id', $meId);
                        $canEdit = (bool) ($mine?->pivot?->can_edit ?? false);
                        $notify = (bool) ($mine?->pivot?->notify ?? false);
                    @endphp

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-full bg-slate-900 text-white flex items-center justify-center text-sm font-bold">
                                {{ $c->initials() }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-semibold text-gray-900 truncate">{{ $c->displayName() }}</div>

                                    @if($canEdit)
                                        <a href="{{ route('family.children.edit', $c) }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-gray-900">Modifier</a>
                                    @endif
                                </div>

                                <div class="mt-1 text-sm text-slate-600">
                                    @if($c->birth_date)
                                        Né(e) le {{ $c->birth_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                                    @else
                                        Date de naissance non renseignée
                                    @endif
                                </div>

                                @if($mine)
                                    <div class="mt-2 text-xs text-slate-500">
                                        Vos réglages: {{ $notify ? 'notifications ON' : 'notifications OFF' }}
                                    </div>
                                @endif

                                @php
                                    $guardians = $c->guardians ?? collect();
                                @endphp
                                @if($guardians->count())
                                    <div class="mt-2 text-xs text-slate-500">
                                        Tuteurs: {{ $guardians->pluck('name')->join(', ') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-600">Aucun enfant.</div>
                @endforelse
            </div>
        </div>

        <div class="text-sm text-slate-600">
            Astuce: les anniversaires utilisent maintenant les profils (adultes + enfants).
        </div>
    </div>
</x-app-layout>
