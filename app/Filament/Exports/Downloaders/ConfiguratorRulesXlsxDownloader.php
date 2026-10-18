<?php

namespace App\Filament\Exports\Downloaders;

use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Downloaders\XlsxDownloader;
use Filament\Actions\Exports\Models\Export;
use Symfony\Component\HttpFoundation\Response;

class ConfiguratorRulesXlsxDownloader implements Downloader
{
    public function __invoke(Export $export): Response
    {
        $path = $export->getFileDirectory().DIRECTORY_SEPARATOR.$export->file_name.'.xlsx';
        if (! $export->getFileDisk()->exists($path)) {
            return response(__('data_exchange.rules.workbook_pending'), 409, ['Retry-After' => '5']);
        }

        return app(XlsxDownloader::class)($export);
    }
}
