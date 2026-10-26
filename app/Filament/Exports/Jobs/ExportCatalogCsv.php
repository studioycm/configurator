<?php

namespace App\Filament\Exports\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\CatalogExportFiles;
use App\Services\CatalogCsv;
use App\Services\CatalogDataExchangePolicy;
use Filament\Actions\Exports\Jobs\ExportCsv;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

class ExportCatalogCsv extends ExportCsv implements ShouldQueueAfterCommit
{
    public ?int $tries = 1;

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $actor = $this->export->user()->firstOrFail();
        Gate::forUser($actor)->authorize('manage-catalog');
        $files = new CatalogExportFiles($this->export);
        $files->assertPayload($this->page, $this->records, $this->query, $this->columnMap, $this->options);
        $capture = $files->capture($this->page);
        if ($capture !== null) {
            $files->adopt($capture);

            return;
        }
        auth()->setUser($actor);
        try {
            $query = EloquentSerializeFacade::unserialize($this->query);
            foreach ($this->exporter->getCachedColumns() as $column) {
                $column->applyRelationshipAggregates($query);
                $column->applyEagerLoading($query);
            }
            $records = $query->find($this->records)->keyBy(fn ($record): string => (string) $record->getKey());
            if ($records->count() !== count($this->records)) {
                throw new RuntimeException('Selected export records are missing; outcome is unconfirmed.');
            }
            $csv = app(CatalogCsv::class)->writer($this->exporter::getCsvDelimiter());
            $successful = $rejected = [];
            foreach ($this->records as $id) {
                try {
                    $values = ($this->exporter)($records[(string) $id]);
                } catch (Throwable $exception) {
                    if (! app(CatalogDataExchangePolicy::class)->isFormatterRejection($exception)) {
                        throw $exception;
                    }
                    $rejected[] = ['id' => (string) $id, 'reason' => $exception->getMessage()];

                    continue;
                }
                $csv->insertOne($values);
                $successful[] = (string) $id;
            }
            $files->capturePage($this->page, $csv, $successful, $rejected);
        } finally {
            auth()->forgetGuards();
        }
    }
}
