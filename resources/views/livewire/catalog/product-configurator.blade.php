<section class="min-w-0" aria-label="{{ __('admin.navigation.product_configuration') }}" wire:loading.attr="aria-busy" x-data="{ tab: 'configurator' }">
    <div class="min-w-0 flex-1 space-y-3">
        <div class="space-y-1 rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-blue-950 dark:text-blue-100">
                <span class="text-sm font-medium">{{ __('configurator.labels.product_code') }}</span>
                <span class="break-all font-mono text-xl">{{ $product->product_code }}</span>
            </div>
            <x-catalog.configuration-result :result="$result" inline :show-diagnostics="false" />
        </div>
        <div class="flex flex-wrap gap-1 border-b border-slate-300 dark:border-zinc-700" role="tablist" aria-label="{{ __('configurator.copy.product_sections') }}"
            x-on:keydown.arrow-right.prevent="$focus.wrap().next()" x-on:keydown.arrow-left.prevent="$focus.wrap().previous()" x-on:keydown.home.prevent="$focus.first()" x-on:keydown.end.prevent="$focus.last()">
            @foreach (['configurator' => __('configurator.resource.singular'), 'details' => __('configurator.copy.product_details'), 'information' => __('configurator.copy.information')] as $key => $label)
                <button type="button" id="tab-{{ $key }}-{{ $this->getId() }}" role="tab" aria-controls="panel-{{ $key }}-{{ $this->getId() }}"
                    x-bind:aria-selected="tab === '{{ $key }}'" x-bind:tabindex="tab === '{{ $key }}' ? 0 : -1" x-on:focus="tab = '{{ $key }}'" x-on:click="tab = '{{ $key }}'"
                    class="border-b-2 border-transparent px-3 py-2 text-sm font-medium aria-selected:border-blue-600 aria-selected:text-blue-600 focus-visible:outline-2 focus-visible:outline-blue-600">{{ $label }}</button>
            @endforeach
        </div>
        <div id="panel-configurator-{{ $this->getId() }}" role="tabpanel" aria-labelledby="tab-configurator-{{ $this->getId() }}" x-show="tab === 'configurator'">
            <div class="min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-1.5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-3">
                        <h2 class="text-lg font-semibold">{{ __('configurator.copy.configuration') }}</h2>
                        @if ($showLoadingIndicator)
                            <span class="inline-flex w-32 shrink-0 items-center text-xs text-slate-500" aria-live="polite">
                                <span wire:loading.delay.longer wire:target="selectOption,refreshDefinition" role="status">{{ __('configurator.copy.updating_choices') }}</span>
                            </span>
                        @endif
                    </div>
                    <button type="button" wire:click="refreshDefinition" class="rounded-full border border-slate-300 px-2 py-0.5 text-xs focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('configurator.copy.refresh_choices') }}</button>
                </div>
                @if ($result->definition)
                    <x-catalog.configuration-fields :result="$result" />
                @else
                    <p class="text-sm">{{ __('configurator.copy.configuration_is_not_available_for_this_product') }}</p>
                @endif
            </div>
        </div>
        <div id="panel-details-{{ $this->getId() }}" role="tabpanel" aria-labelledby="tab-details-{{ $this->getId() }}" x-cloak x-show="tab === 'details'" class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid items-start gap-y-0 md:grid-cols-2 md:gap-x-10 xl:gap-x-[60px]">
                <div class="space-y-4">
                    <x-catalog.product-facts :product="$product" :buckets="['properties' => __('catalog.products.buckets.properties'), 'others' => __('catalog.products.buckets.others')]" :property-fields="$propertyFields" />
                </div>
                <x-catalog.product-facts :product="$product" :buckets="['parts' => __('catalog.products.buckets.parts')]" />
            </div>
        </div>
        <div id="panel-information-{{ $this->getId() }}" role="tabpanel" aria-labelledby="tab-information-{{ $this->getId() }}" x-cloak x-show="tab === 'information'" class="space-y-3 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            @if ($product->description)<p class="whitespace-pre-line text-sm leading-6">{{ $product->description }}</p>@endif
        </div>
    </div>
</section>
