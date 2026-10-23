<div class="catalog-preview-action-groups">
    <section class="catalog-preview-action-group" aria-labelledby="preview-product-group-{{ $this->getId() }}">
        <h2 id="preview-product-group-{{ $this->getId() }}" class="catalog-preview-group-heading"><x-filament::icon icon="heroicon-o-cube" />{{ __('configurator.preview.product_group') }}</h2>
        <div class="catalog-preview-product-row">
            @include('livewire.catalog.configurator-preview-product-search')
            @php($catalogHint = $dashboardProduct ? __($productId === null ? 'configurator.preview.opens_first_product' : 'configurator.preview.opens_product', ['code' => $dashboardProduct->product_code]) : __($productId === null ? 'configurator.preview.no_dashboard_product' : 'configurator.preview.selected_product_unavailable'))
            @if ($dashboardProduct)
                <x-filament::icon-button type="button" tag="a" :href="route('catalog.products.show', $dashboardProduct)" target="_blank" rel="noopener noreferrer" color="gray" icon="heroicon-o-arrow-top-right-on-square" :label="__('configurator.copy.view_in_dashboard_catalog')" :tooltip="$catalogHint" />
            @else
                <x-filament::icon-button type="button" color="gray" icon="heroicon-o-information-circle" :wire:loading.attr="false" :label="$catalogHint" :tooltip="$catalogHint" />
            @endif
        </div>
    </section>
    <section class="catalog-preview-action-group" aria-labelledby="preview-testing-group-{{ $this->getId() }}">
        <h2 id="preview-testing-group-{{ $this->getId() }}" class="catalog-preview-group-heading"><x-filament::icon icon="heroicon-o-beaker" />{{ __('configurator.preview.testing_group') }}</h2>
        <div class="flex flex-wrap items-center gap-1.5">
            <x-filament::button type="button" size="sm" :color="$previewSource === 'saved' ? 'primary' : 'gray'" aria-pressed="{{ $previewSource === 'saved' ? 'true' : 'false' }}" x-on:click="rebuild('saved')" x-bind:disabled="busy">{{ __('configurator.preview.test_saved') }}</x-filament::button>
            <x-filament::button type="button" size="sm" :color="$previewSource === 'unsaved' ? 'primary' : 'gray'" aria-pressed="{{ $previewSource === 'unsaved' ? 'true' : 'false' }}" x-on:click="rebuild('unsaved')" x-bind:disabled="busy">{{ __('configurator.preview.test_current') }}</x-filament::button>
            <x-filament::icon-button type="button" size="sm" color="gray" icon="heroicon-o-arrow-path" :label="__('configurator.preview.rebuild')" :tooltip="__('configurator.preview.rebuild')" x-on:click="rebuild()" x-bind:disabled="busy" />
        </div>
        <p class="catalog-preview-subline">{{ __($previewSource === 'saved' ? 'configurator.preview.saved_help' : 'configurator.preview.current_help') }}</p>
    </section>
    <section class="catalog-preview-action-group" aria-labelledby="preview-changes-group-{{ $this->getId() }}">
        <h2 id="preview-changes-group-{{ $this->getId() }}" class="catalog-preview-group-heading"><x-filament::icon icon="heroicon-o-pencil-square" />{{ __('configurator.preview.changes_group') }}</h2>
        <div class="flex flex-wrap items-center gap-1.5">
            <x-filament::button type="button" size="sm" color="gray" x-on:click="review()" x-bind:disabled="busy">{{ __('configurator.preview.review_changes') }}</x-filament::button>
            <x-filament::button type="button" size="sm" x-on:click="save()" x-bind:disabled="busy || {{ $this->canAttemptSave() ? 'false' : 'true' }}" :disabled="! $this->canAttemptSave()" :tooltip="__('configurator.preview.save_help')">{{ __('configurator.preview.save_changes') }}</x-filament::button>
        </div>
        <p class="catalog-preview-subline" aria-live="polite">
            @if ($previewChanges !== [])
                {{ trans_choice('configurator.preview.edited_count', count($previewChanges), ['count' => count($previewChanges)]) }} · {{ trans_choice('configurator.preview.field_count', collect($previewChanges)->sum(fn ($change) => count($change['fields'] ?? [])), ['count' => collect($previewChanges)->sum(fn ($change) => count($change['fields'] ?? []))]) }}
            @else
                {{ __('configurator.preview.no_unsaved_short') }}
            @endif
        </p>
    </section>
</div>
<div class="catalog-preview-bar" wire:key="preview-presentation-bar">
    <p class="min-w-0 text-xs"><span class="text-gray-500 dark:text-gray-400">{{ __('configurator.preview.previewing') }}</span> <strong>{{ __($previewSource === 'saved' ? 'configurator.preview.saved_source' : 'configurator.preview.unsaved_source') }}</strong></p>
    <div class="flex flex-wrap items-center gap-2">
        <div class="catalog-preview-presentation" role="group" aria-label="{{ __('configurator.preview.presentation') }}">
            <button type="button" x-on:click="setPresentation('all')" x-bind:aria-pressed="settings.presentation === 'all'">{{ __('configurator.preview.all_states') }}</button>
            <button type="button" x-on:click="setPresentation('catalog')" x-bind:aria-pressed="settings.presentation === 'catalog'">{{ __('configurator.preview.catalog_result') }}</button>
        </div>
        <x-filament::dropdown placement="bottom-end" :shift="true" :size="true" :size-padding="12" width="sm" max-height="min(70dvh, 34rem)" class="catalog-preview-display" wire:key="preview-display-menu">
            <x-slot name="trigger"><x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-adjustments-horizontal" :wire:loading.attr="false">{{ __('configurator.preview.display') }}</x-filament::button></x-slot>
            <div class="catalog-preview-display-fields" x-on:click.stop>
                <p class="catalog-preview-menu-heading">{{ __('configurator.preview.value_metadata') }}</p>
                <label><span>{{ __('configurator.preview.value_code') }}</span><input type="checkbox" x-bind:checked="showCodes" x-on:change="patchMetadata({codes: $event.target.checked})"></label>
                <label><span>{{ __('configurator.preview.value_id') }}</span><input type="checkbox" x-bind:checked="showIds" x-on:change="patchMetadata({ids: $event.target.checked})"></label>
                <p class="catalog-preview-menu-heading">{{ __('configurator.preview.panels') }}</p>
                <label><span>{{ __('configurator.preview.rules_and_state_notes') }}</span><input type="checkbox" x-bind:checked="settings.notes" x-on:change="patch({notes: $event.target.checked})"></label>
                <label><span>{{ __('configurator.preview.diagnostics_trace') }}</span><input type="checkbox" x-bind:checked="settings.trace" x-on:change="patch({trace: $event.target.checked})"></label>
                <p class="catalog-preview-menu-heading">{{ __('configurator.preview.spacing_labels') }}</p>
                <label><span>{{ __('configurator.preview.compact') }}</span><input type="checkbox" x-bind:checked="settings.compact" x-on:change="patch({compact: $event.target.checked})"></label>
                <label><span>{{ __('configurator.preview.attribute_size') }}</span><select x-bind:value="settings.attributeSize" x-on:change="patch({attributeSize: Number($event.target.value)})">@foreach ([12, 14, 16] as $size)<option value="{{ $size }}">{{ $size }} px</option>@endforeach</select></label>
                <label><span>{{ __('configurator.preview.value_size') }}</span><select x-bind:value="settings.valueSize" x-on:change="patch({valueSize: Number($event.target.value)})">@foreach ([11, 12, 14] as $size)<option value="{{ $size }}">{{ $size }} px</option>@endforeach</select></label>
                <p class="catalog-preview-menu-heading">{{ __('configurator.preview.color_mode') }}</p>
                <div class="flex flex-wrap gap-2"><x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-sun" :wire:loading.attr="false" x-on:click="setTheme('light')">{{ __('configurator.preview.light') }}</x-filament::button><x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-moon" :wire:loading.attr="false" x-on:click="setTheme('dark')">{{ __('configurator.preview.dark') }}</x-filament::button></div>
                <button type="button" class="catalog-preview-text-action mt-3" x-on:click="resetDisplay()">{{ __('configurator.preview.reset_display') }}</button>
            </div>
        </x-filament::dropdown>
    </div>
</div>
