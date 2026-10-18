<?php

namespace App\Filament\Exports\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\ConfiguratorRuleExporter;
use App\Services\ConfiguratorRuleExchange;
use Filament\Actions\Exports\Jobs\ExportCsv;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use League\Csv\Writer;
use RuntimeException;
use SplTempFileObject;
use Throwable;

class ExportConfiguratorRulesCsv extends ExportCsv
{
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        Gate::forUser($this->export->user()->firstOrFail())->authorize('manage-catalog');
        $csv = '';
        $query = EloquentSerializeFacade::unserialize($this->query);
        $records = $query->find($this->records)->keyBy('id');
        $successful = 0;
        $lookups = [];
        $failures = [];
        $exchange = app(ConfiguratorRuleExchange::class);
        foreach ($this->records as $id) {
            try {
                $record = $records->get($id) ?? throw new RuntimeException('The selected rule no longer exists or is outside the export scope.');
                $snapshot = $exchange->snapshot($record);
                /** @var ConfiguratorRuleExporter $exporter */
                $exporter = $this->exporter;
                $rows = $exporter->formatRows($snapshot);
                $completeRule = Writer::from(new SplTempFileObject);
                $completeRule->setDelimiter(ConfiguratorRuleExporter::getCsvDelimiter());
                $completeRule->setEscape('');
                $completeRule->insertAll($rows);
                $csv .= $completeRule->toString();
                array_push($lookups, ...$snapshot['lookups']);
                $successful++;
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = ['rule_id' => (string) $id, 'message' => $exception->getMessage()];
            }
        }
        $path = $this->export->getFileDirectory().DIRECTORY_SEPARATOR.str_pad((string) $this->page, 16, '0', STR_PAD_LEFT);
        $disk = $this->export->getFileDisk();
        $suffix = '.pending.'.Str::uuid();
        $stagedCsv = $path.'.csv'.$suffix;
        $stagedJson = $path.'.json'.$suffix;
        try {
            if (! $disk->put($stagedCsv, $csv, Filesystem::VISIBILITY_PRIVATE) || ! $disk->put($stagedJson, json_encode(compact('lookups', 'failures'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), Filesystem::VISIBILITY_PRIVATE)) {
                throw new RuntimeException('Unable to stage the private Rules export.');
            }
            DB::transaction(function () use ($disk, $path, $stagedCsv, $stagedJson, $successful): void {
                $export = $this->export::query()->lockForUpdate()->findOrFail($this->export->getKey());
                if (! $disk->move($stagedJson, $path.'.json') || ! $disk->move($stagedCsv, $path.'.csv')) {
                    throw new RuntimeException('Unable to publish the private Rules export.');
                }
                $export->processed_rows = min($export->total_rows, $export->processed_rows + count($this->records));
                $export->successful_rows = min($export->total_rows, $export->successful_rows + $successful);
                $export->save();
            });
        } catch (Throwable $exception) {
            $disk->delete([$stagedCsv, $stagedJson, $path.'.csv', $path.'.json']);
            throw $exception;
        }
    }
}
