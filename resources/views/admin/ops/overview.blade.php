<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">Admin Overview</h2>
            <div class="mt-1 text-sm text-slate-600">KPI + derniers événements</div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin.ops._nav')

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Actifs 24h</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $kpis['active_24h']) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Connexions 24h</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $kpis['logins_24h']) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Actions 24h</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $kpis['actions_24h']) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Uploads 24h</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $kpis['uploads_24h']) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Messages chat 24h</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $kpis['chat_messages_24h']) }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Erreurs 24h (ERROR+)</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $kpis['errors_24h']) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-900">Derniers événements</div>
                        <a href="{{ route('admin.activity') }}" class="text-sm font-semibold text-teal-700 hover:underline">Voir tout</a>
                    </div>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500">
                                    <th class="py-2 pr-3">Date</th>
                                    <th class="py-2 pr-3">User</th>
                                    <th class="py-2 pr-3">Type</th>
                                    <th class="py-2">Route</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($latestEvents as $ev)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2 pr-3 whitespace-nowrap text-slate-600">{{ optional($ev->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="py-2 pr-3 whitespace-nowrap text-slate-800">{{ $ev->user?->name ?? '—' }}</td>
                                        <td class="py-2 pr-3 whitespace-nowrap font-semibold text-slate-900">{{ $ev->type }}</td>
                                        <td class="py-2 text-slate-700">{{ $ev->route ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-3 text-sm text-slate-500">Aucun événement.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-900">Dernières erreurs</div>
                        <a href="{{ route('admin.errors') }}" class="text-sm font-semibold text-teal-700 hover:underline">Voir tout</a>
                    </div>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500">
                                    <th class="py-2 pr-3">Date</th>
                                    <th class="py-2 pr-3">Niveau</th>
                                    <th class="py-2 pr-3">Route</th>
                                    <th class="py-2">Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($latestErrors as $err)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2 pr-3 whitespace-nowrap text-slate-600">{{ optional($err->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="py-2 pr-3 whitespace-nowrap font-semibold text-slate-900">{{ $err->level }}</td>
                                        <td class="py-2 pr-3 whitespace-nowrap text-slate-700">{{ $err->route ?? '—' }}</td>
                                        <td class="py-2 text-slate-700">{{ \Illuminate\Support\Str::limit($err->message, 120) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-3 text-sm text-slate-500">Aucune erreur.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
