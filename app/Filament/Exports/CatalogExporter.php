<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Downloaders\CatalogCsvDownloader;
use App\Filament\Exports\Downloaders\CatalogXlsxDownloader;
use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Enums\Contracts\ExportFormat as ExportFormatInterface;
use Filament\Actions\Exports\Enums\ExportFormat;
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
        return static::completionBody((new CatalogExportFiles($export))->assessment(), $export->getOptions()['_catalog_formats'] ?? []);
    }

    /** @param array{planned:int,successful:int,rejected:int,unconfirmed:int,csv:array{state:string},xlsx:array{state:string}} $assessment @param list<string> $formats */
    public static function completionBody(array $assessment, array $formats): string
    {
        $body = __('data_exchange.notifications.export_results', array_intersect_key($assessment, array_flip(['planned', 'successful', 'rejected', 'unconfirmed'])));
        foreach (array_intersect($formats, ['csv', 'xlsx']) as $format) {
            $body .= ' '.strtoupper($format).': '.__('data_exchange.artifact_states.'.$assessment[$format]['state']).'.';
        }

        return $body;
    }

    public static function getDownloader(ExportFormatInterface $format): Downloader
    {
        return match ($format) {
            ExportFormat::Csv => app(CatalogCsvDownloader::class),
            ExportFormat::Xlsx => app(CatalogXlsxDownloader::class),
            default => parent::getDownloader($format),
        };
    }
}
