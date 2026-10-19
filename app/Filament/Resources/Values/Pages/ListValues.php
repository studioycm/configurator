<?php

namespace App\Filament\Resources\Values\Pages;

use App\Filament\CatalogDataActions;
use App\Filament\Exports\ValueExporter;
use App\Filament\Imports\ValueImporter;
use App\Filament\Pages\ManageTags;
use App\Filament\Resources\SplitListRecords;
use App\Filament\Resources\Values\ValueResource;
use Filament\Actions\Action;
use Livewire\Attributes\On;

class ListValues extends SplitListRecords
{
    protected static string $resource = ValueResource::class;

    protected bool $inlineCreationEnabled = true;

    protected function getHeaderActions(): array
    {
        return [Action::make('manageTags')->label(__('tags.manage'))->url(ManageTags::getUrl()), CatalogDataActions::import('import', ValueImporter::class), CatalogDataActions::export('export', ValueExporter::class)];
    }

    #[On('catalog-tags-updated')]
    #[On('catalog-batch-applied')]
    public function refreshTagFilters(): void
    {
        $this->flushCachedTableRecords();
    }
}
