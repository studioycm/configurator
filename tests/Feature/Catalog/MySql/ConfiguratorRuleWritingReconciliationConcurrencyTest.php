<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
if (getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Writing reconciliation contention requires the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';

use App\Actions\SaveCanonicalDefinition;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorRuleWritingBatch;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleRecords;
use App\Services\ConfiguratorRuleWritingDrafts;
use App\Services\ConfiguratorRuleWritingReconciliation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 3).'/ConfiguratorRuleWritingFixtures.php';

beforeEach(function () {
    $this->writingFixtures = writingBeginOwnedFixtures();
    try {
        config(['app.key' => 'w5-private-test-key']);
        $this->reconActor = writingFixtureActor('ycm@data4.work');
        [$this->reconOwner, $graph] = writingCanonicalDefinitionFixture();
        $condition = [...fixtureCondition('mode'), 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'option_ids' => [], 'property_key' => 'Mode', 'operand' => 'old'];
        $graph['rules'] = [[...fixtureMapping(), 'conditions' => [$condition]], [...fixtureAdvanced('advanced', [[...$condition, 'id' => 'new:advancedmode']], 'C', 'SetHint', [], 'old hint'), 'priority' => 1]];
        app(SaveConfiguratorDefinition::class)->handle($this->reconActor, $this->reconOwner, $graph);
        $this->reconGraph = app(ConfiguratorDefinitionLoader::class)->draft($this->reconOwner->fresh());
        $this->reconOther = writingFixtureActor('beny.cr@gmail.com');
        $opened = app(ConfiguratorRuleWritingDrafts::class)->openRule($this->reconActor, $this->reconOwner, (int) $this->reconGraph['rules'][1]['id'], (string) Str::uuid());
        $this->reconDraft = ConfiguratorRuleWritingDraft::findOrFail($opened['draft']['id']);
        app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $this->reconDraft->id, 'expected_revision' => 1, 'sequence' => 0]]);
        $this->reconDraft->refresh();
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

test('independent administrators serialize displayed draft updates with canonical parents before the draft lock', function () {
    $actor = $this->reconActor;
    $other = $this->reconOther;
    $owner = $this->reconOwner;
    $draft = $this->reconDraft;
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $one = $service->capture($actor, $owner, $draft->id, $draft->revision)['snapshot'];
    $two = $service->capture($other, $owner, $draft->id, $draft->revision)['snapshot'];
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $draft, $one): array {
            $locks = [];
            DB::listen(function (QueryExecuted $query) use ($channel, &$locks): void {
                if (str_contains($query->sql, 'for update') && preg_match('/from `([^`]+)`/', $query->sql, $match)) {
                    $locks[] = $match[1];
                    if ($match[1] === 'configurator_rule_writing_drafts') {
                        writeCatalogContentionMessage($channel, ['phase' => 'draft_locked']);
                        readCatalogContentionMessage($channel);
                    }
                }
            });

            return [...catalogContentionResult(fn () => app(ConfiguratorRuleWritingReconciliation::class)->updateDraft($actor, $owner, $draft->id, $draft->revision, $one, [], 10)), 'locks' => $locks];
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($other, $owner, $draft, $two): array {
            reconAnnounceOwnerAttempt($channel);

            return catalogContentionResult(fn () => app(ConfiguratorRuleWritingReconciliation::class)->updateDraft($other, $owner, $draft->id, $draft->revision, $two, [], 11));
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'draft_locked']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first['status'])->toBe('committed')->and($second['status'])->toBe('rejected');
        expect($first['locks'])->toBe(['configurators', 'catalog_context_settings', 'attributes', 'values', 'options', 'configurator_rule_writing_drafts']);
        expect($second['errors'])->toHaveKey('draft_revision');
        expect($first['value']['acknowledgement'])->toMatchArray(['sequence' => 10, 'revision' => $draft->revision + 1, 'source' => $draft->source]);
        $fresh = $draft->fresh();
        expect($fresh->last_saved_authoring)->toEqual($draft->last_saved_authoring);
        expect($fresh->updated_by)->toBe($actor->id);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});

function reconAnnounceOwnerAttempt(mixed $channel): void
{
    $announced = false;
    DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced): void {
        if (! $announced && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
            $announced = true;
            writeCatalogContentionMessage($channel, ['phase' => 'attempting']);
        }
    });
}

test('regular Rule writes on either side of draft refresh stay authoritative and make later Writing saves stale', function (bool $refreshFirst) {
    $actor = $this->reconActor;
    $owner = $this->reconOwner;
    $draft = $this->reconDraft;
    $snapshot = app(ConfiguratorRuleWritingReconciliation::class)->capture($actor, $owner, $draft->id, $draft->revision)['snapshot'];
    $rule = $draft->base_rule;
    $regular = fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, [...$rule, 'label' => 'normal form wins'], []);
    $refresh = fn () => app(ConfiguratorRuleWritingReconciliation::class)->updateDraft($actor, $owner, $draft->id, $draft->revision, $snapshot, [], 1);
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($regular, $refresh, $refreshFirst): array {
            $paused = false;
            DB::listen(function (QueryExecuted $query) use ($channel, &$paused, $refreshFirst): void {
                $table = $refreshFirst ? 'configurator_rule_writing_drafts' : 'options';
                if (! $paused && str_contains($query->sql, 'from `'.$table.'`') && str_contains($query->sql, 'for update')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'first_locked']);
                    readCatalogContentionMessage($channel);
                }
            });

            return catalogContentionResult($refreshFirst ? $refresh : $regular);
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($regular, $refresh, $refreshFirst): array {
            reconAnnounceOwnerAttempt($channel);

            return catalogContentionResult($refreshFirst ? $regular : $refresh);
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'first_locked']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first['status'])->toBe('committed')->and($second['status'])->toBe('committed');
        $fresh = $draft->fresh();
        $before = $fresh->getRawOriginal();
        $records = app(ConfiguratorRuleRecords::class)->read(ConfiguratorRule::findOrFail($draft->origin_rule_id));
        expect($records['rule']['label'])->toBe('normal form wins');
        expect(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, [['id' => $fresh->id, 'expected_revision' => $fresh->revision, 'sequence' => 2]]))->toThrow(ValidationException::class);
        expect($draft->fresh()->getRawOriginal())->toBe($before);
        expect(app(ConfiguratorRuleRecords::class)->read(ConfiguratorRule::findOrFail($draft->origin_rule_id)))->toBe($records);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
})->with([true, false]);

test('a canonical Value rename between display and refresh is observed after owner locks and rejects old references', function () {
    $actor = $this->reconActor;
    $owner = $this->reconOwner;
    $draft = $this->reconDraft;
    $valueId = DB::table('options')->where('id', $this->reconGraph['attributes'][0]['options'][0]['option_id'])->value('value_id');
    $value = Value::findOrFail($valueId);
    $source = str_replace('$"Mode" = "old"', '@A = #'.$value->semantic_id, $draft->source);
    expect($source)->not->toBe($draft->source)->toContain('@A = #');
    app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $draft->id, $draft->revision, ['source' => $source], 1);
    $draft->refresh();
    $snapshot = app(ConfiguratorRuleWritingReconciliation::class)->capture($actor, $owner, $draft->id, $draft->revision)['snapshot'];
    expect($draft->bindings['dictionary']['A'][0]['values'][$value->semantic_id])->toContain($this->reconGraph['attributes'][0]['options'][0]['id']);
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $value): array {
            $paused = false;
            DB::listen(function (QueryExecuted $query) use ($channel, &$paused): void {
                if (! $paused && str_contains($query->sql, 'from `values`') && str_contains($query->sql, 'for update')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'value_locked']);
                    readCatalogContentionMessage($channel);
                }
            });

            return catalogContentionResult(fn () => app(SaveCanonicalDefinition::class)->handle($actor, $value, ['label' => $value->label, 'semantic_id' => 'renamed_w5']));
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $draft, $snapshot): array {
            reconAnnounceOwnerAttempt($channel);

            return catalogContentionResult(fn () => app(ConfiguratorRuleWritingReconciliation::class)->updateDraft($actor, $owner, $draft->id, $draft->revision, $snapshot, [], 2));
        });
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'value_locked']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        expect(readCatalogContentionMessage($workers[0]['channel'])['status'])->toBe('committed');
        expect(readCatalogContentionMessage($workers[1]['channel'])['status'])->toBe('rejected');
        expect($draft->fresh()->getRawOriginal())->toBe($draft->getRawOriginal());
        expect($value->fresh()->semantic_id)->toBe('renamed_w5');
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});
