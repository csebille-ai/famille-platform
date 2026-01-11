<x-app-layout pageBgClass="bg-slate-50">
    @php
        $provider = (string) (config('visio.provider') ?? 'link');
        $visioUrl = (string) (config('visio.url') ?? '');
        $visioLabel = (string) (config('visio.label') ?? 'Rejoindre la visio');
        $hasUrl = trim($visioUrl) !== '';

        $defaultRoom = (string) (config('visio.default_room') ?? 'famille');
        $jitsiDomain = (string) (config('visio.jitsi_domain') ?? 'meet.jit.si');
        $jitsiDomain = preg_replace('#^https?://#i', '', trim($jitsiDomain));
        $jitsiDomain = rtrim((string) $jitsiDomain, "/");
        $jitsiDirectUrl = $jitsiDomain !== '' ? ('https://' . $jitsiDomain . '/' . rawurlencode($defaultRoom)) : '';
    @endphp

    <div class="max-w-3xl mx-auto px-6 py-6 space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-base font-semibold text-gray-900">Visio</div>
            <div class="text-sm text-slate-500 mt-1">
                Une salle visio (Jitsi en iframe ou lien externe).
            </div>

            @if ($provider === 'jitsi')
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a
                        href="{{ route('visio.room', ['room' => $defaultRoom]) }}"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        <i class="ph ph-video-camera mr-2" aria-hidden="true"></i>
                        {{ $visioLabel }}
                    </a>

                    @if ($jitsiDirectUrl)
                        <a
                            href="{{ $jitsiDirectUrl }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 hover:bg-slate-50"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="ph ph-arrow-square-out mr-2" aria-hidden="true"></i>
                            Ouvrir en plein écran
                        </a>
                    @endif
                </div>

                <div class="mt-4 text-xs text-slate-500">
                    Astuce iPhone/iPad: si la caméra/micro ne marchent pas dans l’iframe, utilise “Ouvrir en plein écran”.
                </div>
            @elseif (!$hasUrl)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    La visio n’est pas configurée. Ajoute <span class="font-mono">VISIO_URL</span> dans le <span class="font-mono">.env</span>.
                </div>
            @else
                <div class="mt-5 flex items-center gap-3">
                    <a
                        href="{{ $visioUrl }}"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="ph ph-video-camera mr-2" aria-hidden="true"></i>
                        {{ $visioLabel }}
                    </a>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 hover:bg-slate-50"
                        x-data
                        @click="navigator.clipboard?.writeText('{{ $visioUrl }}')"
                    >
                        <i class="ph ph-copy mr-2" aria-hidden="true"></i>
                        Copier le lien
                    </button>
                </div>

                <div class="mt-4 text-xs text-slate-500 break-all">
                    {{ $visioUrl }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
