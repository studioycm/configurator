<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
if (getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Rules Writing MySQL tests require the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';

use App\Actions\SaveConfiguratorDefinition;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleWritingDrafts;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once dirname(__DIR__, 3).'/ConfiguratorRuleWritingFixtures.php';

beforeEach(function () {
    $this->writingFixtures = writingBeginOwnedFixtures();
    try {
        $this->draftActor = writingFixtureActor('ycm@data4.work');
        [$this->draftOwner, $graph] = writingCanonicalDefinitionFixture();
        $graph['rules'] = [fixtureMapping()];
        app(SaveConfiguratorDefinition::class)->handle($this->draftActor, $this->draftOwner, $graph);
        $this->draftGraph = app(ConfiguratorDefinitionLoader::class)->draft($this->draftOwner->fresh());
        $this->draftOwners = [$this->draftOwner];
        $this->draftId = (string) Str::uuid();
        app(ConfiguratorRuleWritingDrafts::class)->create($this->draftActor, $this->draftOwner, $this->draftId, ['kind' => 'Mapping', 'source' => '/when'], 0);
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

test('independent admins serialize draft CAS and never acknowledge another writers source', function () {
    $actor = $this->draftActor;
    $owner = $this->draftOwner;
    $id = $this->draftId;
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $id): array {
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

            return [...catalogContentionResult(fn () => app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $id, 1, ['source' => 'first admin | '], 10)), 'locks' => $locks];
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $id): array {
            $announced = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced): void {
                if (! $announced && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting']);
                }
            });

            return catalogContentionResult(fn () => app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $id, 1, ['source' => 'losing admin'], 11));
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'draft_locked']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first['status'])->toBe('committed')->and($first['locks'])->toBe(['configurators', 'configurator_rule_writing_drafts']);
        expect($second['status'])->toBe('rejected')->and($second['errors'])->toHaveKey('draft_revision');
        expect($first['value']['acknowledgement'])->toMatchArray(['sequence' => 10, 'revision' => 2, 'source' => 'first admin | ']);
        app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $id, 2, ['source' => 'later admin'], 12);
        expect(ConfiguratorRuleWritingDraft::findOrFail($id)->revision)->toBe(3);
        expect($first['value']['acknowledgement']['revision'])->toBe(2);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});

test('different Configurators open first linked drafts without retaining a lower rank gap lock', function () {
    $actor = $this->draftActor;
    $owner = $this->draftOwner;
    ConfiguratorRuleWritingDraft::whereKey($this->draftId)->delete();
    expect(DB::table('configurator_rule_writing_drafts')->where('configurator_id', $owner->id)->count())->toBe(0);
    $other = app(SaveConfiguratorDefinition::class)->duplicate($actor, $owner, 'Other Writing owner');
    $this->draftOwners[] = $other;
    $otherGraph = app(ConfiguratorDefinitionLoader::class)->draft($other->fresh());
    $firstRule = (int) $this->draftGraph['rules'][0]['id'];
    $secondRule = (int) $otherGraph['rules'][0]['id'];
    $firstId = (string) Str::uuid();
    $secondId = (string) Str::uuid();
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $owner, $firstRule, $firstId): array {
            $paused = false;
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$paused): void {
                if (! $paused && str_contains($sql, 'from `catalog_context_settings`') && str_contains($sql, 'for update')) {
                    $paused = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'initialization_starting']);
                    readCatalogContentionMessage($channel);
                }
            });

            return catalogContentionResult(fn () => app(ConfiguratorRuleWritingDrafts::class)->openRule($actor, $owner, $firstRule, $firstId));
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $other, $secondRule, $secondId): array {
            DB::connection()->beforeExecuting(function (string $sql) use ($channel): void {
                if (str_starts_with($sql, 'insert into `configurator_rule_writing_drafts`')) {
                    writeCatalogContentionMessage($channel, ['phase' => 'draft_insert_starting']);
                }
            });

            return catalogContentionResult(fn () => app(ConfiguratorRuleWritingDrafts::class)->openRule($actor, $other, $secondRule, $secondId, fresh: true));
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'initialization_starting']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'draft_insert_starting']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first['status'])->toBe('committed')->and($second['status'])->toBe('committed');
        expect($first['value']['draft']['origin_rule_id'])->toBe($firstRule);
        expect($second['value']['draft']['origin_rule_id'])->toBe($secondRule);
        expect(DB::table('configurator_rule_writing_drafts')->whereIn('configurator_id', [$owner->id, $other->id])->count())->toBe(2);
    } finally {
        stopCatalogContentionWorkers($workers);
    }
});
