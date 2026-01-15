<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $user->name }}
                </h2>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $user->email }}
                </div>
            </div>

            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center h-10 px-4 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50">
                Retour
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs text-gray-500">Rôle</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $user->role ?? 'member' }}</div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs text-gray-500">Invitation</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $user->invited_at ? 'Envoyée' : 'En attente' }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs text-gray-500">Date de naissance</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ optional($user->date_of_birth)->format('Y-m-d') ?? '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs text-gray-500">Heure de naissance</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $user->birth_time ?? '—' }}
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-4 sm:col-span-2">
                            <div class="text-xs text-gray-500">Lieu de naissance</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $user->birth_place ?? '—' }}
                            </div>
                            @if($user->birth_latitude || $user->birth_longitude)
                                <div class="mt-2 text-xs text-gray-500">
                                    @if($user->birth_latitude && $user->birth_longitude)
                                        <span>Coords: {{ $user->birth_latitude }}, {{ $user->birth_longitude }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @include('profile.partials.astro-profile-card', ['user' => $user])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
