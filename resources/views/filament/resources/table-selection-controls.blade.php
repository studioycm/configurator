@if ($this->getTable()->isSelectionEnabled() && $this->getTable()->getMaxSelectableRecords() !== 1 && ! $this->isTableReordering())
    <div @if (method_exists($this, 'workspaceSelectionScope')) x-data="workspaceSelectionScope" x-effect="refreshSelectionScope([...selectedRecords], JSON.stringify([$wire.tableSearch, $wire.tableColumnSearches, $wire.tableFilters, $wire.tableSearchScope, $wire.tableSearchFields]))" @endif class="catalog-table-selection-controls" x-cloak x-show="getSelectedRecordsCount()" wire:key="{{ $this->getId() }}.selection-controls">
        <x-filament::dropdown placement="bottom-start">
            <x-slot name="trigger">
                <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-down" icon-position="after">
                    <span role="status" aria-live="polite" x-text="getSelectedRecordsCount() + ' ' + {{ \Illuminate\Support\Js::from(__('admin-ux.selected')) }}"></span>
                </x-filament::button>
            </x-slot>
            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item tag="button" x-show="canSelectAllRecords()" x-on:click="selectAllRecords(); close()">{{ __('admin-ux.select_all') }}</x-filament::dropdown.list.item>
                <x-filament::dropdown.list.item tag="button" color="danger" x-on:click="deselectAllRecords(); close()">{{ __('admin-ux.clear_selection') }}</x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        </x-filament::dropdown>
        @if (method_exists($this, 'workspaceSelectionScope'))
            <span class="text-xs text-gray-500 dark:text-gray-400" aria-live="polite" title="{{ __('admin-ux.selection_scope_help') }}">
                <span x-show="selectionScopeBusy">{{ __('admin-ux.selection_scope_loading') }}</span>
                <span x-show="selectionScope" x-text="selectionScope ? uiInterpolate(@js(__('admin-ux.selection_scope')), selectionScope) : ''"></span>
                <span x-show="selectionScopeFailed">{{ __('admin-ux.selection_scope_unavailable') }}</span>
            </span>
        @endif
    </div>
@endif
