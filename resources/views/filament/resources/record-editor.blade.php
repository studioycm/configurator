<x-filament-panels::page x-data="{ savedDraft: JSON.stringify($wire.data) }"
    x-bind:data-draft-dirty="JSON.stringify($wire.data) !== savedDraft"
    x-on:catalog-editor-saved="savedDraft = JSON.stringify($wire.data)">
    {{ $this->content }}
</x-filament-panels::page>
