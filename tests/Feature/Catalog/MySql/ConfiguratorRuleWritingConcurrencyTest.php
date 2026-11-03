<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
if (getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Rules Writing MySQL tests require the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

use App\Actions\SaveConfiguratorDefinition;
use App\Models\CatalogContextSettings;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/ConfiguratorRuleWritingFixtures.php';

beforeEach(function () {
    $this->writingFixtures = writingBeginOwnedFixtures();
    try {
        $this->writingActor = writingFixtureActor('ycm@data4.work');
        [$owner, $graph] = writingCanonicalDefinitionFixture();
        $graph['rules'] = [fixtureMapping()];
        app(SaveConfiguratorDefinition::class)->handle($this->writingActor, $owner, $graph);
        $other = app(SaveConfiguratorDefinition::class)->duplicate($this->writingActor, $owner, 'Shared canonical parents');
        $this->writingOwners = [$owner, $other];
        $this->writingGraphs = array_map(fn ($record) => app(ConfiguratorDefinitionLoader::class)->draft($record->fresh()), $this->writingOwners);
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

test('Writing serializes actual independent connections and checks the fresh owned baseline', function (bool $sameOwner) {
    $actor = $this->writingActor;
    $firstOwner = $this->writingOwners[0];
    $secondIndex = $sameOwner ? 0 : 1;
    $secondOwner = $this->writingOwners[$secondIndex];
    $firstRule = $this->writingGraphs[0]['rules'][0];
    $secondRule = $this->writingGraphs[$secondIndex]['rules'][0];
    $workers = [];
    DB::purge('mysql');
    try {
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $firstOwner, $firstRule): array {
            $paused = false;
            $locks = [];
            DB::listen(function (QueryExecuted $query) use ($channel, &$paused, &$locks): void {
                if (str_contains($query->sql, 'for update') && preg_match('/from `([^`]+)`/', $query->sql, $match)) {
                    $locks[] = $match[1];
                    if (! $paused && $match[1] === 'attributes') {
                        $paused = true;
                        writeCatalogContentionMessage($channel, ['phase' => 'parents_locked']);
                        readCatalogContentionMessage($channel);
                    }
                }
            });

            return [...catalogContentionResult(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $firstOwner, $firstRule, [...$firstRule, 'label' => 'First writer'], [])), 'locks' => $locks];
        });
        $workers[] = startCatalogContentionWorker(function ($channel) use ($actor, $secondOwner, $secondRule, $sameOwner): array {
            $announced = false;
            $table = $sameOwner ? 'configurators' : 'catalog_context_settings';
            DB::connection()->beforeExecuting(function (string $sql) use ($channel, &$announced, $table): void {
                if (! $announced && str_contains($sql, 'from `'.$table.'`') && str_contains($sql, 'for update')) {
                    $announced = true;
                    writeCatalogContentionMessage($channel, ['phase' => 'attempting']);
                }
            });

            return catalogContentionResult(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($actor, $secondOwner, $secondRule, [...$secondRule, 'label' => 'Second writer'], []));
        });
        expect($workers[0]['connection_id'])->not->toBe($workers[1]['connection_id']);
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[0]['channel']))->toBe(['phase' => 'parents_locked']);
        writeCatalogContentionMessage($workers[1]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[1]['channel']))->toBe(['phase' => 'attempting']);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        expect(fn () => DB::transaction(fn () => CatalogContextSettings::query()->lockForUpdate()->get()))->toThrow(QueryException::class, '1205');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        writeCatalogContentionMessage($workers[0]['channel'], ['phase' => 'release']);
        $first = readCatalogContentionMessage($workers[0]['channel']);
        $second = readCatalogContentionMessage($workers[1]['channel']);
        expect($first)->toMatchArray(['phase' => 'result', 'status' => 'committed']);
        expect(array_slice($first['locks'], 0, 5))->toBe(['configurators', 'catalog_context_settings', 'attributes', 'values', 'options']);
        expect($second['status'])->toBe($sameOwner ? 'rejected' : 'committed');
        $saved = app(ConfiguratorDefinitionLoader::class)->draft($firstOwner->fresh())['rules'][0];
        expect($saved['label'])->toBe('First writer')->and($saved['id'])->toBe($firstRule['id']);
        expect($first['value']['identities']['rules'][$firstRule['id']])->toBe((int) $saved['id']);
    } finally {
        stopCatalogContentionWorkers($workers);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
    }
})->with([true, false]);
