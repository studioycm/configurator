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
use Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\FileAttachmentResource;
use Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\Schemas\FileAttachmentForm;
use Tests\Fixtures\Legacy\Filament\Resources\FileAttachments\Tables\FileAttachmentsTable;

class FileAttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'fileAttachments';

    protected static ?string $relatedResource = FileAttachmentResource::class;

    public function form(Schema $schema): Schema
    {
        return FileAttachmentForm::configure($schema, hideAttachable: true);
    }

    public function table(Table $table): Table
    {
        return FileAttachmentsTable::configure($table)
            ->recordTitleAttribute('title')
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
