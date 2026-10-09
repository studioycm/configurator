<x-filament-panels::page>
    <div class="catalog-workspace-split catalog-split-layout" x-data="Object.assign(workspaceSplit(@js(['user' => auth()->id(), 'owner' => $this::getResource(), 'tab' => 'list'])), { hasDraft() { return !!this.$el.querySelector('.catalog-record-editor [data-draft-dirty=true]') } })"
        x-bind:data-split-stacked="String(stacked)" x-bind:style="{ '--workspace-columns': columns }"
        x-on:catalog-editor-opened.window="$nextTick(() => { if (stacked) { $el.querySelector('.catalog-record-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); } })"
        x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
        x-on:click.capture="if (!$event.target.closest('.fi-modal') && hasDraft() && $event.target.closest('[data-editor-transition], .catalog-record-list .fi-ta-row.fi-clickable, .catalog-record-list .fi-ta-record')) { if (! confirm('Discard unsaved changes and switch editors?')) { $event.preventDefault(); $event.stopImmediatePropagation(); } }">
        <div class="catalog-record-list min-w-0">{{ $this->table }}</div>
        @if ($this->hasEditorPane())
            <x-catalog.workspace-splitter />
            <div class="catalog-record-editor min-w-0">
                @if ($editor = $this->editorComponent())
                    @livewire($editor, $isCreatingRecord ? ['embedded' => true] : ['record' => $selectedRecord], key('editor-'.$editor.'-'.($isCreatingRecord ? 'create' : $selectedRecord)))
                @else
                    <div class="catalog-editor-placeholder">{{ __('Select a record to view or edit it here.') }}</div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
