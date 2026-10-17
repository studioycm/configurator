<?php

namespace App\Filament\Resources;

use App\Services\ItemLists;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;

trait InteractsWithScopedTableSearch
{
    public string $tableSearchScope = 'all';

    /** @var array<string, mixed> */
    #[Locked]
    public array $batchPreview = [];

    /** @param array<string, mixed> $state */
    public function restoreTableBrowserProfile(array $state): void
    {
        TableBrowserProfile::restore($this, $state);
    }

    public function mountInteractsWithScopedTableSearch(): void
    {
        $controls = session()->get(TableBrowserProfile::sessionKey($this, 'controls'), []);
        foreach (['tableSearchScope', 'showSelection', 'showOrderControls'] as $property) {
            if (property_exists($this, $property) && isset($controls[$property]) && get_debug_type($controls[$property]) === get_debug_type($this->{$property})) {
                $this->{$property} = $controls[$property];
            }
        }
    }

    public function dehydrateInteractsWithScopedTableSearch(): void
    {
        $controls = [];
        foreach (['tableSearchScope', 'showSelection', 'showOrderControls'] as $property) {
            if (property_exists($this, $property)) {
                $controls[$property] = $this->{$property};
            }
        }
        session()->put(TableBrowserProfile::sessionKey($this, 'controls'), $controls);
    }

    public function getTableColumnsSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'columns');
    }

    public function getHasReorderedTableColumnsSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'reordered');
    }

    public function getTableSearchSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'search');
    }

    public function getTableColumnSearchesSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'column-search');
    }

    public function getTableSortSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'sort');
    }

    public function getTableFiltersSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'filters');
    }

    public function getTablePerPageSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'per-page');
    }

    public function getTableGroupingSessionKey(): string
    {
        return TableBrowserProfile::sessionKey($this, 'group');
    }

    /** @return array<string, string> */
    public function scopedSearchColumns(): array
    {
        $fields = ['all' => __('ui.tables.all_fields')];
        foreach ($this->getTable()->getColumns() as $column) {
            if ($column->isGloballySearchable()) {
                $fields[$column->getName()] = (string) $column->getLabel();
            }
        }

        return $fields;
    }

    public function resetTableColumnManager(): void
    {
        $this->setTableColumns($this->getDefaultTableColumnState());
        if ($this->hasReorderableTableColumns()) {
            $this->updateTableColumns();
            $this->persistHasReorderedTableColumns();
        }
        $this->persistTableColumns();
        $this->dispatch('table-widths-reset', table: $this->getTable()->getExtraAttributes()['data-width-table']);
    }

    public function updatedTableSearchScope(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    /** @return list<string> */
    #[Renderless]
    public function itemCountPreview(string $key, int $parentId): array
    {
        return app(ItemLists::class)->preview(auth()->user(), $key, $parentId);
    }

    protected function applyGlobalSearchToTableQuery(Builder $query): Builder
    {
        $search = $this->getTableSearch();
        if (blank($search)) {
            return $query;
        }
        if (! array_key_exists($this->tableSearchScope, $this->scopedSearchColumns())) {
            return $query->whereRaw('1 = 0');
        }
        $words = $this->getTable()->shouldSplitSearchTerms() ? $this->extractTableSearchWords($search) : [$search];
        foreach ($words as $word) {
            $query->where(function (Builder $nested) use ($word): void {
                $isFirst = true;
                foreach ($this->getTable()->getColumns() as $column) {
                    if (! $column->isGloballySearchable() || ($this->tableSearchScope !== 'all' && $column->getName() !== $this->tableSearchScope)) {
                        continue;
                    }
                    $column->applySearchConstraint($nested, $word, $isFirst);
                }
            });
        }

        return $query;
    }
}
