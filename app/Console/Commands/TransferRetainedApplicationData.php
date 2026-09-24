<?php

namespace App\Console\Commands;

use App\Actions\TransferRetainedApplicationData as TransferData;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransferRetainedApplicationData extends Command
{
    protected $signature = 'catalog:transfer-retained-data
        {--source-env= : Private source environment file; read only in this process}
        {--apply : Apply the reviewed snapshot to the isolated target}
        {--source-hash= : Source hash from the dry-run}';

    protected $description = 'Review and transfer classified local retained data without changing the source or active database';

    public function handle(TransferData $transfer): int
    {
        try {
            $path = $this->option('source-env');
            if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
                throw ValidationException::withMessages(['source' => 'Supply the private original environment file with --source-env.']);
            }
            $source = Dotenv::parse(file_get_contents($path));
            foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $variable) {
                config(['database.connections.catalog_source.'.$key => $source[$variable] ?? null]);
            }
            DB::purge('catalog_source');
            $result = $transfer->transferAll('catalog_source', $source['APP_KEY'] ?? '', (bool) $this->option('apply'), $this->option('source-hash'));
        } catch (ValidationException $exception) {
            foreach (array_merge(...array_values($exception->errors())) as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Retained-data transfer failed. Target transaction rolled back; source was not changed. Inspect the private log.');

            return self::FAILURE;
        }
        $this->line('Mode: '.$result['mode']);
        $this->line('Source SHA-256: '.$result['source_hash']);
        $this->table(['Table', 'New rows', 'Unchanged', 'Total'], collect($result['tables'])->map(fn (array $counts, string $table): array => [$table, $counts['created'], $counts['unchanged'], $counts['total']])->values()->all());
        $path = 'imports/ari/reports/retained-'.now()->format('Ymd-His-u').'.json';
        try {
            if (! Storage::disk('local')->put($path, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
                $this->warn('Transfer result could not be saved; the reported database outcome still applies.');
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->warn('Transfer result could not be saved; the reported database outcome still applies.');
        }

        return self::SUCCESS;
    }
}
