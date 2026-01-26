<x-app-layout pageBgClass="fam-page-bg">
    <div class="chat-theme flex flex-col min-h-[calc(100vh-var(--mobile-bottom-nav-h,4rem))]" data-chat-view="1">
        @include('chat.partials.index-header')
        @include('chat.partials.index-messages')

        <div class="sm:hidden border-t border-slate-100 bg-white sticky bottom-[calc(var(--mobile-bottom-nav-h,4rem)+env(safe-area-inset-bottom))] z-40">
            @include('chat.partials.index-bottom-dock')
        </div>

        @include('chat.partials.index-composer-desktop')
        @include('chat.partials.overlays')
        @include('chat.partials.bootstrap')

        @vite(['resources/js/chat-page.js'])
    </div>
</x-app-layout>
