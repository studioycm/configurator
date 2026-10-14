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
                ->label(fn (): string => $this->showOrderControls ? __('admin.ordering.labels.hide_updown_controls') : __('admin.ordering.labels.show_updown_controls'))
                ->tooltip(fn (): string => $this->showOrderControls ? __('admin.ordering.help.hide_updown_controls') : __('admin.ordering.help.show_updown_controls'))
                ->visible(fn (): bool => ! $this->isTableReordering())
                ->action(fn () => $this->toggleOrderControls()),
            Action::make('dragOrder')->authorize('manage-catalog')->iconButton()
                ->icon(fn (): Heroicon => $this->isTableReordering() ? Heroicon::OutlinedCheck : Heroicon::OutlinedBars3)
                ->label(fn (): string => $this->isTableReordering() ? __('admin.ordering.labels.done_reordering') : __('admin.ordering.labels.drag_to_reorder'))
                ->tooltip(fn (): string => $this->isTableReordering() ? __('admin.ordering.help.done_resume_search_and_filters') : __('admin.ordering.help.drag_the_complete_ordered_list'))
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
