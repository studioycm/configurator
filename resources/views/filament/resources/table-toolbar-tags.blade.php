@if ($this->getTable()->getFilter('tags') && $this instanceof \App\Filament\Resources\HasWorkspaceTable)
    <div class="catalog-inline-tag-filter" wire:key="{{ $this->getId() }}.inline-tags">
        @php($isAnd = ($this->tableFilters['tags']['operator'] ?? 'or') === 'and')
        <x-filament::icon-button
            wire:click="toggleTagFilterOperator"
            :icon="$isAnd ? 'heroicon-o-queue-list' : 'heroicon-o-funnel'"
            :color="$isAnd ? 'success' : 'primary'"
            :label="__($isAnd ? 'tags.match_all_tooltip' : 'tags.match_any_tooltip')"
            :tooltip="__($isAnd ? 'tags.match_all_tooltip' : 'tags.match_any_tooltip')"
            class="catalog-tag-operator"
        />
        {{ $this->inlineTagsForm }}
    </div>
@endif
@if (! $this->isTableReordering() && ($this instanceof \App\Filament\Resources\HasWorkspaceTable && array_diff($this->tableQuickFilterNames(), ['tags']) !== []) && $this->quickFiltersForm->getComponents())
    <div class="catalog-workspace-quick-filters" wire:key="{{ $this->getId() }}.quick-filters">
        {{ $this->quickFiltersForm }}
    </div>
@endif
