<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
if (getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Writing inspection MySQL tests require the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';

use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorRuleWritingBatch;
use App\Models\Configurator;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleWritingDrafts;
use App\Services\ConfiguratorRuleWritingInspection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once dirname(__DIR__, 3).'/ConfiguratorRuleWritingFixtures.php';

beforeEach(function () {
    $this->writingFixtures = writingBeginOwnedFixtures();
    try {
        $this->batchActor = writingFixtureActor('ycm@data4.work');
        [$this->batchOwner, $graph] = writingCanonicalDefinitionFixture();
        $graph['rules'] = [fixtureMapping(), [...fixtureAdvanced('advanced', [fixtureCondition('when')], 'C'), 'priority' => 1]];
        app(SaveConfiguratorDefinition::class)->handle($this->batchActor, $this->batchOwner, $graph);
        $this->batchGraph = app(ConfiguratorDefinitionLoader::class)->draft($this->batchOwner->fresh());
        $opened = app(ConfiguratorRuleWritingDrafts::class)->openRule($this->batchActor, $this->batchOwner, (int) $this->batchGraph['rules'][1]['id'], (string) Str::uuid());
        $this->inspectionSaved = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, [['id' => $opened['draft']['id'], 'expected_revision' => 1, 'sequence' => 1]])['items'][0];
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

test('inspection holds a coherent owner-protected read while an independent writer waits under READ COMMITTED', function (string $mode) {
    expect(DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level)->toBe('REPEATABLE-READ');
    $actor = $this->batchActor;
    $owner = $this->batchOwner;
    $saved = $this->inspectionSaved;
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $saved, $mode): array {
            if ($mode === 'one-shot RC') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            } elseif ($mode === 'session RC') {
                DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
            $paused = false;
            DB::listen(function (QueryExecuted $query) use ($channel, &$paused): void {
                if (! $paused && str_contains($query->sql, 'from `configurator_rules`')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'root_read']);
                    readCatalogContentionMessage($channel);
                }
            });

            return [...catalogContentionResult(fn () => app(ConfiguratorRuleWritingInspection::class)->inspect($actor, $owner, $saved['draft']['id'])), 'session_after' => DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level];
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $saved): array {
            $announced = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced): void {
                if (! $announced && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'writer_attempting']);
                }
            });
            $proposal = [...$saved['saved_rule'], 'label' => 'New current root'];
            $proposal['effects'][0]['kind'] = 'SetHint';
            $proposal['effects'][0]['display_value'] = 'New current child';

            return catalogContentionResult(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $saved['saved_rule'], $proposal, []));
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'root_read']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'writer_attempting']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        expect(fn () => DB::transaction(fn () => Configurator::whereKey($owner->id)->lockForUpdate()->firstOrFail()))->toThrow(QueryException::class, '1205');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'resume']);
        $old = readCatalogContentionMessage($workers[0]['channel']);
        $write = readCatalogContentionMessage($workers[1]['channel']);
        expect($write['status'])->toBe('committed');
        expect($old['session_after'])->toBe($mode === 'session RC' ? 'READ-COMMITTED' : 'REPEATABLE-READ');
        expect($old['status'])->toBe('committed');
        expect($old['value']['current']['records'])->toBe($saved['records']);
        expect($old['value']['status']['capture'])->toBe('matches_current');
        $new = app(ConfiguratorRuleWritingInspection::class)->inspect($actor, $owner, $saved['draft']['id']);
        expect($new['current']['rule']['label'])->toBe('New current root');
        expect($new['current']['records']['effects'][0]['display_value'])->toBe('New current child');
        expect($new['status']['capture'])->toBe('older_or_different');
        expect($new['last_writing_capture'])->toBe($saved['draft']['last_saved_authoring']);
        expect($new['draft']['revision'])->toBe($saved['draft']['revision']);
        expect($new)->not->toHaveKey('acknowledgement');
    } finally {
        stopCatalogContentionWorkers($workers);
    }
})->with(['normal RR', 'one-shot RC', 'session RC']);

test('inspection preserves an existing READ COMMITTED session configuration while protecting its owned records', function () {
    $before = DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level;
    expect($before)->toBe('REPEATABLE-READ');
    try {
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $this->inspectionSaved['draft']['id']);
        expect($result['current']['records'])->toBe($this->inspectionSaved['records']);
        expect(DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level)->toBe('READ-COMMITTED');
    } finally {
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    }
});

test('inspection protects a one-shot READ COMMITTED transaction without changing its REPEATABLE READ session default', function () {
    expect(DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level)->toBe('REPEATABLE-READ');
    DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $this->inspectionSaved['draft']['id']);
    expect($result['current']['records'])->toBe($this->inspectionSaved['records']);
    expect(DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level)->toBe('REPEATABLE-READ');
});

test('failed inspection releases its shared owner lock while its connection stays open', function () {
    $actor = $this->batchActor;
    $owner = $this->batchOwner;
    $missingId = (string) Str::uuid();
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $missingId): array {
            try {
                app(ConfiguratorRuleWritingInspection::class)->inspect($actor, $owner, $missingId);
            } catch (ModelNotFoundException $exception) {
                writeCatalogContentionMessage($channel, ['phase' => 'inspection_failed', 'transaction_level' => DB::transactionLevel(), 'pdo_transaction' => DB::connection()->getPdo()->inTransaction()]);
                readCatalogContentionMessage($channel);

                return ['status' => 'rejected', 'exception' => $exception::class];
            }

            return ['status' => 'unexpected_success'];
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'inspection_failed', 'transaction_level' => 0, 'pdo_transaction' => false]);
        expect((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id)->not->toBe($workers[0]['connection_id']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $record = DB::transaction(fn () => Configurator::whereKey($owner->id)->lockForUpdate()->firstOrFail());
        expect($record->id)->toBe($owner->id);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'finish']);
        expect(readCatalogContentionMessage($workers[0]['channel'])['status'])->toBe('rejected');
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});

test('writer-first inspection waits for the committed Rule draft and capture together', function (bool $oneShot) {
    $actor = $this->batchActor;
    $owner = $this->batchOwner;
    $saved = $this->inspectionSaved;
    $source = str_replace('/hide @C', '/set-hint @C [[New committed child]]', $saved['draft']['source']);
    app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $saved['draft']['id'], $saved['draft']['revision'], ['source' => $source, 'label' => 'New committed root', 'description' => 'Committed capture metadata'], 3);
    $selection = [['id' => $saved['draft']['id'], 'expected_revision' => $saved['draft']['revision'] + 1, 'sequence' => 4]];
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $selection): array {
            $paused = false;
            DB::listen(function (QueryExecuted $query) use ($channel, &$paused): void {
                if (! $paused && str_starts_with($query->sql, 'update `configurator_rule_writing_drafts`') && str_contains($query->sql, 'last_saved_authoring')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'writer_uncommitted']);
                    readCatalogContentionMessage($channel);
                }
            });

            return catalogContentionResult(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, $selection));
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $saved, $oneShot): array {
            if ($oneShot) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }
            $announced = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced): void {
                if (! $announced && str_contains($sql, 'from `configurators`') && (str_contains($sql, 'lock in share mode') || str_contains($sql, 'for share'))) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'inspection_attempting']);
                }
            });

            return [...catalogContentionResult(fn () => app(ConfiguratorRuleWritingInspection::class)->inspect($actor, $owner, $saved['draft']['id'])), 'session_after' => DB::selectOne('SELECT @@transaction_isolation AS isolation_level')->isolation_level];
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'writer_uncommitted']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'inspection_attempting']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        expect(fn () => DB::transaction(fn () => Configurator::whereKey($owner->id)->sharedLock()->firstOrFail()))->toThrow(QueryException::class, '1205');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'commit']);
        $write = readCatalogContentionMessage($workers[0]['channel']);
        $read = readCatalogContentionMessage($workers[1]['channel']);
        expect($write['status'])->toBe('committed')->and($read['status'])->toBe('committed');
        expect($read['value']['current']['records'])->toBe($write['value']['items'][0]['records']);
        expect($read['value']['current']['rule']['label'])->toBe('New committed root');
        expect($read['value']['current']['records']['effects'][0]['display_value'])->toBe('New committed child');
        expect($read['value']['last_writing_capture'])->toBe($write['value']['items'][0]['draft']['last_saved_authoring']);
        expect($read['value']['draft']['description'])->toBe('Committed capture metadata');
        expect($read['value']['draft']['revision'])->toBe($write['value']['items'][0]['draft']['revision']);
        expect($read['session_after'])->toBe('REPEATABLE-READ');
        expect($read['value'])->not->toHaveKey('acknowledgement');
    } finally {
        stopCatalogContentionWorkers($workers);
    }
})->with([false, true]);
