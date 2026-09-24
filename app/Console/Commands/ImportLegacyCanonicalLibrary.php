<?php

namespace App\Console\Commands;

use App\Actions\ImportLegacyCanonicalLibrary as ImportLibrary;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class ImportLegacyCanonicalLibrary extends Command
{
    protected $signature = 'catalog:import-legacy-library
        {source : Private reviewed legacy library JSON export}
        {--actor= : Existing admitted administrator ID}
        {--apply : Apply the reviewed library atomically}
        {--source-hash= : SHA-256 printed by the dry-run}
        {--hold-duplicate-codes : Leave every Option sharing a source code pending review}';

    protected $description = 'Review or import legacy Attributes, Options and separate Values without overwriting canonical definitions';

    public function handle(ImportLibrary $import): int
    {
        $actorId = (string) $this->option('actor');
        $actor = ctype_digit($actorId) ? User::find($actorId) : null;
        if ($actor === null) {
            $this->error('Supply an existing admitted administrator with --actor.');

            return self::FAILURE;
        }
        try {
            $result = $import->handle($actor, $this->argument('source'), (bool) $this->option('apply'), $this->option('source-hash'), (bool) $this->option('hold-duplicate-codes'));
        } catch (ValidationException $exception) {
            foreach (array_slice(array_merge(...array_values($exception->errors())), 0, 20) as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        } catch (AuthorizationException) {
            $this->error('This account is not admitted to catalog management.');

            return self::FAILURE;
        } catch (JsonException) {
            $this->error('The legacy library file is not valid JSON.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Import failed and was rolled back. See the private application log for details.');

            return self::FAILURE;
        }
        $this->line('Mode: '.$result['mode']);
        $this->line('Source SHA-256: '.$result['source_hash']);
        $this->table(['Outcome', 'Count'], collect($result['totals'])->map(fn (int $count, string $key): array => [$key, $count])->values()->all());
        if ($result['pending_options'] !== []) {
            $this->warn('Pending duplicate codes: '.collect($result['pending_options'])->pluck('code')->uniqueStrict()->implode(', '));
        }
        $path = 'imports/ari/reports/'.now()->format('Ymd-His').'-legacy-library-'.Str::uuid().'.json';
        try {
            if (! Storage::disk('local')->put($path, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
                throw new \RuntimeException('Report write failed.');
            }
            $this->line('Private report: '.Storage::disk('local')->path($path));
        } catch (Throwable $exception) {
            report($exception);
            $this->warn('The report could not be saved; the database outcome above still applies.');
        }

        return self::SUCCESS;
    }
}
