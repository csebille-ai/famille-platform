@php
    $maxUploadMb = (int) ($maxUploadMb ?? 0);
@endphp

<x-app-layout pageBgClass="fam-page-bg">
    <div
        class="max-w-3xl mx-auto px-6 py-6 space-y-6"
        x-data="{
            uploadFileName: '',
            uploadFileSize: '',
            isDragOver: false,
            setFileFromInput(e) {
                const f = e?.target?.files?.[0];
                if (!f) {
                    this.uploadFileName = '';
                    this.uploadFileSize = '';
                    return;
                }
                this.uploadFileName = f.name;
                this.uploadFileSize = `${Math.round(f.size / 1024 / 1024 * 10) / 10} MB`;
            },
            setFileFromDrop(e) {
                const f = e?.dataTransfer?.files?.[0];
                if (!f) return;
                if (this.$refs.uploadInput) {
                    this.$refs.uploadInput.files = e.dataTransfer.files;
                }
                this.uploadFileName = f.name;
                this.uploadFileSize = `${Math.round(f.size / 1024 / 1024 * 10) / 10} MB`;
            },
        }"
    >
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Importer une image</h1>
                <div class="microcopy text-sm text-slate-500 mt-1">Taille max : {{ $maxUploadMb > 0 ? $maxUploadMb : 100 }} MB</div>
            </div>

            <a href="{{ route('media.index', ['tab' => 'photos']) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900">
                Retour
            </a>
        </div>

        @if (session('status'))
            <div class="bg-white rounded-2xl shadow-sm p-4 text-sm text-gray-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <div class="text-sm font-semibold text-red-600">Erreur</div>
                <ul class="mt-2 space-y-1 text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <form method="POST" action="{{ route('images.store') }}" enctype="multipart/form-data" x-on:submit="if(!$refs.uploadInput?.files?.length){ $event.preventDefault(); $refs.uploadInput?.click(); }">
                @csrf

                <input
                    x-ref="uploadInput"
                    id="image-upload-input"
                    name="image"
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    required
                    x-on:change="setFileFromInput($event)"
                />

                <div
                    class="border-2 border-dashed border-slate-200 rounded-2xl p-6"
                    :class="isDragOver ? 'bg-slate-50' : 'bg-white'"
                    x-on:dragover.prevent="isDragOver = true"
                    x-on:dragleave.prevent="isDragOver = false"
                    x-on:drop.prevent="isDragOver = false; setFileFromDrop($event)"
                    x-on:click="$refs.uploadInput?.click()"
                    role="button"
                    tabindex="0"
                    x-on:keydown.enter.prevent="$refs.uploadInput?.click()"
                    x-on:keydown.space.prevent="$refs.uploadInput?.click()"
                    aria-label="Zone d'import"
                >
                    <div class="text-center">
                        <div class="text-sm font-medium text-gray-900">Glissez-déposez une image ici</div>
                        <div class="microcopy text-sm text-slate-500 mt-1">ou</div>
                        <div class="mt-3">
                            <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-gray-900" x-on:click.stop="$refs.uploadInput?.click()">
                                Choisir un fichier
                            </button>
                        </div>

                        <template x-if="uploadFileName">
                            <div class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-gray-900">
                                <span class="font-medium" x-text="uploadFileName"></span>
                                <span class="text-slate-500" x-text="uploadFileSize"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end">
                    <button type="submit" class="bg-slate-900 text-white rounded-xl px-4 py-2 text-sm font-semibold">
                        Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
