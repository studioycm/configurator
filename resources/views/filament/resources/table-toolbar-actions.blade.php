@if (method_exists($this, 'getTable') && isset($this->getTable()->getExtraAttributes()['data-workspace-table']))
    <span class="contents" x-data="tableColumnWidths({{ \Illuminate\Support\Js::from(['user' => auth()->id(), 'table' => $this->getTable()->getExtraAttributes()['data-width-table'], 'labels' => ['resize_column' => __('admin.tables.column_resize_help')]]) }})"></span>
    <span class="contents" x-data="tablePagination()"></span>
    @if (method_exists($this, 'restoreTableBrowserProfile'))
        <span class="contents" wire:key="{{ $this->getId() }}.local-profile" x-data="localTableProfile({{ \Illuminate\Support\Js::from(['user' => auth()->id(), 'scope' => \App\Filament\Resources\TableBrowserProfile::scope($this), 'query' => ['searchScope' => $this instanceof \App\Filament\Resources\SplitListRecords ? 'scope' : null, 'searchFields' => $this instanceof \App\Filament\Resources\SplitListRecords ? 'searchFields' : null, 'sort' => $this->getIdentifiedTableQueryStringPropertyNameFor('sort'), 'search' => $this->getIdentifiedTableQueryStringPropertyNameFor('search'), 'filters' => $this->getIdentifiedTableQueryStringPropertyNameFor('filters')]]) }})"></span>
    @endif
    @foreach ($this->getTable()->getHeaderActions() as $action)
        @if ($action->isVisible() && (! $this->isTableReordering() || ($action instanceof \Filament\Actions\Action && $action->getName() === 'dragOrder')))
            {{ $action }}
        @endif
    @endforeach
@endif
