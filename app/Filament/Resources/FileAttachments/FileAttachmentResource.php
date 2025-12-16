<?php

namespace App\Filament\Resources\FileAttachments;

use App\Filament\Resources\FileAttachments\Pages\CreateFileAttachment;
use App\Filament\Resources\FileAttachments\Pages\EditFileAttachment;
use App\Filament\Resources\FileAttachments\Pages\ListFileAttachments;
use App\Filament\Resources\FileAttachments\Schemas\FileAttachmentForm;
use App\Filament\Resources\FileAttachments\Tables\FileAttachmentsTable;
use App\Models\FileAttachment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FileAttachmentResource extends Resource
{
    protected static ?string $model = FileAttachment::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Assets';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FileAttachmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FileAttachmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFileAttachments::route('/'),
            'create' => CreateFileAttachment::route('/create'),
            'edit' => EditFileAttachment::route('/{record}/edit'),
        ];
    }
}
