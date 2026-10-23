<div class="catalog-master-batch-columns">
    <x-filament::dropdown placement="bottom-start">
        <x-slot name="trigger"><x-filament::button color="gray" size="sm" icon="heroicon-o-view-columns">{{ __('ui.tables.columns') }}</x-filament::button></x-slot>
        <div class="catalog-master-batch-column-options">
            <label><x-filament::input.checkbox x-model="showTags" /> {{ __('catalog.fields.tags') }}</label>
            <label><x-filament::input.checkbox x-model="showSpecification" /> {{ __('catalog.fields.specification') }}</label>
            <x-filament::button color="gray" size="sm" x-on:click="resetColumns()">{{ __('admin.workspace.labels.reset_columns') }}</x-filament::button>
        </div>
    </x-filament::dropdown>
</div>
