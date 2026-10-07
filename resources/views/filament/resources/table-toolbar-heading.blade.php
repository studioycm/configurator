@if (method_exists($this, 'getTable') && isset($this->getTable()->getExtraAttributes()['data-workspace-table']))
    <div class="catalog-table-heading">
        <h3>{{ $this->getTable()->getHeading() ?? $this->getTable()->getPluralModelLabel() }}</h3>
        @if ($this->getTable()->getDescription())
            <span class="catalog-table-description">{{ $this->getTable()->getDescription() }}</span>
        @endif
    </div>
@endif
