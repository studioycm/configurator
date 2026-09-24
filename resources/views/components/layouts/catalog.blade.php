<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? __('Product catalog')])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased dark:bg-zinc-950 dark:text-zinc-100" x-data="{ navigationOpen: false }">
    <a href="#catalog-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:p-3 focus:text-zinc-900">{{ __('Skip to catalog') }}</a>
    <button x-cloak x-show="navigationOpen" x-on:click="navigationOpen = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden" aria-label="{{ __('Close navigation') }}"></button>
    <aside id="dashboard-navigation" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#17212c] p-5 text-slate-200 transition-transform lg:translate-x-0" x-bind:class="navigationOpen ? 'translate-x-0 visible' : '-translate-x-full invisible lg:visible'" x-on:keydown.escape.window="navigationOpen = false">
        <a href="{{ route('dashboard') }}" aria-label="{{ __('Aquestia dashboard') }}" class="mb-2 rounded-lg bg-white px-3 py-3">
            <img src="{{ asset('images/logo-ari.png') }}" alt="Aquestia" width="269" height="56" class="h-auto w-full" />
        </a>
        <p class="mb-8 px-3 text-sm text-slate-400">{{ __('Product dashboard') }}</p>
        <nav class="space-y-2" aria-label="{{ __('Main navigation') }}">
            <a href="{{ route('catalog.index') }}" @if (request()->routeIs('catalog.*')) aria-current="page" @endif class="block rounded-lg px-3 py-3 font-medium hover:bg-slate-700 aria-[current=page]:bg-[#294762]">{{ __('Product catalog') }}</a>
            @can('manage-catalog')
                <a href="{{ route('filament.admin.pages.dashboard') }}" class="block rounded-lg px-3 py-3 font-medium hover:bg-slate-700">{{ __('Administration') }}</a>
            @endcan
        </nav>
        @auth
            <div class="mt-auto space-y-3 border-t border-slate-700 pt-4">
                <p class="truncate px-3 text-sm">{{ auth()->user()->name }}</p>
                <a href="{{ route('profile.edit') }}" class="block px-3 text-sm hover:text-white">{{ __('Account settings') }}</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="px-3 py-2 text-sm hover:text-white">{{ __('Log out') }}</button></form>
            </div>
        @endauth
    </aside>
    <div class="min-w-0 lg:pl-64">
        <header class="flex min-h-24 items-center gap-4 border-b border-slate-200 bg-white px-4 py-5 sm:px-8 dark:border-zinc-700 dark:bg-zinc-900">
            <button type="button" x-on:click="navigationOpen = ! navigationOpen" x-bind:aria-expanded="navigationOpen" aria-controls="dashboard-navigation" class="rounded border px-3 py-2 lg:hidden">{{ __('Menu') }}</button>
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $title ?? __('Product catalog') }}</h1>
                @if (! empty($subtitle))<p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $subtitle }}</p>@endif
            </div>
            <flux:button x-on:click="$flux.dark = ! $flux.dark" variant="subtle" square aria-label="{{ __('Toggle dark mode') }}">
                <flux:icon.sun variant="mini" class="hidden dark:block" /><flux:icon.moon variant="mini" class="dark:hidden" />
            </flux:button>
        </header>
        <main id="catalog-content" class="min-w-0 px-4 py-6 sm:px-8" tabindex="-1">{{ $slot }}</main>
    </div>
    @fluxScripts
</body>
</html>
