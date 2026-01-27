@php
    $event = $event ?? null;
    $defaults = $defaults ?? [];

    $isEdit = $event instanceof \App\Models\Event;
    $v = function (string $key, $fallback = '') use ($event, $defaults) {
        if (old($key) !== null) return old($key);
        if ($event instanceof \App\Models\Event) {
            return match ($key) {
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'location_label' => $event->location_label,
                'location_lat' => $event->location_lat,
                'location_lon' => $event->location_lon,
                'household_key' => $event->household_key,
                'category' => $event->category,
                'visibility' => $event->visibility,
                'is_important' => $event->is_important,
                'notify' => $event->notify,
                'reminder_minutes' => $event->reminder_minutes ?? 0,
                'date' => $event->start_at?->format('Y-m-d'),
                'time' => $event->start_at?->format('H:i'),
                'all_day' => $event->all_day,
                'end_date' => $event->end_at?->format('Y-m-d'),
                'end_time' => $event->end_at?->format('H:i'),
                'add_end' => $event->end_at !== null,
                'status' => $event->status,
                default => $fallback,
            };
        }

        if (is_array($defaults) && array_key_exists($key, $defaults)) {
            return $defaults[$key];
        }

        return $fallback;
    };

    $bool = function (string $key, bool $fallback = false) use ($v): bool {
        $val = $v($key, $fallback);
        if ($val === true || $val === false) return (bool) $val;
        $s = strtolower(trim((string) $val));
        return in_array($s, ['1', 'true', 'on', 'yes'], true);
    };

    $selected = function (string $key, string $value) use ($v): string {
        return ((string) $v($key, '') === $value) ? 'selected' : '';
    };

    $isAdmin = auth()->user()?->can('manage-users') === true;
@endphp

<div class="space-y-3">
    <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4 space-y-3">

        <div>
            <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Titre</label>
            <input
                name="title"
                value="{{ $v('title') }}"
                placeholder="Anniv Hugo, Départ vacances, RDV dentiste…"
                class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25"
                required
            />
            @error('title')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Catégorie</label>
                <select name="category" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25">
                    <option value="family" {{ $selected('category','family') }}>Famille</option>
                    <option value="personal" {{ $selected('category','personal') }}>Perso</option>
                    <option value="school" {{ $selected('category','school') }}>École</option>
                    <option value="travel" {{ $selected('category','travel') }}>Voyage</option>
                    <option value="medical" {{ $selected('category','medical') }}>Médical</option>
                    <option value="other" {{ $selected('category','other') }}>Autre</option>
                    @if($isAdmin)
                        <option value="admin" {{ $selected('category','admin') }}>Admin</option>
                    @endif
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Visibilité</label>
                <select id="eventVisibilitySelect" name="visibility" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25" required>
                    <option value="family" {{ $selected('visibility','family') }}>Famille</option>
                    <option value="private" {{ $selected('visibility','private') }}>Privé</option>
                </select>
                <div class="mt-1 text-[0.7rem] text-[color:var(--fam-muted)]">Privé = visible uniquement par les personnes sélectionnées (et les admins).</div>

                @php
                    /** @var \Illuminate\Support\Collection<int,\App\Models\User>|array<int,\App\Models\User>|null $users */
                    $users = $users ?? collect();
                    if (is_array($users)) $users = collect($users);
                    $sharedIds = old('shared_user_ids');
                    if ($sharedIds === null) {
                        $sharedIds = $sharedUserIds ?? [];
                    }
                    $sharedIds = is_array($sharedIds) ? array_map('intval', $sharedIds) : [];
                @endphp
                <div id="eventPrivateShareBox" class="mt-2 rounded-2xl border border-[color:var(--fam-border-soft)] bg-[color:var(--fam-surface-alt)] p-3 space-y-2" style="display:none;">
                    <div class="text-xs font-extrabold text-[color:var(--fam-muted)] uppercase tracking-wide">Partager avec</div>
                    <div class="grid grid-cols-1 gap-1">
                        @foreach($users as $u)
                            @if((int) $u->id !== (int) auth()->id())
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-[color:var(--fam-text)]">
                                    <input type="checkbox" name="shared_user_ids[]" value="{{ $u->id }}" {{ in_array((int) $u->id, $sharedIds, true) ? 'checked' : '' }} class="h-5 w-5 rounded border-[color:var(--fam-border-soft)] text-[color:var(--fam-primary)] focus:ring-[color:var(--fam-primary)]/25" />
                                    <span>{{ $u->name }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <div class="text-[0.7rem] text-[color:var(--fam-muted)]">Si tu ne coches personne, l’événement privé ne sera visible que par toi (et les admins).</div>
                </div>

                @error('visibility')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
                @error('shared_user_ids')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
                @error('shared_user_ids.*')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-2xl border border-[color:var(--fam-border-soft)] bg-[color:var(--fam-surface-alt)] px-3 py-2">
            <div>
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Important</div>
                <div class="text-[0.7rem] text-[color:var(--fam-muted)]">Mis en avant sur l’accueil.</div>
            </div>
            <label class="inline-flex items-center gap-2">
                <input type="hidden" name="is_important" value="0" />
                <input type="checkbox" name="is_important" value="1" {{ $bool('is_important') ? 'checked' : '' }} class="h-5 w-5 rounded border-[color:var(--fam-border-soft)] text-[color:var(--fam-primary)] focus:ring-[color:var(--fam-primary)]/25" />
            </label>
        </div>
    </div>

    <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4 space-y-3">
        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Date & heure</div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Date</label>
                <input type="date" name="date" value="{{ $v('date') }}" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25" required />
                @error('date')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Heure <span class="font-normal">(facultatif)</span></label>
                <input type="time" name="time" value="{{ $v('time') }}" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Fin <span class="font-normal">(date de fin)</span></label>
                <input type="date" name="end_date" value="{{ $v('end_date') }}" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25" />
                @error('end_date')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Heure <span class="font-normal">(facultatif)</span></label>
                <input type="time" name="end_time" value="{{ $v('end_time') }}" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25" />
                @error('end_time')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <input type="hidden" name="all_day" value="0" />
        <input type="hidden" name="add_end" value="0" />
    </div>

    <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4 space-y-3">

        <div>
            <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Foyer (rapide)</label>
            <select 
                id="eventHouseholdSelect" 
                name="household_key" 
                class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25"
            >
                <option value="">— Autre lieu (saisir manuellement)</option>
                @foreach(config('households', []) as $key => $household)
                    <option value="{{ $key }}" {{ $v('household_key') === $key ? 'selected' : '' }}>
                        {{ $household['icon'] ?? '🏠' }} {{ $household['label'] }}
                    </option>
                @endforeach
            </select>
            <div class="mt-1 text-[0.7rem] text-[color:var(--fam-muted)]">Sélectionne un foyer pour pré-remplir l'adresse automatiquement</div>
        </div>

        <div>
            <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Lieu</label>
            <input 
                id="eventLocationInput" 
                name="location" 
                value="{{ $v('location') }}" 
                placeholder="Adresse, salle, ville…" 
                class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25" 
                autocomplete="off"
            />
            <input type="hidden" id="eventLocationLabel" name="location_label" value="{{ $v('location_label') }}" />
            <input type="hidden" id="eventLocationLat" name="location_lat" value="{{ $v('location_lat') }}" />
            <input type="hidden" id="eventLocationLon" name="location_lon" value="{{ $v('location_lon') }}" />
            @error('location')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Description</label>
            <textarea name="description" rows="4" placeholder="Infos utiles (adresse, tenue, quoi apporter…)" class="mt-1 w-full rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 py-2 text-sm text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25">{{ $v('description') }}</textarea>
            @error('description')<div class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4 space-y-3">
        <div class="text-sm font-semibold text-[color:var(--fam-text)]">Notifications</div>

        <div class="flex items-center justify-between gap-3 rounded-2xl border border-[color:var(--fam-border-soft)] bg-[color:var(--fam-surface-alt)] px-3 py-2">
            <div>
                <div class="text-sm font-semibold text-[color:var(--fam-text)]">Notifier</div>
                <div class="text-[0.7rem] text-[color:var(--fam-muted)]">Envoie un push selon le rappel.</div>
            </div>
            <label class="inline-flex items-center gap-2">
                <input type="hidden" name="notify" value="0" />
                <input type="checkbox" name="notify" value="1" {{ $bool('notify', true) ? 'checked' : '' }} class="h-5 w-5 rounded border-[color:var(--fam-border-soft)] text-[color:var(--fam-primary)] focus:ring-[color:var(--fam-primary)]/25" />
            </label>
        </div>

        <div>
            <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Rappel</label>
            <select name="reminder_minutes" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25">
                <option value="0" {{ ((string) $v('reminder_minutes', '0') === '0') ? 'selected' : '' }}>Au moment</option>
                <option value="15" {{ ((string) $v('reminder_minutes', '0') === '15') ? 'selected' : '' }}>15 min avant</option>
                <option value="60" {{ ((string) $v('reminder_minutes', '0') === '60') ? 'selected' : '' }}>1 h avant</option>
                <option value="1440" {{ ((string) $v('reminder_minutes', '0') === '1440') ? 'selected' : '' }}>La veille</option>
            </select>
        </div>
    </div>

    @if($isEdit && $isAdmin)
        <div class="rounded-2xl bg-white border border-[color:var(--fam-border)] shadow-sm p-4 space-y-3">
            <div class="text-sm font-semibold text-[color:var(--fam-text)]">Admin</div>
            <div>
                <label class="text-xs font-semibold text-[color:var(--fam-muted)]">Statut</label>
                <select name="status" class="mt-1 w-full h-11 rounded-2xl border border-[color:var(--fam-border-soft)] bg-white px-3 text-sm font-semibold text-[color:var(--fam-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--fam-primary)]/25">
                    <option value="active" {{ $selected('status','active') }}>Actif</option>
                    <option value="cancelled" {{ $selected('status','cancelled') }}>Annulé</option>
                    <option value="archived" {{ $selected('status','archived') }}>Archivé</option>
                </select>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
// Household data
const households = @json(config('households', []));

document.addEventListener('DOMContentLoaded', () => {
    const visibilitySelect = document.getElementById('eventVisibilitySelect');
    const privateShareBox = document.getElementById('eventPrivateShareBox');
    const locationInput = document.getElementById('eventLocationInput');
    const locationLabel = document.getElementById('eventLocationLabel');
    const locationLat = document.getElementById('eventLocationLat');
    const locationLon = document.getElementById('eventLocationLon');
    const householdSelect = document.getElementById('eventHouseholdSelect');

    const refreshPrivateShareVisibility = () => {
        if (!visibilitySelect || !privateShareBox) return;
        privateShareBox.style.display = (visibilitySelect.value === 'private') ? 'block' : 'none';
    };
    if (visibilitySelect) {
        visibilitySelect.addEventListener('change', refreshPrivateShareVisibility);
        refreshPrivateShareVisibility();
    }

    if (locationInput) {
        // If user edits manually after an autofill, we keep their value.
        locationInput.addEventListener('input', () => {
            locationInput.dataset.householdAutofilled = '0';
        });
    }

    // Household selection handler
    if (householdSelect) {
        householdSelect.addEventListener('change', (e) => {
            const key = e.target.value;
            
            if (!key || !households[key]) {
                // Back to manual: keep what the user typed, just clear derived fields.
                if (locationLabel) locationLabel.value = '';
                if (locationLat) locationLat.value = '';
                if (locationLon) locationLon.value = '';
                return;
            }

            const household = households[key];
            const address = household.address;

            // Fill visible field with full address, but store alias in location_label for display.
            if (locationInput) {
                locationInput.value = address.label || '';
                locationInput.dataset.householdAutofilled = '1';
            }
            if (locationLabel) {
                locationLabel.value = household.label || '';
            }
            
            // If coords are available, use them
            if (household.coords && household.coords.lat && household.coords.lon) {
                if (locationLat) locationLat.value = household.coords.lat;
                if (locationLon) locationLon.value = household.coords.lon;
            } else {
                // Trigger geocoding for this address
                if (locationLat) locationLat.value = '';
                if (locationLon) locationLon.value = '';
                if (address.label) {
                    fetch(`/geo/search?q=${encodeURIComponent(address.label)}`)
                        .then(r => r.json())
                        .then(results => {
                            if (results && results.length > 0) {
                                if (locationLat) locationLat.value = results[0].lat;
                                if (locationLon) locationLon.value = results[0].lon;
                            }
                        })
                        .catch(err => console.error('Geocoding error:', err));
                }
            }
        });
    }
});
</script>
@endpush
