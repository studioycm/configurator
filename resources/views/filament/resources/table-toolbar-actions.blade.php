@if (method_exists($this, 'getTable') && isset($this->getTable()->getExtraAttributes()['data-workspace-table']))
    <span class="contents" x-data="tableColumnWidths(@js(['user' => auth()->id(), 'table' => $this->getTable()->getExtraAttributes()['data-width-table'], 'labels' => ['resize_column' => __('admin.tables.column_resize_help')]]))"></span>
    @foreach ($this->getTable()->getHeaderActions() as $action)
        @if ($action->isVisible() && (! $this->isTableReordering() || ($action instanceof \Filament\Actions\Action && $action->getName() === 'dragOrder')))
            {{ $action }}
        @endif
    @endforeach
@endif
