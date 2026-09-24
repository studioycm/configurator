<div data-catalog-state="{{ json_encode($result?->state->toArray()) }}" data-catalog-repair="{{ $result?->repaired ? '1' : '0' }}" data-catalog-url="{{ route('catalog.groups.show', ['group' => $group, 'd' => $result?->state->toArray()]) }}" x-data x-on:catalog-focus-criteria.window="$nextTick(() => $refs.criteria?.focus())">
    <x-catalog.breadcrumbs :group="$group" :ancestors="$ancestors" />
    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $group->name }}</h1>
    @if ($group->description)<p class="mt-4 max-w-3xl leading-7 text-zinc-600 dark:text-zinc-400">{{ $group->description }}</p>@endif
    @if ($children->isNotEmpty())
        <x-catalog.group-tree :groups="$children" />
    @else
        <section aria-label="Product filters" class="mt-6 rounded-xl border border-zinc-300 p-4 dark:border-zinc-700">
            <div x-ref="criteria" tabindex="-1" class="mb-4 flex flex-wrap items-center gap-2">
                <h2 class="mr-auto text-sm font-semibold">{{ __('Find a product') }}</h2>
                <button type="button" wire:click="clearFilters" class="min-h-9 rounded-lg border border-zinc-300 px-3 text-xs dark:border-zinc-600">{{ __('Clear filters') }}</button>
                <button type="button" wire:click="resetAll" class="min-h-9 rounded-lg border border-zinc-300 px-3 text-xs dark:border-zinc-600">{{ __('Reset all') }}</button>
            </div>
            @if ($result->subGroups !== [])
                <div class="mb-6 flex flex-wrap items-center gap-3">
                    <label for="catalog-preset" class="text-sm font-semibold">{{ __('Preset') }}</label>
                    <select id="catalog-preset" wire:change="selectSubGroup($event.target.value ? Number($event.target.value) : null)" class="min-h-11 max-w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                        <option value="" @selected($result->state->subGroupId === null)>{{ __('All products') }}</option>
                        @foreach ($result->subGroups as $preset)<option value="{{ $preset['id'] }}" @selected($result->state->subGroupId === $preset['id'])>{{ $preset['label'] }}</option>@endforeach
                    </select>
                    @if ($result->state->subGroupId !== null)<button type="button" wire:click="selectSubGroup(null)" class="min-h-11 text-sm underline underline-offset-4">{{ __('Remove preset') }}</button>@endif
                </div>
            @endif
            <div class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8">
                @foreach ($result->fields as $field)<x-catalog.filter-field :field="$field" :group-id="$group->id" />@endforeach
            </div>
            @if ($result->fields !== [])<p id="catalog-choice-help" class="mt-4 text-xs leading-5 text-zinc-600 dark:text-zinc-400">{{ __('Counts show compatibility with your other choices. Choosing a zero-count value may clear earlier choices. Clear filters keeps your preset; Reset all removes it too.') }}</p>@endif
        </section>
        <section class="mt-8" aria-label="Products" wire:loading.attr="aria-busy">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <div role="status" aria-live="polite" aria-atomic="true">
                    <p class="font-medium">{{ trans_choice(':count product|:count products', $result->products->total(), ['count' => $result->products->total()]) }}</p>
                    @foreach ($result->notices as $notice)<p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $notice }}</p>@endforeach
                </div>
                @if ($result->settings['allow_page_size_change'])
                    <label class="flex items-center gap-2 text-sm">{{ __('Products per page') }}
                        <select wire:change="changePageSize(Number($event.target.value))" class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 dark:border-zinc-600 dark:bg-zinc-900">
                            @foreach ($result->settings['page_size_options'] as $size)<option value="{{ $size }}" @selected($result->state->perPage === $size)>{{ $size }}</option>@endforeach
                        </select>
                    </label>
                @endif
            </div>
            <span wire:loading.delay class="mb-4 text-sm">{{ __('Updating products…') }}</span>
            @if ($result->products->isEmpty())
                <p>{{ $result->state->filters !== [] || $result->state->subGroupId !== null ? __('No products match these choices. Clear filters or Reset all to try again.') : __('There are no products in this group yet.') }}</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($result->products as $product)<div wire:key="product-{{ $product->id }}"><x-catalog.product-card :product="$product" :group="$group" :main-group="$ancestors->first()" /></div>@endforeach
                </div>
                <x-catalog.pagination :products="$result->products" />
            @endif
        </section>
    @endif
</div>
