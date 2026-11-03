<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorRuleWritingBatch;
use App\CatalogWriteFailure;
use App\CatalogWriteReplan;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleWritingCodec;
use App\Services\ConfiguratorRuleWritingDrafts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

if (defined('CATALOG_MYSQL_TEST_RUN') && getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Writing batch tests require the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

require_once dirname(__DIR__, 2).'/ConfiguratorRuleWritingFixtures.php';

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
        $this->batchDictionary = $dictionary;
        $this->batchSources = array_map(fn ($rule) => $codec->open($rule, $dictionary)['source'], $this->batchGraph['rules']);
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

function newBatchDraft(User $actor, Configurator $owner, string $kind, string $source, ?string $label = null): ConfiguratorRuleWritingDraft
{
    $saved = app(ConfiguratorRuleWritingDrafts::class)->create($actor, $owner, (string) Str::uuid(), ['kind' => $kind, 'source' => $source, 'label' => $label], 1);

    return ConfiguratorRuleWritingDraft::findOrFail($saved['draft']['id']);
}

function batchSelection(ConfiguratorRuleWritingDraft ...$drafts): array
{
    return array_map(fn ($draft) => ['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 9], $drafts);
}

function batchRaw(): array
{
    return collect(['configurators', 'configurator_attributes', 'configurator_options', 'configurator_rules', 'rule_condition_groups', 'rule_conditions', 'rule_condition_options', 'rule_effects', 'rule_effect_options', 'mapping_sets', 'mapping_set_sources', 'mapping_set_targets', 'configurator_rule_writing_drafts'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
}

test('canonical batches validate the final graph instead of failing on a transient sequential cycle', function () {
    $graph = $this->batchGraph;
    $a = $graph['attributes'][0];
    $b = $graph['attributes'][1];
    $c = $graph['attributes'][2];
    $r1 = $graph['rules'][0];
    $r2 = [...$r1, 'id' => 'new:r2', 'label' => 'Second', 'priority' => 2, 'driver_configurator_attribute_id' => $c['id'], 'target_configurator_attribute_id' => $a['id']];
    $r2['sets'][0] = [...$r2['sets'][0], 'id' => 'new:r2set', 'source_option_ids' => [$c['options'][0]['id']], 'target_option_ids' => [$a['options'][1]['id']]];
    $graph['rules'] = [$r1, $r2];
    app(SaveConfiguratorDefinition::class)->handle($this->batchActor, $this->batchOwner, $graph);
    $current = app(ConfiguratorDefinitionLoader::class)->draft($this->batchOwner->fresh());
    [$r1, $r2] = $current['rules'];
    $p1 = [...$r1, 'target_configurator_attribute_id' => $c['id']];
    $p1['sets'][0]['target_option_ids'] = [$c['options'][1]['id']];
    $p2 = [...$r2, 'driver_configurator_attribute_id' => $b['id']];
    $p2['sets'][0]['source_option_ids'] = [$b['options'][0]['id']];
    expect(fn () => app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($this->batchActor, $this->batchOwner, $r1, $p1, []))->toThrow(ValidationException::class);
    $result = app(SaveConfiguratorDefinition::class)->changeRulesWithIdentities($this->batchActor, $this->batchOwner, fn () => [
        ['expected_rule' => $r1, 'proposed_rule' => $p1, 'reference_bindings' => []], ['expected_rule' => $r2, 'proposed_rule' => $p2, 'reference_bindings' => []],
    ]);
    expect(array_column($result['rules'], 'operation'))->toBe(['update', 'update']);
    expect($result['rules'][0]['saved_rule']['target_configurator_attribute_id'])->toBe($c['id']);
});

test('mixed Mapping Update and Advanced Create save directly while unfinished drafts remain unchanged', function () {
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $opened = $store->openRule($this->batchActor, $this->batchOwner, (int) $this->batchGraph['rules'][0]['id'], (string) Str::uuid());
    $map = ConfiguratorRuleWritingDraft::findOrFail($opened['draft']['id']);
    $store->update($this->batchActor, $this->batchOwner, $map->id, 1, ['source' => $map->source, 'label' => '0', 'description' => "exact\r\n תיאור"], 3);
    $advanced = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', $this->batchSources[1]);
    $unfinished = newBatchDraft($this->batchActor, $this->batchOwner, 'Mapping', '/when');
    $before = $unfinished->getRawOriginal();
    $result = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($map->fresh(), $advanced));
    expect(array_column($result['items'], 'operation'))->toBe(['update', 'create']);
    expect($result['items'][0]['saved_rule']['label'])->toBe('0');
    expect($result['items'][1]['saved_rule']['priority'])->toBe(2)->and($result['items'][1]['saved_rule']['is_active'])->toBeTrue();
    expect($unfinished->fresh()->getRawOriginal())->toBe($before);
    foreach ($result['items'] as $item) {
        $capture = $item['draft']['last_saved_authoring'];
        expect($capture['source'])->toBe($item['acknowledgement']['source']);
        expect($capture['records']['rule']['id'])->toBe($item['rule_id']);
        expect($item['draft']['origin_rule_id'])->toBe($item['rule_id']);
        expect($capture['identities']['rules'])->toContain($item['rule_id']);
    }
});

test('multiple new drafts have distinct staged children and repeat saving cannot duplicate creates', function () {
    $one = newBatchDraft($this->batchActor, $this->batchOwner, 'Mapping', $this->batchSources[0]);
    $two = newBatchDraft($this->batchActor, $this->batchOwner, 'Mapping', $this->batchSources[0]);
    $result = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($one, $two));
    expect(array_column($result['items'], 'rule_id'))->toHaveCount(2);
    expect($result['items'][0]['rule_id'])->not->toBe($result['items'][1]['rule_id']);
    expect($result['items'][0]['saved_rule']['label'])->toBe('A → B');
    expect(array_column($result['items'], 'saved_rule', 'rule_id'))->toHaveCount(2);
    expect(DB::table('configurator_rules')->where('configurator_id', $this->batchOwner->id)->count())->toBe(4);
    expect(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($one, $two)))->toThrow(ValidationException::class);
    $before = batchRaw();
    $repeat = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($one->fresh(), $two->fresh()));
    expect(array_column($repeat['items'], 'operation'))->toBe(['unchanged', 'unchanged']);
    expect(batchRaw())->toBe($before);
});

test('autosaved executable edits after a Create use accepted source baseline and retain actual child identities', function () {
    $draft = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', '/set-hint @C [[First "0"]]');
    $first = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($draft));
    $effectId = $first['items'][0]['saved_rule']['effects'][0]['id'];
    $draft->refresh();
    $source = "/set-hint @C [[Second\n\"0\"]]";
    app(ConfiguratorRuleWritingDrafts::class)->update($this->batchActor, $this->batchOwner, $draft->id, $draft->revision, ['source' => $source], 2);
    $second = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($draft->fresh()));
    expect($second['items'][0]['saved_rule']['effects'][0])->toMatchArray(['id' => $effectId, 'display_value' => "Second\n\"0\""]);
    expect($second['items'][0]['draft']['last_saved_authoring']['source'])->toBe($source);
    expect($second['items'][0]['rule_id'])->toBe($first['items'][0]['rule_id']);
});

test('one invalid stale deleted or rebound selected draft rejects all executable and capture writes', function (string $failure) {
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $opened = $store->openRule($this->batchActor, $this->batchOwner, (int) $this->batchGraph['rules'][0]['id'], (string) Str::uuid());
    $existing = ConfiguratorRuleWritingDraft::findOrFail($opened['draft']['id']);
    $new = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', $this->batchSources[1]);
    $selection = batchSelection($new, $existing);
    if ($failure === 'incomplete') {
        $store->update($this->batchActor, $this->batchOwner, $existing->id, 1, ['source' => '/when all {'], 2);
        $selection = batchSelection($new, $existing->fresh());
    }
    if ($failure === 'draft stale') {
        $store->update($this->batchActor, $this->batchOwner, $existing->id, 1, ['source' => $existing->source, 'description' => 'other admin'], 2);
    }
    if ($failure === 'Rule stale') {
        DB::table('configurator_rules')->where('id', $existing->origin_rule_id)->update(['label' => 'regular form']);
    }
    if ($failure === 'deleted') {
        DB::table('configurator_rules')->where('id', $existing->origin_rule_id)->delete();
    }
    if ($failure === 'rebound') {
        DB::table('attributes')->where('id', $this->batchGraph['attributes'][0]['attribute_id'])->update(['key' => 'renamed']);
    }
    $before = batchRaw();
    expect(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, $selection))->toThrow(ValidationException::class);
    expect(batchRaw())->toBe($before);
})->with(['incomplete', 'draft stale', 'Rule stale', 'deleted', 'rebound']);

test('foreign drafts duplicate targets and invalid selection metadata cannot save', function () {
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $ruleId = (int) $this->batchGraph['rules'][0]['id'];
    $one = $store->openRule($this->batchActor, $this->batchOwner, $ruleId, (string) Str::uuid());
    $two = $store->openRule($this->batchActor, $this->batchOwner, $ruleId, (string) Str::uuid(), fresh: true);
    $a = ConfiguratorRuleWritingDraft::findOrFail($one['draft']['id']);
    $b = ConfiguratorRuleWritingDraft::findOrFail($two['draft']['id']);
    $action = app(SaveConfiguratorRuleWritingBatch::class);
    $before = batchRaw();
    foreach ([[], batchSelection($a, $a), batchSelection($a, $b), [['id' => $a->id, 'expected_revision' => 0, 'sequence' => 0]], [['id' => $a->id, 'expected_revision' => 1, 'sequence' => 0, 'source' => 'forged']]] as $selection) {
        expect(fn () => $action->handle($this->batchActor, $this->batchOwner, $selection))->toThrow(ValidationException::class);
    }
    $foreign = ConfiguratorRuleWritingDraft::factory()->create();
    try {
        expect(fn () => $action->handle($this->batchActor, $this->batchOwner, batchSelection($foreign)))->toThrow(ModelNotFoundException::class);
    } finally {
        $foreign->configurator->delete();
    }
    expect(batchRaw())->toBe($before);
});

test('new child nodes added to two existing drafts remain distinct and fresh Enabled and priority survive', function () {
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $drafts = [];
    foreach ($this->batchGraph['rules'] as $rule) {
        $o = $store->openRule($this->batchActor, $this->batchOwner, (int) $rule['id'], (string) Str::uuid());
        $d = ConfiguratorRuleWritingDraft::findOrFail($o['draft']['id']);
        $source = '/when $"Connection Size" = "001"'."\n".$d->source;
        if ($rule['conditions'] !== []) {
            $source = str_replace('/when all {', '/when all {'."\n  ".'$"Connection Size" = "001"', $d->source);
        }
        $store->update($this->batchActor, $this->batchOwner, $d->id, 1, ['source' => $source], 2);
        $drafts[] = $d->fresh();
        DB::table('configurator_rules')->where('id', $rule['id'])->update(['is_active' => false, 'priority' => $rule['priority'] + 10]);
    }
    $result = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection(...$drafts));
    expect(array_column(array_column($result['items'], 'saved_rule'), 'priority'))->toBe([10, 11]);
    expect(array_column(array_column($result['items'], 'saved_rule'), 'is_active'))->toBe([false, false]);
    expect($result['items'][0]['saved_rule']['conditions'][0]['operand'])->toBe('001');
});

test('source-only save replaces the entire capture while ordinary edits preserve it unchanged', function () {
    $draft = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', '/set-label @C "0"', '0');
    $first = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($draft));
    $draft->refresh();
    $records = $first['items'][0]['records'];
    $source = "// original comment\r\n".$draft->source;
    app(ConfiguratorRuleWritingDrafts::class)->update($this->batchActor, $this->batchOwner, $draft->id, $draft->revision, ['source' => $source, 'description' => 'metadata'], 2);
    $second = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($draft->fresh()));
    expect($second['items'][0]['operation'])->toBe('unchanged')->and($second['items'][0]['records'])->toBe($records);
    $capture = $second['items'][0]['draft']['last_saved_authoring'];
    expect($capture['source'])->toBe($source)->and($capture['description'])->toBe('metadata');
    app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($this->batchActor, $this->batchOwner, $second['items'][0]['saved_rule'], [...$second['items'][0]['saved_rule'], 'label' => 'regular edit'], []);
    expect($draft->fresh()->last_saved_authoring)->toEqual($capture);
});

test('late capture failure rolls the whole batch back and only classified transient rollbacks retry finitely', function (bool $transient) {
    $draft = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', '/set-label @C "hello"');
    $before = batchRaw();
    $attempts = 0;
    ConfiguratorRuleWritingDraft::updating(function () use (&$attempts, $transient) {
        $attempts++;
        if ($transient) {
            $exception = new PDOException('Synthetic lock timeout');
            $exception->errorInfo = ['HY000', 1205, 'Synthetic timeout'];
            throw $exception;
        }
        throw new RuntimeException('Synthetic capture persistence failure');
    });
    try {
        app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($draft));
        $this->fail('Expected classified failure');
    } catch (CatalogWriteFailure $e) {
        expect($e->rolledBack)->toBeTrue();
    }
    expect($attempts)->toBe($transient ? 3 : 1)->and(batchRaw())->toBe($before);
})->with([false, true]);

test('commit uncertainty cannot replay a create or manufacture an acknowledgement', function () {
    $draft = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', '/set-label @C "hello"');
    $commits = 0;
    Event::listen(TransactionCommitted::class, function ($event) use (&$commits) {
        if ($event->connection->transactionLevel() === 0) {
            $commits++;
            throw new RuntimeException('Synthetic commit response failure');
        }
    });
    try {
        app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($draft));
        $this->fail('Expected uncertain commit');
    } catch (CatalogWriteFailure $e) {
        expect($e->rolledBack)->toBeFalse();
    }
    expect($commits)->toBe(1);
    expect($draft->fresh()->origin_rule_id)->not->toBeNull();
    expect(DB::table('configurator_rules')->where('configurator_id', $this->batchOwner->id)->count())->toBe(3);
});

test('batch entry authorizes before reads and refuses an ambient transaction', function () {
    $action = app(SaveConfiguratorRuleWritingBatch::class);
    $outsider = User::factory()->make();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    expect(fn () => $action->handle($outsider, $this->batchOwner, []))->toThrow(AuthorizationException::class);
    expect($queries)->toBe([]);
    DB::transaction(function () use ($action) {
        expect(fn () => $action->handle($this->batchActor, $this->batchOwner, [['id' => (string) Str::uuid(), 'expected_revision' => 1, 'sequence' => 0]]))->toThrow(LogicException::class);
    });
});

test('combined new dependencies reject a cycle without saving either draft', function () {
    app(SaveConfiguratorDefinition::class)->change($this->batchActor, $this->batchOwner, fn ($graph) => [...$graph, 'rules' => []]);
    $reverse = $this->batchGraph['rules'][0];
    $reverse['driver_configurator_attribute_id'] = $this->batchGraph['attributes'][1]['id'];
    $reverse['target_configurator_attribute_id'] = $this->batchGraph['attributes'][0]['id'];
    $reverse['sets'][0]['source_option_ids'] = [$this->batchGraph['attributes'][1]['options'][0]['id']];
    $reverse['sets'][0]['target_option_ids'] = [$this->batchGraph['attributes'][0]['options'][0]['id']];
    $source = app(ConfiguratorRuleWritingCodec::class)->open($reverse, $this->batchDictionary)['source'];
    $a = newBatchDraft($this->batchActor, $this->batchOwner, 'Mapping', $this->batchSources[0]);
    $b = newBatchDraft($this->batchActor, $this->batchOwner, 'Mapping', $source);
    $before = batchRaw();
    expect(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($a, $b)))->toThrow(ValidationException::class);
    expect(batchRaw())->toBe($before);
});

test('accepted interleaved groups and Value list order keep their writing and raw records on source-only edits', function () {
    $keys = array_keys($this->batchDictionary['A'][0]['values']);
    $source = '/when all {'."\n".'  any {'."\n".'    @A in #'.$keys[1].' | #'.$keys[0]."\n".'    $"Connection Size" = "001"'."\n".'  }'."\n".'  $"Pressure" != "0"'."\n".'}'."\n".'/set-hint @C [[first]]';
    $draft = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', $source);
    $action = app(SaveConfiguratorRuleWritingBatch::class);
    $first = $action->handle($this->batchActor, $this->batchOwner, batchSelection($draft));
    $commented = "// preserve my layout\r\n".$source;
    $draft->refresh();
    app(ConfiguratorRuleWritingDrafts::class)->update($this->batchActor, $this->batchOwner, $draft->id, $draft->revision, ['source' => $commented], 2);
    $second = $action->handle($this->batchActor, $this->batchOwner, batchSelection($draft->fresh()));
    expect($second['items'][0]['operation'])->toBe('unchanged');
    expect($second['items'][0]['records'])->toBe($first['items'][0]['records']);
    expect($second['items'][0]['draft']['last_saved_authoring']['source'])->toBe($commented);
    $draft->refresh();
    $changed = str_replace('[[first]]', '[[second]]', $commented);
    app(ConfiguratorRuleWritingDrafts::class)->update($this->batchActor, $this->batchOwner, $draft->id, $draft->revision, ['source' => $changed], 3);
    $third = $action->handle($this->batchActor, $this->batchOwner, batchSelection($draft->fresh()));
    expect(array_column($third['items'][0]['records']['conditions'], 'id'))->toBe(array_column($first['items'][0]['records']['conditions'], 'id'));
    expect($third['items'][0]['saved_rule']['effects'][0]['display_value'])->toBe('second');
    expect($third['items'][0]['draft']['source'])->toBe($changed);
});

test('compiler-invalid selected source reports the draft UUID rather than its aggregate Rule index', function () {
    $good = newBatchDraft($this->batchActor, $this->batchOwner, 'Mapping', $this->batchSources[0]);
    $bad = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', '/set-label @C ""');
    $before = batchRaw();
    try {
        app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($good, $bad));
        $this->fail('Compiler-invalid source must reject the entire selected batch.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('drafts.'.$bad->id.'.source.effects.0.display_value');
        expect(collect(array_keys($exception->errors()))->filter(fn ($key) => str_starts_with($key, 'rules.')))->toHaveCount(0);
    }
    expect(batchRaw())->toBe($before);
});

test('participant replans escape unchanged rather than becoming selected source-validation errors', function () {
    $draft = newBatchDraft($this->batchActor, $this->batchOwner, 'Advanced', '/set-label @C "hello"');
    $before = batchRaw();
    $replan = CatalogWriteReplan::withMessages(['rules.2.label' => 'The participant plan changed.']);
    ConfiguratorRule::creating(function () use ($replan) {
        throw $replan;
    });
    try {
        app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, batchSelection($draft));
        $this->fail('Expected participant replan.');
    } catch (CatalogWriteReplan $exception) {
        expect($exception)->toBe($replan);
        expect($exception->errors())->toHaveKey('rules.2.label');
    }
    expect(batchRaw())->toBe($before);
});
