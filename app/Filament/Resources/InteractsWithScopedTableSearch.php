<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Models\Value;
use App\Services\ItemLists;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;

trait InteractsWithScopedTableSearch
{
    public string $tableSearchScope = 'all';

    /** @var list<string>|null */
    #[Locked]
    public ?array $tableTagFilters = null;

    /** @return array<string, string> */
    public function personalTagFilterOptions(): array
    {
        $options = Value::tagOptions();
        $active = array_values(array_filter($this->tableFilters['tags']['values'] ?? [], 'is_string'));
        $ordered = $this->tableTagFilters ?? array_keys($options);
        $visible = [];
        foreach (array_unique([...$ordered, ...$active]) as $tag) {
            if (isset($options[$tag]) || in_array($tag, $active, true)) {
                $visible[$tag] = isset($options[$tag]) ? $options[$tag] : __('tags.unused_filter', ['tag' => $tag]);
            }
        }

        return $visible;
    }

    public function inlineTagsForm(Schema $schema): Schema
    {
        return $schema->statePath('tableFilters.tags')->live()->components([
            ValuesTable::tagFilterField()->id('table-tags-'.$this->getId())->options(fn (): array => $this->personalTagFilterOptions())->hiddenLabel(),
        ]);
    }

    public function updatedTableFilters(mixed $value = null, ?string $key = null): void
    {
        if ($this->getTable()->hasDeferredFilters()) {
            $name = explode('.', $key ?? '')[0];
            $quick = ['tags', ...(method_exists($this, 'workspaceQuickFilterNames') ? $this->workspaceQuickFilterNames() : [])];
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
        if (method_exists($this, 'removeNativeTableFilter')) {
            $this->removeNativeTableFilter($filterName, $field, $isRemovingAllFilters);
        } else {
            parent::removeTableFilter($filterName, $field, $isRemovingAllFilters);
        }
        if ($preservePending) {
            $this->tableDeferredFilters = [...$pending, $filterName => $this->tableFilters[$filterName]];
        }
    }

    public function tagFilterPreferencesAction(): Action
    {
        return Action::make('tagFilterPreferences')->label(__('tags.preferences'))->authorize('manage-catalog')
            ->fillForm(fn (): array => ['all' => $this->tableTagFilters === null, 'tags' => array_map(fn (string $tag): array => ['tag' => $tag], array_values(array_intersect($this->tableTagFilters ?? [], array_keys(Value::tagOptions()))))])
            ->schema([
                Toggle::make('all')->label(__('tags.all_filters'))->live(),
                Repeater::make('tags')->label(__('tags.chosen_filters'))->helperText(__('tags.preferences_help'))
                    ->visible(fn (Get $get): bool => ! $get('all'))->defaultItems(0)->reorderableWithButtons()
                    ->schema([Select::make('tag')->label(__('tags.tag'))->options(fn (): array => Value::tagOptions())->searchable()->required()->disableOptionsWhenSelectedInSiblingRepeaterItems()]),
            ])
            ->action(function (array $data): void {
                Gate::authorize('manage-catalog');
                $this->restoreTableBrowserProfile(['tagFilters' => ($data['all'] ?? false) ? null : array_column($data['tags'] ?? [], 'tag')]);
            });
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
        if (array_key_exists('tableTagFilters', $controls) && (is_array($controls['tableTagFilters']) || $controls['tableTagFilters'] === null)) {
            $this->tableTagFilters = $controls['tableTagFilters'];
        }
        foreach (['tableSearchScope', 'showSelection', 'showOrderControls'] as $property) {
            if (property_exists($this, $property) && isset($controls[$property]) && get_debug_type($controls[$property]) === get_debug_type($this->{$property})) {
                $this->{$property} = $controls[$property];
            }
        }
    }

    public function dehydrateInteractsWithScopedTableSearch(): void
    {
        $controls = ['tableTagFilters' => $this->tableTagFilters];
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
