<x-app-layout>
    @php
        /** @var \App\Models\User $user */
        $user = $user ?? auth()->user();
    @endphp

    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-[color:var(--fam-text)] leading-tight">Profil</h2>
                <div class="mt-1 text-sm text-[color:var(--fam-muted)]">Paramètres du compte</div>
            </div>

            <div class="shrink-0">
                @include('profile.partials.avatar-photo-card', ['embedded' => true])
            </div>
        </div>
    </x-slot>

    @php
        $hasDob = $user->date_of_birth !== null;
        $hasTime = trim((string) ($user->birth_time ?? '')) !== '';
        $hasPlace = trim((string) ($user->birth_place ?? '')) !== '';
        $hasCoords = trim((string) ($user->birth_latitude ?? '')) !== '' && trim((string) ($user->birth_longitude ?? '')) !== '';

        $completeness = ($hasDob ? 1 : 0) + ($hasTime ? 1 : 0) + ($hasPlace ? 1 : 0) + ($hasCoords ? 1 : 0);

        [$astroBadgeLabel, $astroBadgeClass] = match (true) {
            $completeness >= 4 => ['Complet', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
            $completeness >= 2 => ['Partiel', 'border-amber-200 bg-amber-50 text-amber-900'],
            default => ['Incomplet', 'border-[color:var(--fam-border)] bg-white text-[color:var(--fam-muted)]'],
        };
    @endphp

    <div class="pt-4 pb-24 sm:pt-8 sm:pb-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-5">
            <form id="send-verification" method="post" action="{{ route('verification.send') }}" class="hidden">
                @csrf
            </form>

            <div class="rounded-2xl border border-[color:var(--fam-border)] bg-white p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-[color:var(--fam-text)]">Identité</div>
                            <div class="microcopy mt-1 text-xs text-[color:var(--fam-muted)]">Nom, email, sécurité.</div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autocomplete="name" form="profile-form" />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <x-input-label for="email" :value="__('Email')" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" form="profile-form" />
                                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                    <p class="mt-2 text-xs text-[color:var(--fam-muted)]">
                                        {{ __('Your email address is unverified.') }}
                                        <button form="send-verification" class="underline font-semibold text-[color:var(--fam-muted)] hover:text-[color:var(--fam-text)]">
                                            {{ __('Click here to re-send the verification email.') }}
                                        </button>
                                    </p>

                                    @if (session('status') === 'verification-link-sent')
                                        <p class="mt-2 font-semibold text-xs text-emerald-700">
                                            {{ __('A new verification link has been sent to your email address.') }}
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <details class="mt-2 rounded-2xl border border-[color:var(--fam-border-soft)] bg-[color:var(--fam-surface-2)] px-4 py-3">
                            <summary class="cursor-pointer select-none text-sm font-semibold text-[color:var(--fam-muted)]">
                                Mot de passe
                            </summary>

                            <div class="mt-4">
                                <div class="microcopy text-xs text-[color:var(--fam-muted)]">Pour changer ton mot de passe, utilise ce formulaire dédié.</div>

                                <div class="mt-4">
                                    @include('profile.partials.update-password-form')
                                </div>
                            </div>
                        </details>
                    </div>
            </div>

            <div class="rounded-2xl border border-[color:var(--fam-border)] bg-white p-5 sm:p-6" id="astro-birth">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-[color:var(--fam-text)]">Astro</div>
                            <div class="microcopy mt-1 text-xs text-[color:var(--fam-muted)]">Naissance (uniquement pour générer la fiche “fun”).</div>
                        </div>

                        <div class="shrink-0 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $astroBadgeClass }}">
                                {{ $astroBadgeLabel }}
                            </span>
                            <a href="{{ route('astro.show') }}" class="inline-flex items-center justify-center h-9 px-3 rounded-xl bg-white border border-[color:var(--fam-border)] text-[color:var(--fam-text)] text-xs font-semibold hover:bg-[color:var(--fam-tint)]">
                                Voir ma fiche
                            </a>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="date_of_birth" :value="__('Date de naissance')" />
                            <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full" :value="old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d'))" form="profile-form" />
                            <x-input-error class="mt-2" :messages="$errors->get('date_of_birth')" />
                        </div>

                        <div>
                            <x-input-label for="birth_time" :value="__('Heure de naissance (HH:MM)')" />
                            <x-text-input id="birth_time" name="birth_time" type="time" class="mt-1 block w-full" :value="old('birth_time', $user->birth_time)" form="profile-form" />
                            <x-input-error class="mt-2" :messages="$errors->get('birth_time')" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="birth_place" :value="__('Lieu de naissance')" />
                            <x-text-input id="birth_place" name="birth_place" type="text" class="mt-1 block w-full" :value="old('birth_place', $user->birth_place)" placeholder="ex: Lille, France" form="profile-form" />
                            <x-input-error class="mt-2" :messages="$errors->get('birth_place')" />
                        </div>
                    </div>

                    <details class="mt-4">
                        <summary class="cursor-pointer select-none text-sm font-semibold text-[color:var(--fam-muted)]">
                            Coordonnées (optionnel)
                        </summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="birth_latitude" :value="__('Latitude')" />
                                <x-text-input id="birth_latitude" name="birth_latitude" type="text" class="mt-1 block w-full" :value="old('birth_latitude', $user->birth_latitude)" placeholder="ex: 50.6292" form="profile-form" />
                                <x-input-error class="mt-2" :messages="$errors->get('birth_latitude')" />
                            </div>
                            <div>
                                <x-input-label for="birth_longitude" :value="__('Longitude')" />
                                <x-text-input id="birth_longitude" name="birth_longitude" type="text" class="mt-1 block w-full" :value="old('birth_longitude', $user->birth_longitude)" placeholder="ex: 3.0573" form="profile-form" />
                                <x-input-error class="mt-2" :messages="$errors->get('birth_longitude')" />
                            </div>
                        </div>
                        <div class="microcopy mt-2 text-xs text-[color:var(--fam-muted)]">Astuce: Google Maps → clic droit → “Plus d’infos sur cet endroit”.</div>
                    </details>
            </div>

            <div class="rounded-2xl border border-[color:var(--fam-border)] bg-white p-5 sm:p-6">
                @include('profile.partials.google-calendar-form')
            </div>

            <div class="rounded-2xl border border-rose-200 bg-[color:var(--fam-surface-2)] p-5 sm:p-6">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>

    @php
        $profileFieldErrors = $errors->hasAny([
            'name',
            'email',
            'date_of_birth',
            'birth_time',
            'birth_place',
            'birth_latitude',
            'birth_longitude',
        ]);
    @endphp

    <form id="profile-form" method="post" action="{{ route('profile.update') }}" class="hidden" data-has-errors="{{ $profileFieldErrors ? '1' : '0' }}">
        @csrf
        @method('patch')
    </form>

    <div class="fixed inset-x-0 bottom-[calc(5.25rem+env(safe-area-inset-bottom))] sm:bottom-6 z-40">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-[color:var(--fam-border)] bg-white/90 backdrop-blur px-4 py-3 shadow-sm">
                <button id="profile-save-btn" type="submit" form="profile-form" disabled class="w-full inline-flex items-center justify-center h-11 rounded-2xl text-sm font-extrabold transition disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 bg-[color:var(--fam-primary)] text-white hover:bg-[color:var(--fam-primary-hover)]">
                    <span id="profile-save-label">Aucune modification</span>
                </button>

                @if (session('status') === 'profile-updated')
                    <div class="mt-2 text-center text-xs font-semibold text-emerald-700">Enregistré.</div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (() => {
            const form = document.getElementById('profile-form');
            const btn = document.getElementById('profile-save-btn');
            const label = document.getElementById('profile-save-label');
            if (!form || !btn || !label) return;

            const hasErrors = form.dataset.hasErrors === '1';

            const serialize = (f) => {
                const fd = new FormData(f);
                fd.delete('_token');
                fd.delete('_method');

                const entries = [];
                fd.forEach((v, k) => entries.push([k, String(v)]));
                entries.sort((a, b) => a[0].localeCompare(b[0]) || a[1].localeCompare(b[1]));
                return JSON.stringify(entries);
            };

            const initial = serialize(form);

            const sync = () => {
                const dirty = hasErrors || serialize(form) !== initial;
                btn.disabled = !dirty;
                label.textContent = dirty ? 'Enregistrer' : 'Aucune modification';
            };

            const onChange = (e) => {
                const t = e?.target;
                if (!t) return;
                if (t.form !== form) return;
                sync();
            };

            document.addEventListener('input', onChange, { passive: true });
            document.addEventListener('change', onChange, { passive: true });
            sync();
        })();
    </script>
</x-app-layout>
