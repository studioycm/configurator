<?php

namespace App\Filament;

use App\Models\Option;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Importer;

class CatalogDataActions
{
    /** @param class-string<Importer> $importer */
    public static function import(string $name, string $importer): ImportAction
    {
        return ImportAction::make($name)->importer($importer)->authorize('manage-catalog')
            ->modelLabel($importer::getModel() === Option::class ? __('data_exchange.attribute_value') : null)
            ->pluralModelLabel($importer::getModel() === Option::class ? __('data_exchange.attribute_values') : null)
            ->label(__('data_exchange.actions.import'));
    }

    /** @param class-string<Exporter> $exporter */
    public static function export(string $name, string $exporter, bool $all = false): ExportAction
    {
        return ExportAction::make($name)->exporter($exporter)->authorize('manage-catalog')
            ->modelLabel($exporter::getModel() === Option::class ? __('data_exchange.attribute_value') : null)
            ->pluralModelLabel($exporter::getModel() === Option::class ? __('data_exchange.attribute_values') : null)
            ->label(__($all ? 'data_exchange.actions.export_all' : 'data_exchange.actions.export'));
    }
}
