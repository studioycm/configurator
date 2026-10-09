<?php

namespace App\Filament\Resources;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

trait InteractsWithRowOrdering
{
    #[Locked]
    public bool $showOrderControls = true;

    public function toggleOrderControls(): void
    {
        Gate::authorize('manage-catalog');
        $this->showOrderControls = ! $this->showOrderControls;
    }

    public function toggleTableReordering(): void
    {
        Gate::authorize('manage-catalog');
        abort_unless($this->getTable()->isReorderable(), 403);
        $this->isTableReordering = ! $this->isTableReordering;
        $this->flushCachedTableRecords();
    }

    /** @return list<Action> */
    public function rowOrderingActions(): array
    {
        return [
            Action::make('reorderRows')->authorize('manage-catalog')->iconButton()->icon(Heroicon::OutlinedArrowsUpDown)
                ->label(fn (): string => $this->showOrderControls ? 'Hide up/down controls' : 'Show up/down controls')
                ->tooltip(fn (): string => $this->showOrderControls ? 'Hide up/down controls' : 'Show up/down controls')
                ->visible(fn (): bool => ! $this->isTableReordering())
                ->action(fn () => $this->toggleOrderControls()),
            Action::make('dragOrder')->authorize('manage-catalog')->iconButton()
                ->icon(fn (): Heroicon => $this->isTableReordering() ? Heroicon::OutlinedCheck : Heroicon::OutlinedBars3)
                ->label(fn (): string => $this->isTableReordering() ? 'Done reordering' : 'Drag to reorder')
                ->tooltip(fn (): string => $this->isTableReordering() ? 'Done: resume search and filters' : 'Drag the complete ordered list')
                ->action(fn () => $this->toggleTableReordering()),
        ];
    }

    public function filterTableQuery(Builder $query): Builder
    {
        if (! $this->isTableReordering()) {
            return parent::filterTableQuery($query);
        }
        foreach ($this->getTable()->getVisibleColumns() as $column) {
            $column->applyRelationshipAggregates($query);
            $column->applyEagerLoading($query);
        }

        return $query;
    }
}
