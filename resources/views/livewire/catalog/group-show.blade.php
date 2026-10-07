<x-slot:breadcrumbs>
    <x-catalog.breadcrumbs :group="$group" :ancestors="$ancestors" compact />
</x-slot:breadcrumbs>

<div wire:key="catalog-group-{{ $group->id }}">
    @if ($children->isNotEmpty())
        <x-catalog.group-tree :groups="$children" />
    @else
        <div wire:ignore x-data="catalogFilters" data-catalog-filters data-group-id="{{ $group->id }}" data-dataset-url="{{ route('catalog.groups.dataset', $group) }}">
            <script type="application/json" data-catalog-snapshot>{!! \Illuminate\Support\Js::encode($snapshot?->data) !!}</script>
            <noscript><p>{{ __('Enable JavaScript to use the product filters.') }}</p></noscript>
            <div x-cloak x-show="!available" role="status" class="mt-4 space-y-2">
                <p x-text="unavailable"></p>
                <button type="button" x-show="!reloadRequired" @click="refreshSnapshot()" :disabled="refreshing" class="rounded-lg border px-3 py-2">{{ __('Retry filters') }}</button>
                <a x-show="reloadRequired" href="{{ route('catalog.groups.show', $group) }}" class="underline">{{ __('Reload page') }}</a>
                <a href="{{ route('catalog.index') }}" class="ml-3 underline">{{ __('Return to catalog') }}</a>
            </div>
            <section wire:ignore x-cloak x-show="available" aria-label="Product filters" class="mt-3">
                <div class="mb-3 flex flex-wrap items-center gap-4">
                    <div role="status" aria-live="polite" aria-atomic="true">
                        <p class="font-medium" data-catalog-total x-text="total === 1 ? '1 product' : `${total} products`"></p>
                        <template x-for="notice in notices" :key="notice"><p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400" x-text="notice"></p></template>
                    </div>
                    <button x-ref="reset" type="button" @click="choose('reset')" class="min-h-8 rounded-full border border-slate-300 bg-white px-3 text-sm hover:bg-slate-100 dark:bg-zinc-900">{{ __('Reset all') }}</button>
                </div>
                <fieldset x-show="presets.length > 0" class="mb-3 min-w-0">
                    <legend class="mb-2 text-base font-semibold">
                        <span class="inline-flex items-center gap-2">
                            <span>{{ __('Presets') }}</span>
                            <button type="button" @click="choose('preset', null)" aria-label="{{ __('Clear subgroup') }}" :disabled="state.subGroupId === null" class="min-h-6 shrink-0 rounded border border-zinc-300 px-1.5 py-0.5 text-xs font-normal leading-4 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 disabled:cursor-default disabled:opacity-40 dark:border-zinc-600 dark:hover:bg-zinc-800">{{ __('Clear') }}</button>
                        </span>
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="preset in presets" :key="preset.id">
                            <button type="button" @click="choose('preset', preset.id)" :aria-pressed="state.subGroupId === preset.id"
                                class="inline-flex min-h-8 max-w-full items-center gap-2 rounded-full border px-3 py-1 text-left text-base leading-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
                                :class="state.subGroupId === preset.id ? 'border-blue-600 bg-blue-600 text-white' : 'border-zinc-300 bg-white text-zinc-800 hover:border-teal-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200'">
                                <span class="min-w-0 wrap-break-word" x-text="preset.label"></span>
                            </button>
                        </template>
                    </div>
                </fieldset>
                <div class="mb-2 flex items-center gap-2">
                    <h2 class="text-lg font-semibold">{{ __('Filters') }}</h2>
                    <button type="button" @click="choose('clear')" aria-label="{{ __('Clear filters') }}" :disabled="Object.keys(state.filters).length === 0" class="min-h-6 shrink-0 rounded border border-zinc-300 px-1.5 py-0.5 text-xs font-normal leading-4 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 disabled:cursor-default disabled:opacity-40 dark:border-zinc-600 dark:hover:bg-zinc-800">{{ __('Clear') }}</button>
                </div>
                <div class="catalog-filter-grid grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-7">
                    <template x-for="field in fields" :key="field.key">
                        <fieldset x-show="!field.hidden" :data-filter-key="field.key" class="min-w-0 rounded-xl border border-slate-200 bg-white px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900">
                            <legend class="catalog-filter-heading px-2 text-sm font-semibold leading-5" x-text="field.label"></legend>
                            <div class="grid grid-cols-1 gap-1">
                                <template x-for="option in field.options" :key="option.identity">
                                    <button type="button" @click="choose('filter', field.key, option.value)" :aria-pressed="option.selected" :data-availability="option.compatible ? 'available' : 'empty'"
                                        :aria-describedby="option.compatible ? null : 'catalog-choice-help'" :title="option.compatible ? null : 'No match with the other choices. Selecting this may clear an earlier choice.'"
                                        class="min-h-8 w-full min-w-0 rounded-lg border px-2 py-1 text-center text-base leading-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                                        :class="option.selected ? 'border-blue-600 bg-blue-600 text-white' : option.compatible ? 'border-slate-300 bg-white text-slate-800 hover:border-blue-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200' : 'border-dashed border-slate-300 bg-slate-100 text-slate-600 hover:border-slate-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400'">
                                        <span class="wrap-break-word" x-text="option.label"></span>
                                    </button>
                                </template>
                            </div>
                        </fieldset>
                    </template>
                </div>
                <p id="catalog-choice-help" class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">{{ __('Muted choices have no matches with your other choices. A newer choice may clear incompatible earlier choices. Clear filters keeps your subgroup; Reset all removes it too.') }}</p>
            </section>
            <div x-cloak x-show="freshnessError" role="status" class="mt-3 text-sm">
                <span x-text="freshnessError"></span>
                <button x-show="!reloadRequired" type="button" @click="refreshSnapshot()" :disabled="refreshing" class="ml-2 underline">{{ __('Retry update') }}</button>
                <a x-show="reloadRequired" href="{{ route('catalog.groups.show', $group) }}" class="ml-2 underline">{{ __('Reload page') }}</a>
            </div>
            <section wire:ignore x-cloak x-show="available" class="mt-4" aria-label="Products" :aria-busy="cardStatus === 'loading'">
                <p x-show="cardStatus === 'loading'" role="status" class="mb-2 text-sm">{{ __('Updating products…') }}</p>
                <p x-show="cardStatus === 'empty'">{{ __('There are no products matching these choices.') }}</p>
                <p x-show="cardStatus === 'above_threshold'" class="text-sm text-zinc-600 dark:text-zinc-400" x-text="maximum === 1 ? 'Use the filters to narrow your results to 1 product.' : `Use the filters to narrow your results to ${maximum} products or fewer.`"></p>
                <div x-show="cardStatus === 'error'" role="status">
                    <span x-text="cardError"></span>
                    <button type="button" @click="retryCards()" class="ml-2 underline">{{ __('Retry products') }}</button>
                </div>
                <p x-show="cardsVisible && cardPropertiesNotice" role="status" aria-live="polite" class="mb-2 text-sm text-zinc-600 dark:text-zinc-400" x-text="cardPropertiesNotice"></p>
                <div data-cards-visible="false" :data-cards-visible="cardsVisible ? 'true' : 'false'" :aria-hidden="cardsVisible ? null : 'true'" class="catalog-card-grid" :style="{'--catalog-cards-max-columns': columns}">
                    <template x-for="chunk in cardChunks" :key="chunk.key"><div class="contents" :data-card-chunk="chunk.key" x-html="chunk.html"></div></template>
                </div>
            </section>
        </div>
    @endif
</div>
