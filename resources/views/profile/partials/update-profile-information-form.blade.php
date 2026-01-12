<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-sm font-semibold text-slate-900">Naissance (pour l’astro)</div>
            <div class="mt-1 text-xs text-slate-500">Ces champs servent uniquement à générer la fiche astrale “fun”.</div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="date_of_birth" :value="__('Date de naissance')" />
                    <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full" :value="old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d'))" />
                    <x-input-error class="mt-2" :messages="$errors->get('date_of_birth')" />
                </div>

                <div>
                    <x-input-label for="birth_time" :value="__('Heure de naissance (HH:MM)')" />
                    <x-text-input id="birth_time" name="birth_time" type="time" class="mt-1 block w-full" :value="old('birth_time', $user->birth_time)" />
                    <x-input-error class="mt-2" :messages="$errors->get('birth_time')" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="birth_place" :value="__('Lieu de naissance')" />
                <x-text-input id="birth_place" name="birth_place" type="text" class="mt-1 block w-full" :value="old('birth_place', $user->birth_place)" placeholder="ex: Lille, France" />
                <x-input-error class="mt-2" :messages="$errors->get('birth_place')" />
            </div>

            <div class="mt-4">
                <x-input-label for="birth_timezone" :value="__('Fuseau horaire (IANA)')" />
                <x-text-input id="birth_timezone" name="birth_timezone" type="text" class="mt-1 block w-full" :value="old('birth_timezone', $user->birth_timezone ?: config('app.timezone'))" placeholder="ex: Europe/Paris" />
                <x-input-error class="mt-2" :messages="$errors->get('birth_timezone')" />
            </div>

            <details class="mt-4">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Coordonnées (optionnel)</summary>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="birth_latitude" :value="__('Latitude')" />
                        <x-text-input id="birth_latitude" name="birth_latitude" type="text" class="mt-1 block w-full" :value="old('birth_latitude', $user->birth_latitude)" placeholder="ex: 50.6292" />
                        <x-input-error class="mt-2" :messages="$errors->get('birth_latitude')" />
                    </div>
                    <div>
                        <x-input-label for="birth_longitude" :value="__('Longitude')" />
                        <x-text-input id="birth_longitude" name="birth_longitude" type="text" class="mt-1 block w-full" :value="old('birth_longitude', $user->birth_longitude)" placeholder="ex: 3.0573" />
                        <x-input-error class="mt-2" :messages="$errors->get('birth_longitude')" />
                    </div>
                </div>
                <div class="mt-2 text-xs text-slate-500">Astuce: Google Maps → clic droit → “Plus d’infos sur cet endroit” pour copier les coordonnées.</div>
            </details>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
