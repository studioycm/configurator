<?php

namespace App\Filament\Forms\Components;

use App\Livewire\Catalog\WorkspaceTablePicker;
use Filament\Forms\Components\TableSelect;
use Filament\Support\View\ComponentAttributeBag;
use Livewire\Livewire;

class WorkspaceTableSelect extends TableSelect
{
    protected bool $shouldSelectRowsByClick = false;

    public function selectRowsByClick(bool $condition = true): static
    {
        $this->shouldSelectRowsByClick = $condition;

        return $this;
    }

    public function toEmbeddedHtml(): string
    {
        $properties = [
            'isDisabled' => $this->isDisabled(),
            'maxSelectableRecords' => $this->getMaxItems(),
            'model' => $this->getModel(),
            'record' => $this->getRecord(),
            'relationshipName' => $this->getRelationshipName(),
            'shouldIgnoreRelatedRecords' => $this->shouldIgnoreRelatedRecords(),
            'tableConfiguration' => base64_encode($this->getTableConfiguration()),
            'tableArguments' => $this->getTableArguments(),
            $this->applyStateBindingModifiers('wire:model') => $this->getStatePath(),
        ];
        $attributes = (new ComponentAttributeBag)->merge([
            'aria-labelledby' => $this->getId().'-label',
            'id' => $this->getId(),
            'role' => 'group',
        ], escape: false)->merge($this->getExtraAttributes(), escape: false);

        if ($this->shouldSelectRowsByClick) {
            $attributes = $attributes->merge(['data-workspace-row-selection' => true, 'x-data' => 'workspaceTableSelection()', 'x-on:click' => 'toggle($event)'], escape: false);
        }

        return $this->wrapEmbeddedHtml('<div '.$attributes->toHtml().'>'.Livewire::mount(WorkspaceTablePicker::class, $properties, $this->getLivewireKey()).'</div>', labelTag: 'div');
    }
}
