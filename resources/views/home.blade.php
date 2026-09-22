<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Home')])
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="flex min-h-screen items-center justify-center px-6 py-16">
            <section class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm sm:p-10 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="home-heading">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Product configurator') }}</p>
                <h1 id="home-heading" class="mt-3 text-4xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
                <p class="mt-4 text-base leading-7 text-zinc-600 dark:text-zinc-400">{{ __('Sign in to manage your products and configurations.') }}</p>
                <a href="{{ route('filament.admin.auth.login') }}" class="mt-8 inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-zinc-900 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-zinc-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-zinc-900 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200 dark:focus-visible:outline-white">
                    {{ __('Log in to admin panel') }}
                </a>
            </section>
        </main>
        @fluxScripts
    </body>
</html>
