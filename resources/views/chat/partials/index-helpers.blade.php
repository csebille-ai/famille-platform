@php
        $ATTACH_PREFIX = '[[ATTACHMENT]]';
        $parseAttachment = function (?string $body) use ($ATTACH_PREFIX): ?array {
            $body = (string) $body;
            if (!str_starts_with($body, $ATTACH_PREFIX)) {
                return null;
            }
            $json = substr($body, strlen($ATTACH_PREFIX));
            $data = json_decode($json, true);
            if (!is_array($data)) {
                return null;
            }
            $type = (string) ($data['media_type'] ?? '');
            if (!in_array($type, ['image', 'video'], true)) {
                return null;
            }
            return $data;
        };

        $parseLinkCard = function (?string $body): ?array {
            $b = trim((string) $body);
            if ($b === '') return null;

            $url = null;
            if (preg_match('/^📹\s*Visio:\s*(https?:\/\/\S+)\s*$/u', $b, $m)) {
                $url = $m[1] ?? null;
            } elseif (preg_match('/^(https?:\/\/\S+)\s*$/u', $b, $m)) {
                $url = $m[1] ?? null;
            }
            $url = $url ? trim((string) $url) : null;
            if (!$url) return null;

            $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
            $domain = $host !== '' ? $host : preg_replace('/^https?:\/\//i', '', $url);

            $title = 'Lien';
            if (str_contains($b, 'Visio') || str_contains($domain, 'jit.si')) {
                $title = 'Appel vidéo';
            }

            return [
                'url' => $url,
                'domain' => $domain,
                'title' => $title,
            ];
        };

        $palette = [
            ['chip' => 'bg-indigo-50 text-indigo-700 border-indigo-200', 'avatar' => 'bg-indigo-600 text-white'],
            ['chip' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'avatar' => 'bg-emerald-600 text-white'],
            ['chip' => 'bg-amber-50 text-amber-800 border-amber-200', 'avatar' => 'bg-amber-600 text-white'],
            ['chip' => 'bg-rose-50 text-rose-700 border-rose-200', 'avatar' => 'bg-rose-600 text-white'],
            ['chip' => 'bg-sky-50 text-sky-700 border-sky-200', 'avatar' => 'bg-sky-600 text-white'],
            ['chip' => 'bg-violet-50 text-violet-700 border-violet-200', 'avatar' => 'bg-violet-600 text-white'],
        ];

        $paletteFor = function (?int $userId) use ($palette) {
            if (!$userId) return $palette[0];
            return $palette[$userId % count($palette)];
        };

        $initialsFor = function (?string $name) {
            $name = trim((string) $name);
            if ($name === '') return '—';
            $parts = preg_split('/\s+/', $name);
            $first = $parts[0] ?? '';
            $last = $parts[count($parts) - 1] ?? '';
            $initials = mb_substr($first, 0, 1);
            if ($last && $last !== $first) {
                $initials .= mb_substr($last, 0, 1);
            }
            return mb_strtoupper($initials);
        };

        $firstNameFor = function (?string $name) {
            $name = trim((string) $name);
            if ($name === '') return '—';
            $parts = preg_split('/\s+/', $name);
            return $parts[0] ?? $name;
        };

        $onlineList = collect($initialOnline ?? [])
            ->map(function ($u) {
                $id = data_get($u, 'id') ?? data_get($u, 'user_id') ?? data_get($u, 'user.id');
                $name = data_get($u, 'name') ?? data_get($u, 'user.name');
                return ['id' => $id ? (int) $id : null, 'name' => $name ?: '—'];
            })
            ->filter(fn ($u) => !empty($u['id']))
            ->values();

        if (auth()->check()) {
            $onlineList = $onlineList->prepend([
                'id' => (int) auth()->id(),
                'name' => auth()->user()?->name ?? 'Vous',
            ]);
        }

        $onlineList = $onlineList->unique('id')->values();
    @endphp
