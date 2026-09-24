<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? __('Product catalog')])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#catalog-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3 focus:text-zinc-900">{{ __('Skip to catalog') }}</a>
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 px-4 py-3 sm:px-6 lg:px-8">
                <nav class="col-start-2 flex items-center gap-6" aria-label="{{ __('Main navigation') }}">
                    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif class="rounded-sm py-2 text-sm font-medium text-zinc-600 underline-offset-8 hover:text-zinc-900 hover:underline aria-[current=page]:text-zinc-900 aria-[current=page]:underline focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-zinc-400 dark:hover:text-white dark:aria-[current=page]:text-white">{{ __('Home') }}</a>
                    <a href="{{ route('catalog.index') }}" @if (request()->routeIs('catalog.*')) aria-current="page" @endif class="rounded-sm py-2 text-sm font-medium text-zinc-600 underline-offset-8 hover:text-zinc-900 hover:underline aria-[current=page]:text-zinc-900 aria-[current=page]:underline focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-zinc-400 dark:hover:text-white dark:aria-[current=page]:text-white">{{ __('Product catalog') }}</a>
                </nav>
                <div class="justify-self-end">
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" variant="subtle" square aria-label="{{ __('Toggle dark mode') }}" x-bind:aria-pressed="$flux.dark.toString()">
                        <flux:icon.sun variant="mini" class="hidden dark:block" />
                        <flux:icon.moon variant="mini" class="dark:hidden" />
                    </flux:button>
                </div>
            </div>
        </header>
        <main id="catalog-content" class="w-full px-4 py-8 sm:px-6 lg:px-8" tabindex="-1">
            {{ $slot }}
        </main>
        @fluxScripts
    </body>
</html>
