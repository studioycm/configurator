<?php

namespace App\Filament\Exports;

use App\Models\Option;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

class AttributeValueExporter extends CatalogExporter
{
    protected static ?string $model = Option::class;

    public static function getColumns(): array
    {
        return [
            static::importColumn(),
            static::textColumn('attribute.key')->label('attribute_key'),
            ExportColumn::make('value.semantic_id')->label('value_semantic_id'),
            ExportColumn::make('code')->label('code'),
            ExportColumn::make('is_active')->label('is_active')->formatStateUsing(fn (bool $state): int => (int) $state),
            ExportColumn::make('is_hidden')->label('is_hidden')->formatStateUsing(fn (bool $state): int => (int) $state),
            static::textColumn('attribute.label')->label('helper_attribute_label')->enabledByDefault(false),
            static::textColumn('value.label')->label('helper_value_label')->enabledByDefault(false),
            ...static::helperColumns(),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['attribute', 'value']);
    }
}
