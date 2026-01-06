<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500">Ressources</div>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Créer une ressource') }}
                </h2>
            </div>

            <a href="{{ route('resources.index') }}" class="inline-flex items-center px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">
                Retour
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-semibold text-red-700">Certaines informations sont invalides.</div>
                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('resources.store') }}" class="space-y-6" enctype="multipart/form-data">
                        @csrf

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <div class="text-sm font-semibold text-gray-900">Cible</div>
                            <div class="mt-4">
                                <x-input-label for="concerned_user_id" :value="__('Concerne')" />
                                <select id="concerned_user_id" name="concerned_user_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    @php $concern = old('concerned_user_id'); @endphp
                                    <option value="" @selected($concern === null || $concern === '')>Commun (tout le monde)</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected((string) $concern === (string) $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-1 text-xs text-gray-500">Choisis une personne ou “Commun”.</div>
                                <x-input-error class="mt-2" :messages="$errors->get('concerned_user_id')" />
                            </div>
                        </div>

                        <div>
                            <div class="rounded-2xl border border-gray-200 p-5">
                                <div class="text-sm font-semibold text-gray-900">Détails</div>

                                <div class="mt-4">
                                    <x-input-label for="title" :value="__('Titre')" />
                                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required autofocus />
                                    <x-input-error class="mt-2" :messages="$errors->get('title')" />
                                </div>

                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="section" :value="__('Section')" />
                                        <select id="section" name="section" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                            @php $section = old('section', 'administratives'); @endphp
                                            <option value="administratives" @selected($section === 'administratives')>1 — Administratives</option>
                                            <option value="pratiques" @selected($section === 'pratiques')>2 — Pratiques</option>
                                            <option value="utiles" @selected($section === 'utiles')>3 — Utiles</option>
                                        </select>
                                        <x-input-error class="mt-2" :messages="$errors->get('section')" />
                                    </div>

                                    <div>
                                        <x-input-label for="folder" :value="__('Dossier')" />
                                        <select id="folder" name="folder" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                            @php $folder = old('folder', 'A1'); @endphp
                                            <option value="A1" @selected($folder === 'A1')>A1</option>
                                            <option value="A2" @selected($folder === 'A2')>A2</option>
                                            <option value="A3" @selected($folder === 'A3')>A3</option>
                                        </select>
                                        <x-input-error class="mt-2" :messages="$errors->get('folder')" />
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <x-input-label for="content" :value="__('Note (optionnel)')" />
                                    <textarea id="content" name="content" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" rows="4" placeholder="Optionnel : une petite note…">{{ old('content') }}</textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('content')" />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">Fichier</div>
                                    <div class="mt-1 text-xs text-gray-500">PDF, image, document… (vidéos interdites)</div>
                                </div>
                                <span class="text-xs px-2.5 py-1.5 rounded-full border border-gray-200 bg-gray-50 text-gray-700">Max 20 Mo</span>
                            </div>

                            <div class="mt-4">
                                <input id="file" name="file" type="file" class="block w-full text-sm text-gray-900 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-900 file:text-white hover:file:bg-gray-700" accept="image/*,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/rtf,application/vnd.oasis.opendocument.text" />
                                <x-input-error class="mt-2" :messages="$errors->get('file')" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('resources.index') }}" class="text-sm text-gray-700 hover:underline">
                                Annuler
                            </a>
                            <x-primary-button>
                                Enregistrer
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
