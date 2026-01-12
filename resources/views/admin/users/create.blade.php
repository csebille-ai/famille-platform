<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Create user') }}
            </h2>
            <div class="mt-1 text-sm text-gray-600">
                {{ __('Add a user and their profile information') }}
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="email">{{ __('Email') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('email')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                            Aucun email n’est envoyé automatiquement à la création. Tu peux créer tous les utilisateurs d’abord, puis envoyer toutes les invitations plus tard depuis la liste.
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="role">{{ __('Role') }}</label>
                                <select id="role" name="role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    @foreach ($roles as $r)
                                        <option value="{{ $r }}" @selected(old('role', 'member') === $r)>{{ $r }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="date_of_birth">{{ __('Date of birth') }}</label>
                                <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('date_of_birth')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <div class="text-sm font-semibold text-gray-800">Naissance (astro fun)</div>
                            <div class="mt-1 text-xs text-gray-600">Optionnel — sert à calculer la fiche astrale.</div>

                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" for="birth_time">Heure de naissance</label>
                                    <input id="birth_time" name="birth_time" type="time" value="{{ old('birth_time') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    @error('birth_time')
                                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700" for="birth_timezone">Fuseau horaire</label>
                                    <input id="birth_timezone" name="birth_timezone" type="text" value="{{ old('birth_timezone') }}" placeholder="Europe/Paris" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    @error('birth_timezone')
                                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700" for="birth_place">Lieu de naissance</label>
                                <input id="birth_place" name="birth_place" type="text" value="{{ old('birth_place') }}" placeholder="Lille, France" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('birth_place')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <details class="mt-4">
                                <summary class="cursor-pointer text-sm font-medium text-gray-700">Coordonnées (optionnel)</summary>
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="birth_latitude">Latitude</label>
                                        <input id="birth_latitude" name="birth_latitude" type="text" value="{{ old('birth_latitude') }}" placeholder="50.6292" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                        @error('birth_latitude')
                                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700" for="birth_longitude">Longitude</label>
                                        <input id="birth_longitude" name="birth_longitude" type="text" value="{{ old('birth_longitude') }}" placeholder="3.0573" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                        @error('birth_longitude')
                                            <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </details>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="phone">{{ __('Phone') }}</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('phone')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="address_line1">{{ __('Address') }}</label>
                            <input id="address_line1" name="address_line1" type="text" value="{{ old('address_line1') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('address_line1')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="address_line2">{{ __('Address (line 2)') }}</label>
                            <input id="address_line2" name="address_line2" type="text" value="{{ old('address_line2') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            @error('address_line2')
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="postal_code">{{ __('Postal code') }}</label>
                                <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('postal_code')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="city">{{ __('City') }}</label>
                                <input id="city" name="city" type="text" value="{{ old('city') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('city')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>
                                {{ __('Create') }}
                            </x-primary-button>

                            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
