<?php

namespace App\Console\Commands;

use App\Actions\ImportCatalogProducts as ImportProducts;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class ImportCatalogProducts extends Command
{
    protected $signature = 'catalog:import-products
        {source : Path to the canonical 67-column CSV}
        {--actor= : Existing admitted administrator ID}
        {--apply : Apply the entire reviewed file atomically}
        {--source-hash= : SHA-256 printed by the source dry-run}
        {--map-hash= : SHA-256 printed by the map dry-run}
        {--parents-hash= : SHA-256 printed for the reviewed parent metadata, including an empty map}
        {--parents= : Optional JSON map keyed by child legacy Group ID}';

    protected $description = 'Review or atomically import canonical catalog Products; defaults to dry-run';

    public function handle(ImportProducts $import): int
    {
        $actorId = (string) $this->option('actor');
        $actor = ctype_digit($actorId) ? User::query()->find($actorId) : null;
        if ($actor === null) {
            $this->error('Supply an existing admitted administrator with --actor.');

            return self::FAILURE;
        }
        try {
            $parents = [];
            if ($path = $this->option('parents')) {
                if (! is_file($path) || ! is_readable($path)) {
                    throw ValidationException::withMessages(['parents' => 'The parent metadata file is not readable.']);
                }
                $parents = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($parents)) {
                    throw ValidationException::withMessages(['parents' => 'Parent metadata must be a JSON object keyed by child legacy Group ID.']);
                }
            }
            $result = $import->handle($actor, $this->argument('source'), (bool) $this->option('apply'), $this->option('source-hash'), $this->option('map-hash'), $parents, $this->option('parents-hash'));
        } catch (ValidationException $exception) {
            $errors = array_slice(array_merge(...array_values($exception->errors())), 0, 20);
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->saveReport(['mode' => 'rejected', 'errors' => $errors]);

            return self::FAILURE;
        } catch (AuthorizationException) {
            $this->error('This account is not admitted to catalog management.');

            return self::FAILURE;
        } catch (LockTimeoutException) {
            $this->error('Another catalog import is in progress. Retry after it finishes.');

            return self::FAILURE;
        } catch (JsonException) {
            $this->error('The parent metadata file is not valid JSON.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Import failed and was rolled back. See the private application log for details.');
            $this->saveReport(['mode' => 'failed', 'errors' => ['Import transaction failed; see the private application log.']]);

            return self::FAILURE;
        }
        $this->line('Mode: '.$result['mode']);
        $this->line('Source SHA-256: '.$result['source_hash']);
        $this->line('Map SHA-256: '.$result['map_hash']);
        $this->line('Parents SHA-256: '.$result['parents_hash']);
        $this->table(['Outcome', 'Count'], collect($result['totals'])->map(fn (int $count, string $key): array => [$key, $count])->values()->all());
        $this->saveReport($result);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $result */
    private function saveReport(array $result): void
    {
        $path = 'imports/ari/reports/'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
        try {
            $saved = Storage::disk('local')->put($path, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($saved) {
                $this->line('Private report: '.Storage::disk('local')->path($path));

                return;
            }
        } catch (Throwable $exception) {
            report($exception);
        }
        $this->warn('The outcome report could not be saved. The database outcome above still applies.');
    }
}
