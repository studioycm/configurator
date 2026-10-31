<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Actions\SaveGroupAttributes;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupAttributes;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

test('real JSON preserves string lists and independent Group attribute writers reject the stale contender without resetting retained data', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $prefix = getenv('CATALOG_GA1_FIXTURE_PREFIX');
    if (! is_string($prefix) || preg_match('/\AGA1-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1) {
        throw new RuntimeException('This non-resetting proof requires its separately approved unique GA1 fixture prefix.');
    }
    $email = 'ycm@data4.work';
    $matches = User::query()->whereRaw('LOWER(email) = ?', [$email])->limit(2)->get();
    if ($matches->count() > 1 || ($matches->isNotEmpty() && $matches->first()->email !== $email)) {
        throw new RuntimeException('The approved manager email has conflicting or case-colliding records; no User may be changed.');
    }
    $name = $prefix.'-group';
    expect(Group::query()->where('name', $name)->exists())->toBeFalse();
    $createdActor = $matches->isEmpty();
    $actor = $matches->first() ?? User::factory()->create(['name' => $prefix.'-manager', 'email' => $email]);
    $group = null;
    $workers = [];
    try {
        Gate::forUser($actor)->authorize('manage-catalog');
        $actorBefore = $actor->fresh()->getAttributes();
        $group = Group::factory()->create(['name' => $name]);
        $attributes = app(GroupAttributes::class);
        $save = app(SaveGroupAttributes::class);
        expect($group->fresh()->getRawOriginal('catalog_attributes'))->toBeNull();
        $values = ['0', '001', 'Case', 'case', 'מים Multi Word'];
        $save->handle($actor, $group, ['application' => $values], $attributes->fingerprint($group));
        $types = DB::selectOne("SELECT JSON_TYPE(catalog_attributes) AS root_type, JSON_TYPE(JSON_EXTRACT(catalog_attributes, '$.application')) AS list_type, JSON_TYPE(JSON_EXTRACT(catalog_attributes, '$.application[0]')) AS value_type FROM `groups` WHERE id = ?", [$group->id]);
        expect([$types->root_type, $types->list_type, $types->value_type])->toBe(['OBJECT', 'ARRAY', 'STRING'])
            ->and($attributes->document($group->fresh()))->toBe(['application' => $values]);
        $save->handle($actor, $group, ['application' => []], $attributes->fingerprint($group->fresh()));
        expect(json_decode($group->fresh()->getRawOriginal('catalog_attributes')))->toBeInstanceOf(stdClass::class);
        $fingerprint = $attributes->fingerprint($group->fresh());
        $before = $group->fresh()->getAttributes();
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $group, $fingerprint): array {
            expect(DB::transactionLevel())->toBe(0);
            $locks = [];
            DB::listen(function (QueryExecuted $query) use ($channel, &$locks): void {
                if (str_contains($query->sql, 'for update')) {
                    $locks[] = $query->sql;
                    writeCatalogContentionMessage($channel, ['phase' => 'group_locked']);
                    expect(readCatalogContentionMessage($channel))->toBe(['phase' => 'release']);
                }
            });
            $result = catalogContentionResult(fn () => app(SaveGroupAttributes::class)->handle(User::findOrFail($actor->id), Group::findOrFail($group->id), ['application' => ['Winner']], $fingerprint)->id);

            return [...$result, 'locks' => $locks];
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'group_locked']);
        $workers[] = startCatalogContentionWorker(function (mixed $channel) use ($actor, $group, $fingerprint): array {
            expect(DB::transactionLevel())->toBe(0);
            DB::connection()->beforeExecuting(function (string $sql) use ($channel): void {
                if (str_contains($sql, 'from `groups`') && str_contains($sql, 'for update')) {
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting_group_lock']);
                }
            });

            return catalogContentionResult(fn () => app(SaveGroupAttributes::class)->handle(User::findOrFail($actor->id), Group::findOrFail($group->id), ['application' => ['Stale']], $fingerprint)->id);
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting_group_lock']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            DB::transaction(fn () => Group::whereKey($group->id)->lockForUpdate()->firstOrFail());
            throw new RuntimeException('The first independent writer did not retain the Group lock.');
        } catch (QueryException $exception) {
            expect((int) $exception->errorInfo[1])->toBe(1205);
        }
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first['status'])->toBe('committed')->and($first['value'])->toBe($group->id)
            ->and($first['locks'])->toHaveCount(1)->and($first['locks'][0])->toContain('from `groups`')
            ->and($second['status'])->toBe('rejected')->and($second['errors'])->toHaveKey('catalog_attribute_fingerprint');
        foreach ($workers as $worker) {
            expect(pcntl_waitpid($worker['pid'], $status))->toBe($worker['pid'])
                ->and(pcntl_wifexited($status))->toBeTrue()->and(pcntl_wexitstatus($status))->toBe(0);
        }
        DB::purge('mysql');
        assertCatalogMySqlSafety(app());
        $after = $group->fresh();
        expect($attributes->document($after))->toBe(['application' => ['Winner']])
            ->and((int) $after->catalog_revision)->toBe((int) $before['catalog_revision'] + 1)
            ->and(array_diff_key($after->getAttributes(), array_flip(['catalog_attributes', 'catalog_revision', 'updated_at'])))
            ->toBe(array_diff_key($before, array_flip(['catalog_attributes', 'catalog_revision', 'updated_at'])))
            ->and($actor->fresh()->getAttributes())->toBe($actorBefore)
            ->and(DB::transactionLevel())->toBe(0);
    } finally {
        stopCatalogContentionWorkers($workers);
        if ($group !== null) {
            DB::table('groups')->where('id', $group->id)->where('name', $name)->delete();
        }
        if ($createdActor) {
            DB::table('users')->where('id', $actor->id)->where('email', $email)->where('name', $prefix.'-manager')->delete();
        }
    }
    expect(Group::query()->whereKey($group->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($actor->id)->exists())->toBe(! $createdActor);
});
