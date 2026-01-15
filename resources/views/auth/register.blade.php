<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-sm font-semibold text-slate-900">Naissance (pour l’astro)</div>
            <div class="microcopy mt-1 text-xs text-slate-500">Optionnel — tu peux aussi le remplir plus tard dans ton profil.</div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="date_of_birth" :value="__('Date de naissance')" />
                    <x-text-input id="date_of_birth" class="block mt-1 w-full" type="date" name="date_of_birth" :value="old('date_of_birth')" autocomplete="bday" />
                    <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="birth_time" :value="__('Heure de naissance (HH:MM)')" />
                    <x-text-input id="birth_time" class="block mt-1 w-full" type="time" name="birth_time" :value="old('birth_time')" />
                    <x-input-error :messages="$errors->get('birth_time')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="birth_place" :value="__('Lieu de naissance')" />
                <x-text-input id="birth_place" class="block mt-1 w-full" type="text" name="birth_place" :value="old('birth_place')" placeholder="ex: Lille, France" autocomplete="off" />
                <x-input-error :messages="$errors->get('birth_place')" class="mt-2" />
            </div>

            <details class="mt-4">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Coordonnées (optionnel)</summary>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="birth_latitude" :value="__('Latitude')" />
                        <x-text-input id="birth_latitude" class="block mt-1 w-full" type="text" name="birth_latitude" :value="old('birth_latitude')" placeholder="ex: 50.6292" />
                        <x-input-error :messages="$errors->get('birth_latitude')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="birth_longitude" :value="__('Longitude')" />
                        <x-text-input id="birth_longitude" class="block mt-1 w-full" type="text" name="birth_longitude" :value="old('birth_longitude')" placeholder="ex: 3.0573" />
                        <x-input-error :messages="$errors->get('birth_longitude')" class="mt-2" />
                    </div>
                </div>
            </details>
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
