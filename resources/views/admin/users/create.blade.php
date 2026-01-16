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

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="first_name">Prénom</label>
                                <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('first_name')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="last_name">Nom</label>
                                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                @error('last_name')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

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
                            <div class="microcopy mt-1 text-xs text-gray-600">Optionnel — sert à calculer la fiche astrale.</div>

                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700" for="birth_time">Heure de naissance</label>
                                    <input id="birth_time" name="birth_time" type="time" value="{{ old('birth_time') }}" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    @error('birth_time')
                                        <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700" for="birth_place">Lieu de naissance</label>
                                <div class="relative">
                                    <input id="birth_place" name="birth_place" type="text" value="{{ old('birth_place') }}" placeholder="Lille, France" autocomplete="off" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    <div id="birth_place_suggestions" class="absolute z-10 mt-1 hidden w-full rounded-md border border-gray-200 bg-white shadow-lg">
                                        <div class="max-h-56 overflow-auto py-1"></div>
                                    </div>
                                </div>
                                @error('birth_place')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mt-4 rounded-md border border-gray-200 bg-white p-4">
                                <div class="text-sm font-semibold text-gray-800">Coordonnées</div>
                                <div class="microcopy mt-1 text-xs text-gray-600">Remplies automatiquement quand tu sélectionnes une ville (modifiable si besoin).</div>

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
                            </div>
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
                                <div class="relative">
                                    <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code') }}" autocomplete="off" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    <div id="postal_code_suggestions" class="absolute z-10 mt-1 hidden w-full rounded-md border border-gray-200 bg-white shadow-lg">
                                        <div class="max-h-56 overflow-auto py-1"></div>
                                    </div>
                                </div>
                                @error('postal_code')
                                    <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700" for="city">{{ __('City') }}</label>
                                <div class="relative">
                                    <input id="city" name="city" type="text" value="{{ old('city') }}" autocomplete="off" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                    <div id="city_suggestions" class="absolute z-10 mt-1 hidden w-full rounded-md border border-gray-200 bg-white shadow-lg">
                                        <div class="max-h-56 overflow-auto py-1"></div>
                                    </div>
                                </div>
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

    <script>
        (function () {
            const input = document.getElementById('birth_place');
            const lat = document.getElementById('birth_latitude');
            const lon = document.getElementById('birth_longitude');
            const box = document.getElementById('birth_place_suggestions');
            const list = box ? box.querySelector('div') : null;

            if (!input || !box || !list) return;

            let aborter = null;
            let timer = null;

            function hide() {
                box.classList.add('hidden');
                list.innerHTML = '';
            }

            function show(items) {
                list.innerHTML = '';
                if (!items || items.length === 0) {
                    hide();
                    return;
                }

                for (const item of items) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-gray-50';
                    btn.textContent = item.label;
                    btn.addEventListener('click', function () {
                        input.value = item.label;
                        const latNum = Number(item.latitude);
                        const lonNum = Number(item.longitude);
                        if (lat && !Number.isNaN(latNum)) lat.value = String(latNum);
                        if (lon && !Number.isNaN(lonNum)) lon.value = String(lonNum);
                        hide();
                    });
                    list.appendChild(btn);
                }

                box.classList.remove('hidden');
            }

            async function search(q) {
                if (aborter) aborter.abort();
                aborter = new AbortController();

                const url = new URL('/api/geo/cities', window.location.origin);
                url.searchParams.set('q', q);

                const resp = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                    signal: aborter.signal,
                });

                if (!resp.ok) return [];
                const data = await resp.json();
                return Array.isArray(data?.items) ? data.items : [];
            }

            input.addEventListener('input', function () {
                const q = (input.value || '').trim();
                if (timer) window.clearTimeout(timer);
                if (q.length < 3) {
                    hide();
                    return;
                }
                timer = window.setTimeout(async function () {
                    try {
                        const items = await search(q);
                        show(items);
                    } catch (e) {
                        // ignore (abort/network)
                    }
                }, 200);
            });

            document.addEventListener('click', function (e) {
                if (e.target === input) return;
                if (box.contains(e.target)) return;
                hide();
            });
        })();
    </script>

    <script>
        (function () {
            const postal = document.getElementById('postal_code');
            const city = document.getElementById('city');

            const postalBox = document.getElementById('postal_code_suggestions');
            const postalList = postalBox ? postalBox.querySelector('div') : null;

            const cityBox = document.getElementById('city_suggestions');
            const cityList = cityBox ? cityBox.querySelector('div') : null;

            if (!postal || !city || !postalBox || !postalList || !cityBox || !cityList) return;

            let aborter = null;
            let postalTimer = null;
            let cityTimer = null;

            function hidePostal() {
                postalBox.classList.add('hidden');
                postalList.innerHTML = '';
            }

            function hideCity() {
                cityBox.classList.add('hidden');
                cityList.innerHTML = '';
            }

            function applyItem(item) {
                if (item?.postcode) postal.value = String(item.postcode);
                if (item?.city) city.value = String(item.city);
                hidePostal();
                hideCity();
            }

            function render(listEl, boxEl, items) {
                listEl.innerHTML = '';
                if (!items || items.length === 0) {
                    boxEl.classList.add('hidden');
                    return;
                }
                for (const item of items) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-gray-50';
                    btn.textContent = item.label;
                    btn.addEventListener('click', function () {
                        applyItem(item);
                    });
                    listEl.appendChild(btn);
                }
                boxEl.classList.remove('hidden');
            }

            async function search(q) {
                if (aborter) aborter.abort();
                aborter = new AbortController();

                const url = new URL('/api/geo/cities', window.location.origin);
                url.searchParams.set('q', q);

                const resp = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                    signal: aborter.signal,
                });

                if (!resp.ok) return [];
                const data = await resp.json();
                return Array.isArray(data?.items) ? data.items : [];
            }

            function digitsOnly(value) {
                return String(value || '').replace(/\D+/g, '');
            }

            postal.addEventListener('input', function () {
                const cp = digitsOnly(postal.value);
                if (postalTimer) window.clearTimeout(postalTimer);

                if (cp.length === 0) {
                    hidePostal();
                    return;
                }

                postalTimer = window.setTimeout(async function () {
                    try {
                        const q = (cp + ' ' + (city.value || '')).trim();
                        const items = await search(q);

                        // If user typed a full CP and city is empty: auto-fill when unambiguous.
                        if (cp.length >= 5 && String(city.value || '').trim() === '' && items.length === 1) {
                            applyItem(items[0]);
                            return;
                        }

                        render(postalList, postalBox, items);
                    } catch (e) {
                        // ignore
                    }
                }, 200);
            });

            city.addEventListener('input', function () {
                const qCity = String(city.value || '').trim();
                if (cityTimer) window.clearTimeout(cityTimer);

                if (qCity.length < 3) {
                    hideCity();
                    return;
                }

                cityTimer = window.setTimeout(async function () {
                    try {
                        const cp = digitsOnly(postal.value);
                        const q = (cp ? (cp + ' ' + qCity) : qCity).trim();
                        const items = await search(q);
                        render(cityList, cityBox, items);
                    } catch (e) {
                        // ignore
                    }
                }, 200);
            });

            document.addEventListener('click', function (e) {
                if (e.target === postal) return;
                if (e.target === city) return;
                if (postalBox.contains(e.target)) return;
                if (cityBox.contains(e.target)) return;
                hidePostal();
                hideCity();
            });
        })();
    </script>
</x-app-layout>
