@php
    /** @var \App\Models\Person $child */
    /** @var \Illuminate\Database\Eloquent\Collection<int,\App\Models\User> $users */
    $users = $users ?? collect();

    $meId = (int) (auth()->id() ?? 0);
    $guardianById = collect($child->guardians ?? [])->keyBy('id');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifier l’enfant</h2>
            <div class="mt-1 text-sm text-gray-600">{{ $child->displayName() }}</div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('family.children.update', $child) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="first_name">Prénom</label>
                                <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $child->first_name) }}" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('first_name')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="last_name">Nom</label>
                                <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $child->last_name) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('last_name')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="birth_date">Date de naissance</label>
                            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', optional($child->birth_date)->toDateString()) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('birth_date')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <div class="text-sm font-semibold text-gray-800">Tuteurs</div>
                            <div class="mt-1 text-xs text-gray-600">Vous restez toujours tuteur avec l’édition activée (anti lock-out).</div>

                            <div class="mt-3 space-y-2">
                                @foreach($users as $u)
                                    @php
                                        $existing = $guardianById->get($u->id);
                                        $enabledDefault = (bool) $existing;
                                        $canEditDefault = (bool) ($existing?->pivot?->can_edit ?? false);
                                        $notifyDefault = (bool) ($existing?->pivot?->notify ?? false);

                                        if ($u->id === $meId) {
                                            $enabledDefault = true;
                                            $canEditDefault = true;
                                        }

                                        $enabled = old('guardians.' . $u->id . '.enabled', $enabledDefault ? 1 : 0);
                                        $canEdit = old('guardians.' . $u->id . '.can_edit', $canEditDefault ? 1 : 0);
                                        $notify = old('guardians.' . $u->id . '.notify', $notifyDefault ? 1 : 0);
                                    @endphp

                                    <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-white p-3">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-sm text-gray-900 truncate">{{ $u->name }}</div>
                                            <div class="mt-1 flex items-center gap-4 text-xs text-gray-600">
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="checkbox" name="guardians[{{ $u->id }}][enabled]" value="1" @checked((bool) $enabled) class="rounded border-gray-300" />
                                                    Actif
                                                </label>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="checkbox" name="guardians[{{ $u->id }}][can_edit]" value="1" @checked((bool) $canEdit) class="rounded border-gray-300" />
                                                    Peut modifier
                                                </label>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="checkbox" name="guardians[{{ $u->id }}][notify]" value="1" @checked((bool) $notify) class="rounded border-gray-300" />
                                                    Notifier
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>Enregistrer</x-primary-button>
                            <a href="{{ route('family.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Retour</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
