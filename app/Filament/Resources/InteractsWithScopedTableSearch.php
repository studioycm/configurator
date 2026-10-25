<?php

namespace App\Filament\Resources;

use App\Models\Value;
use App\Services\ItemLists;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;

trait InteractsWithScopedTableSearch
{
    /** @return array{owner: int|string, widthVariant: string, profileVariant: string} */
    abstract public function tableHostIdentity(): array;

    /** @return list<string> */
    public function tableProfileControlNames(): array
    {
        return ['tableSearchScope', 'tableSearchFields'];
    }

    /** @return array<string, mixed>|null */
    public function tableProfileColumnMigration(): ?array
    {
        return null;
    }

    /** @return list<string> */
    public function tableQuickFilterNames(): array
    {
        return ['tags'];
    }

    protected function removeWorkspaceNativeFilter(string $filterName, ?string $field, bool $isRemovingAllFilters): void
    {
        parent::removeTableFilter($filterName, $field, $isRemovingAllFilters);
    }

    public string $tableSearchScope = 'all';

    /** @var list<string> */
    public array $tableSearchFields = [];

    protected function queryStringInteractsWithScopedTableSearch(): array
    {
        return $this instanceof SplitListRecords ? [
            'tableSearchScope' => ['as' => 'scope', 'except' => 'all'],
            'tableSearchFields' => ['as' => 'searchFields', 'except' => []],
        ] : [];
    }

    /** @return array<int|string, string> */
    public function tagFilterOptions(): array
    {
        return ValueTagField::options(Value::tagVocabulary(), $this->tableFilters['tags']['values'] ?? []);
    }

    public function inlineTagsForm(Schema $schema): Schema
    {
        return $schema->statePath('tableFilters.tags')->live()->components([
            Hidden::make('operator')->default('or'),
            ValueTagField::make(fn (): array => $this->tagFilterOptions())->id('table-tags-'.$this->getId())->hiddenLabel(),
        ]);
    }

    public function toggleTagFilterOperator(): void
    {
        Gate::authorize('manage-catalog');
        if ($this->getTable()->getFilter('tags') === null) {
            return;
        }
        $this->tableFilters['tags']['operator'] = ($this->tableFilters['tags']['operator'] ?? 'or') === 'and' ? 'or' : 'and';
        $this->updatedTableFilters(null, 'tags.operator');
    }

    public function updatedTableFilters(mixed $value = null, ?string $key = null): void
    {
        if ($this->getTable()->hasDeferredFilters()) {
            $name = explode('.', $key ?? '')[0];
            $quick = $this->tableQuickFilterNames();
            if (in_array($name, $quick, true)) {
                $this->tableDeferredFilters[$name] = $this->tableFilters[$name] ?? [];
            } else {
                $this->tableDeferredFilters = $this->tableFilters;
            }
        }
        $this->handleTableFilterUpdates();
    }

    public function removeTableFilter(string $filterName, ?string $field = null, bool $isRemovingAllFilters = false): void
    {
        $preservePending = ! $isRemovingAllFilters && $this->getTable()->hasDeferredFilters();
        $pending = $this->tableDeferredFilters;
        if ($preservePending) {
            $this->tableDeferredFilters = $this->tableFilters;
        }
        $this->removeWorkspaceNativeFilter($filterName, $field, $isRemovingAllFilters);
        if ($preservePending) {
            $this->tableDeferredFilters = [...$pending, $filterName => $this->tableFilters[$filterName]];
        }
    }

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
        foreach ($this->tableProfileControlNames() as $property) {
            if ($this instanceof SplitListRecords && (($property === 'tableSearchScope' && request()->has('scope')) || ($property === 'tableSearchFields' && request()->has('searchFields')))) {
                continue;
            }
            if (isset($controls[$property]) && get_debug_type($controls[$property]) === get_debug_type($this->{$property})) {
                $this->{$property} = $controls[$property];
            }
        }
    }

    public function dehydrateInteractsWithScopedTableSearch(): void
    {
        $controls = [];
        foreach ($this->tableProfileControlNames() as $property) {
            $controls[$property] = $this->{$property};
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
        if ($this->tableSearchFields !== [] && count(array_filter($this->tableSearchFields, 'is_string')) === count($this->tableSearchFields) && array_diff($this->tableSearchFields, array_diff(array_keys($fields), ['all'])) === []) {
            $fields['subset'] = __('admin-ux.related_list_fields');
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
                    $inScope = $this->tableSearchScope === 'all' || ($this->tableSearchScope === 'subset' ? in_array($column->getName(), $this->tableSearchFields, true) : $column->getName() === $this->tableSearchScope);
                    if (! $column->isGloballySearchable() || ! $inScope) {
                        continue;
                    }
                    $column->applySearchConstraint($nested, $word, $isFirst);
                }
            });
        }

        return $query;
    }
}
