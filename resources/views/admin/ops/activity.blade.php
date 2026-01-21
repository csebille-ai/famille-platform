<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">Activity</h2>
            <div class="mt-1 text-sm text-slate-600">Audit log</div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin.ops._nav')

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <div class="text-xs font-semibold text-slate-500">Période</div>
                        <select name="period" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="24h" @selected($filters['period'] === '24h')>24h</option>
                            <option value="7d" @selected($filters['period'] === '7d')>7j</option>
                            <option value="30d" @selected($filters['period'] === '30d')>30j</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-slate-500">User</div>
                        <select name="user_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="0">Tous</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @selected((int) $filters['user_id'] === (int) $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-slate-500">Type</div>
                        <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Tous</option>
                            @foreach($types as $t)
                                <option value="{{ $t }}" @selected($filters['type'] === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3 flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center h-10 px-4 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">Filtrer</button>
                        <a href="{{ route('admin.activity') }}" class="text-sm font-semibold text-slate-600 hover:underline">Reset</a>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">User</th>
                            <th class="py-2 pr-3">Type</th>
                            <th class="py-2 pr-3">Route</th>
                            <th class="py-2">Meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $ev)
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-600">{{ optional($ev->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-800">{{ $ev->user?->name ?? '—' }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap font-semibold text-slate-900">{{ $ev->type }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-700">{{ $ev->route ?? '—' }}</td>
                                <td class="py-2 text-slate-700">
                                    @php
                                        $meta = is_array($ev->metadata ?? null) ? $ev->metadata : [];
                                        $metaStr = $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
                                    @endphp
                                    <span class="text-xs">{{ \Illuminate\Support\Str::limit($metaStr, 140) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-slate-500">Aucun événement.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $events->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
