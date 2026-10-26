<?php

namespace App\Filament\Exports\Downloaders;

use App\Filament\Exports\CatalogExportFiles;
use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Downloaders\CsvDownloader;
use Filament\Actions\Exports\Models\Export;
use Symfony\Component\HttpFoundation\Response;

class CatalogCsvDownloader implements Downloader
{
    public function __invoke(Export $export): Response
    {
        $files = new CatalogExportFiles($export);
        $state = $files->assessment()['csv'];
        abort_unless($state['download'], 409, __('data_exchange.notifications.export_unconfirmed'));
        if ($state['state'] === 'historical') {
            $response = app(CsvDownloader::class)($export);
        } else {
            $response = response()->streamDownload(function () use ($files): void {
                foreach ($files->csvChunks() as $chunk) {
                    echo $chunk;
                }
            }, $export->file_name.'.csv', ['Content-Type' => 'text/csv']);
        }
        $response->headers->set('X-Catalog-Artifact-State', $state['state'] === 'historical' ? 'historical-not-reverified' : 'verified');

        return $response;
    }
}
