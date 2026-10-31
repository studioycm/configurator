<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\SaveCatalogPresentationSettings;
use App\Actions\SaveGroupSettings;
use App\Actions\SaveGroupTags;
use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCards;
use App\Services\CatalogPresentation;
use App\Services\CatalogSnapshots;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

test('independent first saves have one winner and reject all stale contenders without duplicate-lock errors', function (int $contenders) {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $isolation = DB::selectOne('SELECT @@session.transaction_isolation AS isolation_level')->isolation_level;
    expect(strtoupper($isolation))->toBe('REPEATABLE-READ');
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $settings = array_replace($service->defaults(), ['sidebar_mode' => 'tags', 'tag_counts' => false]);
    $this->assertDatabaseCount('catalog_presentation_settings', 0);
    $workers = [];
    $results = [];
    $joins = [];
    $admission = [];
    $lockProbe = null;
    $observedWritten = false;
    try {
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $settings): array {
            expect(DB::transactionLevel())->toBe(0);
            $publications = [];
            Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$publications): void {
                $publications[] = $event->key;
            });
            DB::listen(function (QueryExecuted $query) use ($channel): void {
                if (str_starts_with(strtolower($query->sql), 'insert into `catalog_presentation_settings`')
                    && str_contains(strtolower($query->sql), 'on duplicate key update `id` =')) {
                    writeCatalogContentionMessage($channel, ['phase' => 'singleton_prepared']);
                    expect(readCatalogContentionMessage($channel))->toBe(['phase' => 'release']);
                }
            });
            $result = catalogContentionResult(function () use ($actor, $settings): array {
                $saved = (new SaveCatalogPresentationSettings(new CatalogPresentation))->handle(User::findOrFail($actor->id), $settings, 'defaults:v1');

                return ['id' => $saved->id, 'version' => $saved->version, 'settings' => $saved->settings, 'raw' => $saved->getAttributes()];
            });

            return [...$result, 'cache_publications' => count($publications), 'transaction_level' => DB::transactionLevel()];
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'singleton_prepared']);
        for ($index = 1; $index < $contenders; $index++) {
            $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $settings, $index): array {
                expect(DB::transactionLevel())->toBe(0);
                $publications = [];
                Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$publications): void {
                    $publications[] = $event->key;
                });
                DB::connection()->beforeExecuting(function (string $sql) use ($channel): void {
                    if (str_starts_with(strtolower($sql), 'insert into `catalog_presentation_settings`')
                        && str_contains(strtolower($sql), 'on duplicate key update `id` =')) {
                        writeCatalogContentionMessage($channel, ['phase' => 'attempting_singleton_insert']);
                    }
                });
                $attempt = array_replace($settings, ['group_image_scale' => $index === 1 ? 'small' : 'large']);
                $result = catalogContentionResult(fn () => (new SaveCatalogPresentationSettings(new CatalogPresentation))
                    ->handle(User::findOrFail($actor->id), $attempt, 'defaults:v1')->version);

                return [...$result, 'cache_publications' => count($publications), 'transaction_level' => DB::transactionLevel()];
            });
            writeCatalogContentionMessage($workers[$index]['channel'], ['phase' => 'start']);
            expect(readCatalogContentionMessage($workers[$index]['channel']))->toBe(['phase' => 'attempting_singleton_insert']);
        }
        expect(array_unique(array_column($workers, 'connection_id')))->toHaveCount($contenders);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            DB::transaction(fn () => CatalogPresentationSettings::query()->whereKey(1)->lockForUpdate()->first());
            throw new RuntimeException('The prepared singleton did not retain its independent row lock.');
        } catch (QueryException $exception) {
            $lockProbe = ['sqlstate' => $exception->errorInfo[0], 'driver_code' => (int) $exception->errorInfo[1]];
            expect((int) $exception->errorInfo[1])->toBe(1205);
        }
        observeCatalogPresentationInsertAdmission(array_column(array_slice($workers, 1), 'connection_id'), $admission);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        foreach ($workers as $worker) {
            $results[] = readCatalogContentionMessage($worker['channel']);
        }
        foreach ($workers as $worker) {
            $joins[] = joinCatalogPresentationWorker($worker);
        }
        $observed = [
            'state' => 'observed-before-outcome-assertions', 'isolation' => $isolation,
            'results' => sanitizeCatalogPresentationResults($results), 'worker_pids' => array_column($workers, 'pid'),
            'connection_ids' => array_column($workers, 'connection_id'), 'insert_admission' => $admission,
            'winner_lock_probe' => $lockProbe, 'child_join_outcomes' => $joins,
            'cleanup' => 'unchanged owned-worker helper runs in finally; launcher owns final process/window receipt',
        ];
        recordCatalogPresentationMySqlProof('first-save-'.$contenders.'-observed', $observed);
        $observedWritten = true;
        expect($results[0]['status'])->toBe('committed');
        expect($results[0]['value']['id'])->toBe(1);
        expect($results[0]['cache_publications'])->toBe(1);
        foreach (array_slice($results, 1) as $result) {
            expect($result['status'])->toBe('rejected');
            expect($result['errors'])->toHaveKey('settings');
            expect($result['cache_publications'])->toBe(0);
            expect($result['transaction_level'])->toBe(0);
        }
        foreach ($joins as $join) {
            expect($join['joined'])->toBeTrue();
            expect($join['exited'])->toBeTrue();
            expect($join['exit_code'])->toBe(0);
        }
        DB::purge('mysql');
        assertCatalogMySqlSafety(app());
        $fresh = (new CatalogPresentation)->current();
        $this->assertDatabaseCount('catalog_presentation_settings', 1);
        expect($fresh['version'])->toBe($results[0]['value']['version']);
        expect($fresh['settings'])->toBe($settings);
        expect(CatalogPresentationSettings::query()->findOrFail(1)->getAttributes())->toBe($results[0]['value']['raw']);
        expect(DB::transactionLevel())->toBe(0);
        recordCatalogPresentationMySqlProof('first-save-'.$contenders, [
            ...$observed, 'state' => 'passed-outcome-assertions',
            'final_version' => $fresh['version'], 'final_settings' => $fresh['settings'],
            'limit' => 'Owned loser inserts observed together in server execution while the winner lock was retained; no arbitrary-isolation or stress claim.',
        ]);
    } catch (Throwable $exception) {
        foreach ($workers as $worker) {
            if (! in_array($worker['pid'], array_column($joins, 'pid'), true)) {
                $joins[] = joinCatalogPresentationWorker($worker);
            }
        }
        try {
            recordCatalogPresentationMySqlProof('first-save-'.$contenders.($observedWritten ? '-failed' : '-observed'), [
                'state' => $observedWritten ? 'failed-after-observed-snapshot' : 'failed-before-outcome-assertions',
                'isolation' => $isolation, 'results' => sanitizeCatalogPresentationResults($results),
                'worker_pids' => array_column($workers, 'pid'), 'connection_ids' => array_column($workers, 'connection_id'),
                'insert_admission' => $admission, 'winner_lock_probe' => $lockProbe, 'child_join_outcomes' => $joins,
                'failure' => sanitizeCatalogPresentationResults([catalogContentionResult(function () use ($exception): never {
                    throw $exception;
                })])[0],
                'cleanup' => 'unchanged owned-worker helper runs in finally; launcher owns final process/window receipt',
            ]);
        } catch (Throwable $evidenceException) {
            fwrite(STDERR, 'K2 observed evidence unavailable ('.$evidenceException::class.'); original failure retained.'.PHP_EOL);
        }
        throw $exception;
    } finally {
        stopCatalogContentionWorkers($workers);
    }
})->with(['two contenders' => [2], 'three duplicate-key contenders' => [3]]);

test('physical MySQL JSON object order is a no-op while ordered lists and strict scalar repairs advance version', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $type = DB::selectOne("SELECT DATA_TYPE AS data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'catalog_presentation_settings' AND COLUMN_NAME = 'settings'")->data_type;
    expect(strtolower($type))->toBe('json');
    $this->freezeTime();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $categories = GroupTagCategory::factory()->count(2)->create();
    $service = new CatalogPresentation;
    $save = new SaveCatalogPresentationSettings($service);
    $settings = array_replace($service->defaults(), [
        'tag_counts' => false, 'group_card_content' => ['legacy_id', 'tags', 'description'],
        'group_card_tag_categories' => [$categories[1]->id, $categories[0]->id],
    ]);
    $record = $save->handle($actor, $settings, 'defaults:v1');
    DB::table('catalog_presentation_settings')->where('id', 1)->update(['settings' => json_encode(array_reverse($settings, true), JSON_THROW_ON_ERROR)]);
    $before = $record->fresh()->getAttributes();
    $this->travel(1)->minute();
    $writes = [];
    $publications = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$publications): void {
        $publications[] = $event->key;
    });

    $unchanged = $save->handle($actor, $settings, $record->version);

    expect($writes)->toBeEmpty();
    expect($publications)->toBeEmpty();
    expect($unchanged->version)->toBe($record->version);
    expect($record->fresh()->getAttributes())->toBe($before);
    expect($unchanged->settings['group_card_content'])->toBe($settings['group_card_content']);
    expect($unchanged->settings['group_card_tag_categories'])->toBe($settings['group_card_tag_categories']);
    $changed = array_replace($settings, [
        'group_card_content' => array_reverse($settings['group_card_content']),
        'group_card_tag_categories' => array_reverse($settings['group_card_tag_categories']),
    ]);
    $ordered = $save->handle($actor, $changed, $unchanged->version);
    expect($ordered->version)->not->toBe($unchanged->version);
    expect($ordered->fresh()->settings['group_card_content'])->toBe($changed['group_card_content']);
    expect($ordered->fresh()->settings['group_card_tag_categories'])->toBe($changed['group_card_tag_categories']);
    expect($publications)->toHaveCount(1);
    DB::table('catalog_presentation_settings')->where('id', 1)->update(['settings' => json_encode(array_replace($changed, ['tag_columns' => '2', 'tag_counts' => 0]), JSON_THROW_ON_ERROR)]);
    $invalidTypes = DB::selectOne("SELECT JSON_TYPE(JSON_EXTRACT(settings, '$.tag_columns')) AS columns_type, JSON_TYPE(JSON_EXTRACT(settings, '$.tag_counts')) AS counts_type FROM catalog_presentation_settings WHERE id = 1");
    expect($invalidTypes->columns_type)->toBe('STRING');
    expect($invalidTypes->counts_type)->toBe('INTEGER');
    $repaired = $save->handle($actor, $changed, $ordered->version);
    expect($repaired->version)->not->toBe($ordered->version);
    expect($repaired->fresh()->settings['tag_columns'])->toBe(2);
    expect($repaired->fresh()->settings['tag_counts'])->toBeFalse();
    expect($publications)->toHaveCount(2);
    $finalTypes = DB::selectOne("SELECT JSON_TYPE(JSON_EXTRACT(settings, '$.tag_columns')) AS columns_type, JSON_TYPE(JSON_EXTRACT(settings, '$.tag_counts')) AS counts_type FROM catalog_presentation_settings WHERE id = 1");
    expect($finalTypes->columns_type)->toBe('INTEGER');
    expect($finalTypes->counts_type)->toBe('BOOLEAN');
    recordCatalogPresentationMySqlProof('json-noop-and-types', [
        'storage_type' => $type, 'observed_top_level_keys' => array_keys($unchanged->settings),
        'no_op_raw_row_unchanged' => true, 'no_op_sql_writes' => 0, 'no_op_publications' => 0,
        'versions' => [$record->version, $ordered->version, $repaired->version],
        'content' => $repaired->settings['group_card_content'], 'categories' => $repaired->settings['group_card_tag_categories'],
        'invalid_types' => (array) $invalidTypes, 'final_types' => (array) $finalTypes,
    ]);
    $pdo = DB::connection()->getPdo();
    $identity = $pdo->query('SELECT CURRENT_USER() principal, DATABASE() selected_database, @@server_uuid server_uuid, @@hostname server_hostname, @@port server_port, VERSION() server_version, @@default_storage_engine default_engine, @@foreign_key_checks foreign_keys, @@sql_generate_invisible_primary_key invisible_primary_key, @@lower_case_table_names lower_case_table_names')->fetch(PDO::FETCH_ASSOC);
    if ($identity['selected_database'] !== 'configurator_reconciliation_test') {
        throw new RuntimeException('DDL shape capture requires the approved reconciliation TEST target.');
    }
    $names = "('groups','migrations','group_tag_categories','group_tag_values','group_tag_value','catalog_presentation_settings')";
    $shape = [
        'target_kind' => 'reconciliation_TEST', 'identity' => $identity,
        'purpose' => 'Actual TEST metadata only; any later Main shape comparison is separate and does not establish Main identity.',
    ];
    $queries = [
        'tables' => "SELECT TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN $names ORDER BY TABLE_NAME",
        'columns' => "SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN $names ORDER BY TABLE_NAME,ORDINAL_POSITION",
        'indexes' => "SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,COLLATION,INDEX_TYPE,EXPRESSION,IS_VISIBLE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN $names ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",
        'foreign_keys' => 'SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.ORDINAL_POSITION,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION',
        'constraints' => "SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN $names ORDER BY TABLE_NAME,CONSTRAINT_NAME",
        'triggers' => "SELECT EVENT_OBJECT_TABLE,TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN $names ORDER BY EVENT_OBJECT_TABLE,TRIGGER_NAME",
    ];
    foreach ($queries as $section => $sql) {
        $shape[$section] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    $shape['history'] = $pdo->query('SELECT id,migration,batch FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $previousMask = umask(0077);
    try {
        recordCatalogPresentationMySqlProof('ddl-shape', $shape);
    } finally {
        umask($previousMask);
    }
});

test('real enclosing rollback discards prepared presentation and only outer commit publishes the next version', function (bool $existing) {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $save = new SaveCatalogPresentationSettings($service);
    if ($existing) {
        $save->handle($actor, $service->defaults(), 'defaults:v1');
    }
    $before = $service->current();
    $rawBefore = CatalogPresentationSettings::query()->find(1)?->getAttributes();
    $connectionBefore = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    $publications = [];
    $rolledVersion = null;
    Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$publications): void {
        $publications[] = $event->key;
    });
    expect(function () use ($actor, $save, $service, $before, &$publications, &$rolledVersion): void {
        DB::transaction(function () use ($actor, $save, $service, $before, &$publications, &$rolledVersion): void {
            $record = $save->handle($actor, array_replace($service->defaults(), ['tag_counts' => false]), $before['version']);
            $rolledVersion = $record->version;
            expect($service->current()['version'])->toBe($record->version);
            expect($publications)->toBeEmpty();
            throw new RuntimeException('presentation outer rollback');
        });
    })->toThrow(RuntimeException::class, 'presentation outer rollback');
    expect($rolledVersion)->not->toBeNull();
    expect($rolledVersion)->not->toBe($before['version']);
    expect($publications)->toBeEmpty();
    DB::purge('mysql');
    assertCatalogMySqlSafety(app());
    $connectionAfter = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    expect($connectionAfter)->not->toBe($connectionBefore);
    expect(CatalogPresentationSettings::query()->find(1)?->getAttributes())->toBe($rawBefore);
    expect($service->current())->toBe($before);
    expect((new CatalogPresentation)->current())->toBe($before);
    $committed = DB::transaction(function () use ($actor, $save, $service, $before, &$publications): CatalogPresentationSettings {
        $record = $save->handle($actor, array_replace($service->defaults(), ['tag_counts' => false]), $before['version']);
        expect($service->current()['version'])->toBe($record->version);
        expect($publications)->toBeEmpty();

        return $record;
    });
    expect($publications)->toHaveCount(1);
    expect($committed->fresh()->version)->toBe($committed->version);
    expect($service->current()['version'])->toBe($committed->version);
    expect($committed->version)->not->toBe($rolledVersion);
    expect(DB::transactionLevel())->toBe(0);
    recordCatalogPresentationMySqlProof($existing ? 'rollback-existing' : 'rollback-first-save', [
        'before_version' => $before['version'], 'rolled_version' => $rolledVersion, 'committed_version' => $committed->version,
        'connection_ids' => [$connectionBefore, $connectionAfter], 'rollback_raw_unchanged' => true,
        'rollback_publications' => 0, 'outer_commit_publications' => count($publications), 'final_settings' => $committed->settings,
    ]);
})->with(['absent singleton' => [false], 'existing singleton' => [true]]);

test('reference selection and taxonomy removal serialize in both orders without partial writes', function (string $scope, bool $deleteFirst) {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create(['result_settings' => ['future' => false]])->fresh();
    $category = GroupTagCategory::factory()->create();
    $value = null;
    $selectedValueIds = [];
    $originalPivots = [];
    $selectedPivots = [];
    if ($scope === 'group-assignment') {
        $retainedFirst = GroupTagValue::factory()->for($category, 'category')->create();
        $retainedSecond = GroupTagValue::factory()->for($category, 'category')->create();
        $value = GroupTagValue::factory()->for($category, 'category')->create();
        $group->tagValues()->attach([
            $retainedFirst->id => ['position' => 4],
            $retainedSecond->id => ['position' => 11],
        ]);
        $selectedValueIds = [(int) $retainedSecond->id, (int) $value->id, (int) $retainedFirst->id];
        $originalPivots = [
            ['group_id' => (int) $group->id, 'group_tag_value_id' => (int) $retainedFirst->id, 'position' => 4],
            ['group_id' => (int) $group->id, 'group_tag_value_id' => (int) $retainedSecond->id, 'position' => 11],
        ];
        $selectedPivots = [
            ['group_id' => (int) $group->id, 'group_tag_value_id' => (int) $retainedSecond->id, 'position' => 0],
            ['group_id' => (int) $group->id, 'group_tag_value_id' => (int) $value->id, 'position' => 1],
            ['group_id' => (int) $group->id, 'group_tag_value_id' => (int) $retainedFirst->id, 'position' => 2],
        ];
    }
    $service = new CatalogPresentation;
    if ($scope === 'shared-existing') {
        (new SaveCatalogPresentationSettings($service))->handle($actor, $service->defaults(), 'defaults:v1');
    }
    $expectedVersion = $service->current()['version'];
    $groupFingerprint = $service->groupFingerprint($group);
    $tagFingerprint = app(SaveGroupTags::class)->fingerprint($group);
    $before = catalogPresentationRemovalState((int) $group->id, (int) $category->id, $value?->id);
    expect($before['pivot_rows'])->toBe($originalPivots);
    $select = function () use ($scope, $actor, $group, $category, $selectedValueIds, $expectedVersion, $groupFingerprint, $tagFingerprint): void {
        $actor = User::query()->findOrFail($actor->id);
        if (str_starts_with($scope, 'shared-')) {
            $service = new CatalogPresentation;
            (new SaveCatalogPresentationSettings($service))->handle($actor,
                array_replace($service->defaults(), ['group_card_tag_categories' => [(int) $category->id], 'tag_counts' => false]), $expectedVersion);
        } elseif ($scope === 'group-override') {
            app(SaveGroupSettings::class)->patchPresentation($actor, $group,
                ['catalog_presentation' => ['group_card_tag_categories' => [(int) $category->id]]], [], $groupFingerprint);
        } else {
            app(SaveGroupTags::class)->assign($actor, $group, $selectedValueIds, $tagFingerprint);
        }
    };
    $delete = function () use ($actor, $category, $value): void {
        $writer = app(SaveGroupTags::class);
        if ($value !== null) {
            $writer->removeValue(User::query()->findOrFail($actor->id), $value);
        } else {
            $writer->removeCategory(User::query()->findOrFail($actor->id), $category);
        }
    };
    $winner = $deleteFirst ? $delete : $select;
    $loser = $deleteFirst ? $select : $delete;
    $stem = ($value === null ? 'category-removal-' : 'value-removal-').$scope.($deleteFirst ? '-deletion-first' : '-selection-first');
    $command = str_starts_with($scope, 'shared-')
        ? ($deleteFirst && $scope === 'shared-absent' ? 'insert-singleton' : 'lock-singleton') : 'lock-groups';
    $workers = [];
    $results = [];
    $joins = [];
    $admission = [];
    $probe = null;
    $observedWritten = false;
    try {
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($winner, $group, $category, $value): array {
            $result = catalogContentionResult(fn (): array => DB::transaction(function () use ($winner, $channel, $group, $category, $value): array {
                $winner();
                writeCatalogContentionMessage($channel, ['phase' => 'authoritative-winner-held']);
                expect(readCatalogContentionMessage($channel))->toBe(['phase' => 'release']);

                return catalogPresentationRemovalState((int) $group->id, (int) $category->id, $value?->id);
            }));

            return [...$result, 'transaction_level' => DB::transactionLevel()];
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'authoritative-winner-held']);
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($loser, $command): array {
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, $command): void {
                if (catalogPresentationMatchesCommand($sql, $command)) {
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting-authoritative-lock']);
                }
            });
            $result = catalogContentionResult($loser);

            return [...$result, 'transaction_level' => DB::transactionLevel()];
        });
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting-authoritative-lock']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            DB::transaction(function () use ($deleteFirst, $scope, $value, $category, $group): void {
                if ($deleteFirst) {
                    ($value === null ? GroupTagCategory::query() : GroupTagValue::query())->whereKey($value?->id ?? $category->id)->lockForUpdate()->first();
                } elseif (str_starts_with($scope, 'shared-')) {
                    CatalogPresentationSettings::query()->whereKey(1)->lockForUpdate()->first();
                } else {
                    Group::query()->whereKey($group->id)->lockForUpdate()->first();
                }
            });
            throw new RuntimeException('The authoritative winner did not retain its lock.');
        } catch (QueryException $exception) {
            $probe = ['sqlstate' => $exception->errorInfo[0], 'driver_code' => (int) $exception->errorInfo[1]];
            expect((int) $exception->errorInfo[1])->toBe(1205);
        }
        observeCatalogPresentationCommandAdmission($workers[1]['connection_id'], $command, $admission);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        foreach ($workers as $worker) {
            $results[] = readCatalogContentionMessage($worker['channel']);
        }
        foreach ($workers as $worker) {
            $joins[] = joinCatalogPresentationWorker($worker);
        }
        $observed = ['state' => 'observed-before-outcome-assertions', 'scope' => $scope, 'delete_first' => $deleteFirst,
            'results' => sanitizeCatalogPresentationResults($results), 'connection_ids' => array_column($workers, 'connection_id'),
            'worker_pids' => array_column($workers, 'pid'), 'admission' => $admission, 'retained_lock_probe' => $probe, 'child_join_outcomes' => $joins,
            'original_pivot_rows' => $originalPivots, 'expected_winner_pivot_rows' => $deleteFirst ? $originalPivots : $selectedPivots];
        recordCatalogPresentationMySqlProof($stem.'-observed', $observed);
        $observedWritten = true;
        expect($results[0]['status'])->toBe('committed');
        expect($results[1]['status'])->toBe('rejected');
        expect($results[1]['errors'])->toHaveKey($deleteFirst
            ? ($scope === 'group-assignment' ? 'tag_values' : (str_starts_with($scope, 'shared-') ? 'settings.group_card_tag_categories' : 'settings.result_settings.catalog_presentation.group_card_tag_categories'))
            : ($value === null ? 'tag_category' : 'tag_value'));
        foreach ($results as $result) {
            expect($result['transaction_level'])->toBe(0);
        }
        foreach ($joins as $join) {
            expect($join['joined'])->toBeTrue();
            expect($join['exited'])->toBeTrue();
            expect($join['exit_code'])->toBe(0);
        }
        DB::purge('mysql');
        assertCatalogMySqlSafety(app());
        $after = catalogPresentationRemovalState((int) $group->id, (int) $category->id, $value?->id);
        expect($after)->toBe($results[0]['value']);
        if ($deleteFirst) {
            expect($after['group'])->toBe($before['group']);
            expect($after['shared'])->toBe($before['shared']);
            expect($after['assigned_ids'])->toBe($before['assigned_ids']);
            expect($after['pivot_rows'])->toBe($originalPivots);
            expect($after['pivot_rows'])->toBe($before['pivot_rows']);
            expect($value === null ? $after['category_exists'] : $after['value_exists'])->toBeFalse();
        } else {
            expect($after['category_exists'])->toBeTrue();
            if ($scope === 'group-assignment') {
                expect($after['assigned_ids'])->toBe($selectedValueIds);
                expect($after['pivot_rows'])->toBe($selectedPivots);
                expect($after['group'])->toBe($before['group']);
            } elseif ($scope === 'group-override') {
                expect((int) $after['group']['catalog_revision'])->toBe((int) $before['group']['catalog_revision'] + 1);
                expect(Group::query()->findOrFail($group->id)->result_settings)->toBe(['future' => false, 'catalog_presentation' => ['group_card_tag_categories' => [(int) $category->id]]]);
            } else {
                expect($after['group'])->toBe($before['group']);
                expect((new CatalogPresentation)->current()['settings']['group_card_tag_categories'])->toBe([(int) $category->id]);
            }
        }
        expect(DB::transactionLevel())->toBe(0);
        recordCatalogPresentationMySqlProof($stem, [...$observed, 'state' => 'passed-outcome-assertions',
            'fresh_readback_matches_winner_raw' => true, 'group_revision' => $after['group']['catalog_revision'],
            'category_exists' => $after['category_exists'], 'value_exists' => $after['value_exists'], 'assigned_ids' => $after['assigned_ids'], 'pivot_rows' => $after['pivot_rows']]);
    } catch (Throwable $exception) {
        foreach ($workers as $worker) {
            if (! in_array($worker['pid'], array_column($joins, 'pid'), true)) {
                $joins[] = joinCatalogPresentationWorker($worker);
            }
        }
        try {
            recordCatalogPresentationMySqlProof($stem.($observedWritten ? '-failed' : '-observed'), [
                'state' => 'failed', 'results' => sanitizeCatalogPresentationResults($results), 'admission' => $admission,
                'retained_lock_probe' => $probe, 'child_join_outcomes' => $joins, 'failure_class' => $exception::class,
                'connection_ids' => array_column($workers, 'connection_id'), 'worker_pids' => array_column($workers, 'pid'),
            ]);
        } catch (Throwable $evidenceException) {
            fwrite(STDERR, 'K2 observed evidence unavailable ('.$evidenceException::class.'); original failure retained.'.PHP_EOL);
        }
        throw $exception;
    } finally {
        stopCatalogContentionWorkers($workers);
    }
})->with(['shared-absent', 'shared-existing', 'group-override', 'group-assignment'])->with(['selection first' => [false], 'deletion first' => [true]]);

test('an actual card read keeps one display pair during a concurrent shared-only commit', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $isolation = DB::selectOne('SELECT @@session.transaction_isolation AS isolation_level')->isolation_level;
    expect(strtoupper($isolation))->toBe('REPEATABLE-READ');
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create()->fresh();
    Product::factory()->for($group)->create();
    $service = new CatalogPresentation;
    $initial = (new SaveCatalogPresentationSettings($service))->handle($actor, $service->defaults(), 'defaults:v1');
    $snapshot = app(CatalogSnapshots::class)->get((int) $group->id);
    $criteria = ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []];
    $workers = [];
    $joins = [];
    $writerResult = [];
    $fired = false;
    $observedWritten = false;
    try {
        $workers[] = startCatalogContentionWorker(function () use ($actor, $initial): array {
            $result = catalogContentionResult(function () use ($actor, $initial): string {
                $service = new CatalogPresentation;

                return (new SaveCatalogPresentationSettings($service))->handle(User::query()->findOrFail($actor->id),
                    array_replace($service->defaults(), ['product_image_placement' => 'right']), $initial->version)->version;
            });

            return [...$result, 'transaction_level' => DB::transactionLevel()];
        });
        DB::listen(function (QueryExecuted $query) use (&$fired, &$writerResult, &$workers): void {
            if ($fired || $query->connection->transactionLevel() === 0 || ! str_contains(strtolower($query->sql), 'from `groups` left join `configurators`')) {
                return;
            }
            $fired = true;
            writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
            $writerResult = readCatalogContentionMessage($workers[0]['channel']);
        });
        $first = app(CatalogCards::class)->get((int) $group->id, $criteria, $snapshot->revision(), 'coherent-first', $initial->version);
        $joins[] = joinCatalogPresentationWorker($workers[0]);
        $observed = ['state' => 'observed-before-outcome-assertions', 'isolation' => $isolation,
            'real_consumer' => CatalogCards::class, 'midread_barrier_fired' => $fired,
            'writer' => sanitizeCatalogPresentationResults([$writerResult]), 'first_pair' => [
                'revision' => $first['revision'], 'display_version' => $first['displayVersion'], 'placement' => $first['presentation']['product_image_placement'] ?? null,
            ], 'connection_ids' => array_column($workers, 'connection_id'), 'worker_pids' => array_column($workers, 'pid'), 'child_join_outcomes' => $joins];
        recordCatalogPresentationMySqlProof('display-read-coherence-observed', $observed);
        $observedWritten = true;
        expect($fired)->toBeTrue();
        expect($writerResult['status'])->toBe('committed');
        expect($writerResult['transaction_level'])->toBe(0);
        expect($first['status'])->toBe('ready');
        expect($first['displayVersion'])->toBe($initial->version);
        expect($first['presentation']['product_image_placement'])->toBe('left');
        expect($first['revision'])->toBe($snapshot->revision());
        expect($joins[0]['joined'])->toBeTrue();
        expect($joins[0]['exited'])->toBeTrue();
        expect($joins[0]['exit_code'])->toBe(0);
        DB::purge('mysql');
        assertCatalogMySqlSafety(app());
        $stale = app(CatalogCards::class)->get((int) $group->id, $criteria, $snapshot->revision(), 'coherent-stale', $initial->version);
        expect($stale['status'])->toBe('refresh_required');
        expect($stale['htmlChunks'])->toBe([]);
        expect($stale['displayVersion'])->toBe($writerResult['value']);
        $next = app(CatalogCards::class)->get((int) $group->id, $criteria, $snapshot->revision(), 'coherent-next', $writerResult['value']);
        expect($next['status'])->toBe('ready');
        expect($next['presentation']['product_image_placement'])->toBe('right');
        expect($next['displayVersion'])->not->toBe($initial->version);
        expect($next['revision'])->toBe($snapshot->revision());
        expect(app(CatalogSnapshots::class)->cached((int) $group->id, $snapshot->revision())->data)->toBe($snapshot->data);
        expect(DB::transactionLevel())->toBe(0);
        recordCatalogPresentationMySqlProof('display-read-coherence', [...$observed, 'state' => 'passed-outcome-assertions',
            'next_pair' => ['revision' => $next['revision'], 'display_version' => $next['displayVersion'], 'placement' => $next['presentation']['product_image_placement']],
            'stale_pair_returned_no_chunks' => true, 'business_snapshot_unchanged' => true]);
    } catch (Throwable $exception) {
        foreach ($workers as $worker) {
            if (! in_array($worker['pid'], array_column($joins, 'pid'), true)) {
                $joins[] = joinCatalogPresentationWorker($worker);
            }
        }
        try {
            recordCatalogPresentationMySqlProof('display-read-coherence'.($observedWritten ? '-failed' : '-observed'), [
                'state' => 'failed', 'writer' => sanitizeCatalogPresentationResults([$writerResult]), 'midread_barrier_fired' => $fired,
                'child_join_outcomes' => $joins, 'failure_class' => $exception::class,
                'connection_ids' => array_column($workers, 'connection_id'), 'worker_pids' => array_column($workers, 'pid'),
            ]);
        } catch (Throwable $evidenceException) {
            fwrite(STDERR, 'K2 observed evidence unavailable ('.$evidenceException::class.'); original failure retained.'.PHP_EOL);
        }
        throw $exception;
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});

/** @return array<string,mixed> */
function catalogPresentationRemovalState(int $groupId, int $categoryId, ?int $valueId): array
{
    return ['group' => Group::query()->findOrFail($groupId)->getAttributes(), 'shared' => CatalogPresentationSettings::query()->find(1)?->getAttributes(),
        'assigned_ids' => Group::query()->findOrFail($groupId)->tagValues()->pluck('group_tag_values.id')->map(fn (mixed $id): int => (int) $id)->all(),
        'pivot_rows' => DB::table('group_tag_value')->where('group_id', $groupId)->orderBy('position')->orderBy('group_tag_value_id')
            ->get(['group_id', 'group_tag_value_id', 'position'])->map(fn (object $row): array => (array) $row)->all(),
        'category_exists' => GroupTagCategory::query()->whereKey($categoryId)->exists(),
        'value_exists' => $valueId === null ? null : GroupTagValue::query()->whereKey($valueId)->exists()];
}

function catalogPresentationMatchesCommand(string $sql, string $command): bool
{
    $sql = strtolower(ltrim($sql));

    return match ($command) {
        'insert-singleton' => str_starts_with($sql, 'insert into `catalog_presentation_settings`') && str_contains($sql, 'on duplicate key update `id` ='),
        'lock-singleton' => str_starts_with($sql, 'select ') && str_contains($sql, 'from `catalog_presentation_settings`') && str_contains($sql, 'for update'),
        'lock-groups' => str_starts_with($sql, 'select ') && str_contains($sql, 'from `groups`') && str_contains($sql, 'for update'),
        default => throw new InvalidArgumentException('Unknown owned contention command.'),
    };
}

/** @param array<string,mixed> $observation */
function observeCatalogPresentationCommandAdmission(int $connectionId, string $command, array &$observation): void
{
    [$prefix, $fragment, $suffix] = match ($command) {
        'insert-singleton' => ['insert into `catalog_presentation_settings`', 'on duplicate key update `id` =', ''],
        'lock-singleton' => ['select ', 'from `catalog_presentation_settings`', 'for update'],
        'lock-groups' => ['select ', 'from `groups`', 'for update'],
        default => throw new InvalidArgumentException('Unknown owned contention command.'),
    };
    $deadline = hrtime(true) + 2_000_000_000;
    $observation = ['state' => 'pending', 'connection_id' => $connectionId, 'command_kind' => $command, 'polls' => 0, 'session' => null];
    do {
        $row = DB::table('information_schema.PROCESSLIST')->where('ID', $connectionId)->where('DB', DB::connection()->getDatabaseName())
            ->select(['ID as connection_id', 'COMMAND as command', 'STATE as state'])
            ->selectRaw('CASE WHEN LOCATE(?, LOWER(LTRIM(INFO))) = 1 AND LOCATE(?, LOWER(INFO)) > 0 AND LOCATE(?, LOWER(INFO)) > 0 THEN 1 ELSE 0 END AS intended_command', [$prefix, $fragment, $suffix])->first();
        $observation['polls']++;
        $observation['session'] = $row === null ? null : ['connection_id' => (int) $row->connection_id, 'command' => $row->command, 'state' => $row->state, 'intended_command' => (bool) $row->intended_command];
        if ($row !== null && (bool) $row->intended_command && in_array($row->command, ['Query', 'Execute'], true)) {
            $observation['state'] = 'owned-command-in-server-execution-under-held-winner-lock';

            return;
        }
        usleep(10_000);
    } while (hrtime(true) < $deadline);
    $observation['state'] = 'admission-timeout';
    throw new RuntimeException('The owned contender did not enter its intended server command.');
}

/**
 * Query only owned session IDs; statement text is reduced to a boolean inside MySQL.
 *
 * @param  list<int>  $connectionIds
 * @param  array<string,mixed>  $observation
 */
function observeCatalogPresentationInsertAdmission(array $connectionIds, array &$observation): void
{
    $deadline = hrtime(true) + 2_000_000_000;
    $observation = ['state' => 'pending', 'owned_connection_ids' => $connectionIds, 'polls' => 0, 'sessions' => []];
    do {
        $sessions = DB::table('information_schema.PROCESSLIST')->whereIn('ID', $connectionIds)
            ->where('DB', DB::connection()->getDatabaseName())->orderBy('ID')
            ->select(['ID as connection_id', 'COMMAND as command', 'STATE as state'])
            ->selectRaw('CASE WHEN LOCATE(?, LOWER(LTRIM(INFO))) = 1 AND LOCATE(?, LOWER(INFO)) > 0 THEN 1 ELSE 0 END AS singleton_insert', [
                'insert into `catalog_presentation_settings`', 'on duplicate key update `id` =',
            ])
            ->get()->map(fn (object $row): array => [
                'connection_id' => (int) $row->connection_id, 'command' => $row->command,
                'state' => $row->state, 'singleton_insert' => (bool) $row->singleton_insert,
            ])->all();
        $observation['polls']++;
        $observation['sessions'] = $sessions;
        if (count($sessions) === count($connectionIds) && array_filter($sessions,
            fn (array $session): bool => ! $session['singleton_insert'] || ! in_array($session['command'], ['Query', 'Execute'], true)) === []) {
            $observation['state'] = 'all-owned-inserts-in-server-execution-under-held-winner-lock';

            return;
        }
        usleep(10_000);
    } while (hrtime(true) < $deadline);

    $observation['state'] = 'admission-timeout';
    throw new RuntimeException('Not all owned singleton inserts reached MySQL before the bounded admission deadline.');
}

/** @param list<array<string,mixed>> $results @return list<array<string,mixed>> */
function sanitizeCatalogPresentationResults(array $results): array
{
    return array_map(fn (array $result): array => [
        'status' => $result['status'] ?? null, 'class' => $result['class'] ?? null,
        'sqlstate' => $result['sqlstate'] ?? null, 'driver_code' => $result['driver_code'] ?? null,
        'error_fields' => array_keys($result['errors'] ?? []),
        'value' => $result['value'] ?? null, 'cache_publications' => $result['cache_publications'] ?? null,
        'transaction_level' => $result['transaction_level'] ?? null,
    ], $results);
}

/**
 * @param  array{pid:int,channel:mixed,connection_id:int}  $worker
 * @return array{pid:int,joined:bool,exited:bool,exit_code:?int,signal:?int,timed_out:bool,wait_error:bool}
 */
function joinCatalogPresentationWorker(array $worker): array
{
    $deadline = hrtime(true) + 3_000_000_000;
    do {
        $joined = pcntl_waitpid($worker['pid'], $status, WNOHANG);
        if ($joined === $worker['pid']) {
            return [
                'pid' => $worker['pid'], 'joined' => true, 'exited' => pcntl_wifexited($status),
                'exit_code' => pcntl_wifexited($status) ? pcntl_wexitstatus($status) : null,
                'signal' => pcntl_wifsignaled($status) ? pcntl_wtermsig($status) : null, 'timed_out' => false, 'wait_error' => false,
            ];
        }
        if ($joined === -1) {
            return ['pid' => $worker['pid'], 'joined' => false, 'exited' => false, 'exit_code' => null, 'signal' => null, 'timed_out' => false, 'wait_error' => true];
        }
        usleep(10_000);
    } while (hrtime(true) < $deadline);

    return ['pid' => $worker['pid'], 'joined' => false, 'exited' => false, 'exit_code' => null, 'signal' => null, 'timed_out' => true, 'wait_error' => false];
}

/** @param array<string,mixed> $proof */
function recordCatalogPresentationMySqlProof(string $name, array $proof): void
{
    $directory = getenv('CATALOG_K2_EVIDENCE_DIR');
    if (! is_string($directory) || ! is_dir($directory) || ! is_writable($directory)) {
        throw new RuntimeException('The approved launcher must supply a private fresh K2 evidence directory.');
    }
    $file = fopen($directory.'/mysql-'.$name.'.json', 'x');
    if ($file === false) {
        throw new RuntimeException('Could not create exclusive K2 MySQL proof.');
    }
    try {
        $content = json_encode($proof, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
        if (fwrite($file, $content) !== strlen($content)) {
            throw new RuntimeException('Could not finish writing K2 MySQL proof.');
        }
    } finally {
        fclose($file);
    }
}
