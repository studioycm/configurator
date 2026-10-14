@php
    $searchFields = \App\Filament\Resources\TablePresentation::searchColumns($this->getTable());
    $hasScopedSearch = method_exists($this, 'scopedSearchColumns');
    $scope = $hasScopedSearch ? $this->tableSearchScope : ($this->tableFilters['workspaceSearch']['scope'] ?? 'all');
@endphp
<x-filament::input.select class="catalog-table-scope" :wire:model.live="$hasScopedSearch ? 'tableSearchScope' : 'tableFilters.workspaceSearch.scope'" :disabled="$this->isTableReordering()" aria-label="{{ __('admin.workspace.copy.search_field') }}" :title="is_string($scope) ? ($searchFields[$scope] ?? __('admin.workspace.copy.search_field')) : __('admin.workspace.copy.search_field')">
    @foreach ($searchFields as $key => $label)
        <option value="{{ $key }}">{{ $key === 'all' ? __('ui.choices.all') : $label }}</option>
    @endforeach
</x-filament::input.select>
