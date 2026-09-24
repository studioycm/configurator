<?php

namespace Tests\Fixtures\Legacy\Filament\Resources\CatalogGroups\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\ProductProfileResource;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Schemas\ProductProfileForm;
use Tests\Fixtures\Legacy\Filament\Resources\ProductProfiles\Tables\ProductProfilesTable;

class ProductProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'productProfiles';

    protected static ?string $relatedResource = ProductProfileResource::class;

    public function form(Schema $schema): Schema
    {
        return ProductProfileForm::configure($schema, hideCatalogGroup: true);
    }

    public function table(Table $table): Table
    {
        return ProductProfilesTable::configure($table)
            ->recordTitleAttribute('name')
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
