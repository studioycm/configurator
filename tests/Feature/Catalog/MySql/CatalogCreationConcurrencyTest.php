<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();
require_once dirname(__DIR__, 3).'/ConfiguratorFixtures.php';
require_once dirname(__DIR__, 3).'/CatalogMySqlContention.php';

use App\Actions\AddConfiguratorAttributes;
use App\Actions\AddConfiguratorValues;
use App\Actions\CreateSharedAttributes;
use App\Actions\SaveCanonicalDefinition;
use App\Actions\SaveConfiguratorDefinition;
use App\CatalogWriteReplan;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function creationContentionRef(): string
{
    return 'new:'.Str::uuid();
}

/** @return array<string, mixed> */
function creationContentionFixture(): array
{
    return DB::transaction(function (): array {
        $actor = User::factory()->create(['email' => 'ycm@data4.work']);
        test()->actingAs($actor);
        [$owner, $data] = canonicalDefinitionFixture();
        $data['rules'] = [fixtureMapping(), [...fixtureAdvanced('retained', [fixtureCondition('retained')], 'B', 'SetHint', [], 'Keep this hint'), 'priority' => 1]];
        $data['attributes'][0]['default_configurator_option_id'] = 'new:A1';
        $data['attributes'][0]['options'][0]['label_override'] = 'Keep local name';
        $data['attributes'][0]['options'][0]['hint'] = 'Keep local hint';
        app(SaveConfiguratorDefinition::class)->handle($actor, $owner, $data);
        $saved = app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh());
        $attributeIds = array_column($saved['attributes'], 'attribute_id');
        $valueIds = Option::whereIn('attribute_id', $attributeIds)->pluck('value_id')->all();

        return compact('actor', 'owner', 'saved', 'attributeIds', 'valueIds');
    });
}

/** @param array<string, mixed> $fixture */
function removeCreationContentionFixture(array $fixture): void
{
    DB::purge('mysql');
    $ownerId = $fixture['owner']->id;
    $attributes = array_unique([...$fixture['attributeIds'], ...Attribute::whereIn('key', ['race_loser_early', 'race_loser_late', 'race_winner'])->pluck('id')->all()]);
    $values = array_unique([...$fixture['valueIds'], ...Value::whereIn('semantic_id', ['race_loser_early', 'race_loser_middle', 'race_loser_late', 'race_winner', 'contention_added'])->pluck('id')->all()]);
    DB::table('configurator_rules')->where('configurator_id', $ownerId)->delete();
    DB::table('configurator_attributes')->where('configurator_id', $ownerId)->update(['default_configurator_option_id' => null]);
    $inclusions = DB::table('configurator_attributes')->where('configurator_id', $ownerId)->pluck('id')->all();
    DB::table('configurator_options')->whereIn('configurator_attribute_id', $inclusions)->delete();
    DB::table('configurator_attributes')->where('configurator_id', $ownerId)->delete();
    DB::table('configurators')->where('id', $ownerId)->delete();
    DB::table('options')->whereIn('attribute_id', $attributes)->delete();
    DB::table('attributes')->whereIn('id', $attributes)->delete();
    DB::table('values')->whereIn('id', $values)->delete();
    DB::table('users')->where('id', $fixture['actor']->id)->delete();
}

/** @return array{masters:array, attributes:array, reviews:array} */
function losingCreationGraph(): array
{
    $masters = [];
    foreach (['early', 'middle', 'late'] as $name) {
        $masters[] = ['ref' => creationContentionRef(), 'label' => 'Losing '.$name, 'semantic_id' => 'race_loser_'.$name, 'tags' => ['losing'], 'description' => 'Must roll back'];
    }
    $attributes = [
        ['ref' => creationContentionRef(), 'label' => '', 'key' => '', 'values' => []],
        ['ref' => creationContentionRef(), 'label' => 'Losing early', 'key' => 'race_loser_early', 'values' => [['ref' => creationContentionRef(), 'value_ref' => $masters[0]['ref'], 'code' => 'Z7']]],
        ['ref' => creationContentionRef(), 'label' => 'Losing late', 'key' => 'race_loser_late', 'values' => [
            ['ref' => creationContentionRef(), 'value_ref' => $masters[1]['ref'], 'code' => 'Z8'],
            ['ref' => creationContentionRef(), 'value_ref' => $masters[2]['ref'], 'code' => 'Z9'],
        ]],
    ];
    $reviews = [];
    foreach (array_slice($attributes, 1) as $attribute) {
        $values = array_map(fn (array $row): array => ['ref' => creationContentionRef(), 'option_ref' => $row['ref']], $attribute['values']);
        $reviews[] = ['ref' => creationContentionRef(), 'attribute_ref' => $attribute['ref'], 'values' => $values, 'default_ref' => $values[0]['ref']];
    }

    return compact('masters', 'attributes', 'reviews');
}

/** @param list<string> $locks */
function assertCreationLockPrefix(array $locks): void
{
    expect(array_slice($locks, 0, 3))->toBe(['configurators', 'catalog_context_settings', 'attributes']);
}

/** @return list<string> */
function &traceCreationLocks(): array
{
    $locks = [];
    DB::listen(function (QueryExecuted $query) use (&$locks): void {
        if (str_contains($query->sql, 'for update') && preg_match('/from `([^`]+)`/', $query->sql, $table) === 1) {
            $locks[] = $table[1];
        }
    });

    return $locks;
}

test('a canonical edit rejects an owner added after discovery instead of acquiring that owner late', function () {
    DB::commit();
    $fixture = null;
    $incoming = null;
    $pending = false;
    $writerName = 'canonical_new_owner_writer';
    config(['database.connections.'.$writerName => config('database.connections.mysql')]);
    try {
        $fixture = creationContentionFixture();
        $incoming = Configurator::factory()->create(['name' => 'Incoming shared owner']);
        $attribute = Attribute::findOrFail($fixture['attributeIds'][0]);
        $row = $fixture['saved']['attributes'][0];
        foreach ($row['options'] as $i => &$option) {
            $option['id'] = 'new:incoming-option-'.$i;
        }
        unset($option);
        $row['id'] = 'new:incoming-attribute';
        $row['default_configurator_option_id'] = $row['options'][0]['id'];
        $data = [...$fixture['saved'], 'name' => $incoming->name, 'attributes' => [$row], 'rules' => []];
        DB::connection()->beforeExecuting(function (string $sql) use (&$pending, $writerName, $fixture, $incoming, $data): void {
            if (! $pending || ! str_contains($sql, 'from `attributes`') || ! str_contains($sql, 'for update')) {
                return;
            }
            $pending = false;
            DB::setDefaultConnection($writerName);
            try {
                app(SaveConfiguratorDefinition::class)->handle($fixture['actor'], $incoming, $data);
            } finally {
                DB::setDefaultConnection('mysql');
            }
        });
        $pending = true;
        expect(fn () => app(SaveCanonicalDefinition::class)->handle($fixture['actor'], $attribute, ['key' => $attribute->key, 'label' => 'Must replan first']))
            ->toThrow(CatalogWriteReplan::class);
        expect($pending)->toBeFalse()->and($attribute->fresh()->label)->toBe($attribute->label)
            ->and($incoming->attributes()->count())->toBe(1);
        app(SaveCanonicalDefinition::class)->handle($fixture['actor'], $attribute, ['key' => $attribute->key, 'label' => 'Fresh retry']);
        expect($attribute->fresh()->label)->toBe('Fresh retry');
    } finally {
        $pending = false;
        DB::setDefaultConnection('mysql');
        DB::purge($writerName);
        if ($incoming !== null) {
            $ids = DB::table('configurator_attributes')->where('configurator_id', $incoming->id)->pluck('id');
            DB::table('configurator_attributes')->whereIn('id', $ids)->update(['default_configurator_option_id' => null]);
            DB::table('configurator_options')->whereIn('configurator_attribute_id', $ids)->delete();
            DB::table('configurator_attributes')->whereIn('id', $ids)->delete();
            DB::table('configurators')->where('id', $incoming->id)->delete();
        }
        if ($fixture !== null) {
            removeCreationContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
});

test('a canonical key edit detects a newly committed dependency despite an earlier repeatable read', function () {
    DB::commit();
    $actor = null;
    $attribute = null;
    $value = null;
    $pending = false;
    $writerName = 'canonical_dependency_writer';
    config(['database.connections.'.$writerName => config('database.connections.mysql')]);
    $writer = DB::connection($writerName);
    try {
        $actor = User::factory()->create(['email' => 'ycm@data4.work']);
        $attribute = Attribute::factory()->create();
        $value = Value::factory()->create();
        $option = Option::factory()->make(['attribute_id' => $attribute->id, 'value_id' => $value->id, 'code' => 'Q9']);
        DB::connection()->beforeExecuting(function (string $sql) use (&$pending, $writer, $option): void {
            if (! $pending || ! str_contains($sql, 'from `attributes`') || ! str_contains($sql, 'for update')) {
                return;
            }
            $pending = false;
            $writer->table('options')->insert($option->getAttributes());
        });
        $pending = true;
        expect(fn () => DB::transaction(function () use ($actor, $attribute): void {
            Option::count();
            app(SaveCanonicalDefinition::class)->handle($actor, $attribute, ['key' => $attribute->key.'_changed', 'label' => $attribute->label]);
        }))->toThrow(ValidationException::class);
        expect($pending)->toBeFalse()->and($attribute->fresh()->key)->toBe($attribute->key);
    } finally {
        $pending = false;
        DB::purge($writerName);
        if ($attribute !== null) {
            DB::table('options')->where('attribute_id', $attribute->id)->delete();
            DB::table('attributes')->where('id', $attribute->id)->delete();
        }
        if ($value !== null) {
            DB::table('values')->where('id', $value->id)->delete();
        }
        if ($actor !== null) {
            DB::table('users')->where('id', $actor->id)->delete();
        }
        DB::beginTransaction();
    }
});

test('V27 a genuine late global Code race rejects its original later row and rolls back the entire losing graph', function () {
    DB::commit();
    $fixture = null;
    $workers = [];
    try {
        $fixture = creationContentionFixture();
        $graph = losingCreationGraph();
        DB::purge('mysql');
        $workers['loser'] = startCatalogContentionWorker(function (mixed $channel) use ($fixture, $graph): array {
            $locks = &traceCreationLocks();
            $writes = ['values' => 0, 'attributes' => 0, 'options' => 0];
            $codeChecks = 0;
            DB::listen(function (QueryExecuted $query) use (&$writes, &$codeChecks): void {
                if (preg_match('/^insert into `(values|attributes|options)`/', $query->sql, $table) === 1) {
                    $writes[$table[1]]++;
                }
                if (str_contains($query->sql, 'from `options`') && str_contains($query->sql, '`code`') && in_array('Z9', $query->bindings, true)) {
                    $codeChecks++;
                }
            });
            DB::connection()->beforeExecuting(function (string $sql, array $bindings) use ($channel, &$writes, &$codeChecks, &$locks): void {
                if (str_starts_with($sql, 'insert into `options`') && in_array('Z9', $bindings, true)) {
                    writeCatalogContentionMessage($channel, ['phase' => 'late-insert', 'earlier_writes' => $writes, 'code_checks' => $codeChecks, 'locks' => $locks]);
                    readCatalogContentionMessage($channel);
                }
            });
            $result = catalogContentionResult(fn (): int => app(AddConfiguratorAttributes::class)->handle($fixture['actor'], $fixture['owner'], ['master_values' => $graph['masters'], 'attributes' => $graph['attributes'], 'reviews' => $graph['reviews']])->id);

            return [...$result, 'locks' => $locks];
        });
        $workers['winner'] = startCatalogContentionWorker(function (mixed $channel) use ($fixture): array {
            $locks = &traceCreationLocks();
            $masterRef = creationContentionRef();
            $result = catalogContentionResult(fn (): array => array_map(fn (Attribute $attribute): int => $attribute->id, app(CreateSharedAttributes::class)->handle($fixture['actor'], [
                ['ref' => creationContentionRef(), 'label' => 'Winning shared', 'key' => 'race_winner', 'values' => [['ref' => creationContentionRef(), 'value_ref' => $masterRef, 'code' => 'Z9']]],
            ], [['ref' => $masterRef, 'label' => 'Winning Master', 'semantic_id' => 'race_winner', 'tags' => ['winner'], 'description' => 'Committed by supported writer']])));

            return [...$result, 'locks' => $locks];
        });
        expect($workers['loser']['connection_id'])->not->toBe($workers['winner']['connection_id']);
        writeCatalogContentionMessage($workers['loser']['channel'], ['phase' => 'start']);
        $barrier = readCatalogContentionMessage($workers['loser']['channel']);
        expect($barrier)->toMatchArray(['phase' => 'late-insert', 'earlier_writes' => ['values' => 3, 'attributes' => 2, 'options' => 2]]);
        expect($barrier['code_checks'])->toBeGreaterThanOrEqual(1);
        assertCreationLockPrefix($barrier['locks']);
        writeCatalogContentionMessage($workers['winner']['channel'], ['phase' => 'start']);
        $winner = readCatalogContentionMessage($workers['winner']['channel']);
        expect($winner['status'])->toBe('committed');
        expect($winner['locks'])->not->toContain('configurators', 'catalog_context_settings');
        writeCatalogContentionMessage($workers['loser']['channel'], ['phase' => 'release']);
        $loser = readCatalogContentionMessage($workers['loser']['channel']);
        expect($loser['status'])->toBe('rejected');
        expect($loser['errors'])->toBe(['attributes.2.values.1.code' => [__('catalog.attribute_values.validation.this_code_or_attributevalue_pair_was_saved')]]);
        expect(Attribute::whereIn('key', ['race_loser_early', 'race_loser_late'])->exists())->toBeFalse();
        expect(Value::whereIn('semantic_id', array_column($graph['masters'], 'semantic_id'))->exists())->toBeFalse();
        expect(Option::whereIn('code', ['Z7', 'Z8'])->exists())->toBeFalse();
        $won = Option::with(['attribute', 'value'])->where('code', 'Z9')->sole();
        expect($won->attribute->key)->toBe('race_winner');
        expect($won->value->semantic_id)->toBe('race_winner');
        expect($won->value->label)->toBe('Winning Master');
        expect(app(ConfiguratorDefinitionLoader::class)->draft($fixture['owner']->fresh()))->toBe($fixture['saved']);
        if (getenv('CATALOG_CONCURRENCY_TRACE')) {
            fwrite(STDERR, 'CREATION_RACE '.json_encode(['connection_ids' => array_column($workers, 'connection_id'), 'barrier' => $barrier, 'winner' => $winner, 'loser' => $loser], JSON_THROW_ON_ERROR)."\n");
        }
    } finally {
        stopCatalogContentionWorkers($workers);
        if ($fixture !== null) {
            removeCreationContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
});

test('V27 inclusion and canonical definition edits serialize with a real follower wait and retain local identities', function (string $leader) {
    DB::commit();
    $fixture = null;
    $workers = [];
    try {
        $fixture = creationContentionFixture();
        $inclusion = $fixture['saved']['attributes'][0];
        $attribute = Attribute::findOrFail($inclusion['attribute_id']);
        $masterRef = creationContentionRef();
        $payload = ['master_values' => [['ref' => $masterRef, 'label' => 'Added in contention', 'semantic_id' => 'contention_added']], 'new_values' => [['ref' => creationContentionRef(), 'value_ref' => $masterRef, 'code' => 'Z6']]];
        $operations = [
            'inclusion' => fn (): int => app(AddConfiguratorValues::class)->handle($fixture['actor'], $fixture['owner'], (int) $inclusion['id'], $payload)->id,
            'canonical' => fn (): int => app(SaveCanonicalDefinition::class)->handle($fixture['actor'], $attribute, ['label' => 'Canonical renamed concurrently', 'key' => $attribute->key])->id,
        ];
        DB::purge('mysql');
        foreach ($operations as $name => $operation) {
            $workers[$name] = startCatalogContentionWorker(function (mixed $channel) use ($name, $leader, $operation): array {
                $locks = &traceCreationLocks();
                $attempted = false;
                $paused = false;
                $beforeMaster = null;
                $beforeOption = null;
                DB::connection()->beforeExecuting(function (string $sql) use ($name, $leader, $channel, &$attempted, &$beforeMaster, &$beforeOption, &$locks): void {
                    if ($name !== $leader && ! $attempted && str_contains($sql, 'from `configurators`') && str_contains($sql, 'for update')) {
                        $attempted = true;
                        writeCatalogContentionMessage($channel, ['phase' => 'attempting', 'table' => 'configurators']);
                    }
                    if ($name === 'inclusion' && $beforeMaster === null && str_starts_with($sql, 'insert into `values`')) {
                        $beforeMaster = $locks;
                    }
                    if ($name === 'inclusion' && $beforeOption === null && str_starts_with($sql, 'insert into `options`')) {
                        $beforeOption = $locks;
                    }
                });
                DB::listen(function (QueryExecuted $query) use ($name, $leader, $channel, &$paused): void {
                    if ($name === $leader && ! $paused && str_contains($query->sql, 'from `configurators`') && str_contains($query->sql, 'for update')) {
                        $paused = true;
                        writeCatalogContentionMessage($channel, ['phase' => 'locked', 'table' => 'configurators']);
                        readCatalogContentionMessage($channel);
                    }
                });
                $result = catalogContentionResult($operation);

                return [...$result, 'locks' => $locks, 'before_master' => $beforeMaster, 'before_option' => $beforeOption];
            });
        }
        expect($workers['inclusion']['connection_id'])->not->toBe($workers['canonical']['connection_id']);
        $follower = $leader === 'inclusion' ? 'canonical' : 'inclusion';
        writeCatalogContentionMessage($workers[$leader]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[$leader]['channel']))->toMatchArray(['phase' => 'locked', 'table' => 'configurators']);
        writeCatalogContentionMessage($workers[$follower]['channel'], ['phase' => 'start']);
        expect(readCatalogContentionMessage($workers[$follower]['channel']))->toMatchArray(['phase' => 'attempting', 'table' => 'configurators']);
        $waiting = false;
        $waitState = null;
        $deadline = microtime(true) + 4;
        do {
            foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
                if ((int) $process->Id === $workers[$follower]['connection_id'] && str_contains((string) $process->Info, 'from `configurators`') && str_contains((string) $process->Info, 'for update')) {
                    $waiting = true;
                    $waitState = $process->State;
                    break 2;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        expect($waiting)->toBeTrue();
        $readable = [$workers[$follower]['channel']];
        $writable = $exceptions = [];
        expect(stream_select($readable, $writable, $exceptions, 0, 1000))->toBe(0);
        writeCatalogContentionMessage($workers[$leader]['channel'], ['phase' => 'release']);
        $results = [];
        foreach ($workers as $name => $worker) {
            $results[$name] = readCatalogContentionMessage($worker['channel']);
            expect($results[$name]['status'])->toBe('committed');
        }
        expect(array_slice($results['inclusion']['before_master'], 0, 2))->toBe(['configurators', 'catalog_context_settings']);
        assertCreationLockPrefix($results['inclusion']['before_option']);
        expect($attribute->fresh()->label)->toBe('Canonical renamed concurrently');
        $after = app(ConfiguratorDefinitionLoader::class)->draft($fixture['owner']->fresh());
        $added = array_pop($after['attributes'][0]['options']);
        expect($after)->toBe($fixture['saved']);
        $option = Option::where('code', 'Z6')->sole();
        expect($added['option_id'])->toBe($option->id);
        expect((int) $added['id'])->toBeGreaterThan(0);
        expect($option->attribute_id)->toBe($attribute->id);
        expect($option->value->semantic_id)->toBe('contention_added');
        if (getenv('CATALOG_CONCURRENCY_TRACE')) {
            fwrite(STDERR, 'CREATION_SERIALIZATION '.json_encode(['leader' => $leader, 'connection_ids' => array_column($workers, 'connection_id'), 'observed_wait_state' => $waitState, 'results' => $results], JSON_THROW_ON_ERROR)."\n");
        }
    } finally {
        stopCatalogContentionWorkers($workers);
        if ($fixture !== null) {
            removeCreationContentionFixture($fixture);
        }
        DB::beginTransaction();
    }
})->with(['inclusion first' => 'inclusion', 'canonical first' => 'canonical']);
