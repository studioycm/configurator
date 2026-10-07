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

    /** @return array<string, string> */
    public function scopedSearchColumns(): array
    {
        $fields = ['all' => 'All fields'];
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
