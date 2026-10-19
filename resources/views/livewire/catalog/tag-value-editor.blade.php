<form wire:submit="save" class="catalog-tag-row-editor"
    x-data="{ get changed() { return JSON.stringify($wire.data.tags ?? []) !== JSON.stringify($wire.originalTags) } }"
    x-bind:data-tag-dirty="changed"
    x-on:keydown.ctrl.enter.prevent="$wire.save()"
    x-on:keydown.meta.enter.prevent="$wire.save()"
    x-on:keydown.escape.prevent="$wire.cancel()">
    {{ $this->form }}
    <div class="catalog-tag-row-actions">
        <x-filament::button type="submit" size="sm" x-bind:disabled="!changed" wire:loading.attr="disabled">{{ __('tags.save') }}</x-filament::button>
        <x-filament::link tag="button" type="button" wire:click="cancel" x-bind:class="{ 'invisible': !changed }">{{ __('ui.actions.cancel') }}</x-filament::link>
    </div>
</form>
