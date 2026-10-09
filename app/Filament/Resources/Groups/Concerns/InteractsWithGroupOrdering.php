<?php

namespace App\Filament\Resources\Groups\Concerns;

use App\Actions\ReorderCatalogGroups;
use App\Filament\Resources\InteractsWithRowOrdering;
use App\Models\Group;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait InteractsWithGroupOrdering
{
    use InteractsWithRowOrdering {
        filterTableQuery as filterOrderedRows;
        rowOrderingActions as defaultRowOrderingActions;
    }

    #[Locked]
    public ?int $reorderParentId = null;

    /** @var Collection<int, Group>|null */
    protected ?Collection $groupOrderRows = null;

    /** @return list<Action> */
    public function rowOrderingActions(): array
    {
        $actions = $this->defaultRowOrderingActions();
        $actions[1]->modal(fn (): bool => ! $this->isTableReordering())->modalHeading('Order Groups under one parent')->modalSubmitActionLabel('Start reordering')
            ->schema(fn (): array => $this->isTableReordering() ? [] : [Select::make('parent')->label('Parent')->required()->searchable()
                ->options(fn (): array => ['root' => 'Root Groups'] + Group::query()->has('children')->orderBy('name')->pluck('name', 'id')->all())->default('root')])
            ->action(function (array $data): void {
                if ($this->isTableReordering()) {
                    $this->toggleTableReordering();

                    return;
                }
                $this->beginGroupReordering(($data['parent'] ?? 'root') === 'root' ? null : (int) $data['parent']);
            });

        return $actions;
    }

    public function beginGroupReordering(?int $parentId): void
    {
        Gate::authorize('manage-catalog');
        abort_if($parentId !== null && ! Group::query()->whereKey($parentId)->exists(), 404);
        $this->reorderParentId = $parentId;
        $this->isTableReordering = true;
        $this->flushCachedTableRecords();
    }

    public function filterTableQuery(Builder $query): Builder
    {
        $query = $this->filterOrderedRows($query);

        return $this->isTableReordering() ? $query->where('parent_id', $this->reorderParentId) : $query;
    }

    /** @param list<int|string> $order */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        Gate::authorize('manage-catalog');
        abort_unless($this->isTableReordering(), 403);
        app(ReorderCatalogGroups::class)->handle(auth()->user(), $this->reorderParentId, $order);
        $this->groupOrderRows = null;
        $this->flushCachedTableRecords();
    }

    public function moveGroup(int $id, int $direction): void
    {
        Gate::authorize('manage-catalog');
        if (! $this->usesGroupOrder()) {
            throw ValidationException::withMessages(['order' => 'Restore the default sort order to move Groups.']);
        }
        app(ReorderCatalogGroups::class)->move(auth()->user(), $id, $direction);
        $this->groupOrderRows = null;
        $this->flushCachedTableRecords();
    }

    public function usesGroupOrder(): bool
    {
        return blank($this->tableSort) || $this->tableSort === 'sort_order:asc';
    }

    public function groupNeighbor(Group $record, int $direction): ?Group
    {
        $this->groupOrderRows ??= Group::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id', 'name', 'sort_order']);
        $siblings = $this->groupOrderRows->where('parent_id', $record->parent_id)->values();
        $index = $siblings->search(fn (Group $group): bool => $group->id === $record->id);

        return $index === false ? null : $siblings->get($index + $direction);
    }
}
