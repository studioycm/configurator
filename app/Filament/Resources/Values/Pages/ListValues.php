<?php

namespace App\Filament\Resources\Values\Pages;

use App\Filament\Resources\SplitListRecords;
use App\Filament\Resources\Values\Tables\ValuesTable;
use App\Filament\Resources\Values\ValueResource;
use Filament\Schemas\Schema;

class ListValues extends SplitListRecords
{
    protected static string $resource = ValueResource::class;

    protected bool $inlineCreationEnabled = true;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function inlineTagsForm(Schema $schema): Schema
    {
        return $schema->statePath('tableFilters.tags')->live()->components([
            ValuesTable::tagFilterField()->label('Tags')->hiddenLabel(),
        ]);
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
