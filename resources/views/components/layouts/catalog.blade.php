@props(['title' => null, 'heading' => null, 'subtitle' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? __('Product catalog')])
    @include('filament.resources.appearance-variables')
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="catalog-shell" x-data="catalogShell" x-bind:class="{ 'catalog-shell-collapsed': collapsed, 'catalog-shell-mobile-open': mobileOpen }" x-on:keydown.escape.window="if (mobileOpen && !hasOpenDialog()) closeMobile()">
        <a href="#catalog-content" x-bind:inert="mobileOpen && !isDesktop" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:p-3 focus:text-zinc-900">{{ __('Skip to content') }}</a>
        <div x-cloak x-show="mobileOpen && !isDesktop" x-on:click="closeMobile()" class="catalog-navigation-backdrop" aria-hidden="true"></div>
        <aside id="dashboard-navigation" x-ref="navigation" class="catalog-navigation" x-bind:inert="!isDesktop && !mobileOpen" x-bind:role="isDesktop ? 'complementary' : 'dialog'" x-bind:aria-modal="mobileOpen && !isDesktop ? 'true' : null" x-bind:aria-hidden="!isDesktop && !mobileOpen ? 'true' : null" aria-label="{{ __('Main navigation') }}" x-on:keydown="trapFocus($event)">
            <div class="catalog-brand-row">
                <a href="{{ route('dashboard') }}" class="catalog-brand-link" aria-label="{{ __('Aquestia dashboard') }}">
                    <img src="{{ asset('images/logo-ari_dark.png') }}" alt="Aquestia" width="269" height="56" />
                </a>
                <button type="button" class="catalog-shell-control hidden lg:inline-flex" x-on:click="toggleDesktop()" x-bind:aria-label="collapsed ? @js(__('Expand navigation')) : @js(__('Collapse navigation'))" x-bind:title="collapsed ? @js(__('Expand navigation')) : @js(__('Collapse navigation'))" x-bind:aria-expanded="!collapsed" aria-controls="dashboard-navigation">
                    <flux:icon.chevron-left class="size-5" x-show="!collapsed" />
                    <flux:icon.chevron-right class="size-5" x-cloak x-show="collapsed" />
                </button>
                <button type="button" x-ref="closeNavigation" class="catalog-shell-control inline-flex lg:hidden" x-on:click="closeMobile()" aria-label="{{ __('Close navigation') }}" aria-controls="dashboard-navigation">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>
            <div class="catalog-navigation-content">
                <nav class="grid gap-1" aria-label="{{ __('Main navigation') }}">
                    <flux:tooltip :content="__('Product catalog')" position="right">
                        <a href="{{ route('catalog.index') }}" aria-label="{{ __('Product catalog') }}" @if (request()->routeIs('catalog.*')) aria-current="page" @endif class="catalog-navigation-item">
                            <flux:icon.cube class="size-5 shrink-0" /><span class="catalog-navigation-label">{{ __('Product catalog') }}</span>
                        </a>
                    </flux:tooltip>
                </nav>
            </div>
            @auth
                <div class="catalog-navigation-footer">
                    <p class="catalog-navigation-label truncate px-2 text-xs text-slate-400">{{ auth()->user()->name }}</p>
                    <flux:tooltip :content="__('Account settings')" position="right">
                        <a href="{{ route('profile.edit') }}" wire:navigate aria-label="{{ __('Account settings') }}" @if (request()->routeIs('profile.edit', 'user-password.edit', 'appearance.edit', 'two-factor.show')) aria-current="page" @endif class="catalog-navigation-item">
                            <flux:icon.cog-6-tooth class="size-5 shrink-0" /><span class="catalog-navigation-label">{{ __('Account settings') }}</span>
                        </a>
                    </flux:tooltip>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:tooltip :content="__('Log out')" position="right">
                            <button type="submit" aria-label="{{ __('Log out') }}" class="catalog-navigation-item">
                                <flux:icon.arrow-right-start-on-rectangle class="size-5 shrink-0" /><span class="catalog-navigation-label">{{ __('Log out') }}</span>
                            </button>
                        </flux:tooltip>
                    </form>
                </div>
            @endauth
        </aside>
        <div class="catalog-shell-main" x-bind:inert="mobileOpen && !isDesktop">
            <x-page-header :heading="$heading ?? $title ?? __('Product catalog')" :subtitle="$subtitle">
                <x-slot:leading>
                    <button type="button" class="catalog-shell-control inline-flex lg:hidden" x-on:click="openMobile()" x-bind:aria-expanded="mobileOpen" aria-controls="dashboard-navigation" aria-label="{{ __('Open navigation') }}">
                        <flux:icon.bars-3 class="size-5" />
                    </button>
                </x-slot:leading>
                @isset($breadcrumbs)
                    <x-slot:breadcrumbs>{{ $breadcrumbs }}</x-slot:breadcrumbs>
                @endisset
                <x-slot:actions>
                    {{ $headerActions ?? '' }}
                    <flux:button x-on:click="$flux.dark = ! $flux.dark" variant="subtle" square size="sm" aria-label="{{ __('Toggle dark mode') }}">
                        <flux:icon.sun variant="mini" class="hidden dark:block" /><flux:icon.moon variant="mini" class="dark:hidden" />
                    </flux:button>
                </x-slot:actions>
            </x-page-header>
            <main id="catalog-content" class="catalog-main-content" tabindex="-1">{{ $slot }}</main>
        </div>
    </div>
    @fluxScripts
</body>
</html>
