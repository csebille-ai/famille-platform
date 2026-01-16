<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Modifier utilisateur
                </h2>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $user->email }}
                </div>
            </div>

            <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center h-10 px-4 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50">
                Retour
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="name">Nom affiché</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('name')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('email')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="role">Rôle</label>
                            <select id="role" name="role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($roles as $r)
                                    <option value="{{ $r }}" @selected(old('role', $user->role ?? 'member') === $r)>{{ $r }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="date_of_birth">Date de naissance</label>
                                <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('date_of_birth')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="birth_time">Heure de naissance</label>
                                <input id="birth_time" name="birth_time" type="time" value="{{ old('birth_time', $user->birth_time) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('birth_time')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="birth_place">Lieu de naissance</label>
                            <input id="birth_place" name="birth_place" type="text" value="{{ old('birth_place', $user->birth_place) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('birth_place')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <details class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <summary class="cursor-pointer text-sm font-medium text-gray-700">Coordonnées (optionnel)</summary>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" for="birth_latitude">Latitude</label>
                                    <input id="birth_latitude" name="birth_latitude" type="text" value="{{ old('birth_latitude', $user->birth_latitude) }}" placeholder="50.6292" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    @error('birth_latitude')
                                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" for="birth_longitude">Longitude</label>
                                    <input id="birth_longitude" name="birth_longitude" type="text" value="{{ old('birth_longitude', $user->birth_longitude) }}" placeholder="3.0573" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    @error('birth_longitude')
                                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </details>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="phone">Téléphone</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('phone')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="address_line1">Adresse</label>
                            <input id="address_line1" name="address_line1" type="text" value="{{ old('address_line1', $user->address_line1) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('address_line1')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="address_line2">Adresse (ligne 2)</label>
                            <input id="address_line2" name="address_line2" type="text" value="{{ old('address_line2', $user->address_line2) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('address_line2')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="postal_code">Code postal</label>
                                <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $user->postal_code) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('postal_code')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="city">Ville</label>
                                <input id="city" name="city" type="text" value="{{ old('city', $user->city) }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('city')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>
                                Enregistrer
                            </x-primary-button>

                            <a href="{{ route('admin.users.show', $user) }}" class="text-sm text-gray-600 hover:text-gray-900">
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
