@php
    $searchFields = \App\Filament\Resources\TablePresentation::searchColumns($this->getTable());
    $hasScopedSearch = method_exists($this, 'scopedSearchColumns');
    $scope = $hasScopedSearch ? $this->tableSearchScope : ($this->tableFilters['workspaceSearch']['scope'] ?? 'all');
@endphp
<x-filament::input.select class="catalog-table-scope" :wire:model.live="$hasScopedSearch ? 'tableSearchScope' : 'tableFilters.workspaceSearch.scope'" aria-label="Search field" :title="is_string($scope) ? ($searchFields[$scope] ?? 'Search field') : 'Search field'">
    @foreach ($searchFields as $key => $label)
        <option value="{{ $key }}">{{ $key === 'all' ? 'All' : $label }}</option>
    @endforeach
</x-filament::input.select>
