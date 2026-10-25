<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Filament\CatalogDataActions;
use App\Filament\Imports\AttributeImporter;
use App\Filament\Imports\AttributeValueImporter;
use App\Filament\Imports\ValueImporter;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

beforeEach(function () {
    DB::commit();
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->nativePrefix = 'native_mysql_'.bin2hex(random_bytes(4));
    $this->import = Import::create(['file_name' => 'native.csv', 'file_path' => 'native.csv', 'importer' => ValueImporter::class, 'total_rows' => 2, 'user_id' => $this->actor->id]);
    $this->workers = [];
    $this->nativeOwners = collect();
    $this->nativeHookActive = false;
});

afterEach(function () {
    $this->nativeHookActive = false;
    stopCatalogContentionWorkers($this->workers);
    DB::purge('native_identity_writer');
    foreach ($this->nativeOwners as $owner) {
        $owner->attributes()->update(['default_configurator_option_id' => null]);
        ConfiguratorOption::whereIn('configurator_attribute_id', $owner->attributes()->select('id'))->delete();
        $owner->attributes()->delete();
        $owner->delete();
    }
    $attributeIds = Attribute::where('key', 'like', $this->nativePrefix.'%')->pluck('id');
    $valueIds = Value::where('semantic_id', 'like', $this->nativePrefix.'%')->pluck('id');
    Option::whereIn('attribute_id', $attributeIds)->orWhereIn('value_id', $valueIds)->delete();
    Attribute::whereKey($attributeIds)->delete();
    Value::whereKey($valueIds)->delete();
    $this->import->delete();
    $this->actor->delete();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
    DB::beginTransaction();
});

function mysqlNativeJob(Import $import, array $rows, array $map, array $options = []): ImportCsv
{
    $class = CatalogDataActions::import('native', $import->importer)->getJob();

    return new $class($import, base64_encode(serialize($rows)), $map, $options);
}

test('one native chunk holds every owner before any canonical row is acquired', function () {
    $this->nativeOwners = Configurator::factory()->count(2)->create();
    $values = collect();
    foreach ($this->nativeOwners as $index => $owner) {
        $attribute = Attribute::factory()->create(['key' => $this->nativePrefix.'_a'.$index]);
        $value = Value::factory()->create(['semantic_id' => $this->nativePrefix.'_v'.$index]);
        $values->push($value);
        $option = Option::factory()->create(['attribute_id' => $attribute->id, 'value_id' => $value->id]);
        $inclusion = ConfiguratorAttribute::factory()->create(['configurator_id' => $owner->id, 'attribute_id' => $attribute->id]);
        ConfiguratorOption::factory()->create(['configurator_attribute_id' => $inclusion->id, 'option_id' => $option->id]);
    }
    $rows = $values->reverse()->map(fn (Value $value): array => ['semantic_id' => $value->semantic_id, 'label' => '001'])->values()->all();
    $job = mysqlNativeJob($this->import, $rows, ['semantic_id' => 'semantic_id', 'label' => 'label'], ['update_existing' => true]);
    DB::purge('mysql');
    $this->workers[] = startCatalogContentionWorker(function (mixed $channel) use ($job): array {
        $paused = false;
        DB::listen(function (QueryExecuted $query) use (&$paused, $channel): void {
            if (! $paused && str_contains($query->sql, 'from `configurators`') && str_contains($query->sql, 'for update')) {
                $paused = true;
                writeCatalogContentionMessage($channel, ['phase' => 'owners_locked']);
                readCatalogContentionMessage($channel);
            }
        });

        return catalogContentionResult($job->handle(...));
    });
    writeCatalogContentionMessage($this->workers[0]['channel'], ['phase' => 'start']);
    expect(readCatalogContentionMessage($this->workers[0]['channel']))->toBe(['phase' => 'owners_locked']);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    expect(fn () => DB::transaction(fn () => Configurator::whereKey($this->nativeOwners->first()->id)->lockForUpdate()->firstOrFail()))->toThrow(QueryException::class, '1205');
    expect(DB::transaction(fn () => Value::whereKey($values->last()->id)->lockForUpdate()->firstOrFail())->id)->toBe($values->last()->id);
    writeCatalogContentionMessage($this->workers[0]['channel'], ['phase' => 'release']);
    expect(readCatalogContentionMessage($this->workers[0]['channel']))->toMatchArray(['phase' => 'result', 'status' => 'committed']);
    expect($this->import->fresh()->successful_rows)->toBe(2)->and($values->map(fn (Value $value): string => $value->fresh()->label)->all())->toBe(['001', '001']);
});

test('native identity acquisition replans a parent committed after preflight without stale validation', function (bool $optionImport) {
    $identity = $this->nativePrefix.'_late';
    $attribute = Attribute::factory()->create(['key' => $this->nativePrefix.'_attribute']);
    $this->import->update(['total_rows' => 1, 'importer' => $optionImport ? AttributeValueImporter::class : ValueImporter::class]);
    config(['database.connections.native_identity_writer' => config('database.connections.mysql')]);
    $writer = DB::connection('native_identity_writer');
    $this->nativeHookActive = true;
    DB::connection()->beforeExecuting(function (string $sql) use ($writer, $identity): void {
        if ($this->nativeHookActive && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
            $this->nativeHookActive = false;
            $writer->transaction(fn () => $writer->table('values')->insert(['semantic_id' => $identity, 'label' => 'Concurrent parent', 'description' => 'Keep intervening metadata', 'created_at' => now(), 'updated_at' => now()]));
        }
    });
    $rows = $optionImport ? [['attribute_key' => $attribute->key, 'value_semantic_id' => $identity, 'code' => 'N7']]
        : [['semantic_id' => $identity, 'label' => '001']];
    $map = array_combine(array_keys($rows[0]), array_keys($rows[0]));
    mysqlNativeJob($this->import, $rows, $map, ['update_existing' => true])->handle();
    expect($this->nativeHookActive)->toBeFalse()->and($this->import->fresh()->successful_rows)->toBe(1)
        ->and($this->import->failedRows()->count())->toBe(0);
    $value = Value::where('semantic_id', $identity)->sole();
    expect($value->description)->toBe('Keep intervening metadata');
    if ($optionImport) {
        expect(Option::where('attribute_id', $attribute->id)->sole()->only(['value_id', 'code']))->toBe(['value_id' => $value->id, 'code' => 'N7']);
    } else {
        expect($value->label)->toBe('001');
    }
})->with(['Value identity' => false, 'Attribute Value parent' => true]);

test('native absent identity verification protects the chunk until its own insert', function () {
    $identity = $this->nativePrefix.'_absent';
    $this->import->update(['total_rows' => 1]);
    $job = mysqlNativeJob($this->import, [['semantic_id' => $identity, 'label' => '001']], ['semantic_id' => 'semantic_id', 'label' => 'label']);
    DB::purge('mysql');
    $this->workers[] = startCatalogContentionWorker(function (mixed $channel) use ($job): array {
        $paused = false;
        DB::listen(function (QueryExecuted $query) use (&$paused, $channel): void {
            if (! $paused && str_contains($query->sql, 'from `values`') && str_contains($query->sql, 'for update nowait')) {
                $paused = true;
                writeCatalogContentionMessage($channel, ['phase' => 'absence_checked']);
                readCatalogContentionMessage($channel);
            }
        });

        return catalogContentionResult($job->handle(...));
    });
    writeCatalogContentionMessage($this->workers[0]['channel'], ['phase' => 'start']);
    expect(readCatalogContentionMessage($this->workers[0]['channel']))->toBe(['phase' => 'absence_checked']);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    expect(fn () => DB::transaction(fn () => Value::factory()->create(['semantic_id' => $identity])))->toThrow(QueryException::class, '1205');
    writeCatalogContentionMessage($this->workers[0]['channel'], ['phase' => 'release']);
    expect(readCatalogContentionMessage($this->workers[0]['channel']))->toMatchArray(['phase' => 'result', 'status' => 'committed']);
    expect(Value::where('semantic_id', $identity)->sole()->label)->toBe('001')->and($this->import->fresh()->successful_rows)->toBe(1);
});

test('native repeated Attribute identities preserve SQL matching and source wins within the chunk', function (bool $existing) {
    $identity = $this->nativePrefix.'_tone';
    if ($existing) {
        Attribute::factory()->create(['key' => $identity]);
    }
    $this->import->update(['importer' => AttributeImporter::class]);
    mysqlNativeJob($this->import, [
        ['key' => strtoupper($identity), 'label' => 'First'], ['key' => $identity, 'label' => '001'],
    ], ['key' => 'key', 'label' => 'label'], ['update_existing' => true])->handle();
    expect(Attribute::where('key', $identity)->sole()->only(['key', 'label']))->toBe(['key' => $identity, 'label' => '001'])
        ->and($this->import->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 2, 'successful_rows' => 2])
        ->and($this->import->failedRows()->count())->toBe(0);
})->with(['created in chunk' => false, 'existing before chunk' => true]);

test('a real MySQL deadlock rolls back and replays the entire native chunk once', function () {
    $value = Value::factory()->create(['semantic_id' => $this->nativePrefix.'_retry', 'label' => 'Original']);
    $heavyIds = [];
    foreach (range(1, 30) as $index) {
        $heavyIds[] = Value::factory()->create(['semantic_id' => $this->nativePrefix.'_heavy_'.$index])->id;
    }
    $this->import->update(['total_rows' => 1]);
    $job = mysqlNativeJob($this->import, [['semantic_id' => $value->semantic_id, 'label' => 'Final']], ['semantic_id' => 'semantic_id', 'label' => 'label'], ['update_existing' => true]);
    $importId = $this->import->id;
    DB::purge('mysql');
    $this->workers['holder'] = startCatalogContentionWorker(function (mixed $channel) use ($importId, $value, $heavyIds): array {
        return catalogContentionResult(fn () => DB::transaction(function () use ($channel, $importId, $value, $heavyIds): void {
            Import::whereKey($importId)->lockForUpdate()->firstOrFail();
            foreach ($heavyIds as $id) {
                DB::table('values')->where('id', $id)->update(['label' => 'Heavier competing transaction']);
            }
            writeCatalogContentionMessage($channel, ['phase' => 'counter_locked']);
            readCatalogContentionMessage($channel);
            writeCatalogContentionMessage($channel, ['phase' => 'attempting_value']);
            Value::whereKey($value->id)->lockForUpdate()->firstOrFail();
        }));
    });
    $this->workers['importer'] = startCatalogContentionWorker(function (mixed $channel) use ($job): array {
        $updates = 0;
        if (getenv('CATALOG_CONCURRENCY_TRACE')) {
            DB::connection()->beforeExecuting(function (string $sql): void {
                fwrite(STDERR, 'NATIVE_RETRY_QUERY '.$sql."\n");
            });
        }
        DB::listen(function (QueryExecuted $query) use (&$updates, $channel): void {
            if (str_starts_with($query->sql, 'update `values`')) {
                $updates++;
                if ($updates === 1) {
                    writeCatalogContentionMessage($channel, ['phase' => 'first_row_written']);
                    readCatalogContentionMessage($channel);
                }
            }
        });

        return [...catalogContentionResult($job->handle(...)), 'updates' => $updates];
    });
    writeCatalogContentionMessage($this->workers['holder']['channel'], ['phase' => 'start']);
    expect(readCatalogContentionMessage($this->workers['holder']['channel']))->toBe(['phase' => 'counter_locked']);
    writeCatalogContentionMessage($this->workers['importer']['channel'], ['phase' => 'start']);
    expect(readCatalogContentionMessage($this->workers['importer']['channel']))->toBe(['phase' => 'first_row_written']);
    writeCatalogContentionMessage($this->workers['holder']['channel'], ['phase' => 'request_value']);
    expect(readCatalogContentionMessage($this->workers['holder']['channel']))->toBe(['phase' => 'attempting_value']);
    $waiting = false;
    $deadline = microtime(true) + 3;
    do {
        foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
            if ((int) $process->Id === $this->workers['holder']['connection_id'] && str_contains((string) $process->Info, 'from `values`') && str_contains((string) $process->Info, 'for update')) {
                $waiting = true;
                break 2;
            }
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    expect($waiting)->toBeTrue();
    writeCatalogContentionMessage($this->workers['importer']['channel'], ['phase' => 'request_counter']);
    expect(readCatalogContentionMessage($this->workers['holder']['channel']))->toMatchArray(['phase' => 'result', 'status' => 'committed']);
    expect(readCatalogContentionMessage($this->workers['importer']['channel']))->toMatchArray(['phase' => 'result', 'status' => 'committed', 'updates' => 2]);
    expect($value->fresh()->label)->toBe('Final')->and($this->import->fresh()->only(['processed_rows', 'successful_rows']))->toBe(['processed_rows' => 1, 'successful_rows' => 1])
        ->and($this->import->failedRows()->count())->toBe(0);
});
