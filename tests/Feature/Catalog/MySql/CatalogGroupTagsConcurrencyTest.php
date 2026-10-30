<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\SaveGroupTags;
use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogNavigation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

test('independent assignments serialize on Group locks and reject the competing stale fingerprint', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $values = GroupTagValue::factory()->count(3)->create();
    $save = app(SaveGroupTags::class);
    $save->assign($actor, $group, [$values[0]->id], $save->fingerprint($group));
    $fingerprint = $save->fingerprint($group);
    $before = $group->fresh()->getAttributes();
    $workers = [];
    try {
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $group, $values, $fingerprint): array {
            expect(DB::transactionLevel())->toBe(0);
            $locks = [];
            DB::listen(function (QueryExecuted $query) use ($channel, &$locks): void {
                if (! str_contains($query->sql, 'for update')) {
                    return;
                }
                foreach (['groups', 'group_tag_categories', 'group_tag_values', 'group_tag_value'] as $table) {
                    if (str_contains($query->sql, 'from `'.$table.'`')) {
                        $locks[] = $table;
                        if ($table === 'groups') {
                            writeCatalogContentionMessage($channel, ['phase' => 'groups_locked']);
                            readCatalogContentionMessage($channel);
                        }
                        break;
                    }
                }
            });
            $result = catalogContentionResult(fn () => app(SaveGroupTags::class)->assign(User::findOrFail($actor->id), Group::findOrFail($group->id), [$values[1]->id], $fingerprint)->id);

            return [...$result, 'locks' => $locks];
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'groups_locked']);
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $group, $values, $fingerprint): array {
            expect(DB::transactionLevel())->toBe(0);
            DB::connection()->beforeExecuting(function (string $sql) use ($channel): void {
                if (str_contains($sql, 'from `groups`') && str_contains($sql, 'for update')) {
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting_group_lock']);
                }
            });

            return catalogContentionResult(fn () => app(SaveGroupTags::class)->assign(User::findOrFail($actor->id), Group::findOrFail($group->id), [$values[2]->id], $fingerprint)->id);
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting_group_lock']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            DB::transaction(fn () => Group::whereKey($group->id)->lockForUpdate()->firstOrFail());
            throw new RuntimeException('The independent writer did not retain the Group lock.');
        } catch (QueryException $exception) {
            expect((int) $exception->errorInfo[1])->toBe(1205);
        }
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first['status'])->toBe('committed')->and($first['value'])->toBe($group->id)
            ->and($first['locks'])->toBe(['groups', 'group_tag_categories', 'group_tag_values', 'group_tag_value'])
            ->and($second['status'])->toBe('rejected')->and($second['errors'])->toHaveKey('tag_values');
        foreach ($workers as $worker) {
            expect(pcntl_waitpid($worker['pid'], $status))->toBe($worker['pid'])
                ->and(pcntl_wifexited($status))->toBeTrue()->and(pcntl_wexitstatus($status))->toBe(0);
        }
        DB::purge('mysql');
        assertCatalogMySqlSafety(app());
        expect($group->tagValues()->pluck('group_tag_values.id')->all())->toBe([$values[1]->id])
            ->and($group->fresh()->getAttributes())->toBe($before)
            ->and($save->fingerprint($group))->not->toBe($fingerprint)
            ->and(DB::transactionLevel())->toBe(0);
        recordK1MySqlProof('assignments', ['first' => $first, 'second' => $second, 'child_pids' => array_column($workers, 'pid'), 'connection_ids' => array_column($workers, 'connection_id'), 'final_value_ids' => $group->tagValues()->pluck('group_tag_values.id')->all(), 'final_fingerprint' => $save->fingerprint($group), 'business_row_unchanged' => $group->fresh()->getAttributes() === $before, 'children_joined_exit_zero' => true]);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});

test('an enclosing rollback restores assignment and fingerprint without manual transaction escapes', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $value = GroupTagValue::factory()->create();
    $save = app(SaveGroupTags::class);
    $fingerprint = $save->fingerprint($group);
    expect(fn () => DB::transaction(function () use ($actor, $group, $value, $save, $fingerprint): void {
        $save->assign($actor, $group, [$value->id], $fingerprint);
        throw new RuntimeException('Synthetic enclosing rollback.');
    }))->toThrow(RuntimeException::class, 'Synthetic enclosing rollback.');
    expect($save->fingerprint($group))->toBe($fingerprint)->and($group->tagValues()->count())->toBe(0)
        ->and(DB::transactionLevel())->toBe(0);
});

/** @param array<string,mixed> $proof */
function recordK1MySqlProof(string $name, array $proof): void
{
    $directory = getenv('CATALOG_K1_EVIDENCE_DIR');
    if (is_string($directory) && is_dir($directory)) {
        file_put_contents($directory.'/mysql-'.$name.'.json', json_encode($proof, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL);
    }
}

test('a navigation read stays coherent across an independently committed mid-read hierarchy Product and tag change', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $isolation = DB::selectOne('SELECT @@session.transaction_isolation AS isolation_level')->isolation_level;
    expect(strtoupper($isolation))->toBe('REPEATABLE-READ');
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $originalParent = Group::factory()->create(['name' => 'Original parent']);
    $newParent = Group::factory()->create(['name' => 'New parent']);
    $model = Group::factory()->for($originalParent, 'parent')->create(['name' => 'Before commit']);
    Product::factory()->for($model)->create();
    $old = GroupTagValue::factory()->create(['label' => 'Before tag']);
    $new = GroupTagValue::factory()->create(['group_tag_category_id' => $old->group_tag_category_id, 'label' => 'After tag']);
    $save = app(SaveGroupTags::class);
    $save->assign($actor, $model, [$old->id], $save->fingerprint($model));
    $fingerprint = $save->fingerprint($model);
    $workers = [];
    try {
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $model, $newParent, $new, $fingerprint): array {
            expect(DB::transactionLevel())->toBe(0);

            return catalogContentionResult(fn () => DB::transaction(function () use ($actor, $model, $newParent, $new, $fingerprint): array {
                app(SaveGroupTags::class)->assign(User::findOrFail($actor->id), Group::findOrFail($model->id), [$new->id], $fingerprint);
                DB::table('groups')->where('id', $model->id)->update(['parent_id' => $newParent->id, 'name' => 'After commit']);
                Product::factory()->create(['group_id' => $model->id, 'product_code' => 'K1-SNAPSHOT-COMMITTED']);

                return ['parent_id' => $newParent->id, 'value_id' => $new->id, 'products' => 2];
            }));
        });
        $changed = false;
        $mutation = null;
        $readerTransaction = null;
        DB::listen(function (QueryExecuted $query) use (&$changed, &$mutation, &$readerTransaction, $workers): void {
            if ($changed || ! str_contains($query->sql, 'left join `configurators`')) {
                return;
            }
            $changed = true;
            $readerTransaction = DB::transactionLevel();
            writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'commit_during_read']);
            $mutation = readCatalogContentionMessage($workers[0]['channel']);
        });
        $navigation = app(CatalogNavigation::class);
        $first = $navigation->get($actor, ['requestId' => 'before-mid-read-commit']);
        expect($changed)->toBeTrue()->and($readerTransaction)->toBe(1)
            ->and($mutation['status'])->toBe('committed')
            ->and($first['cards'][0]['name'])->toBe('Before commit')
            ->and($first['cards'][0]['parentId'])->toBe($originalParent->id)
            ->and($first['cards'][0]['productCount'])->toBe(1)
            ->and($first['tags'][0]['values'][0]['id'])->toBe($old->id);
        expect(pcntl_waitpid($workers[0]['pid'], $status))->toBe($workers[0]['pid'])
            ->and(pcntl_wifexited($status))->toBeTrue()->and(pcntl_wexitstatus($status))->toBe(0);
        $fresh = $navigation->get($actor, ['requestId' => 'fresh-after-commit']);
        expect($fresh['cards'][0]['name'])->toBe('After commit')
            ->and($fresh['cards'][0]['parentId'])->toBe($newParent->id)
            ->and($fresh['cards'][0]['productCount'])->toBe(2)
            ->and($fresh['tags'][0]['values'][0]['id'])->toBe($new->id)
            ->and($fresh['treeVersion'])->not->toBe($first['treeVersion'])
            ->and($model->fresh()->parent_id)->toBe($newParent->id)
            ->and($model->products()->count())->toBe(2)
            ->and($model->tagValues()->pluck('group_tag_values.id')->all())->toBe([$new->id])
            ->and(DB::transactionLevel())->toBe(0);
        recordK1MySqlProof('snapshot', ['framework_session_isolation' => $isolation, 'reader_transaction_level' => $readerTransaction,
            'child_pid' => $workers[0]['pid'], 'child_connection_id' => $workers[0]['connection_id'], 'child_exit' => pcntl_wexitstatus($status),
            'committed_mutation' => $mutation, 'first' => $first, 'fresh' => $fresh,
            'limit' => 'Proves the tested REPEATABLE-READ MySQL connection; not arbitrary isolation levels or a main-data connection check.']);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});
