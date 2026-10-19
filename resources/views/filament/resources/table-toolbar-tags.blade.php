@if ($this->getTable()->getFilter('tags') && method_exists($this, 'inlineTagsForm'))
    <div class="catalog-inline-tag-filter" wire:key="{{ $this->getId() }}.inline-tags">
        {{ $this->inlineTagsForm }}
    </div>
@endif
@if (! $this->isTableReordering() && method_exists($this, 'quickFiltersForm') && $this->quickFiltersForm->getComponents())
    <div class="catalog-workspace-quick-filters" wire:key="{{ $this->getId() }}.quick-filters">
        {{ $this->quickFiltersForm }}
    </div>
@endif
