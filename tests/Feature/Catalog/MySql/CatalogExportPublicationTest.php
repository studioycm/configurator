<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use App\Filament\Exports\CatalogExportFiles;
use App\Filament\Exports\Jobs\ExportCatalogCsv;
use App\Filament\Exports\ValueExporter;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    DB::commit();
    $this->previousExportTransactions = app('db.transactions');
    $manager = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $manager);
    DB::connection()->setTransactionManager($manager);
    Storage::fake('local');
    $this->exportActor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->exportValues = Value::factory()->count(2)->create();
    $this->publicationExport = Export::create(['user_id' => $this->exportActor->id, 'exporter' => ValueExporter::class, 'total_rows' => 2, 'file_disk' => 'local', 'file_name' => 'mysql-publication']);
    $this->publicationColumns = ['semantic_id' => 'semantic_id', 'label' => 'label'];
    $this->publicationQuery = EloquentSerializeFacade::serialize(Value::whereKey($this->exportValues->modelKeys()));
    (new CatalogExportFiles($this->publicationExport))->seal($this->publicationColumns, [], ['csv'], $this->publicationQuery, array_map(fn (int $id): array => [$id], $this->exportValues->modelKeys()));
});

afterEach(function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $this->publicationExport->delete();
    Value::whereKey($this->exportValues->modelKeys())->delete();
    $this->exportActor->delete();
    Storage::disk('local')->deleteDirectory($this->publicationExport->getFileDirectory());
    app()->instance('db.transactions', $this->previousExportTransactions);
    DB::connection()->setTransactionManager($this->previousExportTransactions);
    DB::beginTransaction();
});

test('independent mysql page adoptions serialize accounting and retain both immutable captures', function () {
    $workers = [];
    $export = $this->publicationExport;
    $query = $this->publicationQuery;
    $columns = $this->publicationColumns;
    $ids = $this->exportValues->modelKeys();
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($export, $query, $columns, $ids): array {
            $paused = false;
            DB::listen(function (QueryExecuted $event) use ($channel, &$paused): void {
                if (! $paused && str_contains($event->sql, 'from `exports`') && str_contains($event->sql, 'for update')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'export_locked']);
                    readCatalogContentionMessage($channel);
                }
            });

            return catalogContentionResult(function () use ($export, $query, $columns, $ids): void {
                try {
                    (new ExportCatalogCsv($export, $query, [$ids[0]], 1, $columns))->handle();
                } catch (Throwable $exception) {
                    report($exception);
                    throw $exception;
                }
            });
        });
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($export, $query, $columns, $ids): array {
            $announced = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced): void {
                if (! $announced && str_contains($sql, 'from `exports`') && str_contains($sql, 'for update')) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'second_attempting_lock']);
                }
            });

            return catalogContentionResult(fn () => (new ExportCatalogCsv($export, $query, [$ids[1]], 2, $columns))->handle());
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'export_locked']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'second_attempting_lock']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        expect(fn () => DB::transaction(fn () => Export::whereKey($export->id)->lockForUpdate()->firstOrFail()))->toThrow(QueryException::class, '1205');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first)->toMatchArray(['phase' => 'result', 'status' => 'committed']);
        expect($second)->toMatchArray(['phase' => 'result', 'status' => 'committed']);
    } finally {
        stopCatalogContentionWorkers($workers);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
    }
    expect($export->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 2, 'successful_rows' => 2]);
    expect((new CatalogExportFiles($export))->assessment()['csv']['download'])->toBeTrue();
    foreach ([1, 2] as $page) {
        Storage::disk('local')->assertExists($export->getFileDirectory().'/.catalog/receipts/'.$page.'.json');
    }
});

test('mysql publication remains unavailable across an enclosing rollback and reuses captured source on retry', function () {
    $export = $this->publicationExport;
    $files = new CatalogExportFiles($export);
    $id = $this->exportValues[0]->id;
    $original = $this->exportValues[0]->label;
    DB::beginTransaction();
    (new ExportCatalogCsv($export, $this->publicationQuery, [$id], 1, $this->publicationColumns))->handle();
    expect($files->assessment()['csv']['download'])->toBeFalse();
    Storage::disk('local')->assertMissing($export->getFileDirectory().'/0000000000000001.csv');
    DB::rollBack();
    $this->exportValues[0]->update(['label' => 'Later data must not be recaptured']);
    (new ExportCatalogCsv($export, $this->publicationQuery, [$id], 1, $this->publicationColumns))->handle();
    (new ExportCatalogCsv($export, $this->publicationQuery, [$this->exportValues[1]->id], 2, $this->publicationColumns))->handle();
    $bytes = implode('', iterator_to_array($files->csvChunks()));
    expect($bytes)->toContain($original)->not->toContain('Later data must not be recaptured');
    expect($files->assessment()['csv']['download'])->toBeTrue();
});
