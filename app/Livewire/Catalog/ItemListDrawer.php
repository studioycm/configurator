<?php

namespace App\Livewire\Catalog;

use App\DTO\ItemListDefinition;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\InteractsWithRowOrdering;
use App\Filament\Resources\InteractsWithScopedTableSearch;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Options\Pages\EditOption;
use App\Filament\Resources\TablePresentation;
use App\Models\Group;
use App\Models\Option;
use App\Services\ItemListOrdering;
use App\Services\ItemLists;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ItemListDrawer extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithRowOrdering, InteractsWithScopedTableSearch, InteractsWithTable {
        InteractsWithRowOrdering::rowOrderingActions as protected baseRowOrderingActions;
        InteractsWithRowOrdering::toggleTableReordering insteadof InteractsWithTable;
        InteractsWithTable::filterTableQuery as protected filterNormalTableQuery;
        InteractsWithScopedTableSearch::applyGlobalSearchToTableQuery insteadof InteractsWithTable;
        InteractsWithScopedTableSearch::resetTableColumnManager insteadof InteractsWithTable;
    }
    use InteractsWithSchemas;

    #[Locked]
    public string $listKey;

    #[Locked]
    public int $parentId;

    #[Locked]
    public bool $allowLocalEditing = false;

    #[Locked]
    public ?string $selectedRecordId = null;

    public function mount(string $listKey, int $parentId, bool $allowLocalEditing = false): void
    {
        $this->listKey = $listKey;
        $this->parentId = $parentId;
        $this->allowLocalEditing = $allowLocalEditing;
        $this->definition();
    }

    private function definition(): ItemListDefinition
    {
        return app(ItemLists::class)->definition(auth()->user(), $this->listKey, $this->parentId);
    }

    public function table(Table $table): Table
    {
        $definition = $this->definition();
        $columns = [];
        foreach ($definition->columns as $field => $label) {
            $columns[] = TextColumn::make($field)->label($label)->wrap()->searchable()->toggleable();
        }
        if (is_array($definition->query)) {
            $table->records(function (int $page, int|string $recordsPerPage, ?string $search): LengthAwarePaginator|Collection {
                $definition = $this->definition();
                $all = collect($definition->query)->map(fn (array $row, string $key): array => ['key' => $key, ...$row]);
                if ($this->isTableReordering()) {
                    return $all;
                }
                $records = $all->filter(function (array $row) use ($search, $definition): bool {
                    if (blank($search)) {
                        return true;
                    }
                    $fields = $this->tableSearchScope === 'all' ? array_keys($definition->columns) : (array_key_exists($this->tableSearchScope, $definition->columns) ? [$this->tableSearchScope] : []);
                    foreach ($fields as $field) {
                        if (str_contains(mb_strtolower((string) data_get($row, $field)), mb_strtolower($search))) {
                            return true;
                        }
                    }

                    return false;
                });

                return new LengthAwarePaginator($records->forPage($page, (int) $recordsPerPage), $records->count(), (int) $recordsPerPage, $page);
            });
        } else {
            $table->query($definition->query)->defaultSort('id')->recordActions([Action::make('edit')->label($this->allowLocalEditing ? 'Open full editor' : 'Open editor')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Model $record): string => app(ItemLists::class)->recordUrl($record))->openUrlInNewTab()]);
        }

        if ($this->localEditorComponent($definition) !== null) {
            $table->recordAction('select')->recordUrl(null)
                ->recordClasses(fn (Model|array $record): ?string => $record instanceof Model && (string) $record->getKey() === $this->selectedRecordId ? 'catalog-selected-row' : null)
                ->recordActions([
                    Action::make('select')->label($this->listKey === 'group-card-properties' ? 'Edit Presentation' : 'Edit here')
                        ->icon('heroicon-o-pencil-square')->iconButton()->tooltip($this->listKey === 'group-card-properties' ? 'Edit Presentation' : 'Edit here')->authorize('manage-catalog')
                        ->extraAttributes(['data-item-editor-transition' => true])
                        ->action(fn (Model|array $record) => $this->selectRecord($this->getTableRecordKey($record))),
                    ...$table->getRecordActions(),
                ]);
        }

        $column = app(ItemListOrdering::class)->column($this->listKey);
        if ($column !== null) {
            $table->reorderable($column, direction: $this->listKey === 'configurator-rules' ? 'desc' : 'asc')
                ->defaultSort($column, $this->listKey === 'configurator-rules' ? 'desc' : 'asc')
                ->recordActions([
                    Action::make('moveUp')->label('Move up')->icon('heroicon-o-chevron-up')->iconButton()->authorize('manage-catalog')
                        ->visible(fn (): bool => $this->showOrderControls && ! $this->isTableReordering())
                        ->action(fn (Model|array $record) => $this->moveItem($this->getTableRecordKey($record), -1)),
                    Action::make('moveDown')->label('Move down')->icon('heroicon-o-chevron-down')->iconButton()->authorize('manage-catalog')
                        ->visible(fn (): bool => $this->showOrderControls && ! $this->isTableReordering())
                        ->action(fn (Model|array $record) => $this->moveItem($this->getTableRecordKey($record), 1)),
                    ...$table->getRecordActions(),
                ]);
        }

        $headerActions = $definition->fullListUrl ? [Action::make('fullList')->label('Open full list')->url($definition->fullListUrl)->openUrlInNewTab()] : [];
        if ($this->listKey === 'group-card-properties' && $this->localEditorComponent($definition) !== null) {
            array_unshift($headerActions, Action::make('editPresentation')->label('Edit Presentation')->icon('heroicon-o-pencil-square')->authorize('manage-catalog')
                ->extraAttributes(['data-item-editor-transition' => true])->action(fn () => $this->editPresentation()));
        }

        return TablePresentation::configure($table->heading($definition->title)->columns($columns)
            ->paginationPageOptions([10, 25, 50])
            ->headerActions($headerActions), (is_array($definition->query) ? 'array-' : 'items-').$this->listKey, true);
    }

    private function localEditorComponent(ItemListDefinition $definition): ?string
    {
        if (! $this->allowLocalEditing) {
            return null;
        }
        if ($this->listKey === 'group-card-properties') {
            return Group::query()->whereKey($this->parentId)->doesntHave('children')->exists() ? EditGroup::class : null;
        }
        if (! $definition->query instanceof Builder) {
            return null;
        }

        return match ($definition->query->getModel()::class) {
            Option::class => EditOption::class,
            Group::class => EditGroup::class,
            default => null,
        };
    }

    public function editorComponent(): ?string
    {
        return $this->selectedRecordId === null ? null : $this->localEditorComponent($this->definition());
    }

    public function selectRecord(string $record): void
    {
        $definition = $this->definition();
        abort_if($this->localEditorComponent($definition) === null, 404);
        if ($this->listKey === 'group-card-properties') {
            abort_unless(is_array($definition->query) && array_key_exists($record, $definition->query), 404);
            $this->editPresentation();

            return;
        }

        abort_unless($definition->query instanceof Builder, 404);
        $model = (clone $definition->query)->findOrFail($record);
        $resource = $model instanceof Option ? OptionResource::class : GroupResource::class;
        abort_unless($resource::canEdit($model), 403);
        $this->selectedRecordId = (string) $model->getKey();
        $this->dispatch('item-drawer-editor-opened')->self();
    }

    public function editPresentation(): void
    {
        $definition = $this->definition();
        abort_unless($this->listKey === 'group-card-properties' && $this->localEditorComponent($definition) !== null, 404);
        $group = Group::findOrFail($this->parentId);
        abort_unless(GroupResource::canEdit($group), 403);
        $this->selectedRecordId = (string) $group->getKey();
        $this->dispatch('item-drawer-editor-opened')->self();
    }

    #[On('item-drawer-editor-closed')]
    public function closeEditor(?string $listKey = null, ?int $parentId = null): void
    {
        Gate::authorize('manage-catalog');
        if (($listKey !== null && $listKey !== $this->listKey) || ($parentId !== null && $parentId !== $this->parentId)) {
            return;
        }
        $this->selectedRecordId = null;
    }

    #[On('item-drawer-record-saved')]
    public function refreshEditorRecord(string $listKey, int $parentId, string $record): void
    {
        if ($listKey !== $this->listKey || $parentId !== $this->parentId) {
            return;
        }
        $definition = $this->definition();
        if ($this->selectedRecordId === $record && $definition->query instanceof Builder && ! (clone $definition->query)->whereKey($record)->exists()) {
            $this->selectedRecordId = null;
        }
        $this->flushCachedTableRecords();
    }

    /** @return list<Action> */
    public function rowOrderingActions(): array
    {
        return app(ItemListOrdering::class)->column($this->listKey) === null ? [] : $this->baseRowOrderingActions();
    }

    public function filterTableQuery(Builder $query): Builder
    {
        if (! $this->isTableReordering()) {
            return $this->filterNormalTableQuery($query);
        }
        foreach ($this->getTable()->getVisibleColumns() as $column) {
            $column->applyRelationshipAggregates($query);
            $column->applyEagerLoading($query);
        }

        return $query;
    }

    public function moveItem(int|string $id, int $direction): void
    {
        Gate::authorize('manage-catalog');
        if (filled($this->tableSort)) {
            throw ValidationException::withMessages(['order' => 'Clear column sorting before moving rows.']);
        }
        app(ItemListOrdering::class)->move(auth()->user(), $this->listKey, $this->parentId, $id, $direction);
        $this->ordered();
    }

    /** @param list<int|string> $order */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        app(ItemListOrdering::class)->reorder(auth()->user(), $this->listKey, $this->parentId, $order);
        $this->ordered();
    }

    private function ordered(): void
    {
        $this->flushCachedTableRecords();
        $this->dispatch('catalog-record-saved');
        $this->dispatch('configurator-updated');
    }

    public function render(): View
    {
        $this->definition();

        return view('livewire.catalog.item-list-drawer');
    }
}
