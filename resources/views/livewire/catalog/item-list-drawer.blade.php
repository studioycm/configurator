<div x-data="{ confirmDiscard: false, pendingTransition: null, pendingCloseId: null, allowTransition: false,
        hasDraft() { return !!this.$el.querySelector('[data-draft-dirty=true]') },
        keepEditing() { this.confirmDiscard = false; this.pendingTransition = null; this.pendingCloseId = null },
        discardDrafts() {
            const target = this.pendingTransition;
            const closeId = this.pendingCloseId;
            this.keepEditing();
            this.allowTransition = true;
            if (closeId) { this.$dispatch('close-modal', { id: closeId }) } else { target?.click() }
            this.$nextTick(() => { this.allowTransition = false });
        }
    }"
    x-on:beforeunload.window="if (hasDraft()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:click.capture="if (!allowTransition && hasDraft()) {
        const explicit = $event.target.closest('[data-item-editor-transition]');
        const row = $event.target.closest('button, a, input, select, textarea, [role=button]') ? null : $event.target.closest('.catalog-item-record-list .fi-ta-row.fi-clickable, .catalog-item-record-list .fi-ta-record');
        const target = explicit || row;
        if (target) { $event.preventDefault(); $event.stopImmediatePropagation(); pendingTransition = target; pendingCloseId = null; confirmDiscard = true; }
    }"
    x-on:click.window.capture="if (!allowTransition && hasDraft()) {
        const closeButton = $event.target.closest('.fi-modal-footer button');
        const drawerModal = $el.closest('.fi-modal');
        if (drawerModal && closeButton?.closest('.fi-modal') === drawerModal) {
            $event.preventDefault(); $event.stopImmediatePropagation(); pendingTransition = closeButton; pendingCloseId = null; confirmDiscard = true;
        }
    }"
    x-on:close-modal.window.capture="if (!allowTransition && $event.detail.id === $el.closest('.fi-modal')?.id && hasDraft()) {
        $event.preventDefault(); $event.stopImmediatePropagation(); pendingTransition = null; pendingCloseId = $event.detail.id; confirmDiscard = true;
    }"
    x-on:item-drawer-editor-opened="$nextTick(() => { $el.querySelector('.catalog-item-drawer-editor')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })">
    <div class="catalog-item-record-list">{{ $this->table }}</div>
    @if ($editor = $this->editorComponent())
        <div class="catalog-item-drawer-editor mt-4 min-w-0">
            <div x-cloak x-show="confirmDiscard" role="alert" class="mb-2 rounded-lg border border-amber-300 p-2">
                <p class="mb-2 text-sm">Discard the unsaved editor draft?</p>
                <div class="flex gap-2">
                    <x-filament::button color="gray" x-on:click="keepEditing()">Keep editing</x-filament::button>
                    <x-filament::button color="danger" x-on:click="discardDrafts()">Discard drafts</x-filament::button>
                </div>
            </div>
            @livewire($editor, ['record' => $selectedRecordId, 'listKey' => $listKey, 'parentId' => $parentId, 'initialTab' => $listKey === 'group-card-properties' ? 'form.group-settings.presentation::data::tab' : null], key('item-editor-'.$listKey.'-'.$parentId.'-'.$selectedRecordId))
        </div>
    @endif
    <x-filament-actions::modals />
</div>
