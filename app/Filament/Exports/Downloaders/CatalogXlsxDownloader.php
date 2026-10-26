<?php

namespace App\Filament\Exports\Downloaders;

use App\Filament\Exports\CatalogExportFiles;
use Filament\Actions\Exports\Downloaders\Contracts\Downloader;
use Filament\Actions\Exports\Models\Export;
use Symfony\Component\HttpFoundation\Response;

class CatalogXlsxDownloader implements Downloader
{
    public function __invoke(Export $export): Response
    {
        $files = new CatalogExportFiles($export);
        $state = $files->assessment()['xlsx'];
        abort_unless($state['download'], 409, __('data_exchange.notifications.export_unconfirmed'));
        $response = $export->getFileDisk()->download($files->workbookPath());
        $response->headers->set('X-Catalog-Artifact-State', $state['state'] === 'historical' ? 'historical-not-reverified' : 'verified');

        return $response;
    }
}
