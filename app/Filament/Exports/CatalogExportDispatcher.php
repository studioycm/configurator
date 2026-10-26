<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Jobs\CompleteCatalogExport;
use App\Filament\Exports\Jobs\CreateCatalogXlsxFile;
use App\Filament\Exports\Jobs\PrepareCatalogCsvExport;
use App\Services\CatalogDataExchangePolicy;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportDispatcher;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class CatalogExportDispatcher extends ExportDispatcher
{
    public function dispatch(Export $export, string $serializedQuery, array $columnMap, array $options, array $formats, ?array $records, string $job, int $chunkSize, ?string $jobQueue, ?string $jobConnection, ?string $jobBatchName, string $authGuard): void
    {
        if (! in_array($export->exporter, app(CatalogDataExchangePolicy::class)->canonicalExporters(), true)) {
            parent::dispatch($export, $serializedQuery, $columnMap, $options, $formats, $records, $job, $chunkSize, $jobQueue, $jobConnection, $jobBatchName, $authGuard);

            return;
        }
        if ((int) $export->catalog_artifact_version !== CatalogDataExchangePolicy::VERSION) {
            throw new RuntimeException('Do not replace a historical or unsupported export. Create a new export instead.');
        }
        $options['_catalog_formats'] = array_map(fn ($format): string => $format->value, $formats);
        $prepare = new PrepareCatalogCsvExport($export, $serializedQuery, $columnMap, $options, $chunkSize, $records);
        $batch = Bus::batch([$prepare])->allowFailures();
        if ($jobQueue !== null) {
            $batch->onQueue($jobQueue);
        }
        if ($jobConnection !== null) {
            $batch->onConnection($jobConnection);
        }
        if ($jobBatchName !== null) {
            $batch->name($jobBatchName);
        }
        $completion = fn (): CompleteCatalogExport => new CompleteCatalogExport($export, $columnMap, $formats, $options, $authGuard);
        $jobs = [$batch, $completion()];
        if (in_array(ExportFormat::Xlsx, $formats, true)) {
            $jobs[] = new CreateCatalogXlsxFile($export, $columnMap, $options);
            $jobs[] = $completion();
        }
        $chain = Bus::chain($jobs);
        if ($jobQueue !== null) {
            $chain->onQueue($jobQueue);
        }
        if ($jobConnection !== null) {
            $chain->onConnection($jobConnection);
        }
        $chain->dispatch();
    }
}
