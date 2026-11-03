<div class="catalog-images-search">
    <h2 class="font-semibold">{{ __('catalog-images.find_records') }}</h2>
    <div class="catalog-images-filters">{{ $this->imageFilters }}</div>
    <div class="catalog-images-bar">
        <x-filament::dropdown placement="bottom-start" width="sm" :shift="true">
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
        <x-filament::dropdown placement="bottom-end" width="sm" :shift="true">
            <x-slot name="trigger"><x-filament::icon-button color="gray" icon="heroicon-o-adjustments-horizontal" :label="__('catalog-images.options')" /></x-slot>
            <div class="catalog-images-options catalog-images-search-options">
                <fieldset @disabled($exactCodeSet !== null)>
                    <legend class="font-semibold">{{ __('catalog-images.options') }}</legend>
                    <label class="catalog-images-option-field"><span>{{ __('catalog-images.match_method') }}</span><x-filament::input.wrapper><x-filament::input.select wire:model.live="imageSearch.match">
                        @foreach (['contains','starts','ends','whole'] as $match)<option value="{{ $match }}">{{ __('catalog-images.'.$match) }}</option>@endforeach
                    </x-filament::input.select></x-filament::input.wrapper></label>
                    <label class="catalog-images-option-field"><span>{{ __('catalog-images.fragments') }}</span><x-filament::input.wrapper><x-filament::input.select wire:model.live="imageSearch.combine" :disabled="$imageSearch['match'] === 'whole'">
                        <option value="all">{{ __('catalog-images.all') }}</option><option value="any">{{ __('catalog-images.any_fragments') }}</option>
                    </x-filament::input.select></x-filament::input.wrapper></label>
                    <label><x-filament::input.checkbox wire:model.live="imageSearch.case_sensitive" />{{ __('catalog-images.case_sensitive') }}</label>
                    <label><x-filament::input.checkbox wire:model.live="imageSearch.literal_separators" />{{ __('catalog-images.literal') }}</label>
                    <label class="catalog-images-option-field"><span>{{ __('catalog-images.exclude') }}</span><x-filament::input.wrapper><x-filament::input wire:model.live.debounce.500ms="imageSearch.exclude" maxlength="500" /></x-filament::input.wrapper></label>
                    @if ($imageTarget === 'product.main_image')
                        <details><summary>{{ __('catalog-images.positions') }}</summary><fieldset><legend class="sr-only">{{ __('catalog-images.positions') }}</legend><p class="text-xs">{{ __('catalog-images.position_help') }}</p><div class="catalog-images-segments">
                            @foreach (range(1,16) as $position)<label><x-filament::input.checkbox wire:model.live="imageSearch.positions" value="{{ $position }}" />{{ $position }}</label>@endforeach
                        </div></fieldset></details>
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
    </div>
    <details class="catalog-images-search-summary text-xs text-gray-500 dark:text-gray-400"><summary>{{ __('catalog-images.current_search') }}</summary>
        @if ($exactCodeSet !== null){{ __('catalog-images.exact_codes') }}
        @else
            {{ __('catalog-images.'.$imageSearch['match']) }} · {{ __('catalog-images.'.($imageSearch['combine'] === 'any' ? 'any_fragments' : 'all')) }} · {{ __('catalog-images.'.($imageSearch['case_sensitive'] ? 'case_sensitive' : 'ignore_case')) }} · {{ __('catalog-images.'.($imageSearch['literal_separators'] ? 'literal' : 'ignore_separators')) }}
        @endif
    </details>
    <div class="catalog-images-bar">
        <x-filament::button size="sm" color="gray" x-on:click="command('checkImagePage')">{{ __('catalog-images.check_page') }}</x-filament::button>
        <x-filament::button size="sm" color="gray" x-on:click="command('checkImageMatches')">{{ __('catalog-images.check_matches') }}</x-filament::button>
        <span class="text-xs text-gray-500 dark:text-gray-400"><strong>{{ __('catalog-images.selection') }}:</strong> <span x-text="$wire.selectedTableRecords.length">{{ count($selectedTableRecords) }}</span></span>
        <label class="catalog-images-bar text-xs"><x-filament::input.checkbox wire:model.live="imageSearch.checked_only" />{{ __('catalog-images.checked_only') }}</label>
        <x-filament::button color="gray" size="sm" x-cloak x-show="$wire.selectedTableRecords.length" x-on:click="command('clearImageChecks')">{{ __('catalog-images.clear_checks') }}</x-filament::button>
    </div>
    <div class="catalog-images-selection-actions" role="group" aria-label="{{ __('catalog-images.selection_actions') }}">
        <x-filament::button size="sm" x-bind:disabled="!$wire.selectedTableRecords.length" x-bind:aria-disabled="String(!$wire.selectedTableRecords.length)" x-bind:class="{ 'fi-disabled': !$wire.selectedTableRecords.length }" x-on:click="command('openImageReview', 'one_image')">{{ __('catalog-images.assign_one') }}</x-filament::button>
        @if ($imageTarget === 'product.main_image')
            <x-filament::button size="sm" color="gray" x-bind:disabled="!$wire.selectedTableRecords.length" x-bind:aria-disabled="String(!$wire.selectedTableRecords.length)" x-bind:class="{ 'fi-disabled': !$wire.selectedTableRecords.length }" x-on:click="command('openImageReview', 'folder_matches')">{{ __('catalog-images.match_folder') }}</x-filament::button>
        @endif
    </div>
</div>
