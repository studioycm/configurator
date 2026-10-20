<div x-data="editorDrafts()"
    x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:click.capture="if (!allowTransition && hasDraft()) {
        const explicit = $event.target.closest('[data-item-editor-transition]');
        const row = $event.target.closest('button, a, input, select, textarea, [role=button]') ? null : $event.target.closest('.catalog-item-record-list .fi-ta-row.fi-clickable, .catalog-item-record-list .fi-ta-record');
        const target = explicit || row;
        if (target) requestDiscard($event, target);
    }"
    x-on:click.window.capture="if (!allowTransition && hasDraft()) {
        const closeButton = $event.target.closest('.fi-modal-footer button');
        const drawerModal = $el.closest('.fi-modal');
        if (drawerModal && closeButton?.closest('.fi-modal') === drawerModal) {
            requestDiscard($event, closeButton);
        }
    }"
    x-on:close-modal.window.capture="if (!allowTransition && $event.detail.id === $el.closest('.fi-modal')?.id && hasDraft()) {
        requestDiscard($event, null, $event.detail.id);
    }"
    x-on:item-drawer-editor-opened="$nextTick(() => { $el.querySelector('.catalog-item-drawer-editor')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })">
    <div class="catalog-item-record-list">{{ $this->table }}</div>
    @if ($editor = $this->editorComponent())
        <div class="catalog-item-drawer-editor mt-4 min-w-0">
            @include('filament.resources.editor-discard-alert')
            @livewire($editor, ['record' => $selectedRecordId, 'listKey' => $listKey, 'parentId' => $parentId, 'initialTab' => $listKey === 'group-card-properties' ? 'form.group-settings.presentation::data::tab' : null], key('item-editor-'.$listKey.'-'.$parentId.'-'.$selectedRecordId))
        </div>
    @endif
    <x-filament-actions::modals />
</div>
