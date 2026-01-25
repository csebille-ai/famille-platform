<x-app-layout pageBgClass="fam-page-bg">
    @include('chat.partials.index-helpers')

    <x-slot name="bottomDock">
        @include('chat.partials.index-bottom-dock')
    </x-slot>

    <div class="max-w-6xl mx-auto px-0 sm:px-6 py-0 sm:py-6 space-y-4 sm:space-y-6">
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

        <div class="bg-white sm:rounded-2xl shadow-sm flex flex-col h-[calc(100dvh-var(--app-nav-h,0px)-var(--mobile-bottom-nav-h,4rem)-env(safe-area-inset-bottom))] sm:h-[calc(100vh-10rem)] sm:overflow-hidden">
                        @include('chat.partials.index-header')

            @include('chat.partials.index-messages')

            @include('chat.partials.index-composer-desktop')
@include('chat.partials.overlays')
    </div>
    @include('chat.partials.bootstrap')
    @vite(['resources/js/chat-page.js'])
</x-app-layout>
