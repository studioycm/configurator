<div>
    @if ($groups->isNotEmpty())
        <p class="mt-2 max-w-2xl text-zinc-600 dark:text-zinc-400">{{ __('catalog.frontend.copy.choose_a_product_group_to_explore_its_products') }}</p>
        <x-catalog.group-tree :groups="$groups" />
    @else
        <section class="mt-4 max-w-2xl border-l-4 border-zinc-300 py-2 pl-4 dark:border-zinc-600">
            <h2 class="text-xl font-medium">{{ __('catalog.frontend.copy.the_catalog_is_being_prepared') }}</h2>
            <p class="mt-1.5 leading-7 text-zinc-600 dark:text-zinc-400">{{ __('catalog.frontend.copy.product_groups_will_appear_here_when_they_are_available') }}</p>
        </section>
    @endif
</div>
