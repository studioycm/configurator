<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorRuleWritingBatch;
use App\CatalogWriteFailure;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleWritingCodec;
use App\Services\ConfiguratorRuleWritingDrafts;
use App\Services\ConfiguratorRuleWritingInspection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

if (defined('CATALOG_MYSQL_TEST_RUN') && getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Writing inspection tests require the reviewed reconciliation profile.');
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

function inspectionSavedDraft(User $actor, Configurator $owner, int $ruleId): array
{
    $opened = app(ConfiguratorRuleWritingDrafts::class)->openRule($actor, $owner, $ruleId, (string) Str::uuid());

    return app(SaveConfiguratorRuleWritingBatch::class)->handle($actor, $owner, [['id' => $opened['draft']['id'], 'expected_revision' => 1, 'sequence' => 1]])['items'][0];
}

test('unsaved incomplete drafts inspect as drafts without inventing saved records or acknowledgements', function (string $kind) {
    $draft = ConfiguratorRuleWritingDraft::factory()->for($this->batchOwner)->create(['kind' => $kind, 'source' => " /when {\r\n ☃"]);
    $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $draft->id);
    expect($result['current'])->toBeNull()->and($result['last_writing_capture'])->toBeNull();
    expect($result['status'])->toBe(['target' => 'not_saved', 'capture' => 'none', 'draft_changed_since_capture' => null]);
    expect($result['draft']['source'])->toBe($draft->source)->and($result)->not->toHaveKey('acknowledgement');
})->with(['Mapping', 'Advanced']);

test('inspection returns actual save records and an independent complete Writing capture', function () {
    $saved = inspectionSavedDraft($this->batchActor, $this->batchOwner, (int) $this->batchGraph['rules'][0]['id']);
    $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $saved['draft']['id']);
    expect($result['current']['records'])->toBe($saved['records']);
    expect($result['current']['rule'])->toBe($saved['records']['rule']);
    expect($result['current']['fingerprint'])->toBe($saved['draft']['last_saved_authoring']['saved_fingerprint']);
    expect($result['last_writing_capture'])->toBe($saved['draft']['last_saved_authoring']);
    expect($result['status'])->toBe(['target' => 'saved', 'capture' => 'matches_current', 'draft_changed_since_capture' => false]);
    expect($result['draft']['revision'])->toBe($saved['acknowledgement']['revision']);
    expect($result)->not->toHaveKey('acknowledgement');
});

test('regular saved edits and later invalid authoring remain distinct from the original Writing capture', function () {
    $saved = inspectionSavedDraft($this->batchActor, $this->batchOwner, (int) $this->batchGraph['rules'][1]['id']);
    $draft = ConfiguratorRuleWritingDraft::findOrFail($saved['draft']['id']);
    $capture = $draft->last_saved_authoring;
    app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($this->batchActor, $this->batchOwner, $saved['saved_rule'], [...$saved['saved_rule'], 'label' => '0'], []);
    app(ConfiguratorRuleWritingDrafts::class)->update($this->batchActor, $this->batchOwner, $draft->id, $draft->revision, ['source' => '/when unfinished | ', 'label' => '0', 'description' => 'new metadata'], 7);
    $before = $draft->fresh()->getRawOriginal();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $draft->id);
    expect($result['status'])->toBe(['target' => 'saved', 'capture' => 'older_or_different', 'draft_changed_since_capture' => true]);
    expect($result['current']['rule']['label'])->toBe('0');
    expect($result['last_writing_capture'])->toBe($capture);
    expect($result['draft']['source'])->toBe('/when unfinished | ');
    expect($draft->fresh()->getRawOriginal())->toBe($before);
    expect(collect($queries)->filter(fn ($sql) => preg_match('/^(insert|update|delete)/i', $sql)))->toHaveCount(0);
});

test('deleted linked Rules retain the whole original capture without becoming new drafts', function () {
    $saved = inspectionSavedDraft($this->batchActor, $this->batchOwner, (int) $this->batchGraph['rules'][0]['id']);
    DB::table('configurator_rules')->where('id', $saved['rule_id'])->delete();
    $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $saved['draft']['id']);
    expect($result['current'])->toBeNull()->and($result['status']['target'])->toBe('deleted');
    expect($result['last_writing_capture'])->toBe($saved['draft']['last_saved_authoring']);
    expect($result['draft']['origin_rule_id'])->toBe($saved['rule_id']);
});

test('raw inspection tolerates unsupported and invalid stored data without reading unrelated malformed graphs', function (string $case) {
    $index = $case === 'unrelated invalid behavior' ? 1 : 0;
    $saved = inspectionSavedDraft($this->batchActor, $this->batchOwner, (int) $this->batchGraph['rules'][$index]['id']);
    if ($case === 'unsupported kind') {
        DB::table('configurator_rules')->where('id', $saved['rule_id'])->update(['kind' => 'FutureKind']);
    } else {
        DB::table('mapping_sets')->where('rule_id', $this->batchGraph['rules'][0]['id'])->update(['disallowed_target_behavior' => 'FutureBehavior']);
    }
    $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $saved['draft']['id']);
    expect($result['status']['target'])->toBe('saved');
    if ($case === 'unsupported kind') {
        expect($result['current']['rule']['kind'])->toBe('FutureKind');
    }
    if ($case === 'invalid behavior') {
        expect($result['current']['records']['mapping_sets'][0]['disallowed_target_behavior'])->toBe('FutureBehavior');
    }
    if ($case === 'unrelated invalid behavior') {
        expect($result['current']['records'])->toBe($saved['records']);
    }
    expect($result['last_writing_capture'])->toBe($saved['draft']['last_saved_authoring']);
})->with(['unsupported kind', 'invalid behavior', 'unrelated invalid behavior']);

test('inspection scopes drafts and authorizes before any protected query', function () {
    $inspection = app(ConfiguratorRuleWritingInspection::class);
    $outsider = User::factory()->make();
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    expect(fn () => $inspection->inspect($outsider, $this->batchOwner, (string) Str::uuid()))->toThrow(AuthorizationException::class);
    expect($queries)->toBe([]);
    expect(fn () => $inspection->inspect($this->batchActor, $this->batchOwner, (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    $foreign = ConfiguratorRuleWritingDraft::factory()->create();
    try {
        expect(fn () => $inspection->inspect($this->batchActor, $this->batchOwner, $foreign->id))->toThrow(ModelNotFoundException::class);
    } finally {
        $foreign->configurator->delete();
    }
});

test('inspection refuses an ambient transaction rather than claiming an older outer snapshot is current', function () {
    $draft = ConfiguratorRuleWritingDraft::factory()->for($this->batchOwner)->create();
    DB::transaction(function () use ($draft) {
        expect(fn () => app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $draft->id))->toThrow(LogicException::class);
    });
});

test('inspection after a lost commit response shows records without manufacturing a save acknowledgement', function () {
    $id = (string) Str::uuid();
    app(ConfiguratorRuleWritingDrafts::class)->create($this->batchActor, $this->batchOwner, $id, ['kind' => 'Advanced', 'source' => '/set-label @C "0"'], 0);
    $loseResponse = true;
    Event::listen(TransactionCommitted::class, function ($event) use (&$loseResponse) {
        if ($loseResponse && $event->connection->transactionLevel() === 0) {
            $loseResponse = false;
            throw new RuntimeException('Synthetic lost commit response');
        }
    });
    $selection = [['id' => $id, 'expected_revision' => 1, 'sequence' => 5]];
    try {
        app(SaveConfiguratorRuleWritingBatch::class)->handle($this->batchActor, $this->batchOwner, $selection);
        $this->fail('Expected uncertain outcome.');
    } catch (CatalogWriteFailure $exception) {
        expect($exception->rolledBack)->toBeFalse();
    }
    $result = app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $id);
    expect($result['status']['target'])->toBe('saved');
    expect($result)->not->toHaveKey('acknowledgement');
    expect($selection[0]['expected_revision'])->toBe(1);
    expect($result['draft']['revision'])->toBe(2);
});

test('a corrupt cross-owner origin cannot expose another Configurators Rule records', function () {
    $foreign = ConfiguratorRule::factory()->create();
    $draft = ConfiguratorRuleWritingDraft::factory()->for($this->batchOwner)->create(['origin_rule_id' => $foreign->id]);
    try {
        expect(fn () => app(ConfiguratorRuleWritingInspection::class)->inspect($this->batchActor, $this->batchOwner, $draft->id))->toThrow(ModelNotFoundException::class);
    } finally {
        $owner = $foreign->configurator;
        $foreign->delete();
        $owner->delete();
    }
});
