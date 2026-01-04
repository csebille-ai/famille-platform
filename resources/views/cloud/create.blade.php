{{-- Legacy V1 view — kept temporarily for reference, not used in V2 --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Upload File') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('cloud.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="folder_path" :value="__('Folder path')" />
                            <x-text-input id="folder_path" name="folder_path" type="text" class="mt-1 block w-full" :value="old('folder_path', $folderPath)" />
                            <x-input-error class="mt-2" :messages="$errors->get('folder_path')" />
                        </div>

                        <div>
                            <x-input-label for="file" :value="__('File')" />
                            <input id="file" name="file" type="file" class="mt-1 block w-full" required />
                            @if (!empty($maxUploadMb))
                                <div class="mt-2 text-sm text-gray-600">
                                    {{ __('Taille max : :mb MB', ['mb' => $maxUploadMb]) }}
                                </div>
                            @endif
                            <x-input-error class="mt-2" :messages="$errors->get('file')" />
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('cloud.index', ['folder_path' => old('folder_path', $folderPath)]) }}" class="text-sm text-gray-700 hover:underline">
                                {{ __('Cancel') }}
                            </a>
                            <x-primary-button>
                                {{ __('Upload') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
