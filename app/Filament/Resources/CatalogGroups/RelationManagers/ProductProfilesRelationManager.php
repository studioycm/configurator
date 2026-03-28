<?php

namespace App\Filament\Resources\CatalogGroups\RelationManagers;

use App\Filament\Resources\ProductProfiles\ProductProfileResource;
use App\Filament\Resources\ProductProfiles\Schemas\ProductProfileForm;
use App\Filament\Resources\ProductProfiles\Tables\ProductProfilesTable;
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
