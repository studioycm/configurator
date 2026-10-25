<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

use App\Actions\AssignConfiguratorGroups;
use App\Actions\SaveCatalogProduct;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveTestedConfiguratorChanges;
use App\Models\ConfiguratorRule;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorRuleDraft;
use App\Services\ProductEditorData;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/** @return array<string, mixed> */
function previewContentionFixture(): array
{
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    test()->actingAs($actor);
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($actor, $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10', 'Material' => 'Metal Shell', 'a.b' => 'literal']])->fresh();
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $rule = fixtureAdvanced('contention-rule', [], 'B', 'SetHint', [], 'Saved exactly once');
    $rule['effects'][0]['target_configurator_attribute_id'] = $saved['attributes'][1]['id'];
    $capture = app(ConfiguratorEditorChanges::class)->capture(auth()->user(), $configurator->id, [
        ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Preview committed']],
        ['type' => 'attribute', 'id' => $saved['attributes'][0]['id'], 'before' => $saved['attributes'][0], 'after' => [...$saved['attributes'][0], 'label_override' => 'Preview attribute']],
        ['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($rule)],
    ]);
    $fingerprint = app(ConfiguratorEditorChanges::class)->fingerprint($configurator->fresh(), $product->id);
    $productData = ['product_code' => $product->product_code, 'product_name' => $product->product_name, 'description' => $product->description, ...ProductEditorData::formData($product)];
    foreach ($productData['others'] as &$row) {
        if ($row['key'] === 'Working_Pressure') {
            $row['value'] = '25';
        }
    }
    unset($row);

    return compact('actor', 'configurator', 'group', 'product', 'saved', 'capture', 'fingerprint', 'productData') + [
        'productFingerprint' => SaveCatalogProduct::fingerprint($product),
        'groupFingerprint' => ProductEditorData::groupFingerprint($product),
        'revision' => (int) $group->fresh()->catalog_revision,
    ];
}

/** Fixtures are committed for real independent connections, then removed by their exact identities. */
function removePreviewContentionFixture(array $fixture): void
{
    DB::purge('mysql');
    $configuratorId = $fixture['configurator']->id;
    $attributeIds = array_column($fixture['saved']['attributes'], 'attribute_id');
    $optionIds = array_merge(...array_map(fn (array $attribute): array => array_column($attribute['options'], 'option_id'), $fixture['saved']['attributes']));
    $valueIds = DB::table('options')->whereIn('id', $optionIds)->pluck('value_id')->all();
    DB::table('products')->where('id', $fixture['product']->id)->delete();
    DB::table('groups')->where('id', $fixture['group']->id)->delete();
    DB::table('configurator_rules')->where('configurator_id', $configuratorId)->delete();
    DB::table('configurator_attributes')->where('configurator_id', $configuratorId)->update(['default_configurator_option_id' => null]);
    $included = DB::table('configurator_attributes')->where('configurator_id', $configuratorId)->pluck('id')->all();
    DB::table('configurator_options')->whereIn('configurator_attribute_id', $included)->delete();
    DB::table('configurator_attributes')->where('configurator_id', $configuratorId)->delete();
    DB::table('configurators')->where('id', $configuratorId)->delete();
    DB::table('options')->whereIn('id', $optionIds)->delete();
    DB::table('attributes')->whereIn('id', $attributeIds)->delete();
    DB::table('values')->whereIn('id', $valueIds)->delete();
    DB::table('users')->where('id', $fixture['actor']->id)->delete();
}

/** @return array{pid: int, channel: mixed, connection_id: int} */
function startPreviewContentionWorker(Closure $operation, string $attemptTable, ?string $pauseTable, bool $announceAttempt = false): array
{
    return startCatalogContentionWorker(function (mixed $channel) use ($operation, $attemptTable, $pauseTable, $announceAttempt): array {
        $attempts = 0;
        $paused = false;
        $announced = false;
        $ruleCreations = 0;
        $locks = [];
        Event::listen('eloquent.created: '.ConfiguratorRule::class, function () use (&$ruleCreations): void {
            $ruleCreations++;
        });
        DB::connection()->beforeExecuting(function (string $sql) use (&$attempts, &$announced, $attemptTable, $announceAttempt, $channel): void {
            if (str_contains($sql, 'from `'.$attemptTable.'`') && str_contains($sql, 'for update') && DB::transactionLevel() === 1) {
                $attempts++;
                if ($announceAttempt && ! $announced) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting']);
                }
            }
        });
        DB::listen(function (QueryExecuted $query) use (&$paused, &$locks, $pauseTable, $channel): void {
            if (! str_contains($query->sql, 'for update') || preg_match('/from `([^`]+)`/', $query->sql, $table) !== 1) {
                return;
            }
            $locks[] = $table[1];
            if (! $paused && $pauseTable !== null && $table[1] === $pauseTable) {
                $paused = true;
                writeCatalogContentionMessage($channel, ['phase' => 'locked', 'table' => $pauseTable, 'locks' => $locks]);
                readCatalogContentionMessage($channel);
            }
        });

        return [...catalogContentionResult($operation), 'attempts' => $attempts, 'rule_creations' => $ruleCreations, 'locks' => $locks];
    });
}

/** @return array{preview: array<string, mixed>, other: array<string, mixed>, blocked: bool} */
function overlapPreviewWriters(Closure $preview, Closure $other, string $previewPause, string $otherAttempt, string $otherPause, bool $serializeDefinition = false, string $waitTable = 'configurators'): array
{
    if (! function_exists('pcntl_fork')) {
        throw new RuntimeException('pcntl_fork is required to execute actual independent-process contention.');
    }
    DB::purge('mysql');
    $workers = [];
    try {
        $workers['preview'] = startPreviewContentionWorker($preview, $serializeDefinition ? $waitTable : 'configurators', $serializeDefinition ? null : $previewPause, $serializeDefinition);
        $workers['other'] = startPreviewContentionWorker($other, $otherAttempt, $otherPause);
        expect($workers['preview']['connection_id'])->not->toBe($workers['other']['connection_id']);
        writeCatalogContentionMessage($workers['other']['channel'], ['phase' => 'start']);
        $held = readCatalogContentionMessage($workers['other']['channel']);
        expect($held)->toMatchArray(['phase' => 'locked', 'table' => $otherPause]);
        if ($serializeDefinition && $waitTable === 'configurators') {
            expect($held['locks'][0])->toBe('configurators');
        }
        writeCatalogContentionMessage($workers['preview']['channel'], ['phase' => 'start']);
        $blocked = false;
        if ($serializeDefinition) {
            expect(readCatalogContentionMessage($workers['preview']['channel']))->toMatchArray(['phase' => 'attempting']);
            $deadline = microtime(true) + 5;
            do {
                foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
                    if ((int) $process->Id === $workers['preview']['connection_id'] && str_contains((string) $process->Info, 'from `'.$waitTable.'`') && str_contains((string) $process->Info, 'for update')) {
                        $blocked = true;
                        break 2;
                    }
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            expect($blocked)->toBeTrue();
        } else {
            expect(readCatalogContentionMessage($workers['preview']['channel']))->toMatchArray(['phase' => 'locked', 'table' => $previewPause]);
            writeCatalogContentionMessage($workers['preview']['channel'], ['phase' => 'release']);
        }
        writeCatalogContentionMessage($workers['other']['channel'], ['phase' => 'release']);
        $results = ['preview' => readCatalogContentionMessage($workers['preview']['channel']), 'other' => readCatalogContentionMessage($workers['other']['channel']), 'blocked' => $blocked];
        if (getenv('CATALOG_CONCURRENCY_TRACE')) {
            fwrite(STDERR, 'CONTENTION '.json_encode($results, JSON_THROW_ON_ERROR)."\n");
        }

        return $results;
    } finally {
        stopCatalogContentionWorkers($workers);
    }
}

/** @return Closure(): array */
function testedPreviewWrite(array $fixture): Closure
{
    return fn (): array => app(SaveTestedConfiguratorChanges::class)->handle($fixture['actor'], $fixture['configurator']->id, $fixture['product']->id, [$fixture['capture']], $fixture['fingerprint'], []);
}

function assertAtomicPreviewContentionOutcome(array $fixture, array $result): void
{
    expect($result['status'])->toBeIn(['committed', 'rejected']);
    expect($result['attempts'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($fixture['configurator']->fresh());
    if ($result['status'] === 'rejected') {
        expect($result['errors']['preview'])->toContain(__('configurator.preview.saved_conflict'));
        expect($saved)->toBe($fixture['saved']);
        expect($result['rule_creations'])->toBe(0);
    } else {
        expect($saved['description'])->toBe('Preview committed');
        expect($saved['attributes'][0]['label_override'])->toBe('Preview attribute');
        expect($saved['rules'])->toHaveCount(1);
        expect($result['value'])->toHaveKey('new:contention-rule');
        expect($result['rule_creations'])->toBe(1);
    }
    expect($fixture['configurator']->rules()->count())->toBe($result['status'] === 'committed' ? 1 : 0);
}

test('different Configurators sharing canonical rows save without the context canonical deadlock cycle', function () {
    DB::commit();
    $fixture = null;
    $other = null;
    $workers = [];
    try {
        $fixture = previewContentionFixture();
        $other = app(SaveConfiguratorDefinition::class)->duplicate($fixture['actor'], $fixture['configurator'], 'Second shared owner');
        DB::purge('mysql');
        $workers['ordinary'] = startPreviewContentionWorker(fn (): int => app(SaveConfiguratorDefinition::class)->change($fixture['actor'], $other,
            fn (array $draft): array => [...$draft, 'description' => 'Ordinary second owner saved'])->id, 'configurators', 'catalog_context_settings');
        $workers['preview'] = startPreviewContentionWorker(testedPreviewWrite($fixture), 'catalog_context_settings', null, announceAttempt: true);
        writeCatalogContentionMessage($workers['ordinary']['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers['ordinary']['channel']))->toMatchArray(['phase' => 'locked', 'table' => 'catalog_context_settings']);
        writeCatalogContentionMessage($workers['preview']['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers['preview']['channel']))->toMatchArray(['phase' => 'attempting']);
        $waiting = false;
        $deadline = microtime(true) + 5;
        do {
            foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
                if ((int) $process->Id === $workers['preview']['connection_id'] && str_contains((string) $process->Info, 'from `catalog_context_settings`') && str_contains((string) $process->Info, 'for update')) {
                    $waiting = true;
                    break 2;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        expect($waiting)->toBeTrue();
        writeCatalogContentionMessage($workers['ordinary']['channel'], ['phase' => 'release']);
        $ordinary = readCatalogContentionMessage($workers['ordinary']['channel']);
        $preview = readCatalogContentionMessage($workers['preview']['channel']);

        expect($ordinary['status'])->toBe('committed', 'SQLSTATE '.($ordinary['sqlstate'] ?? '').'; driver '.($ordinary['driver_code'] ?? ''))
            ->and($preview['status'])->toBe('committed', 'SQLSTATE '.($preview['sqlstate'] ?? '').'; driver '.($preview['driver_code'] ?? ''))
            ->and($ordinary['attempts'])->toBe(1)->and($preview['attempts'])->toBe(1)
            ->and($other->fresh()->description)->toBe('Ordinary second owner saved')
            ->and($fixture['configurator']->fresh()->description)->toBe('Preview committed');
    } finally {
        stopCatalogContentionWorkers($workers);
        if ($other !== null) {
            $ids = DB::table('configurator_attributes')->where('configurator_id', $other->id)->pluck('id');
            DB::table('configurator_attributes')->whereIn('id', $ids)->update(['default_configurator_option_id' => null]);
            DB::table('configurator_options')->whereIn('configurator_attribute_id', $ids)->delete();
            DB::table('configurator_rules')->where('configurator_id', $other->id)->delete();
            DB::table('configurator_attributes')->whereIn('id', $ids)->delete();
            DB::table('configurators')->where('id', $other->id)->delete();
        }
        if ($fixture !== null) {
            removePreviewContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
});

test('tested Preview waits for a canonical Value and rejects the freshly changed parent instead of a stale snapshot', function () {
    DB::commit();
    $fixture = null;
    $workers = [];
    try {
        $fixture = previewContentionFixture();
        $optionId = $fixture['saved']['attributes'][0]['options'][0]['option_id'];
        $valueId = DB::table('options')->where('id', $optionId)->value('value_id');
        DB::purge('mysql');
        $workers['parent'] = startPreviewContentionWorker(fn (): int => DB::transaction(function () use ($valueId): int {
            $value = Value::whereKey($valueId)->lockForUpdate()->firstOrFail();
            $value->update(['label' => 'Changed while Preview was waiting']);

            return $value->id;
        }), 'values', 'values');
        $workers['preview'] = startPreviewContentionWorker(testedPreviewWrite($fixture), 'values', null, announceAttempt: true);
        writeCatalogContentionMessage($workers['parent']['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers['parent']['channel']))->toMatchArray(['phase' => 'locked', 'table' => 'values']);
        writeCatalogContentionMessage($workers['preview']['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers['preview']['channel']))->toMatchArray(['phase' => 'attempting']);
        $waiting = false;
        $deadline = microtime(true) + 5;
        do {
            foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
                if ((int) $process->Id === $workers['preview']['connection_id'] && str_contains((string) $process->Info, 'from `values`') && str_contains((string) $process->Info, 'for update')) {
                    $waiting = true;
                    break 2;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        expect($waiting)->toBeTrue();
        writeCatalogContentionMessage($workers['parent']['channel'], ['phase' => 'release']);
        expect(readCatalogContentionMessage($workers['parent']['channel'])['status'])->toBe('committed');
        $preview = readCatalogContentionMessage($workers['preview']['channel']);
        expect($preview['status'])->toBe('rejected')
            ->and($preview['errors']['preview'])->toContain(__('configurator.preview.saved_conflict'))
            ->and(app(ConfiguratorDefinitionLoader::class)->draft($fixture['configurator']->fresh()))->toBe($fixture['saved']);
    } finally {
        stopCatalogContentionWorkers($workers);
        if ($fixture !== null) {
            removePreviewContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
});

test('tested Preview rejects a Product moved after group discovery without acquiring its new Group late', function () {
    DB::commit();
    $fixture = null;
    $newGroup = null;
    $moved = false;
    $groupLocks = [];
    $writerName = 'preview_group_move_writer';
    config(['database.connections.'.$writerName => config('database.connections.mysql')]);
    $writer = DB::connection($writerName);
    try {
        $fixture = previewContentionFixture();
        $newGroup = Group::factory()->create(['configurator_id' => $fixture['configurator']->id]);
        DB::listen(function (QueryExecuted $query) use (&$moved, &$groupLocks, $writer, $fixture, $newGroup): void {
            if (str_contains($query->sql, 'from `groups`') && str_contains($query->sql, 'for update')) {
                $groupLocks = [...$groupLocks, ...array_map('intval', $query->bindings)];
            }
            if ($moved || ! str_contains($query->sql, 'select `group_id` from `products`')) {
                return;
            }
            $moved = true;
            $writer->table('products')->where('id', $fixture['product']->id)->update(['group_id' => $newGroup->id]);
        });
        expect(testedPreviewWrite($fixture))->toThrow(ValidationException::class);
        expect($moved)->toBeTrue()->and($fixture['product']->fresh()->group_id)->toBe($newGroup->id)
            ->and($groupLocks)->toContain($fixture['group']->id)->not->toContain($newGroup->id)
            ->and(app(ConfiguratorDefinitionLoader::class)->draft($fixture['configurator']->fresh()))->toBe($fixture['saved']);
    } finally {
        $moved = true;
        DB::purge($writerName);
        if ($fixture !== null) {
            DB::table('products')->where('id', $fixture['product']->id)->update(['group_id' => $fixture['group']->id]);
            if ($newGroup !== null) {
                DB::table('groups')->where('id', $newGroup->id)->delete();
            }
            removePreviewContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
});

test('actual Product and tested Preview serialize at the Group without deadlock retries or partial writes', function () {
    DB::commit();
    $fixture = null;
    try {
        $fixture = previewContentionFixture();
        $results = overlapPreviewWriters(testedPreviewWrite($fixture), fn (): int => app(SaveCatalogProduct::class)->handle($fixture['actor'], $fixture['product'], $fixture['productData'], $fixture['productFingerprint'], $fixture['groupFingerprint'])->id, 'products', 'groups', 'groups', serializeDefinition: true, waitTable: 'groups');

        expect($results['other']['status'])->toBe('committed');
        expect($results['other']['attempts'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
        expect($results['preview']['attempts'])->toBe(1)->and($results['other']['attempts'])->toBe(1);
        assertAtomicPreviewContentionOutcome($fixture, $results['preview']);
        expect(ProductEditorData::rawMap($fixture['product']->fresh(), 'profile_fields'))->toEqual(['Working_Pressure' => '25', 'Material' => 'Metal Shell', 'a.b' => 'literal']);
        expect((int) $fixture['group']->fresh()->catalog_revision)->toBe($fixture['revision'] + 1);
    } finally {
        if ($fixture !== null) {
            removePreviewContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
});

test('actual assignment and tested Preview serialize at the Configurator without deadlock retries', function (string $entrypoint) {
    DB::commit();
    $fixture = null;
    try {
        $fixture = previewContentionFixture();
        $results = overlapPreviewWriters(testedPreviewWrite($fixture), function () use ($fixture, $entrypoint): null {
            if ($entrypoint === 'unassign') {
                app(AssignConfiguratorGroups::class)->unassign($fixture['actor'], $fixture['configurator'], $fixture['group']->id);
            } else {
                app(AssignConfiguratorGroups::class)->handle($fixture['actor'], $fixture['configurator'], [], [$fixture['group']->id]);
            }

            return null;
        }, 'configurators', 'configurators', 'groups', serializeDefinition: true);

        expect($results['other']['status'])->toBeIn(['committed', 'rejected']);
        expect($results['other']['attempts'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
        expect($results['preview']['attempts'])->toBe(1)->and($results['other']['attempts'])->toBe(1);
        assertAtomicPreviewContentionOutcome($fixture, $results['preview']);
        expect($fixture['group']->fresh()->configurator_id)->toBeNull();
        expect(ProductEditorData::rawMap($fixture['product']->fresh(), 'profile_fields'))->toEqual(['Working_Pressure' => '10', 'Material' => 'Metal Shell', 'a.b' => 'literal']);
        expect((int) $fixture['group']->fresh()->catalog_revision)->toBe($fixture['revision']);
    } finally {
        if ($fixture !== null) {
            removePreviewContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
})->with(['direct handle' => 'handle', 'single-group unassign' => 'unassign']);

test('actual definition Save blocks tested Preview then rejects its entire stale capture', function () {
    DB::commit();
    $fixture = null;
    try {
        $fixture = previewContentionFixture();
        $results = overlapPreviewWriters(testedPreviewWrite($fixture), fn (): int => app(SaveConfiguratorDefinition::class)->handle($fixture['actor'], $fixture['configurator'], [...$fixture['saved'], 'description' => 'Definition writer committed'])->id, 'configurators', 'configurators', 'configurators', serializeDefinition: true);

        expect($results['other']['status'])->toBe('committed');
        expect($results['preview']['status'])->toBe('rejected');
        expect($results['preview']['errors']['preview'])->toContain(__('configurator.preview.saved_conflict'));
        expect($results['preview']['rule_creations'])->toBe(0);
        expect($results['preview']['attempts'])->toBe(1);
        expect(app(ConfiguratorDefinitionLoader::class)->draft($fixture['configurator']->fresh()))->toBe([...$fixture['saved'], 'description' => 'Definition writer committed']);
        expect(ProductEditorData::rawMap($fixture['product']->fresh(), 'profile_fields'))->toEqual(['Working_Pressure' => '10', 'Material' => 'Metal Shell', 'a.b' => 'literal']);
        expect((int) $fixture['group']->fresh()->catalog_revision)->toBe($fixture['revision']);
    } finally {
        if ($fixture !== null) {
            removePreviewContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
});
