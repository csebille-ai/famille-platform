<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Users') }}
                </h2>
                <div class="mt-1 text-sm text-gray-600">
                    {{ __('Manage user roles') }}
                </div>
            </div>

            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('admin.users.invites.sendPending') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center h-10 px-4 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50">
                        Envoyer invitations en attente
                    </button>
                </form>

                <a href="{{ route('admin.users.create') }}" class="inline-flex items-center h-10 px-4 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                    {{ __('Add user') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin.ops._nav')

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900">
                    <div class="text-sm font-semibold">{{ __('Something went wrong') }}</div>
                    <ul class="mt-2 text-sm list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-slate-900">
                        {{ session('status') }}
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600">
                                    <th class="py-2 pr-4">{{ __('Name') }}</th>
                                    <th class="py-2 pr-4">{{ __('Email') }}</th>
                                    <th class="py-2 pr-4">{{ __('Role') }}</th>
                                    <th class="py-2 pr-4">Statut</th>
                                    <th class="py-2 pr-4">Dernier login</th>
                                    <th class="py-2 pr-4">Last seen</th>
                                    <th class="py-2 pr-4">Invitation</th>
                                    <th class="py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 pr-4 whitespace-nowrap">
                                            <a href="{{ route('admin.users.show', $user) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">
                                                {{ $user->name }}
                                            </a>
                                        </td>
                                        <td class="py-3 pr-4 whitespace-nowrap">
                                            <a href="{{ route('admin.users.show', $user) }}" class="text-indigo-700 hover:text-indigo-900">
                                                {{ $user->email }}
                                            </a>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex items-center gap-3">
                                                @csrf
                                                @method('PATCH')

                                                <select name="role" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role }}" @selected(($user->role ?? 'member') === $role)>
                                                            {{ $role }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <x-primary-button>
                                                    {{ __('Save') }}
                                                </x-primary-button>
                                            </form>

                                            @error('role')
                                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                                            @enderror
                                        </td>

                                        <td class="py-3 pr-4 whitespace-nowrap">
                                            @if ($user->is_active)
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">Actif</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 border border-slate-200">Désactivé</span>
                                            @endif
                                        </td>

                                        <td class="py-3 pr-4 whitespace-nowrap text-gray-700">
                                            {{ optional($user->last_login_at)->diffForHumans() ?? '—' }}
                                        </td>

                                        <td class="py-3 pr-4 whitespace-nowrap text-gray-700">
                                            {{ optional($user->last_seen_at)->diffForHumans() ?? '—' }}
                                        </td>

                                        <td class="py-3 pr-4 whitespace-nowrap">
                                            @if ($user->invited_at)
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                                    Envoyée
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900 border border-amber-200">
                                                    En attente
                                                </span>
                                            @endif
                                        </td>

                                        <td class="py-3 whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.users.invite', $user) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center h-9 px-3 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                                                        {{ $user->invited_at ? 'Renvoyer invitation' : 'Envoyer invitation' }}
                                                    </button>
                                                </form>

                                                @if (auth()->id() !== $user->id)
                                                    <form method="POST" action="{{ route('admin.users.active', $user) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="inline-flex items-center h-9 px-3 rounded-md border border-slate-300 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">
                                                            {{ $user->is_active ? 'Désactiver' : 'Activer' }}
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-slate-400 px-2">—</span>
                                                @endif

                                                <form method="POST" action="{{ route('admin.users.revokeSessions', $user) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center h-9 px-3 rounded-md border border-slate-300 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50">
                                                        Revoke sessions
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        {{ $users->links() }}
                    </div>
            </div>
        </div>
    </div>
</x-app-layout>
