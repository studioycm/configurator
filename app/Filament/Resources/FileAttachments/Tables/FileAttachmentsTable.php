<?php

namespace App\Filament\Resources\FileAttachments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('file_path')
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
