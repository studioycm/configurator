<?php

namespace App\Filament\Resources\Values\Pages;

use App\Filament\CatalogDataActions;
use App\Filament\Exports\ValueExporter;
use App\Filament\Imports\ValueImporter;
use App\Filament\Pages\ManageTags;
use App\Filament\Resources\SplitListRecords;
use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Filament\Resources\Values\ValueResource;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class ListValues extends SplitListRecords
{
    protected static string $resource = ValueResource::class;

    protected bool $inlineCreationEnabled = true;

    protected function getHeaderActions(): array
    {
        return [Action::make('manageTags')->label(__('tags.manage'))->url(ManageTags::getUrl()), $this->tagFilterPreferencesAction(), CatalogDataActions::import('import', ValueImporter::class), CatalogDataActions::export('export', ValueExporter::class)];
    }

    public function inlineTagsForm(Schema $schema): Schema
    {
        return $schema->statePath('tableFilters.tags')->live()->components([
            ValuesTable::tagFilterField()->options(fn (): array => $this->personalTagFilterOptions())->label(__('catalog.fields.tags'))->hiddenLabel(),
        ]);
    }

    #[On('catalog-tags-updated')]
    #[On('catalog-batch-applied')]
    public function refreshTagFilters(): void
    {
        $this->flushCachedTableRecords();
    }

    public function updatedTableFilters(mixed $value = null, ?string $key = null): void
    {
        if ($key === 'tags' || str_starts_with($key ?? '', 'tags.')) {
            $this->tableDeferredFilters['tags'] = $this->tableFilters['tags'];
            $this->handleTableFilterUpdates();

            return;
        }

        parent::updatedTableFilters();
    }
}
