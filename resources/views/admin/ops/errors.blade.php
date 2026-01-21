<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">Errors</h2>
            <div class="mt-1 text-sm text-slate-600">Logs (DB)</div>
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
                        <div class="text-xs font-semibold text-slate-500">Niveau</div>
                        <select name="level" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Tous</option>
                            @foreach($levels as $lvl)
                                <option value="{{ $lvl }}" @selected($filters['level'] === $lvl)>{{ $lvl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <button type="submit" class="inline-flex items-center h-10 px-4 rounded-xl bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">Filtrer</button>
                        <a href="{{ route('admin.errors') }}" class="text-sm font-semibold text-slate-600 hover:underline">Reset</a>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">Niveau</th>
                            <th class="py-2 pr-3">Route</th>
                            <th class="py-2 pr-3">User</th>
                            <th class="py-2">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($errors as $e)
                            <tr class="border-t border-slate-100 align-top">
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-600">{{ optional($e->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap font-semibold text-slate-900">{{ $e->level }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-700">{{ $e->route ?? '—' }}</td>
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-700">{{ $e->user?->name ?? ($e->user_id ? ('#'.$e->user_id) : '—') }}</td>
                                <td class="py-2 text-slate-700">
                                    <div class="font-semibold text-slate-900">{{ \Illuminate\Support\Str::limit($e->message, 180) }}</div>
                                    @if(!empty($e->stacktrace))
                                        <details class="mt-2">
                                            <summary class="cursor-pointer text-xs font-semibold text-teal-700">Stacktrace</summary>
                                            <pre class="mt-2 whitespace-pre-wrap text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-xl p-3">{{ $e->stacktrace }}</pre>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-slate-500">Aucune erreur sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $errors->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
