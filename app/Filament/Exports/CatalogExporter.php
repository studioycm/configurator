<?php

namespace App\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

abstract class CatalogExporter extends Exporter
{
    protected static function textColumn(string $name): ExportColumn
    {
        return ExportColumn::make($name)
            ->formatStateUsing(fn (mixed $state): mixed => is_string($state) && preg_match("/\\A'+[=+\\-@\\t\\r]/", $state) ? "'".$state : $state)
            ->preventFormulaInjection();
    }

    protected static function importColumn(): ExportColumn
    {
        return ExportColumn::make('import')->label('import')->state(1);
    }

    /** @return list<ExportColumn> */
    protected static function helperColumns(): array
    {
        return [
            ExportColumn::make('id')->label('helper_record_id')->enabledByDefault(false),
            ExportColumn::make('created_at')->label('helper_created_at')->enabledByDefault(false),
            ExportColumn::make('updated_at')->label('helper_updated_at')->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return __('data_exchange.notifications.export_completed', [
            'successful' => number_format($export->successful_rows),
            'failed' => number_format($export->getFailedRowsCount()),
        ]);
    }
}
