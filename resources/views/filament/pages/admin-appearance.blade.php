<x-filament-panels::page>
    <form data-personal-appearance wire:submit="savePersonal" class="space-y-2">
        {{ $this->form }}
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('admin.appearance.copy.save_scope') }}</p>
        <div class="flex flex-wrap gap-2"><x-filament::button type="submit">{{ __('admin.appearance.copy.save_personal') }}</x-filament::button><x-filament::button color="gray" wire:click="resetPersonal">{{ __('admin.appearance.copy.reset_personal') }}</x-filament::button><x-filament::button color="gray" wire:click="save">{{ __('admin.appearance.copy.save_shared_defaults') }}</x-filament::button><x-filament::button color="gray" wire:click="resetDefaults">{{ __('admin.appearance.copy.reset_preview_to_defaults') }}</x-filament::button></div>
    </form>
    <div style="{{ app(\App\Services\AdminAppearance::class)->style($this->data) }}" class="catalog-appearance-preview">
        <p class="text-sm mb-2">{{ __('admin.appearance.copy.unsaved_preview_other_users_see_saved_defaults') }}</p>
        <div class="catalog-appearance-preview-header">
            <div class="catalog-appearance-preview-brand"><img src="{{ asset('images/logo-ari_dark.png') }}" alt="Aquestia logo preview" /></div>
            <div class="catalog-appearance-preview-heading">
                <strong class="catalog-appearance-preview-title">{{ __('admin.appearance.copy.workspace') }}</strong>
                <span class="catalog-appearance-preview-breadcrumb">{{ __('admin.appearance.copy.catalog_edit') }}</span>
                <x-filament::button disabled>{{ __('admin.appearance.copy.save') }}</x-filament::button>
            </div>
        </div>
        <div class="catalog-appearance-preview-body">
            <aside><div>{{ __('admin.appearance.copy.library') }}</div><div>{{ __('admin.appearance.copy.master_values') }}</div><div>{{ __('catalog.resources.attributes.plural') }}</div><div>{{ __('admin.navigation.settings') }}</div></aside>
            <main><div class="catalog-appearance-preview-table"><div>{{ __('admin.appearance.copy.records_search_filters_columns') }}</div><table><thead><tr><th>{{ __('ui.fields.name') }}</th><th>{{ __('catalog.fields.code') }}</th><th>{{ __('admin.appearance.copy.items') }}</th></tr></thead><tbody><tr><td>{{ __('admin.appearance.copy.flange_standard') }}</td><td>{{ __('admin.appearance.copy.a1') }}</td><td>4</td></tr><tr><td>{{ __('admin.appearance.copy.kinetic_valve_body_material') }}</td><td>{{ __('admin.appearance.copy.di') }}</td><td>2</td></tr></tbody></table></div><div class="catalog-appearance-preview-narrow"><div>{{ __('admin.appearance.copy.search_filters_columns_actions') }}</div><table><tr><td>{{ __('admin.appearance.copy.flange_standard') }}</td><td>{{ __('admin.appearance.copy.a1') }}</td></tr></table></div><div class="catalog-appearance-preview-dialog">{{ __('admin.appearance.copy.modal_preview') }} <div>{{ __('admin.appearance.copy.fields_and_controls') }}</div><x-filament::button disabled>{{ __('admin.appearance.copy.save') }}</x-filament::button></div><div class="catalog-appearance-preview-dialog catalog-appearance-preview-slide-over">{{ __('admin.appearance.copy.slide_over_preview') }} <div>{{ __('admin.appearance.copy.related_records_and_controls') }}</div></div></main>
        </div>
    </div>
</x-filament-panels::page>
