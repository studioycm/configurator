<div class="catalog-related-workspace" x-data="{ savedDraft: JSON.stringify($wire.editorData), hasDraft() { return JSON.stringify($wire.editorData) !== this.savedDraft } }"
    x-bind:data-draft-dirty="hasDraft()"
    x-on:configurator-editor-filled="$nextTick(() => savedDraft = JSON.stringify($wire.editorData))"
    x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:click.capture="if (hasDraft() && $event.target.closest('.catalog-related-list .fi-ta-row.fi-clickable, [data-editor-switch], [data-close-editor]')) { if (! confirm('Discard unsaved changes?')) { $event.preventDefault(); $event.stopImmediatePropagation(); } }">
    <div class="catalog-related-list min-w-0">{{ $list }}</div>
    <div class="min-w-0">{{ $slot }}</div>
    <x-filament-panels::unsaved-action-changes-alert />
</div>
