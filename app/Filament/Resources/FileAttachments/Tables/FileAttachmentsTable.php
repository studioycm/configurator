<?php

namespace App\Filament\Resources\FileAttachments\Tables;

use App\Models\CatalogGroup;
use App\Models\ConfigurationPart;
use App\Models\FileAttachment;
use App\Models\Part;
use App\Models\ProductConfiguration;
use App\Models\ProductProfile;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FileAttachmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attachable_type')
                    ->label('Attached To')
                    ->formatStateUsing(fn ($state, $record) => class_basename($record->attachable_type))
                    ->sortable(),
                TextColumn::make('attachable_display')
                    ->label('Name')
                    ->state(function ($record): string {
                        $attachable = $record->attachable;

                        return (string) (
                            $attachable?->name
                            ?? $attachable?->label
                            ?? $attachable?->title
                            ?? ($record->attachable_type ? class_basename($record->attachable_type) : '—').' #'.(string) ($record->attachable_id ?? '—')
                        );
                    }),
                SpatieMediaLibraryImageColumn::make('file_path')
                    ->label('Image')
                    ->tooltip(fn ($record): string => $record->file_path)
                    ->allCollections(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('file_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('mime_type')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_primary')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make('editFile')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->label('')
                    ->tooltip('Replace file')
                    ->modalHeading(fn (FileAttachment $record): string => 'Replace file for '.self::contextLabel($record))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('file_path')
                            ->collection('default')
                            ->disk(config('media-library.disk_name', 'public'))
                            ->visibility('public')
                            ->required(),
                    ])
                    ->action(function (FileAttachment $record, array $data): void {
                        $record->clearMediaCollection('default');

                        if (! empty($data['file_path'])) {
                            $record
                                ->addFromMediaLibraryRequest(['file_path'])
                                ->toMediaCollection('default');
                        }

                        $record->save();
                    }),
                EditAction::make('editAll')
                    ->icon('heroicon-o-pencil-square')
                    ->label('')
                    ->tooltip('Edit all fields')
                    ->modalHeading(fn (FileAttachment $record): string => 'Edit file for '.self::contextLabel($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function contextLabel(FileAttachment $record): string
    {
        $attachable = $record->attachable;

        if ($attachable instanceof ProductConfiguration) {
            $product = $attachable->productProfile;
            $group = $product?->catalogGroup;

            return collect([
                $attachable->name ?? $attachable->configuration_code,
                $product?->short_label ?? $product?->name ?? $product?->product_code,
                $group?->name,
            ])->filter()->implode(' • ');
        }

        if ($attachable instanceof ProductProfile) {
            $group = $attachable->catalogGroup;

            return collect([
                $attachable->short_label ?? $attachable->name ?? $attachable->product_code,
                $group?->name,
            ])->filter()->implode(' • ');
        }

        if ($attachable instanceof ConfigurationPart) {
            $productConfiguration = $attachable->productConfiguration;
            $product = $productConfiguration?->productProfile;
            $group = $product?->catalogGroup;

            return collect([
                $attachable->label ?? $attachable->part?->name,
                $productConfiguration?->name ?? $productConfiguration?->configuration_code,
                $product?->short_label ?? $product?->product_code,
                $group?->name,
            ])->filter()->implode(' • ');
        }

        if ($attachable instanceof Part) {
            return $attachable->name ?? 'Part';
        }

        if ($attachable instanceof CatalogGroup) {
            return $attachable->name ?? 'Catalog Group';
        }

        return $record->title ?? 'File Attachment';
    }
}
