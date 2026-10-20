<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

trait InteractsWithRecordPresentation
{
    #[Locked]
    public bool $inWorkspace = false;

    public function recordHeadingLevel(): int
    {
        if (method_exists($this, 'isItemDrawerEditor') && $this->isItemDrawerEditor()) {
            return 3;
        }

        return $this->inWorkspace ? 2 : 1;
    }

    public function getHeader(): ?View
    {
        return $this->recordHeadingLevel() === 1 ? parent::getHeader() : view('filament.resources.record-header');
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->rootHeadingLevel($this->recordHeadingLevel() + 1);
    }
}
