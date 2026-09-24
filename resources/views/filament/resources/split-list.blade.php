<x-filament-panels::page>
    <div class="catalog-split-layout" x-data="{ hasDraft() { return !!this.$el.querySelector('[data-draft-dirty=true]') } }"
        x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
        x-on:click.capture="if (hasDraft() && $event.target.closest('.catalog-record-list .fi-ta-row.fi-clickable, .catalog-record-list .fi-ta-record')) { if (! confirm('Discard unsaved changes and open another record?')) { $event.preventDefault(); $event.stopImmediatePropagation(); } }">
        <div class="catalog-record-list min-w-0">{{ $this->table }}</div>
        <div class="catalog-record-editor min-w-0">
            @if ($editor = $this->editorComponent())
                @livewire($editor, ['record' => $selectedRecord], key('editor-'.$editor.'-'.$selectedRecord))
            @else
                <div class="catalog-editor-placeholder">{{ __('Select a record to view or edit it here.') }}</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
