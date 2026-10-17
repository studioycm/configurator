<?php

namespace App\Filament\Exports;

use App\Models\Attribute;
use Filament\Actions\Exports\ExportColumn;

class AttributeExporter extends CatalogExporter
{
    protected static ?string $model = Attribute::class;

    public static function getColumns(): array
    {
        return [
            static::importColumn(),
            static::textColumn('key')->label('key'),
            static::textColumn('label')->label('label'),
            ExportColumn::make('is_active')->label('is_active')->formatStateUsing(fn (bool $state): int => (int) $state),
            ...static::helperColumns(),
        ];
    }
}
