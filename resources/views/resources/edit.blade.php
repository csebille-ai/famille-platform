<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Resource') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('resources.update', $resource) }}" class="space-y-6" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="concerned_user_id" :value="__('Concerne')" />
                            <select id="concerned_user_id" name="concerned_user_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @php $concern = old('concerned_user_id', $resource->concerned_user_id); @endphp
                                <option value="" @selected($concern === null || $concern === '')>Commun (tout le monde)</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected((string) $concern === (string) $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('concerned_user_id')" />
                        </div>

                        <div>
                            <x-input-label for="title" :value="__('Title')" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $resource->title)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('title')" />
                        </div>

                        <div>
                            <x-input-label for="section" :value="__('Section')" />
                            @php $section = old('section', $resource->section ?? 'administratives'); @endphp
                            <select id="section" name="section" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="administratives" @selected($section === 'administratives')>1 — Administratives</option>
                                <option value="pratiques" @selected($section === 'pratiques')>2 — Pratiques</option>
                                <option value="utiles" @selected($section === 'utiles')>3 — Utiles</option>
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('section')" />
                        </div>

                        <div>
                            <x-input-label for="folder" :value="__('Dossier')" />
                            @php $folder = old('folder', $resource->folder ?? 'A1'); @endphp
                            <select id="folder" name="folder" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="A1" @selected($folder === 'A1')>A1</option>
                                <option value="A2" @selected($folder === 'A2')>A2</option>
                                <option value="A3" @selected($folder === 'A3')>A3</option>
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('folder')" />
                        </div>

                        <div>
                            <x-input-label for="content" :value="__('Content')" />
                            <textarea id="content" name="content" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" rows="4" placeholder="Optionnel : une petite note…">{{ old('content', $resource->content) }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('content')" />
                        </div>

                        <div>
                            <x-input-label for="files" :value="__('Ajouter des fichiers (optionnel)')" />
                            <div class="mt-1 text-xs text-gray-500">Les fichiers existants sont conservés.</div>
                            <input id="files" name="files[]" type="file" multiple class="mt-2 block w-full text-sm text-gray-900 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-900 file:text-white hover:file:bg-gray-700" accept="image/*,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/rtf,application/vnd.oasis.opendocument.text" />
                            <div class="mt-1 text-xs text-gray-500">Vidéos non autorisées. Taille max: 20 Mo.</div>
                            <x-input-error class="mt-2" :messages="$errors->get('files')" />
                            <x-input-error class="mt-2" :messages="$errors->get('files.*')" />
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('resources.show', $resource) }}" class="text-sm text-gray-700 hover:underline">
                                {{ __('Cancel') }}
                            </a>
                            <x-primary-button>
                                {{ __('Save') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <form method="POST" action="{{ route('resources.destroy', $resource) }}" onsubmit="return confirm('Delete this resource?');">
                            @csrf
                            @method('DELETE')
                            <x-danger-button>
                                {{ __('Delete') }}
                            </x-danger-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
