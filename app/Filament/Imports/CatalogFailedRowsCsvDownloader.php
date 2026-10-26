<?php

namespace App\Filament\Imports;

use App\Services\CatalogCsv;
use Filament\Actions\Imports\ContentGenerators\CsvImportFailureContentGenerator;
use Filament\Actions\Imports\Downloaders\Contracts\Downloader;
use Filament\Actions\Imports\Models\Import;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogFailedRowsCsvDownloader implements Downloader
{
    public function __invoke(Import $import): StreamedResponse
    {
        abort_unless($import->failedRows()->exists(), 404);
        $csv = app(CatalogCsv::class)->writer();
        app(CsvImportFailureContentGenerator::class)($import, $csv);

        return response()->streamDownload(function () use ($csv): void {
            foreach ($csv->chunk(8192) as $chunk) {
                echo $chunk;
            }
        }, __('filament-actions::import.failure_csv.file_name', ['import_id' => $import->getKey(), 'csv_name' => (string) str($import->file_name)->beforeLast('.')->remove('.')]).'.csv', ['Content-Type' => 'text/csv']);
    }
}
