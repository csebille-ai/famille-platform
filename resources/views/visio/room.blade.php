<x-app-layout>
    @php
        $provider = (string) ($provider ?? config('visio.provider') ?? 'link');
        $room = (string) ($room ?? '');
        $domain = (string) ($domain ?? config('visio.jitsi_domain') ?? 'meet.jit.si');

        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim($domain, "/");

        $roomSafe = trim($room);
        $encodedRoom = rawurlencode($roomSafe);

        $jitsiUrl = ($domain !== '' && $roomSafe !== '')
            ? ('https://' . $domain . '/' . $encodedRoom)
            : '';

        $isHttps = request()->isSecure();
    @endphp

    <div style="position:fixed; inset:0; background:#000;">
        @if (!$isHttps)
            <div style="position:absolute; top:0; left:0; right:0; z-index:20; padding:10px 12px; background:rgba(17,24,39,.95); color:#fff; font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto;">
                HTTPS est requis pour la caméra / micro. Ouvre ce site en <strong>https</strong>.
            </div>
        @endif

        <div style="position:absolute; top:12px; right:12px; z-index:30; display:flex; gap:10px;">
            <a
                href="{{ $jitsiUrl }}"
                target="_blank"
                rel="noopener"
                style="display:inline-flex; align-items:center; gap:8px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,.10); color:#fff; text-decoration:none; font:600 13px/1 system-ui,-apple-system,Segoe UI,Roboto; border:1px solid rgba(255,255,255,.18); backdrop-filter:saturate(140%) blur(10px);"
                title="Ouvre la visio dans un nouvel onglet"
            >
                <span aria-hidden="true">↗</span>
                Ouvrir en plein écran
            </a>
        </div>

        @if ($provider !== 'jitsi')
            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding:24px; color:#fff; font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto; text-align:center;">
                <div style="max-width:520px;">
                    <div style="font-weight:700; font-size:16px; margin-bottom:8px;">Visio</div>
                    <div style="opacity:.85;">Le provider visio n’est pas configuré sur <strong>jitsi</strong>.</div>
                </div>
            </div>
        @elseif ($jitsiUrl === '')
            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding:24px; color:#fff; font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto; text-align:center;">
                <div style="max-width:520px;">
                    <div style="font-weight:700; font-size:16px; margin-bottom:8px;">Visio</div>
                    <div style="opacity:.85;">Room invalide ou domaine Jitsi manquant.</div>
                </div>
            </div>
        @else
            <iframe
                src="{{ $jitsiUrl }}"
                style="width:100%; height:100%; border:0;"
                allow="camera; microphone; fullscreen; display-capture"
                allowfullscreen
            ></iframe>
        @endif
    </div>
</x-app-layout>
