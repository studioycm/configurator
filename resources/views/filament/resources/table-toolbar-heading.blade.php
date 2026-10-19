@if (method_exists($this, 'getTable') && isset($this->getTable()->getExtraAttributes()['data-workspace-table']))
    @if (method_exists($this, 'resourceTableCounts'))
        @php($counts = $this->resourceTableCounts)
        <span
            @class(['catalog-table-count', 'catalog-table-count-filtered' => $counts['filtered']])
            role="status"
            x-show="! getSelectedRecordsCount()"
            aria-live="polite"
            aria-label="{{ __('admin.tables.counts.accessible', ['matched' => $counts['matched'], 'total' => $counts['total']]) }}"
            x-tooltip="{ content: @js(__('admin.tables.counts.help')), theme: $store.theme }"
        >{{ $counts['filtered'] ? __('admin.tables.counts.matched', ['matched' => number_format($counts['matched']), 'total' => number_format($counts['total'])]) : __('admin.tables.counts.total', ['total' => number_format($counts['total'])]) }}</span>
    @elseif ($this->getTable()->getHeading() !== '')
        <div class="catalog-table-heading" x-show="! getSelectedRecordsCount()">
            <h3>{{ $this->getTable()->getHeading() ?? $this->getTable()->getPluralModelLabel() }}</h3>
            @if ($this->getTable()->getDescription())
                <span class="catalog-table-description">{{ $this->getTable()->getDescription() }}</span>
            @endif
        </div>
    @endif
    @include('filament.resources.table-selection-controls')
@endif
