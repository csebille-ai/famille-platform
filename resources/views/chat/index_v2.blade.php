<x-app-layout pageBgClass="fam-page-bg" internalScroll="1">
    <x-slot name="bottomDock">
        <div class="sm:hidden border-t border-slate-100 bg-white">
            @include('chat.partials.index-bottom-dock')
        </div>
    </x-slot>

    <div class="chat-theme flex flex-col h-full min-h-0 sm:h-auto" data-chat-view="1">
        @include('chat.partials.index-header')
        @include('chat.partials.index-messages')

        @include('chat.partials.index-composer-desktop')
        @include('chat.partials.overlays')
        @include('chat.partials.bootstrap')

        @vite(['resources/js/chat-page.js'])
    </div>
</x-app-layout>
