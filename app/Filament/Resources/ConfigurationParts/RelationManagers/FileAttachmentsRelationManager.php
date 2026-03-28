<?php

namespace App\Filament\Resources\ConfigurationParts\RelationManagers;

use App\Filament\Resources\FileAttachments\FileAttachmentResource;
use App\Filament\Resources\FileAttachments\Schemas\FileAttachmentForm;
use App\Filament\Resources\FileAttachments\Tables\FileAttachmentsTable;
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
