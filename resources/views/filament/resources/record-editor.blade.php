<x-filament-panels::page x-data="{ savedDraft: JSON.stringify($wire.data) }"
    x-bind:data-draft-dirty="JSON.stringify($wire.data) !== savedDraft"
    x-on:catalog-editor-saved="savedDraft = JSON.stringify($wire.data)">
    @if (property_exists($this, 'batchEditorStale') && $this->batchEditorStale)
        <div role="alert" class="text-sm text-warning-600">
            <p>This record changed. Your draft was kept. Reload before editing again; reloading discards this record's draft.</p>
            <x-filament::button color="warning" wire:click="reloadBatchEditor">Reload saved record</x-filament::button>
        </div>
    @endif
    {{ $this->content }}
</x-filament-panels::page>
