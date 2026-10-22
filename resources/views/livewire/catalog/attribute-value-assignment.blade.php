<div x-data="{ savedDraft: @js($data) }" x-bind:data-draft-dirty="String(JSON.stringify(savedDraft) !== JSON.stringify($wire.data))" x-on:catalog-editor-saved="savedDraft = JSON.parse(JSON.stringify($wire.data))">
    @if ($listKey === 'value-options')
        <h3 class="catalog-inline-editor-heading">{{ __('admin-ux.use_in_attribute') }}</h3>
    @endif
    <form wire:submit="save">
        {{ $this->form }}
        <div class="catalog-assignment-actions">
            <x-filament::button type="submit" wire:loading.attr="disabled">{{ __('admin-ux.save_relations') }}</x-filament::button>
            <x-filament::button color="gray" wire:click="cancel" data-item-editor-transition>{{ __('ui.actions.cancel') }}</x-filament::button>
        </div>
    </form>
    <x-filament-actions::modals />
</div>
