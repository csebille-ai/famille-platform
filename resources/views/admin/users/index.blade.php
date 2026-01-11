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

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ session('status') }}
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600">
                                    <th class="py-2 pr-4">{{ __('Name') }}</th>
                                    <th class="py-2 pr-4">{{ __('Email') }}</th>
                                    <th class="py-2 pr-4">{{ __('Role') }}</th>
                                    <th class="py-2 pr-4">Invitation</th>
                                    <th class="py-2">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 pr-4 whitespace-nowrap">{{ $user->name }}</td>
                                        <td class="py-3 pr-4 whitespace-nowrap">{{ $user->email }}</td>
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

                                        <td class="py-3 whitespace-nowrap text-right">
                                            <form method="POST" action="{{ route('admin.users.invite', $user) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center h-9 px-3 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                                                    {{ $user->invited_at ? 'Renvoyer invitation' : 'Envoyer invitation' }}
                                                </button>
                                            </form>
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
    </div>
</x-app-layout>
