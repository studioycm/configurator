<div class="catalog-images-search">
    <div class="catalog-images-bar">
        <x-filament::dropdown placement="bottom-start">
            <x-slot name="trigger"><x-filament::button color="gray" size="sm" icon="heroicon-o-magnifying-glass">{{ __('catalog-images.search_in') }}</x-filament::button></x-slot>
            <div class="catalog-images-options" role="group" aria-label="{{ __('catalog-images.search_in') }}">
                @foreach ($this->scopedSearchColumns() as $key => $label)
                    <label wire:key="image-target-{{ $imageTarget }}-{{ $key }}"><x-filament::input.checkbox wire:model.live="imageSearch.targets" value="{{ $key }}" :disabled="$exactCodeSet !== null" /> {{ $label }}</label>
                @endforeach
            </div>
        </x-filament::dropdown>
        <div class="catalog-images-search-input">
            <label for="image-record-query" class="sr-only">{{ __('catalog-images.search') }}</label>
            <x-filament::input.wrapper><x-filament::input id="image-record-query" type="search" wire:model.live.debounce.500ms="imageSearch.query" :disabled="$exactCodeSet !== null" :placeholder="$imageTarget === 'product.main_image' ? 'el lp6 4' : __('catalog-images.name')" /></x-filament::input.wrapper>
        </div>
        <x-filament::dropdown placement="bottom-end">
            <x-slot name="trigger"><x-filament::icon-button color="gray" icon="heroicon-o-adjustments-horizontal" :label="__('catalog-images.options')" /></x-slot>
            <div class="catalog-images-options">
                <fieldset @disabled($exactCodeSet !== null)>
                    <legend class="font-semibold">{{ __('catalog-images.options') }}</legend>
                    <label><span>{{ __('catalog-images.options') }}</span><x-filament::input.wrapper><x-filament::input.select wire:model.live="imageSearch.match">
                        @foreach (['contains','starts','ends','whole'] as $match)<option value="{{ $match }}">{{ __('catalog-images.'.$match) }}</option>@endforeach
                    </x-filament::input.select></x-filament::input.wrapper></label>
                    <label><x-filament::input.wrapper><x-filament::input.select aria-label="{{ __('catalog-images.fragments') }}" wire:model.live="imageSearch.combine" :disabled="$imageSearch['match'] === 'whole'">
                        <option value="all">{{ __('catalog-images.all') }}</option><option value="any">{{ __('catalog-images.any_fragments') }}</option>
                    </x-filament::input.select></x-filament::input.wrapper></label>
                    <label><x-filament::input.checkbox wire:model.live="imageSearch.case_sensitive" />{{ __('catalog-images.case_sensitive') }}</label>
                    <label><x-filament::input.checkbox wire:model.live="imageSearch.literal_separators" />{{ __('catalog-images.literal') }}</label>
                    <label>{{ __('catalog-images.exclude') }}<x-filament::input.wrapper><x-filament::input wire:model.live.debounce.500ms="imageSearch.exclude" maxlength="500" /></x-filament::input.wrapper></label>
                    @if ($imageTarget === 'product.main_image')
                        <fieldset><legend>{{ __('catalog-images.positions') }}</legend><p>{{ __('catalog-images.position_help') }}</p><div class="catalog-images-segments">
                            @foreach (range(1,16) as $position)<label><x-filament::input.checkbox wire:model.live="imageSearch.positions" value="{{ $position }}" />{{ $position }}</label>@endforeach
                        </div></fieldset>
                    @endif
                </fieldset>
                @if ($imageTarget === 'product.main_image')
                    <details><summary>{{ __('catalog-images.exact_codes') }}</summary>
                        <p>{{ __('catalog-images.exact_help') }}</p>
                        <label for="image-exact-codes" class="sr-only">{{ __('catalog-images.exact_codes') }}</label>
                        <textarea id="image-exact-codes" x-model="pasteText" rows="5" class="w-full rounded border border-gray-300 p-2 dark:border-gray-600 dark:bg-gray-900"></textarea>
                        <x-filament::button size="sm" x-on:click="transferCodes()" x-bind:disabled="transferring">{{ __('catalog-images.use_codes') }}</x-filament::button>
                        @if ($exactCodeSet !== null)<x-filament::button size="sm" color="gray" x-on:click="command('clearExactCodePaste')">{{ __('catalog-images.clear_codes') }}</x-filament::button>@endif
                    </details>
                @endif
            </div>
        </x-filament::dropdown>
        <x-filament::dropdown placement="bottom-end">
            <x-slot name="trigger"><x-filament::button color="gray" size="sm" icon="heroicon-o-funnel">{{ __('catalog-images.quick_filters') }}</x-filament::button></x-slot>
            <div class="catalog-images-options">{{ $this->imageFilters }}<label><x-filament::input.checkbox wire:model.live="imageSearch.checked_only" />{{ __('catalog-images.checked_only') }}</label></div>
        </x-filament::dropdown>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">
        @if ($exactCodeSet !== null){{ __('catalog-images.exact_codes') }}
        @else
            {{ __('catalog-images.'.$imageSearch['match']) }} · {{ __('catalog-images.'.($imageSearch['combine'] === 'any' ? 'any_fragments' : 'all')) }} · {{ __('catalog-images.'.($imageSearch['case_sensitive'] ? 'case_sensitive' : 'ignore_case')) }} · {{ __('catalog-images.'.($imageSearch['literal_separators'] ? 'literal' : 'ignore_separators')) }}
        @endif
    </p>
    <div class="catalog-images-bar">
        <x-filament::button size="sm" color="gray" x-on:click="command('checkImagePage')">{{ __('catalog-images.check_page') }}</x-filament::button>
        <x-filament::button size="sm" color="gray" x-on:click="command('checkImageMatches')">{{ __('catalog-images.check_matches') }}</x-filament::button>
        @php($counts = $this->imageCheckedCounts())
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('catalog-images.counts', $counts) }}</span>
        @if (count($selectedTableRecords))<x-filament::button color="gray" size="sm" x-on:click="command('clearImageChecks')">{{ __('catalog-images.clear_checks') }}</x-filament::button>@endif
    </div>
</div>
