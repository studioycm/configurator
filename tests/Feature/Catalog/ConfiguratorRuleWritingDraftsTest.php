<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Models\Configurator;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\Group;
use App\Models\User;
use App\RuleKind;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleWritingDrafts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

if (defined('CATALOG_MYSQL_TEST_RUN') && getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Rules Writing MySQL tests require the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

require_once dirname(__DIR__, 2).'/ConfiguratorRuleWritingFixtures.php';

beforeEach(function () {
    $this->writingFixtures = writingBeginOwnedFixtures();
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

function withLinkedDraftFixture(Closure $work): void
{
    $actor = writingFixtureActor('ycm@data4.work');
    [$owner, $graph] = writingCanonicalDefinitionFixture();
    $graph['rules'] = [fixtureMapping(), [...fixtureAdvanced('advanced', [fixtureCondition('condition')], 'C'), 'priority' => 1]];
    app(SaveConfiguratorDefinition::class)->handle($actor, $owner, $graph);
    $work($actor, $owner, app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh()));
}

test('linked draft lookup sorts scalar identities then returns the original large source exactly', function () {
    withLinkedDraftFixture(function ($actor, $owner, $graph) {
        $store = app(ConfiguratorRuleWritingDrafts::class);
        $rule = (int) $graph['rules'][0]['id'];
        $first = $store->openRule($actor, $owner, $rule, (string) Str::uuid())['draft'];
        $store->openRule($actor, $owner, $rule, (string) Str::uuid(), fresh: true);
        $source = str_repeat('// exact large source '."\r\n", 30000);
        $store->update($actor, $owner, $first['id'], 1, ['source' => $source], 1);
        DB::table('configurator_rule_writing_drafts')->where('id', $first['id'])->update(['created_at' => '2000-01-01 00:00:00']);
        $ordered = [];
        DB::listen(function ($query) use (&$ordered): void {
            if (str_contains($query->sql, 'configurator_rule_writing_drafts') && str_contains($query->sql, 'order by')) {
                $ordered[] = $query->sql;
            }
        });

        $opened = $store->openRule($actor, $owner, $rule, (string) Str::uuid());

        expect($opened['draft']['id'])->toBe($first['id'])->and($opened['draft']['source'])->toBe($source);
        expect($ordered)->toHaveCount(1);
        expect(preg_match('/^select ["`]id["`] from /', $ordered[0]))->toBe(1);
    });
});

test('shared drafts retain exact incomplete and invalid writing without executable writes', function (string $kind, string $source) {
    $actor = writingFixtureActor('ycm@data4.work');
    $owner = Configurator::factory()->create();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $id = (string) Str::uuid();
    $result = $store->create($actor, $owner, $id, ['kind' => $kind, 'source' => $source, 'label' => '', 'description' => "תיאור\n"], 7);
    $other = writingFixtureActor('beny.cr@gmail.com');
    $opened = $store->get($other, $owner, $id);

    expect($opened['draft'])->toBe($result['draft']);
    expect($opened['draft'])->toMatchArray(['id' => $id, 'source' => $source, 'kind' => $kind, 'label' => '', 'description' => "תיאור\n", 'revision' => 1, 'format_version' => 1, 'origin_rule_id' => null, 'base_rule' => null, 'bindings' => null, 'last_saved_authoring' => null]);
    expect($result['acknowledgement'])->toMatchArray(['sequence' => 7, 'revision' => 1, 'source' => $source]);
    expect($store->listing($other, $owner))->toHaveCount(1);
    expect(ConfiguratorRuleWritingDraft::findOrFail($id)->kind)->toBe(RuleKind::from($kind));
    expect(collect($queries)->filter(fn ($sql) => preg_match('/^(insert|update|delete).*(`|")?(configurator_rules|groups|rule_effects|mapping_sets)(`|")?/i', $sql)))->toHaveCount(0);
})->with([
    ['Mapping', ''], ['Advanced', '/when'], ['Mapping', "/pair @missing -> @missing\r\n/case #a | "], ['Advanced', " \t/when all {\n\tbad ☃ syntax\n"],
]);

test('identical create retries are scoped and never rewrite another draft', function () {
    $actor = writingFixtureActor('ycm@data4.work');
    $owner = Configurator::factory()->create();
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $id = (string) Str::uuid();
    $input = ['kind' => 'Mapping', 'source' => '/when'];
    $first = $store->create($actor, $owner, strtoupper($id), $input, 1);
    $repeat = $store->create($actor, $owner, $id, $input, 2);
    expect($repeat['draft'])->toBe($first['draft'])->and($repeat['acknowledgement']['sequence'])->toBe(2);
    expect(fn () => $store->create($actor, $owner, $id, [...$input, 'source' => 'different'], 3))->toThrow(ValidationException::class);
    $foreign = Configurator::factory()->create();
    expect(fn () => $store->create($actor, $foreign, $id, $input, 4))->toThrow(QueryException::class);
    expect(fn () => $store->get($actor, $foreign, $id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $store->update($actor, $foreign, $id, 1, ['source' => 'hijack'], 5))->toThrow(ModelNotFoundException::class);
    expect($store->get($actor, $owner, $id)['draft'])->toBe($first['draft']);
});

test('updates compare revision before no-op and acknowledge only their exact snapshot', function () {
    $this->freezeTime();
    $actor = writingFixtureActor('ycm@data4.work');
    $other = writingFixtureActor('beny.cr@gmail.com');
    $owner = Configurator::factory()->create();
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $first = $store->create($actor, $owner, (string) Str::uuid(), ['kind' => 'Advanced', 'source' => 'first'], 1);
    $id = $first['draft']['id'];
    $this->travel(1)->minutes();
    $noop = $store->update($other, $owner, $id, 1, ['source' => 'first'], 2);
    expect($noop['draft'])->toBe($first['draft']);
    $saved = $store->update($other, $owner, $id, 1, ['source' => "second\n", 'label' => 'Beny'], 3);
    $later = $store->update($actor, $owner, $id, 2, ['source' => 'third'], 4);
    expect($saved['acknowledgement'])->toMatchArray(['sequence' => 3, 'revision' => 2, 'source' => "second\n", 'label' => 'Beny']);
    expect($later['draft']['revision'])->toBe(3);
    try {
        $store->update($other, $owner, $id, 2, ['source' => 'third'], 5);
        $this->fail('A stale no-op must reject.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('draft_revision');
    }
    expect($store->get($actor, $owner, $id)['draft'])->toBe($later['draft']);
});

test('draft fields validate UTF8 sizes and ownership without requiring valid Rule syntax', function (array $input) {
    $actor = writingFixtureActor('ycm@data4.work');
    $owner = Configurator::factory()->create();
    expect(fn () => app(ConfiguratorRuleWritingDrafts::class)->create($actor, $owner, (string) Str::uuid(), $input, 1))->toThrow(ValidationException::class);
    expect(DB::table('configurator_rule_writing_drafts')->where('configurator_id', $owner->id)->count())->toBe(0);
})->with([
    [['kind' => 'Other', 'source' => '']], [['kind' => 'Mapping']], [['kind' => 'Mapping', 'source' => null]],
    [['kind' => 'Mapping', 'source' => "\xff"]], [['kind' => 'Mapping', 'source' => str_repeat('x', 1048577)]],
    [['kind' => 'Mapping', 'source' => '', 'label' => str_repeat('א', 256)]], [['kind' => 'Mapping', 'source' => '', 'description' => str_repeat('x', 10001)]],
    [['kind' => 'Mapping', 'source' => '', 'base_rule' => []]], [['kind' => 'Mapping', 'source' => '', 'format_version' => 9]],
]);

test('maximum source and unicode metadata roundtrip without truncation', function () {
    $actor = writingFixtureActor('ycm@data4.work');
    $owner = Configurator::factory()->create();
    $input = ['kind' => 'Advanced', 'source' => str_repeat('x', 1048576), 'label' => str_repeat('א', 255), 'description' => str_repeat('☃', 10000)];
    $result = app(ConfiguratorRuleWritingDrafts::class)->create($actor, $owner, (string) Str::uuid(), $input, 0);
    expect(array_intersect_key($result['draft'], $input))->toBe($input);
});

test('every public draft operation authorizes before protected queries', function (string $operation) {
    $actor = User::factory()->create();
    $owner = Configurator::factory()->create();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $id = (string) Str::uuid();
    expect(fn () => match ($operation) {
        'create' => $store->create($actor, $owner, $id, ['kind' => 'Mapping', 'source' => ''], 0),
        'get' => $store->get($actor, $owner, $id),
        'listing' => $store->listing($actor, $owner),
        'update' => $store->update($actor, $owner, $id, 1, ['source' => ''], 0),
        'openRule' => $store->openRule($actor, $owner, 1, $id),
    })->toThrow(AuthorizationException::class);
    expect($queries)->toBe([]);
})->with(['create', 'get', 'listing', 'update', 'openRule']);

test('supported Mapping and Advanced open losslessly and retain shared writing and deleted targets', function () {
    withLinkedDraftFixture(function ($actor, $owner, $graph) {
        $store = app(ConfiguratorRuleWritingDrafts::class);
        foreach ($graph['rules'] as $rule) {
            $opened = $store->openRule($actor, $owner, (int) $rule['id'], (string) Str::uuid());
            $id = $opened['draft']['id'];
            expect($opened['draft']['base_rule'])->toEqual($rule)->and($opened['generated'])->toBeTrue()->and($opened)->not->toHaveKey('acknowledgement');
            $changed = $store->update($actor, $owner, $id, 1, ['source' => '/when invalid | ', 'description' => 'keep'], 1);
            $reopened = $store->openRule($actor, $owner, (int) $rule['id'], (string) Str::uuid());
            expect($reopened['draft'])->toBe($changed['draft'])->and($reopened['generated'])->toBeFalse();
            $this->travel(1)->seconds();
            $fresh = $store->openRule($actor, $owner, (int) $rule['id'], (string) Str::uuid(), fresh: true);
            expect($fresh['draft']['id'])->not->toBe($id)->and($fresh['draft']['source'])->toBe($opened['draft']['source']);
            DB::table('configurator_rules')->where('id', $rule['id'])->delete();
            expect($store->openRule($actor, $owner, (int) $rule['id'], (string) Str::uuid())['draft']['id'])->toBe($id);
            expect($store->get($actor, $owner, $id)['draft']['origin_rule_id'])->toBe((int) $rule['id']);
        }
    });
});

test('autosave cannot alter trusted captures identities or kind even when writing is invalid', function () {
    $actor = writingFixtureActor('ycm@data4.work');
    $draft = ConfiguratorRuleWritingDraft::factory()->create(['base_rule' => ['id' => '123'], 'bindings' => ['source' => 'trusted'], 'origin_rule_id' => 123, 'last_saved_authoring' => ['source' => 'original']]);
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $result = $store->update($actor, $draft->configurator, $draft->id, 1, ['source' => '/when { invalid'], 5);
    foreach (['base_rule', 'bindings', 'origin_rule_id', 'last_saved_authoring'] as $field) {
        expect($result['draft'][$field])->toBe($draft->$field);
    }
    foreach (['kind', 'origin_rule_id', 'base_rule', 'bindings', 'last_saved_authoring', 'revision', 'created_by', 'updated_by'] as $field) {
        expect(fn () => $store->update($actor, $draft->configurator, $draft->id, 2, ['source' => '', $field => null], 6))->toThrow(ValidationException::class);
    }
});

test('generated openings reject unsupported graphs and ambient transactions without loss', function () {
    withLinkedDraftFixture(function ($actor, $owner, $graph) {
        $store = app(ConfiguratorRuleWritingDrafts::class);
        DB::transaction(function () use ($actor, $owner, $graph, $store) {
            expect(fn () => $store->openRule($actor, $owner, (int) $graph['rules'][0]['id'], (string) Str::uuid()))->toThrow(LogicException::class);
        });
        $condition = $graph['rules'][1]['conditions'][0];
        DB::table('rule_conditions')->where('id', $condition['id'])->update(['source_kind' => 'Territory', 'context_dimension' => 'territory']);
        expect(fn () => $store->openRule($actor, $owner, (int) $graph['rules'][1]['id'], (string) Str::uuid()))->toThrow(ValidationException::class);
        expect(DB::table('configurator_rule_writing_drafts')->where('configurator_id', $owner->id)->count())->toBe(0);
    });
});

test('explicit reconstruction rejects a stored rule with conditions but no outcomes', function () {
    withLinkedDraftFixture(function ($actor, $owner, $graph) {
        DB::table('rule_effects')->where('rule_id', $graph['rules'][1]['id'])->delete();
        $store = app(ConfiguratorRuleWritingDrafts::class);
        expect(fn () => $store->openRule($actor, $owner, (int) $graph['rules'][1]['id'], (string) Str::uuid()))->toThrow(ValidationException::class);
        expect(DB::table('configurator_rule_writing_drafts')->where('configurator_id', $owner->id)->count())->toBe(0);
    });
});

test('outer rollback keeps acknowledged draft mutation provisional', function () {
    $actor = writingFixtureActor('ycm@data4.work');
    $owner = Configurator::factory()->create();
    $id = (string) Str::uuid();
    $result = null;
    try {
        DB::transaction(function () use ($actor, $owner, $id, &$result) {
            $result = app(ConfiguratorRuleWritingDrafts::class)->create($actor, $owner, $id, ['kind' => 'Mapping', 'source' => 'incomplete'], 0);
            throw new RuntimeException('later failure');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('later failure');
    }
    expect($result['acknowledgement']['revision'])->toBe(1);
    $this->assertDatabaseMissing('configurator_rule_writing_drafts', ['id' => $id]);
});

test('draft lifecycle retains attribution and owner boundaries without affecting executable revisions', function () {
    $actor = writingFixtureActor('ycm@data4.work');
    $owner = Configurator::factory()->create();
    $group = Group::factory()->create(['configurator_id' => $owner->id, 'catalog_revision' => 17]);
    $revision = $group->fresh()->catalog_revision;
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $saved = $store->create($actor, $owner, (string) Str::uuid(), ['kind' => 'Advanced', 'source' => '/hide @missing'], 1);
    $store->update($actor, $owner, $saved['draft']['id'], 1, ['source' => '/when {'], 2);
    expect($group->fresh()->catalog_revision)->toBe($revision);
    $actor->delete();
    $draft = ConfiguratorRuleWritingDraft::findOrFail($saved['draft']['id']);
    expect($draft->created_by)->toBeNull()->and($draft->updated_by)->toBeNull();
    $group->delete();
    $owner->delete();
    $this->assertDatabaseMissing('configurator_rule_writing_drafts', ['id' => $draft->id]);
});

test('linked opening retries preserve metadata and reject forged or foreign targets', function () {
    withLinkedDraftFixture(function ($actor, $owner, $graph) {
        $store = app(ConfiguratorRuleWritingDrafts::class);
        $id = (string) Str::uuid();
        $opened = $store->openRule($actor, $owner, (int) $graph['rules'][0]['id'], $id, fresh: true);
        expect($store->openRule($actor, $owner, (int) $graph['rules'][0]['id'], $id, fresh: true))->toBe($opened);
        expect(fn () => $store->openRule($actor, $owner, (int) $graph['rules'][1]['id'], $id, fresh: true))->toThrow(ValidationException::class);
        $foreign = Configurator::factory()->make(['id' => $owner->id + 999]);
        expect(fn () => $store->openRule($actor, $foreign, (int) $graph['rules'][0]['id'], (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
        DB::transaction(function () use ($actor, $owner, $graph, $store, $id) {
            expect($store->openRule($actor, $owner, (int) $graph['rules'][0]['id'], (string) Str::uuid())['draft']['id'])->toBe($id);
        });
        $store->update($actor, $owner, $id, 1, ['source' => $opened['draft']['source'], 'label' => 'New authoring label'], 1);
        expect(fn () => $store->openRule($actor, $owner, (int) $graph['rules'][0]['id'], $id, fresh: true))->toThrow(ValidationException::class);
    });
});
