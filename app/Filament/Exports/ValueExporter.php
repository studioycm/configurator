<?php

namespace App\Filament\Exports;

use App\Models\Value;
use Filament\Actions\Exports\ExportColumn;

class ValueExporter extends CatalogExporter
{
    protected static ?string $model = Value::class;

    public static function getColumns(): array
    {
        return [
            static::importColumn(),
            ExportColumn::make('semantic_id')->label('semantic_id'),
            static::textColumn('label')->label('label'),
            static::textColumn('description')->label('description'),
            ExportColumn::make('tags')->label('tags')->state(fn (Value $record): array => $record->tags ?? [])->listAsJson(),
            ...static::helperColumns(),
        ];
    }
}
