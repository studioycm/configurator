<?php

namespace App\Filament\Exports\Downloaders;

use App\Filament\Exports\ConfiguratorRuleExportFiles;
use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Downloaders\CsvDownloader;
use Filament\Actions\Exports\Models\Export;
use Symfony\Component\HttpFoundation\Response;

class ConfiguratorRulesCsvDownloader implements Downloader
{
    public function __invoke(Export $export): Response
    {
        if (! (new ConfiguratorRuleExportFiles($export))->ready()) {
            return response(__('data_exchange.rules.export_pending'), 409, ['Retry-After' => '5']);
        }

        return app(CsvDownloader::class)($export);
    }
}
