<?php

use App\Actions\SaveConfiguratorDefinition;
use App\CatalogWriteFailure;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\Option;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleExchange;
use App\Services\ConfiguratorRuleImport;
use App\Services\ConfiguratorRuleWritingCodec;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Livewire\Exceptions\PayloadTooLargeException;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\Process\Process;

if (defined('CATALOG_MYSQL_TEST_RUN') && getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Writing HTTP tests require the reviewed reconciliation profile.');
}

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

require_once dirname(__DIR__, 2).'/ConfiguratorRuleWritingFixtures.php';

beforeEach(function () {
    $this->writingFixtures = writingBeginOwnedFixtures();
    try {
        config(['configurator-writing.enabled' => true]);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('w', 32))]);
        $this->writingIds = ['users' => [], 'owners' => [], 'attributes' => [], 'options' => [], 'values' => []];
        $this->writingActor = writingFixtureActor('ycm@data4.work');
        $this->writingIds['users'][] = $this->writingActor->id;
        $this->actingAs($this->writingActor);
        [$this->writingOwner, $graph, $fixtureIds] = writingHttpDefinitionFixture();
        foreach ($fixtureIds as $table => $ids) {
            $this->writingIds[$table] = array_merge($this->writingIds[$table], $ids);
        }
        $graph['rules'] = [fixtureMapping(), [...fixtureAdvanced('advanced', [fixtureCondition('when')], 'C'), 'priority' => 1]];
        app(SaveConfiguratorDefinition::class)->handle($this->writingActor, $this->writingOwner, $graph);
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

/** @return array{Configurator, array<string, mixed>, array<string, list<int>>} */
function writingHttpDefinitionFixture(): array
{
    return DB::transaction(function (): array {
        [$owner, $graph] = writingCanonicalDefinitionFixture();
        $attributeIds = array_column($graph['attributes'], 'attribute_id');
        $optionIds = array_merge(...array_map(fn ($row) => array_column($row['options'], 'option_id'), $graph['attributes']));
        $valueIds = DB::table('options')->whereIn('id', $optionIds)->pluck('value_id')->all();

        return [$owner, $graph, ['owners' => [$owner->id], 'attributes' => $attributeIds, 'options' => $optionIds, 'values' => $valueIds]];
    });
}

test('HTTP fixture allocation rolls back a mid-helper failure while preserving unrelated records', function () {
    $tables = ['configurators', 'attributes', 'options', 'values', 'users'];
    $before = writingHttpState($tables);
    $dispatcher = Configurator::getEventDispatcher();
    Configurator::setEventDispatcher(clone $dispatcher);
    $allocated = 0;
    try {
        Configurator::created(function (Configurator $owner) {
            $this->writingIds['owners'][] = $owner->id;
        });
        Attribute::creating(function (Attribute $attribute) {
            $attribute->key = 'failed_fixture_'.$attribute->key;
        });
        Attribute::created(function (Attribute $attribute) {
            $this->writingIds['attributes'][] = $attribute->id;
        });
        Value::created(function (Value $value) {
            $this->writingIds['values'][] = $value->id;
        });
        Option::creating(function (Option $option) {
            $option->code = 'Z'.substr($option->code, -1);
        });
        Option::created(function (Option $option) use (&$allocated) {
            $this->writingIds['options'][] = $option->id;
            if (++$allocated === 2) {
                throw new RuntimeException('Injected partial fixture failure.');
            }
        });

        expect(fn () => writingHttpDefinitionFixture())->toThrow(RuntimeException::class, 'Injected partial fixture failure.');

        expect($allocated)->toBe(2);
        expect(writingHttpState($tables))->toBe($before);
        expect(DB::transactionLevel())->toBe(0)->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
    } finally {
        Configurator::setEventDispatcher($dispatcher);
    }
});

function writingHttpUrl(object $test, string $operation): string
{
    return "/admin/configurators/{$test->writingOwner->id}/writing/operations/{$operation}";
}

test('committed fixture cleanup preserves an existing eligible actor unchanged', function () {
    $actor = $this->writingActor;
    $created = in_array($actor->id, $this->writingFixtures->ids['users'] ?? [], true);
    $this->writingFixtures->ids['users'] = array_values(array_diff($this->writingFixtures->ids['users'] ?? [], [$actor->id]));
    writingCleanupOwnedFixtures($this->writingFixtures);
    $before = $actor->fresh()->getRawOriginal();
    $this->writingFixtures = writingBeginOwnedFixtures();
    try {
        expect(writingFixtureActor('ycm@data4.work')->id)->toBe($actor->id);
        [$owner] = writingCanonicalDefinitionFixture();
        expect($owner->exists)->toBeTrue();
    } finally {
        writingCleanupOwnedFixtures($this->writingFixtures);
    }
    expect($actor->fresh()->getRawOriginal())->toBe($before);
    if ($created) {
        $actor->delete();
    }
});

test('HTTP draft transport keeps exact unfinished source and optional metadata', function () {
    $id = (string) Str::uuid();
    $source = "  // original\r\n/when @A in #missing |\r\n";
    $response = $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => $id, 'authoring' => ['kind' => 'Mapping', 'source' => $source, 'label' => '0', 'description' => ''], 'sequence' => 0]);
    $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('data.draft.source', $source)->assertJsonPath('data.draft.label', '0')->assertJsonPath('data.draft.description', '');
    expect(ConfiguratorRuleWritingDraft::findOrFail($id)->source)->toBe($source)->and(DB::transactionLevel())->toBe(0);
    expect(DB::table('configurator_rules')->where('configurator_id', $this->writingOwner->id)->count())->toBe(2);
});

test('HTTP counters reject original JSON types before typed service coercion', function (string $raw) {
    $body = '{"id":"'.Str::uuid().'","authoring":{"kind":"Mapping","source":""},"sequence":'.$raw.'}';
    $this->call('POST', writingHttpUrl($this, 'create-draft'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_LENGTH' => strlen($body)], $body)
        ->assertStatus(422)->assertJsonPath('errors.sequence.0', 'Use a nonnegative safe integer.');
    expect(ConfiguratorRuleWritingDraft::where('configurator_id', $this->writingOwner->id)->count())->toBe(0);
})->with(['1.5', '1.0', '1e0', '"1"', 'true', 'null', '[]', '9007199254740992', '-1']);

test('HTTP accepts one MiB raw draft source despite JSON escaping and rejects one extra byte', function () {
    $source = str_repeat("\0", 1048576);
    $id = (string) Str::uuid();
    $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => $id, 'authoring' => ['kind' => 'Advanced', 'source' => $source], 'sequence' => 0])->assertOk();
    expect(ConfiguratorRuleWritingDraft::findOrFail($id)->source)->toBe($source);
    $this->postJson(writingHttpUrl($this, 'update-draft'), ['id' => $id, 'expected_revision' => 1, 'authoring' => ['source' => $source.'x'], 'sequence' => 1])->assertStatus(422)->assertJsonStructure(['errors' => ['source']]);
});

test('HTTP body cap is inclusive and based on actual body bytes', function () {
    $body = '{"page":1,"search":""}';
    $body .= str_repeat(' ', 20971520 - strlen($body));
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_LENGTH' => 10];
    $this->call('POST', writingHttpUrl($this, 'draft-summaries'), [], [], [], $server, $body)->assertOk();
    $this->call('POST', writingHttpUrl($this, 'draft-summaries'), [], [], [], $server, $body.' ')->assertStatus(413);
});

test('HTTP opens and saves existing Mapping and Advanced rules and inspects actual records', function () {
    $graph = app(ConfiguratorDefinitionLoader::class)->draft($this->writingOwner);
    $selected = [];
    foreach ($graph['rules'] as $rule) {
        $opened = $this->postJson(writingHttpUrl($this, 'open-rule'), ['rule_id' => (string) $rule['id'], 'new_draft_id' => (string) Str::uuid()])->assertOk()->json('data.draft');
        $selected[] = ['id' => $opened['id'], 'expected_revision' => $opened['revision'], 'sequence' => 0];
    }
    $saved = $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => $selected])->assertOk()->assertJsonPath('ok', true)->json('data');
    expect($saved['receipt_json'])->toBeString()->and($saved['items'])->toHaveCount(2)->and(DB::transactionLevel())->toBe(0)->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
    $inspection = $this->postJson(writingHttpUrl($this, 'inspect-draft'), ['id' => $selected[0]['id']])->assertOk()->json('data');
    $envelope = json_decode($inspection['json'], true, flags: JSON_THROW_ON_ERROR);
    expect($envelope['current']['records']['rule']['id'])->toBe((int) $graph['rules'][0]['id'])->and($envelope['status']['capture'])->toBe('matches_current');
});

test('Writing saves export real records and an imported update stales the draft without changing its capture', function () {
    $originalRuleIds = $this->writingOwner->rules()->orderBy('id')->pluck('id')->all();
    $originalRules = $this->writingOwner->rules()->orderBy('id')->get()->map(fn (ConfiguratorRule $rule): array => $rule->getRawOriginal())->all();
    $originalChildIds = [];
    foreach (['rule_conditions', 'rule_effects', 'mapping_sets'] as $table) {
        $originalChildIds[$table] = DB::table($table)->whereIn('rule_id', $originalRuleIds)->pluck('id')->all();
    }
    $graph = app(ConfiguratorDefinitionLoader::class)->draft($this->writingOwner);
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $codec = app(ConfiguratorRuleWritingCodec::class);
    $dictionary = $codec->dictionary($graph, $attributes, $options);
    $selected = [];
    $authoredSources = [];
    foreach ($graph['rules'] as $rule) {
        $source = '// New '.$rule['kind']." · שמירה\r\n".str_replace("\n", "\r\n", $codec->open($rule, $dictionary)['source']);
        $opened = $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => (string) Str::uuid(), 'authoring' => ['kind' => $rule['kind'], 'source' => $source, 'label' => 'New '.$rule['kind'].' export', 'description' => 'Preserve "0" and שמירה'], 'sequence' => 0])->assertOk()->json('data.draft');
        expect($opened['origin_rule_id'])->toBeNull();
        $authoredSources[$opened['id']] = $source;
        $selected[] = ['id' => $opened['id'], 'expected_revision' => $opened['revision'], 'sequence' => 0];
    }
    $saved = $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => $selected])->assertOk()->json('data.items');
    $newRuleIds = array_map(fn (array $item): int => (int) $item['rule_id'], $saved);
    expect(array_unique($newRuleIds))->toHaveCount(2)
        ->and(array_intersect($newRuleIds, $originalRuleIds))->toBe([])
        ->and($this->writingOwner->rules()->count())->toBe(4);
    $rows = [];
    $captures = [];
    foreach ($saved as $item) {
        $draft = ConfiguratorRuleWritingDraft::findOrFail($item['draft']['id']);
        $captures[$draft->id] = $draft->getRawOriginal('last_saved_authoring');
        expect($draft->source)->toBe($authoredSources[$draft->id])
            ->and($draft->last_saved_authoring['source'])->toBe($authoredSources[$draft->id]);
        $rule = ConfiguratorRule::findOrFail($item['rule_id']);
        expect((int) $draft->origin_rule_id)->toBe($rule->id);
        $expectedKeys = [];
        foreach (['rule_conditions' => 'c_', 'rule_effects' => 'e_', 'mapping_sets' => 's_'] as $table => $prefix) {
            $ids = DB::table($table)->where('rule_id', $rule->id)->orderBy('id')->pluck('id')->all();
            $expectedKeys = [...$expectedKeys, ...array_map(fn (int $id): string => $prefix.$id, $ids)];
            expect(array_intersect($ids, $originalChildIds[$table]))->toBe([]);
        }
        expect($expectedKeys)->not->toBeEmpty();
        $exported = app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows'];
        expect(array_unique(array_column($exported, 'rule_id')))->toBe([(string) $item['rule_id']]);
        expect(array_column($exported, 'row_key'))->toEqualCanonicalizing($expectedKeys);
        if ($rule->kind === 'Mapping') {
            expect(array_column($exported, 'row_type'))->toBe(['MappingSet']);
            $setId = (int) substr($expectedKeys[0], 2);
            expect(DB::table('mapping_set_sources')->where('mapping_set_id', $setId)->exists())->toBeTrue()
                ->and(DB::table('mapping_set_targets')->where('mapping_set_id', $setId)->exists())->toBeTrue();
        } else {
            expect(array_column($exported, 'row_type'))->toBe(['Condition', 'Effect']);
        }
        foreach ($exported as $row) {
            $rows[] = [...$row, 'import' => '1', 'rule_label' => $row['rule_label'].' imported'];
        }
    }
    $importer = app(ConfiguratorRuleImport::class);
    $preview = $importer->previewRows($this->writingActor, $rows, ['allow_update' => true], $this->writingOwner->id);
    expect($preview['errors'])->toBe([])->and($preview['can_import'])->toBeTrue();
    expect($importer->apply($this->writingActor, $preview['token'], $this->writingOwner->id))->toBe(['created' => 0, 'updated' => 2, 'configurators' => 1]);
    foreach ($saved as $item) {
        $id = $item['draft']['id'];
        $draft = ConfiguratorRuleWritingDraft::findOrFail($id);
        expect($draft->getRawOriginal('last_saved_authoring'))->toBe($captures[$id])
            ->and($draft->source)->toBe($draft->last_saved_authoring['source']);
        $inspection = $this->postJson(writingHttpUrl($this, 'inspect-draft'), ['id' => $id])->assertOk()->json('data');
        expect($inspection['debug']['status']['capture'])->toBe('older_or_different');
    }
    expect($this->writingOwner->rules()->whereIn('id', $originalRuleIds)->orderBy('id')->get()->map(fn (ConfiguratorRule $rule): array => $rule->getRawOriginal())->all())->toBe($originalRules);
    $beforeRetry = writingHttpState(['configurators', 'configurator_rules', 'rule_conditions', 'rule_effects', 'mapping_sets', 'mapping_set_sources', 'mapping_set_targets', 'configurator_rule_writing_drafts']);
    $retry = array_map(fn (array $item): array => ['id' => $item['draft']['id'], 'expected_revision' => $item['draft']['revision'], 'sequence' => 1], $saved);
    $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => $retry])->assertStatus(422);
    expect(writingHttpState(array_keys($beforeRetry)))->toBe($beforeRetry);
});

test('HTTP cannot open a rule belonging to another Configurator', function () {
    $foreign = Configurator::factory()->create();
    $this->writingIds['owners'][] = $foreign->id;
    $rule = ConfiguratorRule::factory()->for($foreign)->create();
    $this->postJson(writingHttpUrl($this, 'open-rule'), ['rule_id' => (string) $rule->id, 'new_draft_id' => (string) Str::uuid()])->assertNotFound();
    expect(ConfiguratorRuleWritingDraft::where('configurator_id', $this->writingOwner->id)->count())->toBe(0);
});

function writingHttpOpen(object $test, ?string $ruleId = null): array
{
    $ruleId ??= (string) $test->writingOwner->rules()->orderBy('id')->firstOrFail()->id;

    return $test->postJson(writingHttpUrl($test, 'open-rule'), ['rule_id' => $ruleId, 'new_draft_id' => (string) Str::uuid()])->assertOk()->json('data.draft');
}

test('HTTP creates a complete Mapping and Advanced batch while retaining an unfinished unselected draft', function () {
    $graph = app(ConfiguratorDefinitionLoader::class)->draft($this->writingOwner);
    [$attributes, $options] = app(ConfiguratorDefinitionLoader::class)->canonical($graph);
    $dictionary = app(ConfiguratorRuleWritingCodec::class)->dictionary($graph, $attributes, $options);
    $selected = [];
    foreach ($graph['rules'] as $rule) {
        $source = app(ConfiguratorRuleWritingCodec::class)->open($rule, $dictionary)['source'];
        $id = (string) Str::uuid();
        $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => $id, 'authoring' => ['kind' => $rule['kind'], 'source' => $source, 'description' => ''], 'sequence' => 0])->assertOk();
        $selected[] = ['id' => $id, 'expected_revision' => 1, 'sequence' => 0];
    }
    $unfinished = (string) Str::uuid();
    $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => $unfinished, 'authoring' => ['kind' => 'Advanced', 'source' => '/when'], 'sequence' => 0])->assertOk();
    $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => $selected])->assertOk();
    expect(DB::table('configurator_rules')->where('configurator_id', $this->writingOwner->id)->count())->toBe(4);
    expect(ConfiguratorRuleWritingDraft::findOrFail($unfinished)->source)->toBe('/when')->and(ConfiguratorRuleWritingDraft::findOrFail($unfinished)->origin_rule_id)->toBeNull();
});

test('HTTP invalid selected rule rejects the whole executable batch and leaves all draft input', function () {
    $valid = writingHttpOpen($this);
    $id = (string) Str::uuid();
    $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => $id, 'authoring' => ['kind' => 'Advanced', 'source' => '/when'], 'sequence' => 0])->assertOk();
    $before = DB::table('configurator_rules')->orderBy('id')->get()->toJson();
    $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => [['id' => $valid['id'], 'expected_revision' => 1, 'sequence' => 0], ['id' => $id, 'expected_revision' => 1, 'sequence' => 0]]])->assertStatus(422);
    expect(DB::table('configurator_rules')->orderBy('id')->get()->toJson())->toBe($before)->and(ConfiguratorRuleWritingDraft::findOrFail($id)->source)->toBe('/when');
});

/** @param list<string> $tables @return array<string, string> */
function writingHttpState(array $tables): array
{
    return collect($tables)->mapWithKeys(fn ($table) => [$table => hash('sha256', DB::table($table)->orderBy('id')->get()->toJson())])->all();
}

test('HTTP post-commit response failure retains the committed rule without fabricating a save acknowledgement', function () {
    $id = (string) Str::uuid();
    $source = '/set-label @C "Committed text"';
    $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => $id, 'authoring' => ['kind' => 'Advanced', 'source' => $source, 'label' => 'Committed rule'], 'sequence' => 0])->assertOk();
    $controlTables = ['catalog_context_settings', 'groups', 'configurators', 'configurator_attributes', 'configurator_options', 'attributes', 'options', 'values'];
    $beforeControl = writingHttpState($controlTables);
    $originalRules = DB::table('configurator_rules')->orderBy('id')->get()->toJson();
    $originalIds = DB::table('configurator_rules')->pluck('id')->all();
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $connection->setEventDispatcher(clone $dispatcher);
    $commits = 0;
    $connection->getEventDispatcher()->listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$commits) {
        if ($event->connection->transactionLevel() === 0 && ++$commits === 1) {
            throw new RuntimeException('Synthetic private post-commit failure.');
        }
    });
    Exceptions::fake([CatalogWriteFailure::class]);
    try {
        $response = $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => [['id' => $id, 'expected_revision' => 1, 'sequence' => 0]]]);
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }

    $response->assertInternalServerError()->assertExactJson(['ok' => false, 'type' => 'failure', 'rolledBack' => false, 'message' => __('configurator.writing.write_failed')]);
    Exceptions::assertReported(fn (CatalogWriteFailure $failure) => ! $failure->rolledBack && $failure->getPrevious()?->getMessage() === 'Synthetic private post-commit failure.');
    expect($commits)->toBe(1);
    $stored = ConfiguratorRuleWritingDraft::findOrFail($id);
    expect($stored->origin_rule_id)->not->toBeNull();
    expect($stored->revision)->toBe(2)->and($stored->last_saved_authoring['source'])->toBe($source)->and($stored->last_saved_authoring['rule_id'])->toBe($stored->origin_rule_id);
    $this->assertDatabaseHas('configurator_rules', ['id' => $stored->origin_rule_id, 'label' => 'Committed rule']);
    expect(DB::table('configurator_rules')->where('configurator_id', $this->writingOwner->id)->count())->toBe(3);
    expect(DB::table('configurator_rules')->whereIn('id', $originalIds)->orderBy('id')->get()->toJson())->toBe($originalRules);
    expect(writingHttpState($controlTables))->toBe($beforeControl);
    expect(DB::transactionLevel())->toBe(0)->and($connection->getPdo()->inTransaction())->toBeFalse();
});

test('HTTP late body failure proves rollback of rules drafts and control state', function () {
    $id = (string) Str::uuid();
    $source = '/set-label @C "Retained draft text"';
    $this->postJson(writingHttpUrl($this, 'create-draft'), ['id' => $id, 'authoring' => ['kind' => 'Advanced', 'source' => $source], 'sequence' => 0])->assertOk();
    $tables = ['catalog_context_settings', 'groups', 'configurators', 'configurator_attributes', 'configurator_options', 'configurator_rules', 'rule_condition_groups', 'rule_conditions', 'rule_condition_options', 'rule_effects', 'rule_effect_options', 'mapping_sets', 'mapping_set_sources', 'mapping_set_targets', 'configurator_rule_writing_drafts', 'attributes', 'options', 'values'];
    $before = writingHttpState($tables);
    $dispatcher = ConfiguratorRuleWritingDraft::getEventDispatcher();
    ConfiguratorRuleWritingDraft::setEventDispatcher(clone $dispatcher);
    $failures = 0;
    $provisional = null;
    ConfiguratorRuleWritingDraft::updating(function (ConfiguratorRuleWritingDraft $draft) use (&$failures, &$provisional) {
        $failures++;
        $provisional = ['rule_count' => ConfiguratorRule::where('configurator_id', $draft->configurator_id)->count(), 'rule_exists' => ConfiguratorRule::whereKey($draft->origin_rule_id)->exists(), 'level' => DB::transactionLevel()];
        throw new RuntimeException('Synthetic private late capture failure.');
    });
    Exceptions::fake([CatalogWriteFailure::class]);
    try {
        $response = $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => [['id' => $id, 'expected_revision' => 1, 'sequence' => 0]]]);
    } finally {
        ConfiguratorRuleWritingDraft::setEventDispatcher($dispatcher);
    }

    $response->assertInternalServerError()->assertExactJson(['ok' => false, 'type' => 'failure', 'rolledBack' => true, 'message' => __('configurator.writing.write_failed')]);
    Exceptions::assertReported(fn (CatalogWriteFailure $failure) => $failure->rolledBack && $failure->getPrevious()?->getMessage() === 'Synthetic private late capture failure.');
    expect($failures)->toBe(1)->and($provisional)->toBe(['rule_count' => 3, 'rule_exists' => true, 'level' => 1]);
    expect(writingHttpState($tables))->toBe($before);
    $stored = ConfiguratorRuleWritingDraft::findOrFail($id);
    expect($stored->source)->toBe($source)->and($stored->revision)->toBe(1)->and($stored->origin_rule_id)->toBeNull()->and($stored->last_saved_authoring)->toBeNull();
    expect(DB::transactionLevel())->toBe(0)->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
});

test('HTTP signed section refresh roundtrips and retains the original full Writing capture after normal save', function () {
    $draft = writingHttpOpen($this);
    $this->postJson(writingHttpUrl($this, 'save-selected'), ['selection' => [['id' => $draft['id'], 'expected_revision' => 1, 'sequence' => 0]]])->assertOk();
    $stored = ConfiguratorRuleWritingDraft::findOrFail($draft['id']);
    $capture = $stored->last_saved_authoring;
    $graph = app(ConfiguratorDefinitionLoader::class)->draft($this->writingOwner);
    $graph['rules'][0]['label'] = 'Regular 0';
    app(SaveConfiguratorDefinition::class)->handle($this->writingActor, $this->writingOwner, $graph);
    $latest = $this->postJson(writingHttpUrl($this, 'capture-latest'), ['id' => $stored->id, 'expected_revision' => $stored->revision])->assertOk()->json('data');
    expect($latest['snapshot_json'])->toBeString();
    $this->postJson(writingHttpUrl($this, 'refresh-sections'), ['id' => $stored->id, 'expected_revision' => $stored->revision, 'snapshot_json' => $latest['snapshot_json'], 'sections' => ['label'], 'sequence' => 1])->assertOk()->assertJsonPath('data.draft.label', 'Regular 0')->assertJsonPath('data.draft.source', $draft['source']);
    expect($stored->fresh()->last_saved_authoring)->toBe($capture);
    $current = $this->postJson(writingHttpUrl($this, 'inspect-draft'), ['id' => $stored->id])->assertOk()->json('data');
    expect($current['debug']['status']['capture'])->toBe('older_or_different');
});

test('HTTP snapshot size crosses Livewire cap without bypassing signed snapshot validation', function () {
    $draft = writingHttpOpen($this);
    $raw = '{"payload":{},"signature":"bad","padding":"';
    $raw .= str_repeat('\\\\', intdiv(8388608 - strlen($raw) - 2, 2)).'"}';
    $raw = str_pad($raw, 8388608, ' ');
    foreach ([$raw, $raw.' '] as $snapshot) {
        $this->postJson(writingHttpUrl($this, 'refresh-sections'), ['id' => $draft['id'], 'expected_revision' => 1, 'snapshot_json' => $snapshot, 'sections' => [], 'sequence' => 1])->assertStatus(422)->assertJsonStructure(['errors' => ['snapshot']]);
    }
    expect(ConfiguratorRuleWritingDraft::findOrFail($draft['id'])->revision)->toBe(1);
});

test('HTTP preserves shared draft losing input and rejects stale or fractional versions', function () {
    $draft = writingHttpOpen($this);
    $body = ['id' => $draft['id'], 'expected_revision' => 1, 'authoring' => ['source' => $draft['source'].'\n// first'], 'sequence' => 1];
    $this->postJson(writingHttpUrl($this, 'update-draft'), $body)->assertOk();
    $body['authoring']['source'] = '// losing input';
    $this->postJson(writingHttpUrl($this, 'update-draft'), $body)->assertStatus(422)->assertJsonStructure(['errors' => ['draft_revision']]);
    expect(ConfiguratorRuleWritingDraft::findOrFail($draft['id'])->source)->not->toBe('// losing input');
    $json = '{"selection":[{"id":"'.$draft['id'].'","expected_revision":2.0,"sequence":1}]}';
    $this->call('POST', writingHttpUrl($this, 'save-selected'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_LENGTH' => strlen($json)], $json)->assertStatus(422)->assertJsonStructure(['errors' => ['selection.0.expected_revision']]);
});

test('HTTP preserves native errors, media parsing, authentication and role denial', function () {
    $url = writingHttpUrl($this, 'draft-summaries');
    $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertStatus(415);
    $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{')->assertStatus(400);
    $this->writingActor->email = 'denied@example.test';
    $this->postJson($url, ['page' => 1])->assertForbidden();
    auth()->forgetGuards();
    $this->postJson($url, ['page' => 1])->assertUnauthorized();
});

test('HTTP request forgery uses the real token and origin policy', function () {
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app->make(Encrypter::class)) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
    $url = writingHttpUrl($this, 'draft-summaries');
    $this->withSession(['_token' => 'writing-private-test-token']);
    $this->postJson($url, ['page' => 1])->assertStatus(419);
    $this->postJson($url, ['page' => 1], ['X-CSRF-TOKEN' => 'writing-private-test-token'])->assertOk();
    $this->postJson($url, ['page' => 1], ['Sec-Fetch-Site' => 'same-origin'])->assertOk();
});

test('HTTP writing transport leaves the default Livewire one MiB limit intact', function () {
    Exceptions::fake([PayloadTooLargeException::class]);
    $uri = app(HandleRequests::class)->getUpdateUri();
    expect(config('livewire.payload.max_size'))->toBe(1048576);
    $this->postJson($uri, ['components' => [], 'padding' => str_repeat('x', 1048576)], ['X-Livewire' => 'true'])->assertStatus(500);
    Exceptions::assertReported(PayloadTooLargeException::class);
    expect(config('livewire.payload.max_size'))->toBe(1048576);
});

test('HTTP fixture file refuses another MySQL profile before framework bootstrap', function () {
    $code = 'define("CATALOG_MYSQL_TEST_RUN", true); putenv("CATALOG_MYSQL_TEST_PROFILE=catalog"); require '.var_export(__FILE__, true).';';
    $process = new Process([PHP_BINARY, '-n', '-r', $code]);
    $process->run();
    expect($process->isSuccessful())->toBeFalse()->and($process->getErrorOutput().$process->getOutput())->toContain('Writing HTTP tests require the reviewed reconciliation profile.');
});
