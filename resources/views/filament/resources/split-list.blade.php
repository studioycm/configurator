<x-filament-panels::page>
    <div class="catalog-workspace-split catalog-split-layout" x-data="Object.assign(workspaceSplit({{ \Illuminate\Support\Js::from(['user' => auth()->id(), 'owner' => $this::getResource(), 'tab' => 'list']) }}), editorDrafts({draftSelector: '.catalog-record-editor [data-draft-dirty=true]'}))"
        x-bind:data-split-stacked="String(stacked)" x-bind:style="{ '--workspace-columns': columns }"
        x-on:catalog-editor-opened.window="$nextTick(() => { if (stacked) { $el.querySelector('.catalog-record-editor')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); } })"
        x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
        x-on:click.capture="if (!allowTransition && !$event.target.closest('.fi-modal') && hasDraft()) { const target = $event.target.closest('[data-editor-transition]'); if (target) requestDiscard($event, target); }">
        <div class="catalog-record-list min-w-0">{{ $this->table }}</div>
        @if ($this->hasEditorPane())
            <x-catalog.workspace-splitter />
            <div class="catalog-record-editor min-w-0">
                @include('filament.resources.editor-discard-alert')
                @if ($editor = $this->editorComponent())
                    @livewire($editor, $isCreatingRecord ? ['embedded' => true, 'inWorkspace' => true] : ['record' => $selectedRecord, 'inWorkspace' => true], key('editor-'.$editor.'-'.($isCreatingRecord ? 'create' : $selectedRecord)))
                @else
                    <div class="catalog-editor-placeholder">{{ __('admin.workspace.copy.select_a_record_to_view_or_edit_it_here') }}</div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
