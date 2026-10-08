@if ($this instanceof \App\Filament\Resources\Values\Pages\ListValues)
    <div class="catalog-inline-tag-filter" wire:key="{{ $this->getId() }}.inline-tags">
        {{ $this->inlineTagsForm }}
    </div>
@endif
