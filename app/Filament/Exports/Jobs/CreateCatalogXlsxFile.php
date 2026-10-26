<?php

namespace App\Filament\Exports\Jobs;

use App\Filament\Exports\CatalogExportFiles;
use App\Services\CatalogCsv;
use App\Services\CatalogDataExchangePolicy;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class CreateCatalogXlsxFile extends CreateXlsxFile implements ShouldQueueAfterCommit
{
    public function handle(): void
    {
        Gate::forUser($this->export->user()->firstOrFail())->authorize('manage-catalog');
        if ((int) $this->export->fresh()->catalog_artifact_version !== CatalogDataExchangePolicy::VERSION) {
            throw new RuntimeException('Unsupported managed export version.');
        }
        if (DB::transactionLevel() !== 0) {
            DB::afterCommit(fn () => $this->handle());

            return;
        }
        $files = new CatalogExportFiles($this->export);
        $temporary = null;
        $stream = null;
        try {
            if ($files->reuseWorkbook()) {
                return;
            }
            $csvState = $files->assessment()['csv']['state'];
            if ($csvState !== 'ready') {
                if ($csvState !== 'no-data') {
                    $files->workbookFailed();
                }

                return;
            }
            $stream = tmpfile();
            if ($stream === false) {
                throw new RuntimeException('Cannot stage workbook input.');
            }
            foreach ($files->csvChunks() as $chunk) {
                if (fwrite($stream, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Incomplete workbook input.');
                }
            }
            rewind($stream);
            $csv = app(CatalogCsv::class)->reader($stream, $this->exporter::getCsvDelimiter());
            app(CatalogCsv::class)->validate($csv);
            $temporary = tempnam(sys_get_temp_dir(), 'catalog-xlsx-');
            if ($temporary === false) {
                throw new RuntimeException('Cannot stage workbook.');
            }
            $writer = app(Writer::class, ['options' => $this->exporter->getXlsxWriterOptions()]);
            $writer->openToFile($temporary);
            $this->exporter->configureXlsxWriterAfterOpen($writer);
            $rows = 0;
            try {
                foreach ($csv->getRecords() as $index => $values) {
                    $writer->addRow($index === 0 ? $this->exporter->makeXlsxHeaderRow($values, $this->exporter->getXlsxHeaderCellStyle() ?? $this->exporter->getXlsxCellStyle()) : $this->exporter->makeXlsxRow($values, $this->exporter->getXlsxCellStyle()));
                    if ($index > 0) {
                        $rows++;
                    }
                }
                $this->exporter->configureXlsxWriterBeforeClose($writer);
            } finally {
                $writer->close();
            }
            $workbook = fopen($temporary, 'rb');
            if ($workbook === false) {
                throw new RuntimeException('Cannot read generated workbook.');
            }
            try {
                $files->captureWorkbook($workbook, $rows);
            } finally {
                fclose($workbook);
            }
        } catch (Throwable $exception) {
            $files->workbookFailed();
            report($exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
