@if (method_exists($this, 'getTable') && isset($this->getTable()->getExtraAttributes()['data-workspace-table']))
    <span class="contents" x-data="tableColumnWidths(@js(['user' => auth()->id(), 'table' => $this->getTable()->getExtraAttributes()['data-width-table']]))"></span>
    @foreach ($this->isTableReordering() ? [] : $this->getTable()->getHeaderActions() as $action)
        @if ($action->isVisible())
            {{ $action }}
        @endif
    @endforeach
@endif
