<div class="catalog-related-workspace" x-data="{ savedDraft: JSON.stringify($wire.editorData), confirmDiscard: false, hasDraft() { return JSON.stringify($wire.editorData) !== this.savedDraft }, hasPendingDrafts() { return this.hasDraft() || Object.keys($wire.editorDrafts || {}).length > 0 } }"
    x-bind:data-draft-dirty="hasPendingDrafts()"
    x-on:configurator-editor-filled="$nextTick(() => { savedDraft = JSON.stringify($wire.editorData); confirmDiscard = false })"
    x-on:beforeunload.window="if (hasPendingDrafts()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:click.capture="if (hasPendingDrafts() && $event.target.closest('[data-close-editor]')) { $event.preventDefault(); $event.stopImmediatePropagation(); confirmDiscard = true; }">
    <div class="catalog-related-list min-w-0">{{ $list }}</div>
    <div class="min-w-0">
        <div x-cloak x-show="confirmDiscard" role="alert" class="mb-2 rounded-lg border border-amber-300 p-2">
            <p class="mb-2 text-sm">Discard all unsaved editor drafts?</p>
            <div class="flex gap-2">
                <x-filament::button color="gray" x-on:click="confirmDiscard = false">Keep editing</x-filament::button>
                <x-filament::button color="danger" x-on:click="confirmDiscard = false; $wire.closeEditor()">Discard drafts</x-filament::button>
            </div>
        </div>
        {{ $slot }}
    </div>
    <x-filament-panels::unsaved-action-changes-alert />
</div>
