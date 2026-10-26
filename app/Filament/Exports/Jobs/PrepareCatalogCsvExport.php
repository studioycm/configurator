<?php

namespace App\Filament\Exports\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\CatalogExportFiles;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Generator;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Gate;

class PrepareCatalogCsvExport extends PrepareCsvExport implements ShouldQueueAfterCommit
{
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        Gate::forUser($this->export->user()->firstOrFail())->authorize('manage-catalog');
        $query = EloquentSerializeFacade::unserialize($this->query);
        $pages = function () use ($query): Generator {
            if ($this->records !== null) {
                foreach (array_chunk(array_slice($this->records, 0, $this->export->total_rows), $this->chunkSize) as $records) {
                    yield $records;
                }

                return;
            }
            if (empty($query->getQuery()->orders)) {
                $query->orderBy($query->getModel()->getQualifiedKeyName());
            }
            $records = [];
            $count = 0;
            foreach ($query->select($query->getModel()->getQualifiedKeyName())->lazy($this->chunkSize) as $record) {
                if ($count >= $this->export->total_rows) {
                    break;
                }
                $records[] = $record->getKey();
                $count++;
                if (count($records) === $this->chunkSize) {
                    yield $records;
                    $records = [];
                }
            }
            if ($records !== []) {
                yield $records;
            }
        };
        $files = new CatalogExportFiles($this->export);
        $files->seal($this->columnMap, $this->options, $this->options['_catalog_formats'] ?? ['csv', 'xlsx'], $this->query, $pages());
        foreach ($files->plan()['pages'] as $reference) {
            $page = $files->pagePlan($reference['page']);
            $job = app(ExportCatalogCsv::class, ['export' => $this->export, 'query' => $this->query, 'records' => $page['records'], 'page' => $page['page'], 'columnMap' => $this->columnMap, 'options' => $this->options]);
            if ($this->batch() !== null) {
                $this->batch()->add([$job]);
            } else {
                dispatch($job);
            }
        }
    }

    public function getExportCsvJob(): string
    {
        return ExportCatalogCsv::class;
    }
}
