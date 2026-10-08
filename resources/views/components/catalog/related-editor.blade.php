@props(['workspace' => 'attributes'])
<div class="catalog-related-workspace" x-data="Object.assign(workspaceSplit(@js(['user' => auth()->id(), 'owner' => $this->getOwnerRecord()->getKey(), 'tab' => $workspace])), { savedDraft: JSON.stringify($wire.editorData), savedOptionDraft: JSON.stringify($wire.optionEditorData || {}), confirmDiscard: false, closingOption: false, hasDraft() { return JSON.stringify($wire.editorData) !== this.savedDraft }, hasOptionDraft() { return JSON.stringify($wire.optionEditorData || {}) !== this.savedOptionDraft || Object.values($wire.optionEditorDrafts || {}).some(rows => Object.keys(rows).length > 0) }, hasPendingDrafts() { return this.hasDraft() || Object.keys($wire.editorDrafts || {}).length > 0 || this.hasOptionDraft() } })"
    x-bind:data-split-stacked="String(stacked)" x-bind:style="{ '--workspace-columns': columns }"
    x-bind:data-draft-dirty="hasPendingDrafts()"
    x-on:configurator-editor-filled="$nextTick(() => { savedDraft = JSON.stringify($wire.editorData); confirmDiscard = false })"
    x-on:configurator-option-editor-filled="$nextTick(() => { savedOptionDraft = JSON.stringify($wire.optionEditorData || {}); confirmDiscard = false })"
    x-on:beforeunload.window="if (hasPendingDrafts()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:click.capture="if ((hasPendingDrafts() && $event.target.closest('[data-close-editor]')) || (hasOptionDraft() && $event.target.closest('[data-close-option-editor]'))) { $event.preventDefault(); $event.stopImmediatePropagation(); closingOption = !!$event.target.closest('[data-close-option-editor]'); confirmDiscard = true; }">
    <div class="catalog-related-list min-w-0">{{ $list }}</div>
    <div role="separator" aria-label="Resize table and editor" aria-orientation="vertical" aria-valuemin="33" aria-valuemax="67" x-bind:aria-valuenow="percentage"
        tabindex="0" title="Drag to resize. Arrow keys change width; double-click or Enter resets to half."
        class="catalog-workspace-splitter" x-on:pointerdown="startDrag($event)" x-on:pointermove="moveDrag($event)"
        x-on:pointerup="endDrag()" x-on:pointercancel="endDrag(true)" x-on:lostpointercapture="endDrag(true)" x-on:keydown="keyDown($event)" x-on:dblclick="reset()"></div>
    <div class="catalog-related-pane min-w-0">
        <div x-cloak x-show="confirmDiscard" role="alert" class="mb-2 rounded-lg border border-amber-300 p-2">
            <p class="mb-2 text-sm">Discard all unsaved editor drafts?</p>
            <div class="flex gap-2">
                <x-filament::button color="gray" x-on:click="confirmDiscard = false">Keep editing</x-filament::button>
                <x-filament::button color="danger" x-on:click="confirmDiscard = false; closingOption ? $wire.closeOptionEditor() : $wire.closeEditor()">Discard drafts</x-filament::button>
            </div>
        </div>
        {{ $slot }}
    </div>
    <x-filament-panels::unsaved-action-changes-alert />
</div>
