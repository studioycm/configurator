<div data-catalog-state="{{ json_encode($result?->state->toArray()) }}" data-catalog-repair="{{ $result?->repaired ? '1' : '0' }}" data-catalog-url="{{ route('catalog.groups.show', ['group' => $group, 'd' => $result?->state->toArray()]) }}">
    <x-catalog.breadcrumbs :group="$group" :ancestors="$ancestors" />
    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $group->name }}</h1>
    @if ($group->description)<p class="mt-4 max-w-3xl leading-7 text-zinc-600 dark:text-zinc-400">{{ $group->description }}</p>@endif
    @if ($children->isNotEmpty())
        <x-catalog.group-tree :groups="$children" />
    @else
        <section aria-label="Product filters" class="mt-6 rounded-xl border border-zinc-300 p-4 dark:border-zinc-700">
            @if ($result->subGroups !== [])
                <fieldset class="mb-6 min-w-0" aria-labelledby="catalog-subgroup-title-{{ $group->id }}">
                    <legend class="mb-2 text-xs font-semibold">
                        <span class="inline-flex items-center gap-2">
                            <span id="catalog-subgroup-title-{{ $group->id }}">{{ __(':property sub-group', ['property' => implode(', ', array_unique(array_column($result->subGroups, 'property_label')))]) }}</span>
                            <button type="button" wire:click="selectSubGroup(null)" aria-label="{{ __('Clear subgroup') }}" @disabled($result->state->subGroupId === null)
                                class="min-h-6 shrink-0 rounded border border-zinc-300 px-1.5 py-0.5 text-xs font-normal leading-4 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 disabled:cursor-default disabled:opacity-40 dark:border-zinc-600 dark:hover:bg-zinc-800">{{ __('Clear') }}</button>
                        </span>
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($result->subGroups as $preset)
                            @php($selected = $result->state->subGroupId === $preset['id'])
                            <button type="button" wire:key="preset-{{ $group->id }}-{{ $preset['id'] }}"
                                wire:click="selectSubGroup({{ $selected ? 'null' : $preset['id'] }})"
                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                @class(['inline-flex min-h-10 max-w-full items-center gap-2 rounded-full border px-4 py-2 text-left text-sm leading-5 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600',
                                    'border-teal-700 bg-teal-700 text-white dark:border-teal-300 dark:bg-teal-300 dark:text-zinc-950' => $selected,
                                    'border-zinc-300 bg-white text-zinc-800 hover:border-teal-600 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200' => ! $selected])>
                                <span class="min-w-0 wrap-break-word">{{ $preset['label'] }}</span>
                                @if ($selected)<span class="shrink-0" aria-hidden="true">✓</span>@endif
                            </button>
                        @endforeach
                    </div>
                </fieldset>
            @endif
            <div class="mb-4">
                <h2 class="text-sm font-semibold">{{ __('Filters') }}</h2>
            </div>
            <div class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8">
                @foreach ($result->fields as $field)<x-catalog.filter-field :field="$field" :group-id="$group->id" />@endforeach
            </div>
            @if ($result->fields !== [])<p id="catalog-choice-help" class="mt-4 text-xs leading-5 text-zinc-600 dark:text-zinc-400">{{ __('Counts show compatibility with your other choices. Choosing a zero-count value may clear earlier choices. Clear filters keeps your preset; Reset all removes it too.') }}</p>@endif
            <footer class="mt-4 flex flex-wrap justify-start gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <button type="button" wire:click="clearFilters" class="min-h-9 rounded-full border border-zinc-300 px-4 text-xs hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-zinc-600 dark:hover:bg-zinc-800">{{ __('Clear filters') }}</button>
                <button type="button" wire:click="resetAll" class="min-h-9 rounded-full border border-zinc-300 px-4 text-xs hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 dark:border-zinc-600 dark:hover:bg-zinc-800">{{ __('Reset all') }}</button>
            </footer>
        </section>
        <section class="mt-8" aria-label="Products" wire:loading.attr="aria-busy">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <div role="status" aria-live="polite" aria-atomic="true">
                    <p class="font-medium">{{ trans_choice(':count product|:count products', $result->products->total(), ['count' => $result->products->total()]) }}</p>
                    @foreach ($result->notices as $notice)<p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $notice }}</p>@endforeach
                </div>
                @if ($result->showProducts && $result->settings['allow_page_size_change'])
                    <label class="flex items-center gap-2 text-sm">{{ __('Products per page') }}
                        <select wire:change="changePageSize(+$event.target.value)" class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 dark:border-zinc-600 dark:bg-zinc-900">
                            @foreach ($result->settings['page_size_options'] as $size)<option value="{{ $size }}" @selected($result->state->perPage === $size)>{{ $size }}</option>@endforeach
                        </select>
                    </label>
                @endif
            </div>
            <span wire:loading.delay class="mb-4 text-sm">{{ __('Updating products…') }}</span>
            @if (! $result->showProducts)
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ trans_choice('Use the filters to narrow your results to :count product.|Use the filters to narrow your results to :count products or fewer.', $result->settings['max_results'], ['count' => $result->settings['max_results']]) }}</p>
            @elseif ($result->products->isEmpty())
                <p>{{ $result->state->filters !== [] || $result->state->subGroupId !== null ? __('No products match these choices. Clear filters or Reset all to try again.') : __('There are no products in this group yet.') }}</p>
            @else
                <div @class(['grid grid-cols-1 gap-4',
                    'sm:grid-cols-2' => $result->settings['cards_per_row'] >= 2,
                    'lg:grid-cols-3' => $result->settings['cards_per_row'] >= 3,
                    'xl:grid-cols-4' => $result->settings['cards_per_row'] === 4,
                    'xl:grid-cols-5' => $result->settings['cards_per_row'] === 5,
                    'xl:grid-cols-6' => $result->settings['cards_per_row'] === 6])>
                    @foreach ($result->products as $product)<div wire:key="product-{{ $product->id }}"><x-catalog.product-card :product="$product" :group="$group" :main-group="$ancestors->first()" :property-keys="$result->settings['card_properties']" /></div>@endforeach
                </div>
                <x-catalog.pagination :products="$result->products" />
            @endif
        </section>
    @endif
</div>
