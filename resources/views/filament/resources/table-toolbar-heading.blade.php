@if (method_exists($this, 'getTable') && isset($this->getTable()->getExtraAttributes()['data-workspace-table']))
    @if (method_exists($this, 'resourceTableCounts'))
        @php($counts = $this->resourceTableCounts)
        <span
            @class(['catalog-table-count', 'catalog-table-count-filtered' => $counts['filtered']])
            role="status"
            aria-live="polite"
            aria-label="{{ $counts['matched'] }} matching records of {{ $counts['total'] }} total"
            x-tooltip="{ content: 'Matching records after search and applied filters, out of the total before filtering. Both counts include all pages.', theme: $store.theme }"
        >{{ $counts['filtered'] ? number_format($counts['matched']).' of '.number_format($counts['total']) : number_format($counts['total']).' total' }}</span>
    @else
        <div class="catalog-table-heading">
            <h3>{{ $this->getTable()->getHeading() ?? $this->getTable()->getPluralModelLabel() }}</h3>
            @if ($this->getTable()->getDescription())
                <span class="catalog-table-description">{{ $this->getTable()->getDescription() }}</span>
            @endif
        </div>
    @endif
@endif
