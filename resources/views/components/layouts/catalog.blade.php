<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? __('Product catalog')])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#catalog-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3 focus:text-zinc-900">{{ __('Skip to catalog') }}</a>
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" aria-label="{{ __('Aquestia home') }}" class="col-start-1 row-start-1 inline-flex min-h-10 min-w-0 items-center justify-self-start rounded-md bg-white px-2 py-1 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">
                    <img src="{{ asset('images/logo-ari.png') }}" alt="Aquestia" width="269" height="56" class="h-7 w-auto max-w-full object-contain" />
                </a>
                <nav class="col-span-2 row-start-2 flex items-center justify-self-center gap-6 sm:col-span-1 sm:col-start-2 sm:row-start-1" aria-label="{{ __('Main navigation') }}">
                    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif class="rounded-sm py-2 text-sm font-medium text-zinc-600 underline-offset-8 hover:text-zinc-900 hover:underline aria-[current=page]:text-zinc-900 aria-[current=page]:underline focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-zinc-400 dark:hover:text-white dark:aria-[current=page]:text-white">{{ __('Home') }}</a>
                    <a href="{{ route('catalog.index') }}" @if (request()->routeIs('catalog.*')) aria-current="page" @endif class="rounded-sm py-2 text-sm font-medium text-zinc-600 underline-offset-8 hover:text-zinc-900 hover:underline aria-[current=page]:text-zinc-900 aria-[current=page]:underline focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-zinc-400 dark:hover:text-white dark:aria-[current=page]:text-white">{{ __('Product catalog') }}</a>
                </nav>
                <div class="col-start-2 row-start-1 flex items-center justify-self-end gap-2 sm:col-start-3">
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" variant="subtle" square aria-label="{{ __('Toggle dark mode') }}" x-bind:aria-pressed="$flux.dark.toString()">
                        <flux:icon.sun variant="mini" class="hidden dark:block" />
                        <flux:icon.moon variant="mini" class="dark:hidden" />
                    </flux:button>
                    <a href="{{ route('filament.admin.auth.login') }}" class="inline-flex min-h-10 shrink-0 items-center rounded-sm px-2 text-sm font-medium text-zinc-600 underline-offset-4 hover:text-zinc-900 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-zinc-400 dark:hover:text-white">{{ __('Log in') }}</a>
                </div>
            </div>
        </header>
        <main id="catalog-content" class="w-full px-4 py-8 sm:px-6 lg:px-8" tabindex="-1">
            {{ $slot }}
        </main>
        @fluxScripts
    </body>
</html>
