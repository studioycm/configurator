<?php

namespace App\Filament\Resources\Configurators\Concerns;

use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait InteractsWithConfiguratorTable
{
    #[Locked]
    public bool $showOrderControls = true;

    #[Locked]
    public bool $showSelection = false;

    /** @var Collection<int, Model>|null */
    protected ?Collection $orderedWorkspaceRows = null;

    public function unmountAction(bool|string|null $cancelParentActions = null): void
    {
        Gate::authorize('manage-catalog');
        parent::unmountAction($cancelParentActions);
        foreach ($this->getErrorBag()->keys() as $path) {
            if (preg_match('/^mountedActions\.(\d+)\./', $path, $matches) && (int) $matches[1] >= count($this->mountedActions)) {
                $this->resetValidation($path);
            }
        }
    }

    public function toggleTableReordering(): void
    {
        Gate::authorize('manage-catalog');
        $this->showOrderControls = ! $this->showOrderControls;
    }

    public function toggleWorkspaceSelection(): void
    {
        Gate::authorize('manage-catalog');
        if ($this->showSelection) {
            foreach ($this->getMountedActions() as $action) {
                if ($action instanceof BulkAction) {
                    throw ValidationException::withMessages(['selection' => 'Finish or cancel the open batch review before hiding selection.']);
                }
            }
            $this->selectedTableRecords = [];
            $this->deselectedTableRecords = [];
            $this->isTrackingDeselectedTableRecords = false;
            unset($this->cachedSelectedTableRecords);
            $this->deselectAllTableRecords();
        }
        $this->showSelection = ! $this->showSelection;
    }

    /** @return list<Action> */
    public function workspaceVisibilityActions(): array
    {
        return [
            Action::make('reorderRows')->authorize('manage-catalog')->icon(Heroicon::OutlinedArrowsUpDown)
                ->label(fn (): string => $this->showOrderControls ? 'Reorder rows: hide controls' : 'Reorder rows: show controls')
                ->action(fn () => $this->toggleTableReordering()),
            Action::make('toggleSelection')->authorize('manage-catalog')->icon(Heroicon::OutlinedCheckCircle)
                ->label(fn (): string => $this->showSelection ? 'Hide selection' : 'Show selection')
                ->action(fn () => $this->toggleWorkspaceSelection()),
        ];
    }

    /** @param Closure(int, int): void $move @return list<Action> */
    protected function workspaceOrderActions(Closure $move): array
    {
        return array_map(fn (int $direction): Action => Action::make($direction === -1 ? 'moveUp' : 'moveDown')
            ->label($direction === -1 ? 'Move up' : 'Move down')->authorize('manage-catalog')
            ->icon($direction === -1 ? Heroicon::OutlinedArrowUp : Heroicon::OutlinedArrowDown)->iconButton()
            ->visible(fn (): bool => $this->showOrderControls)
            ->disabled(fn (Model $record): bool => ! $this->usesWorkspaceOrder() || $this->workspaceNeighbor($record, $direction) === null)
            ->tooltip(function (Model $record) use ($direction): string {
                if (! $this->usesWorkspaceOrder()) {
                    return 'Restore the default order to move rows.';
                }
                $neighbor = $this->workspaceNeighbor($record, $direction);

                return $neighbor === null ? ($direction === -1 ? 'Already first' : 'Already last')
                    : ($direction === -1 ? 'Move before ' : 'Move after ').$this->workspaceRowLabel($neighbor);
            })
            ->action(fn (Model $record) => $move((int) $record->getKey(), $direction)), [-1, 1]);
    }

    protected function assertWorkspaceOrder(): void
    {
        Gate::authorize('manage-catalog');
        if (! $this->usesWorkspaceOrder()) {
            throw ValidationException::withMessages(['order' => 'Restore the default order to move rows.']);
        }
    }

    private function usesWorkspaceOrder(): bool
    {
        return blank($this->tableSort) || ($this->getTableSortColumn() === $this->getTable()->getReorderColumn()
            && $this->getTableSortDirection() === $this->getTable()->getReorderDirection());
    }

    private function workspaceNeighbor(Model $record, int $direction): ?Model
    {
        $this->orderedWorkspaceRows ??= $this->getRelationship()->getQuery()
            ->with(match (true) {
                $record instanceof ConfiguratorAttribute => ['attribute'],
                $record instanceof ConfiguratorOption => ['option.value'],
                default => [],
            })->orderBy($this->getTable()->getReorderColumn(), $this->getTable()->getReorderDirection())->get();
        $index = $this->orderedWorkspaceRows->search(fn (Model $row): bool => $row->getKey() === $record->getKey());

        return $index === false ? null : $this->orderedWorkspaceRows->get($index + $direction);
    }

    private function workspaceRowLabel(Model $record): string
    {
        return match (true) {
            $record instanceof ConfiguratorAttribute => $record->label_override ?? $record->attribute->label,
            $record instanceof ConfiguratorOption => $record->label_override ?? $record->option->value->label,
            $record instanceof ConfiguratorRule => $record->label,
            default => '#'.$record->getKey(),
        };
    }

    /** @return list<string> */
    protected function workspaceQuickFilterNames(): array
    {
        return match (static::getRelationshipName()) {
            'rules' => ['kind'],
            'options' => ['tags'],
            default => [],
        };
    }

    public function quickFiltersForm(Schema $schema): Schema
    {
        return $schema->statePath('tableFilters')->live()->columns(1)->components(fn (): array => collect($this->workspaceQuickFilterNames())->map(fn (string $name) => $this->getTable()->getFilter($name))
            ->filter()->map(fn ($filter) => Group::make(array_map(fn (Field $field): Field => (clone $field)->hiddenLabel(), $filter->getSchemaComponents()))->key('workspace-'.$this->getId().'-'.$filter->getName())->statePath($filter->getName()))->all()
        );
    }

    public function updatedTableFilters(mixed $value = null, ?string $key = null): void
    {
        $name = explode('.', $key ?? '')[0];
        if (in_array($name, $this->workspaceQuickFilterNames(), true)) {
            $this->tableDeferredFilters[$name] = $this->tableFilters[$name];
            $this->handleTableFilterUpdates();

            return;
        }
        parent::updatedTableFilters();
    }

    public function removeTableFilter(string $filterName, ?string $field = null, bool $isRemovingAllFilters = false): void
    {
        if ($isRemovingAllFilters || ! $this->getTable()->hasDeferredFilters()) {
            parent::removeTableFilter($filterName, $field, $isRemovingAllFilters);

            return;
        }
        $pending = $this->tableDeferredFilters;
        $this->tableDeferredFilters = $this->tableFilters;
        parent::removeTableFilter($filterName, $field);
        $this->tableDeferredFilters = [...$pending, $filterName => $this->tableFilters[$filterName]];
    }
}
