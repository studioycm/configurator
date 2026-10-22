<?php

namespace App\Filament\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class TableBrowserProfile
{
    public static function scope(Component $component): string
    {
        $owner = method_exists($component, 'getOwnerRecord') ? $component->getOwnerRecord()->getKey() : ($component->parentId ?? $component->tableArguments['owner_id'] ?? 'list');

        return $component::class.':'.$owner.':'.($component->listKey ?? ($component->tableConfiguration ?? ''));
    }

    public static function sessionKey(Component $component, string $setting): string
    {
        return 'tables.local.'.hash('sha256', auth()->id().':admin:'.self::scope($component)).'.'.$setting;
    }

    /** @param array<string, mixed> $state */
    public static function restore(Component $component, array $state): void
    {
        Gate::authorize('manage-catalog');
        unset($state['tagFilters']);
        Validator::make(['state' => $state, 'size' => strlen(json_encode($state, JSON_THROW_ON_ERROR))], [
            'size' => ['integer', 'max:32768'],
            'state' => ['array:columns,sort,search,searchScope,searchFields,filters,perPage,showSelection,showOrderControls'],
            'state.columns' => ['sometimes', 'array', 'max:100'],
            'state.columns.*.name' => ['required', 'string', 'max:255'],
            'state.columns.*.isToggled' => ['required', 'boolean'],
            'state.sort' => ['nullable', 'string', 'max:255'],
            'state.search' => ['sometimes', 'string', 'max:1000'],
            'state.searchScope' => ['sometimes', 'string', 'max:255'],
            'state.searchFields' => ['sometimes', 'array', 'max:100'],
            'state.searchFields.*' => ['string', 'max:255'],
            'state.filters' => ['sometimes', 'array'],
            'state.filters.*' => ['array'],
            'state.perPage' => ['sometimes', 'integer'],
            'state.showSelection' => ['sometimes', 'boolean'],
            'state.showOrderControls' => ['sometimes', 'boolean'],
        ])->validate();

        $table = $component->getTable();
        if (isset($state['columns'])) {
            $defaults = collect($component->getDefaultTableColumnState())->keyBy('name');
            $columns = [];
            foreach ($state['columns'] as $saved) {
                if (! $defaults->has($saved['name'])) {
                    continue;
                }
                $column = $defaults->pull($saved['name']);
                if ($column['isToggleable']) {
                    $column['isToggled'] = (bool) $saved['isToggled'];
                }
                $columns[] = $column;
            }
            $component->applyTableColumnManager([...$columns, ...$defaults->values()->all()], true);
        }
        if (array_key_exists('sort', $state)) {
            [$name, $direction] = explode(':', $state['sort'] ?? '', 2) + [null, null];
            if ($state['sort'] === null || ($table->getColumn($name)?->isSortable() && in_array($direction, ['asc', 'desc'], true))) {
                $component->tableSort = $state['sort'];
                $component->updatedTableSort();
            }
        }
        if (isset($state['perPage']) && in_array($state['perPage'], $table->getPaginationPageOptions(), true)) {
            $component->tableRecordsPerPage = $state['perPage'];
            $component->updatedTableRecordsPerPage();
        }
        if (isset($state['searchFields'])) {
            $component->tableSearchFields = array_values(array_intersect($state['searchFields'], array_diff(array_keys($component->scopedSearchColumns()), ['all', 'subset'])));
        }
        if (isset($state['searchScope']) && array_key_exists($state['searchScope'], $component->scopedSearchColumns())) {
            $component->tableSearchScope = $state['searchScope'];
        }
        if (array_key_exists('search', $state)) {
            $component->tableSearch = $state['search'];
            $component->updatedTableSearch();
        }
        if (isset($state['filters'])) {
            $filters = Arr::only($state['filters'], array_keys($table->getFilters()));
            $component->getTableFiltersForm()->fill($filters);
            if ($table->hasDeferredFilters()) {
                $component->applyTableFilters();
            } else {
                $component->updatedTableFilters();
            }
        }
        if (isset($state['showSelection']) && property_exists($component, 'showSelection') && $component->showSelection !== (bool) $state['showSelection']) {
            $component->toggleWorkspaceSelection();
        }
        if (isset($state['showOrderControls']) && property_exists($component, 'showOrderControls') && $component->showOrderControls !== (bool) $state['showOrderControls']) {
            $component->toggleOrderControls();
        }
        $component->resetPage();
        $component->flushCachedTableRecords();
    }
}
