<x-filament-panels::page>
    <div class="catalog-split-layout" x-data="{ hasDraft() { return !!this.$el.querySelector('[data-draft-dirty=true]') } }"
        x-on:catalog-editor-opened.window="$nextTick(() => { if (window.matchMedia('(max-width: 1199px)').matches) { $el.querySelector('.catalog-record-editor').scrollIntoView({ behavior: 'smooth', block: 'start' }); } })"
        x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
        x-on:click.capture="if (hasDraft() && $event.target.closest('[data-editor-transition], .catalog-record-list .fi-ta-row.fi-clickable, .catalog-record-list .fi-ta-record')) { if (! confirm('Discard unsaved changes and switch editors?')) { $event.preventDefault(); $event.stopImmediatePropagation(); } }">
        <div class="catalog-record-list min-w-0">{{ $this->table }}</div>
        <div class="catalog-record-editor min-w-0">
            @if ($editor = $this->editorComponent())
                @livewire($editor, $isCreatingRecord ? ['embedded' => true] : ['record' => $selectedRecord], key('editor-'.$editor.'-'.($isCreatingRecord ? 'create' : $selectedRecord)))
            @else
                <div class="catalog-editor-placeholder">{{ __('Select a record to view or edit it here.') }}</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
