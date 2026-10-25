<?php

namespace App\Filament\Exports\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\ConfiguratorRuleExporter;
use App\Filament\Exports\ConfiguratorRuleExportFiles;
use App\Services\ConfiguratorRuleExchange;
use Filament\Actions\Exports\Jobs\ExportCsv;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use League\Csv\Writer;
use RuntimeException;
use SplTempFileObject;
use Throwable;

class ExportConfiguratorRulesCsv extends ExportCsv implements ShouldQueueAfterCommit
{
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        Gate::forUser($this->export->user()->firstOrFail())->authorize('manage-catalog');
        $files = new ConfiguratorRuleExportFiles($this->export);
        $recordIds = array_map(fn (int|string $id): string => (string) $id, $this->records);
        $payloadHash = hash('sha256', json_encode([$this->export->getKey(), $this->page, $recordIds, $this->query, $this->options, ConfiguratorRuleExporter::columnMap($this->options)], JSON_THROW_ON_ERROR));
        $metadata = $files->page($this->page);
        $staging = [];
        $adoptionAttempted = false;
        if ($metadata !== null) {
            $this->assertIdentity($metadata, $payloadHash);
        } else {
            $suffix = '.pending.'.Str::uuid();
            $stagedCsv = $files->base($this->page).'.csv'.$suffix;
            $stagedJson = $files->base($this->page).'.json'.$suffix;
            [$csv, $lookups, $failures, $successful] = $this->capture();
            $recovery = [
                'version' => 1,
                'export_id' => (string) $this->export->id,
                'page' => $this->page,
                'record_ids' => $recordIds,
                'payload_hash' => $payloadHash,
                'processed' => count($recordIds),
                'successful' => $successful,
                'columns' => array_values(ConfiguratorRuleExporter::columnMap($this->options)),
                'csv_sha256' => hash('sha256', $csv),
                'staged_csv' => $stagedCsv,
            ];
            $metadata = compact('lookups', 'failures', 'recovery');
            $staging = [$stagedCsv, $stagedJson];
            try {
                $disk = $this->export->getFileDisk();
                if (! $disk->put($stagedCsv, $csv, Filesystem::VISIBILITY_PRIVATE) || ! $disk->put($stagedJson, json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), Filesystem::VISIBILITY_PRIVATE)) {
                    throw new RuntimeException('Unable to stage the private Rules export.');
                }
            } catch (Throwable $exception) {
                $this->discardStaging($staging);
                throw $exception;
            }
        }
        try {
            DB::transaction(function () use ($metadata, $payloadHash, $staging, &$adoptionAttempted): void {
                $export = $this->export::query()->lockForUpdate()->findOrFail($this->export->getKey());
                $files = new ConfiguratorRuleExportFiles($export);
                $adopted = $files->page($this->page);
                if ($adopted !== null) {
                    $this->assertIdentity($adopted, $payloadHash);
                }
                $path = $files->base($this->page).'.json';
                $accounting = $files->accounting($adopted === null ? ['path' => $path, 'metadata' => $metadata] : null);
                if ($export->processed_rows > $accounting['processed'] || $export->successful_rows > $accounting['successful']) {
                    throw new RuntimeException('The Rules export accounting cannot be safely reconstructed.');
                }
                if ($adopted === null) {
                    $adoptionAttempted = true;
                    if (! $export->getFileDisk()->move($staging[1], $path)) {
                        throw new RuntimeException('Unable to adopt the private Rules export page.');
                    }
                }
                $export->processed_rows = $accounting['processed'];
                $export->successful_rows = $accounting['successful'];
                $export->save();
                DB::afterCommit(fn () => $this->publish($payloadHash));
            });
        } finally {
            if (! $adoptionAttempted) {
                $this->discardStaging($staging);
            }
        }
    }

    private function publish(string $payloadHash): void
    {
        DB::transaction(function () use ($payloadHash): void {
            $export = $this->export::query()->lockForUpdate()->findOrFail($this->export->getKey());
            $files = new ConfiguratorRuleExportFiles($export);
            $metadata = $files->page($this->page) ?? throw new RuntimeException('The captured Rules export page is unavailable.');
            $this->assertIdentity($metadata, $payloadHash);
            $accounting = $files->accounting();
            if ($export->processed_rows !== $accounting['processed'] || $export->successful_rows !== $accounting['successful']) {
                throw new RuntimeException('The Rules export accounting needs reconciliation before publication.');
            }
            $path = $files->base($this->page).'.json';
            $source = $files->source($path, $metadata);
            $final = $files->base($this->page).'.csv';
            if ($source !== $final && ! $export->getFileDisk()->move($source, $final)) {
                throw new RuntimeException('Unable to publish the private Rules export page.');
            }
            $files->source($path, $metadata, finalOnly: true);
        });
    }

    /** @param array<string,mixed> $metadata */
    private function assertIdentity(array $metadata, string $payloadHash): void
    {
        if (! is_string($metadata['recovery']['payload_hash'] ?? null) || ! hash_equals($payloadHash, $metadata['recovery']['payload_hash'])) {
            throw new RuntimeException('This Rules export page belongs to different or older inputs. Create a new export instead of replaying it.');
        }
    }

    /** @param list<string> $paths */
    private function discardStaging(array $paths): void
    {
        if ($paths === []) {
            return;
        }
        try {
            $this->export->getFileDisk()->delete($paths);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array{string,list<array<string,string>>,list<array{rule_id:string,message:string}>,int} */
    private function capture(): array
    {
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

        return [$csv, $lookups, $failures, $successful];
    }
}
