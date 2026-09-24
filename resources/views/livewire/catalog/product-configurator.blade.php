<section class="min-w-0" aria-label="{{ __('Product configuration') }}" wire:loading.attr="aria-busy" x-data="{ tab: 'configurator' }">
    @teleport('#catalog-product-context')
        <div>
            @if ($result->definition)
                <div class="my-4 space-y-3 border-t border-slate-700 pt-3" role="group" aria-label="{{ __('Product context') }}">
                    @foreach (['territory', 'application'] as $dimension)
                        <label class="block" wire:key="context-{{ $dimension }}">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __(ucfirst($dimension)) }}</span>
                            <select wire:change="changeContext('{{ $dimension }}', $event.target.value)" class="w-full rounded-lg border border-slate-600 bg-[#203448] px-2 py-1.5 text-sm text-slate-100 [color-scheme:dark] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-400">
                                <option value="All" @selected($result->context[$dimension] === 'All')>{{ __('All') }}</option>
                                @foreach ($result->definition->contextSchema[$dimension] as $choice)
                                    <option value="{{ $choice['value'] }}" @selected($result->context[$dimension] === $choice['value'])>{{ $choice['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    @endteleport
    <div class="min-w-0 flex-1 space-y-3">
        <div class="space-y-2 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-blue-950 dark:text-blue-100">
                <span class="text-sm font-medium">{{ __('Product Code') }}</span>
                <span class="break-all font-mono text-xl">{{ $product->product_code }}</span>
            </div>
            <x-catalog.configuration-result :result="$result" inline />
        </div>
        <div class="flex flex-wrap gap-1 border-b border-slate-300 dark:border-zinc-700" role="tablist" aria-label="{{ __('Product sections') }}"
            x-on:keydown.arrow-right.prevent="$focus.wrap().next()" x-on:keydown.arrow-left.prevent="$focus.wrap().previous()" x-on:keydown.home.prevent="$focus.first()" x-on:keydown.end.prevent="$focus.last()">
            @foreach (['configurator' => 'Configurator', 'details' => 'Product details', 'information' => 'Information'] as $key => $label)
                <button type="button" id="tab-{{ $key }}-{{ $this->getId() }}" role="tab" aria-controls="panel-{{ $key }}-{{ $this->getId() }}"
                    x-bind:aria-selected="tab === '{{ $key }}'" x-bind:tabindex="tab === '{{ $key }}' ? 0 : -1" x-on:focus="tab = '{{ $key }}'" x-on:click="tab = '{{ $key }}'"
                    class="border-b-2 border-transparent px-3 py-2 text-sm font-medium aria-selected:border-blue-600 aria-selected:text-blue-600 focus-visible:outline-2 focus-visible:outline-blue-600">{{ __($label) }}</button>
            @endforeach
        </div>
        <div id="panel-configurator-{{ $this->getId() }}" role="tabpanel" aria-labelledby="tab-configurator-{{ $this->getId() }}" x-show="tab === 'configurator'">
            <div class="min-w-0 rounded-xl border border-slate-200 bg-white px-4 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ __('Configuration') }}</h2>
                    <button type="button" wire:click="refreshDefinition" class="rounded-full border border-slate-300 px-2 py-0.5 text-xs focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('Refresh choices') }}</button>
                </div>
                @if ($result->definition)
                    <x-catalog.configuration-fields :result="$result" />
                @else
                    <p class="text-sm">{{ __('Configuration is not available for this product.') }}</p>
                @endif
                <p wire:loading.delay class="text-sm text-slate-500" role="status">{{ __('Updating choices…') }}</p>
            </div>
        </div>
        <div id="panel-details-{{ $this->getId() }}" role="tabpanel" aria-labelledby="tab-details-{{ $this->getId() }}" x-cloak x-show="tab === 'details'" class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            @php($factPropertyKeys = $product->group->filters->isNotEmpty() ? $product->group->filters->pluck('property_key')->all() : array_slice(array_keys($product->properties ?? []), 0, 7))
            <div class="grid items-start gap-4 md:grid-cols-2">
                <div class="space-y-4">
                    <x-catalog.product-facts :product="$product" :buckets="['properties' => 'Product facts']" :property-keys="$factPropertyKeys" />
                    <x-catalog.product-facts :product="$product" :buckets="['properties' => 'Specifications and dimensions']" :property-keys="array_diff(array_keys($product->properties ?? []), $factPropertyKeys)" />
                </div>
                <x-catalog.product-facts :product="$product" :buckets="['parts' => 'Parts']" />
            </div>
        </div>
        <div id="panel-information-{{ $this->getId() }}" role="tabpanel" aria-labelledby="tab-information-{{ $this->getId() }}" x-cloak x-show="tab === 'information'" class="space-y-3 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            @if ($product->description)<p class="whitespace-pre-line text-sm leading-6">{{ $product->description }}</p>@endif
            <x-catalog.product-facts :product="$product" :buckets="['extra_data' => 'Preserved source data']" />
        </div>
    </div>
</section>
