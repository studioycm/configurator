<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
if (getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Writing batch MySQL tests require the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';

use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorRuleWritingBatch;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleWritingCodec;
use App\Services\ConfiguratorRuleWritingDrafts;
use Illuminate\Database\Events\QueryExecuted;
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
        [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($this->batchGraph);
        $codec = app(ConfiguratorRuleWritingCodec::class);
        $dictionary = $codec->dictionary($this->batchGraph, $attributes, $options);
        $this->batchDraftIds = [];
        foreach ($this->batchGraph['rules'] as $rule) {
            $id = (string) Str::uuid();
            app(ConfiguratorRuleWritingDrafts::class)->create($this->batchActor, $this->batchOwner, $id, ['kind' => $rule['kind'], 'source' => $codec->open($rule, $dictionary)['source']], 0);
            $this->batchDraftIds[] = $id;
        }
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

test('autosave and selected batch serialize without replacing the winning source or acknowledging the loser', function (bool $batchFirst) {
    $actor = $this->batchActor;
    $owner = $this->batchOwner;
    $id = $this->batchDraftIds[0];
    $selection = array_map(fn ($id) => ['id' => $id, 'expected_revision' => 1, 'sequence' => 7], $this->batchDraftIds);
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $id, $selection, $batchFirst): array {
            $paused = false;
            $locks = [];
            $draftLocks = [];
            DB::listen(function (QueryExecuted $query) use ($channel, &$paused, &$locks, &$draftLocks): void {
                if (str_contains($query->sql, 'for update') && preg_match('/from `([^`]+)`/', $query->sql, $match)) {
                    $locks[] = $match[1];
                    if ($match[1] === 'configurator_rule_writing_drafts') {
                        $draftLocks[] = end($query->bindings);
                        if (! $paused) {
                            $paused = true;
                            writeCatalogContentionMessage($channel, ['phase' => 'draft_locked']);
                            readCatalogContentionMessage($channel);
                        }
                    }
                }
            });
            $result = $batchFirst
                ? catalogContentionResult(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, $selection))
                : catalogContentionResult(fn () => app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $id, 1, ['source' => '/when incomplete'], 8));

            return [...$result, 'locks' => $locks, 'draft_locks' => $draftLocks];
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $id, $selection, $batchFirst): array {
            $announced = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced): void {
                if (! $announced && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting']);
                }
            });

            return $batchFirst
                ? catalogContentionResult(fn () => app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $id, 1, ['source' => 'losing autosave'], 9))
                : catalogContentionResult(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, $selection));
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
        $draft = ConfiguratorRuleWritingDraft::findOrFail($id);
        expect($draft->revision)->toBe(2);
        if ($batchFirst) {
            expect(array_slice($first['locks'], 0, 5))->toBe(['configurators', 'catalog_context_settings', 'attributes', 'values', 'options']);
            $sorted = $this->batchDraftIds;
            sort($sorted, SORT_STRING);
            expect($first['draft_locks'])->toBe($sorted);
            expect($draft->last_saved_authoring['source'])->toBe($draft->source);
            expect($first['value']['items'][0]['acknowledgement'])->toMatchArray(['sequence' => 7, 'revision' => 2, 'source' => $draft->source]);
        } else {
            expect($draft->source)->toBe('/when incomplete')->and($draft->last_saved_authoring)->toBeNull();
            expect(ConfiguratorRuleWritingDraft::findOrFail($this->batchDraftIds[1])->revision)->toBe(1);
        }
        expect(DB::table('configurator_rules')->where('configurator_id', $this->batchOwner->id)->count())->toBe($batchFirst ? 4 : 2);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
})->with([true, false]);

test('normal owned Rule saves and linked Writing batches preserve the first committed version and reject stale updates', function (bool $writingFirst) {
    $actor = $this->batchActor;
    $owner = $this->batchOwner;
    $rule = $this->batchGraph['rules'][0];
    $opened = app(ConfiguratorRuleWritingDrafts::class)->openRule($actor, $owner, (int) $rule['id'], (string) Str::uuid());
    $id = $opened['draft']['id'];
    app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $id, 1, ['source' => $opened['draft']['source'], 'label' => 'Writing wins'], 2);
    $selection = [['id' => $id, 'expected_revision' => 2, 'sequence' => 3], ['id' => $this->batchDraftIds[1], 'expected_revision' => 1, 'sequence' => 4]];
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $rule, $selection, $writingFirst): array {
            $paused = false;
            $pauseTable = $writingFirst ? 'configurator_rule_writing_drafts' : 'options';
            DB::listen(function (QueryExecuted $query) use ($channel, &$paused, $pauseTable): void {
                if (! $paused && str_contains($query->sql, 'from `'.$pauseTable.'`') && str_contains($query->sql, 'for update')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'first_locked']);
                    readCatalogContentionMessage($channel);
                }
            });

            return $writingFirst
                ? catalogContentionResult(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, $selection))
                : catalogContentionResult(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, [...$rule, 'label' => 'Normal wins'], []));
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $rule, $selection, $writingFirst): array {
            $announced = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced): void {
                if (! $announced && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting']);
                }
            });

            return $writingFirst
                ? catalogContentionResult(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $owner, $rule, [...$rule, 'label' => 'Losing normal edit'], []))
                : catalogContentionResult(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, $selection));
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'first_locked']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first['status'])->toBe('committed')->and($second['status'])->toBe('rejected');
        expect(DB::table('configurator_rules')->where('id', $rule['id'])->value('label'))->toBe($writingFirst ? 'Writing wins' : 'Normal wins');
        $draft = ConfiguratorRuleWritingDraft::findOrFail($id);
        expect($draft->source)->toBe($opened['draft']['source']);
        expect($draft->last_saved_authoring === null)->toBe(! $writingFirst);
        expect(DB::table('configurator_rules')->where('configurator_id', $this->batchOwner->id)->count())->toBe($writingFirst ? 3 : 2);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
})->with([true, false]);
