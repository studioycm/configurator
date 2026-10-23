<div class="catalog-preview-legend" aria-label="{{ __('configurator.preview.value_states') }}">
    @foreach (['selected' => 'heroicon-o-check-circle', 'available' => 'heroicon-o-circle-stack', 'disabled' => 'heroicon-o-no-symbol', 'hidden' => 'heroicon-o-eye-slash'] as $state => $icon)
        <span data-state="{{ $state }}">@if ($state === 'available')<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /></svg>@else<x-filament::icon :icon="$icon" />@endif{{ __('configurator.preview.states.'.$state) }}</span>
    @endforeach
</div>
@forelse ($previewView['inspection']['attributes'] ?? [] as $attribute)
    <section class="catalog-preview-attribute" data-state="{{ $attribute['state'] }}" wire:key="preview-all-attribute-{{ $attribute['id'] }}" aria-labelledby="preview-all-label-{{ $this->getId() }}-{{ $attribute['id'] }}">
        <div class="catalog-preview-attribute-label">
            <div class="flex min-w-0 items-start justify-between gap-1">
                <h3 id="preview-all-label-{{ $this->getId() }}-{{ $attribute['id'] }}">{{ $attribute['label'] }}</h3>
                <x-filament::icon-button type="button" size="sm" color="gray" icon="heroicon-o-viewfinder-circle" :label="__('configurator.preview.explain_named', ['name' => $attribute['label']])" :tooltip="__('configurator.preview.explain_named', ['name' => $attribute['label']])" x-on:click="rememberInspectionTrigger($el); inspect({{ \Illuminate\Support\Js::from($attribute['id']) }})" x-bind:disabled="busy" />
            </div>
            @if ($attribute['hint'])<p class="catalog-preview-hint"><x-filament::icon icon="heroicon-o-question-mark-circle" />{{ $attribute['hint'] }}</p>@endif
            @if ($attribute['display_value'] !== null)<p class="text-xs">{{ $attribute['display_value'] }}</p>@endif
            @if (in_array($attribute['state'], ['hidden', 'no_legal_values', 'unavailable'], true))<p class="catalog-preview-attribute-status">{{ __('configurator.preview.attribute_states.'.$attribute['state']) }}</p>@endif
        </div>
        <fieldset class="catalog-preview-value-cards" aria-labelledby="preview-all-label-{{ $this->getId() }}-{{ $attribute['id'] }}">
            @foreach ($attribute['options'] as $option)
                @php($icon = match ($option['state']) { 'selected' => 'heroicon-o-check-circle', 'available' => 'heroicon-o-circle-stack', 'hidden' => 'heroicon-o-eye-slash', default => 'heroicon-o-no-symbol' })
                <div class="catalog-preview-value-card" data-state="{{ $option['state'] }}" wire:key="preview-all-option-{{ $attribute['id'] }}-{{ $option['id'] }}">
                    <label class="catalog-preview-value-choice">
                        <input type="radio" class="sr-only" name="preview-all-{{ $this->getId() }}-{{ $attribute['id'] }}" value="{{ $option['id'] }}" @checked($option['selected']) x-bind:checked="$wire.previewView?.inspection?.attributes?.[{{ \Illuminate\Support\Js::from($attribute['id']) }}]?.options?.[{{ \Illuminate\Support\Js::from($option['id']) }}]?.selected ?? false" @disabled($option['legal'] !== true || $option['hidden']) x-bind:disabled="busy || {{ $option['legal'] !== true || $option['hidden'] ? 'true' : 'false' }}" x-on:change="selectOption({{ \Illuminate\Support\Js::from($attribute['id']) }}, {{ \Illuminate\Support\Js::from($option['id']) }})">
                        @if ($option['state'] === 'available')<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /></svg>@else<x-filament::icon :icon="$icon" />@endif
                        <span class="catalog-preview-value-content"><span class="catalog-preview-value-label">{{ $option['label'] }}<span x-show="showCodes && settings.presentation === 'all'" class="catalog-preview-value-code"> · {{ $option['code'] }}</span></span>
                            @if ($option['semantic_id'] !== null)<span x-cloak x-show="showIds && settings.presentation === 'all'" class="catalog-preview-value-id">{{ $option['semantic_id'] }}</span>@endif
                            @if (trim($option['display_value']) !== '' && $option['display_value'] !== $option['label'])<span class="block text-xs">{{ $option['display_value'] }}</span>@endif
                            <span class="sr-only">{{ __('configurator.preview.states.'.$option['state']) }}</span>
                        </span>
                    </label>
                    <div class="catalog-preview-value-suffix">
                        @if ($option['hint'])<x-filament::icon-button type="button" size="sm" color="gray" icon="heroicon-o-question-mark-circle" :wire:loading.attr="false" :label="$option['hint']" :tooltip="$option['hint']" />@endif
                        <x-filament::icon-button type="button" size="sm" color="gray" icon="heroicon-o-viewfinder-circle" :label="__('configurator.preview.explain_named', ['name' => $option['label']])" :tooltip="__('configurator.preview.explain_named', ['name' => $option['label']])" x-on:click="rememberInspectionTrigger($el); inspect({{ \Illuminate\Support\Js::from($attribute['id']) }}, {{ \Illuminate\Support\Js::from($option['id']) }})" x-bind:disabled="busy" />
                    </div>
                </div>
            @endforeach
        </fieldset>
    </section>
@empty
    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">{{ __($productId === null ? 'configurator.preview.choose_product' : 'configurator.preview.unavailable') }}</p>
@endforelse
