<div>
    @if ($groups->isNotEmpty())
        <p class="mt-4 max-w-2xl text-zinc-600 dark:text-zinc-400">{{ __('Choose a product group to explore its products.') }}</p>
        <x-catalog.group-tree :groups="$groups" />
    @else
        <section class="mt-8 max-w-2xl border-l-4 border-zinc-300 py-2 pl-6 dark:border-zinc-600">
            <h2 class="text-xl font-medium">{{ __('The catalog is being prepared.') }}</h2>
            <p class="mt-3 leading-7 text-zinc-600 dark:text-zinc-400">{{ __('Product groups will appear here when they are available.') }}</p>
        </section>
    @endif
</div>
