@php($searchId = 'preview-product-'.$this->getId())
<div class="catalog-configurator-preview-product min-w-0" x-data="configuratorProductSearch({{ \Illuminate\Support\Js::from($selectedProductCode) }})" x-on:click.outside="close()" x-on:focusout="if (!$el.contains($event.relatedTarget)) close()">
    <label for="{{ $searchId }}" class="mb-1 block text-xs font-medium">{{ __('configurator.labels.product_code') }}</label>
    <div class="relative">
        <x-filament::input.wrapper>
            <x-filament::input id="{{ $searchId }}" type="text" role="combobox" autocomplete="off" aria-autocomplete="list" aria-haspopup="listbox"
                aria-controls="{{ $searchId }}-results" x-bind:aria-expanded="open"
                x-bind:aria-activedescendant="open && activeIndex >= 0 ? '{{ $searchId }}-option-' + options[activeIndex].id : null"
                x-model="query" x-bind:readonly="choosing" x-bind:aria-busy="choosing" placeholder="{{ __('configurator.preview.search_products') }}"
                x-on:focus="$event.target.select(); search()"
                x-on:input="options = []; activeIndex = -1; request++; loading = true; open = true"
                x-on:input.debounce.200ms="if (open && !choosing) search()"
                x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent.stop="chooseActive()" x-on:keydown.escape.prevent.stop="close()" />
            <x-slot name="suffix">
                <x-filament::icon-button type="button" color="gray" size="sm" icon="heroicon-o-x-mark" :label="__('configurator.preview.clear_product')"
                    x-on:click="choose()" x-bind:disabled="choosing || !selectedCode" />
            </x-slot>
        </x-filament::input.wrapper>
        <div x-cloak x-show="open" class="catalog-configurator-preview-product-results absolute inset-x-0 top-full z-30 mt-1 rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-900">
            <p x-show="loading" role="status" class="p-2 text-xs text-gray-500">{{ __('configurator.preview.searching') }}</p>
            <p x-show="!loading && failed" role="status" class="p-2 text-xs text-danger-600">{{ __('configurator.preview.search_failed') }}</p>
            <p x-show="!loading && !failed && !options.length" role="status" class="p-2 text-xs text-gray-500">{{ __('configurator.preview.no_products') }}</p>
            <ul id="{{ $searchId }}-results" role="listbox" aria-label="{{ __('configurator.preview.product_results') }}" x-ref="results" class="max-h-64 overflow-y-auto">
                <template x-for="(option, index) in options" :key="option.id">
                    <li role="option" x-bind:id="'{{ $searchId }}-option-' + option.id" x-bind:aria-selected="index === activeIndex"
                        x-on:mouseenter="activeIndex = index" x-on:mousedown.prevent x-on:click="choose(option)" x-text="option.label"
                        x-bind:class="index === activeIndex ? 'bg-gray-100 dark:bg-gray-800' : ''" class="cursor-pointer rounded-md px-2 py-1.5 text-sm"></li>
                </template>
            </ul>
        </div>
    </div>
    <p x-cloak x-show="failed && !open" role="status" class="mt-1 text-xs text-danger-600">{{ __('configurator.preview.choose_failed') }}</p>
    @error('product')<p role="alert" class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
</div>
