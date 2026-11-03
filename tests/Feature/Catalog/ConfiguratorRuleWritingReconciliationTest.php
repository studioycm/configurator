<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorRuleWritingBatch;
use App\CatalogWriteFailure;
use App\Models\Configurator;
use App\Models\ConfiguratorRule;
use App\Models\ConfiguratorRuleWritingDraft;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleRecords;
use App\Services\ConfiguratorRuleWritingDrafts;
use App\Services\ConfiguratorRuleWritingReconciliation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

if (defined('CATALOG_MYSQL_TEST_RUN') && getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'reconciliation') {
    throw new RuntimeException('Writing reconciliation tests require the reviewed reconciliation profile.');
}
require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

require_once dirname(__DIR__, 2).'/ConfiguratorRuleWritingFixtures.php';

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
    } catch (Throwable $exception) {
        writingCleanupOwnedFixtures($this->writingFixtures);
        throw $exception;
    }
});

afterEach(function () {
    writingCleanupOwnedFixtures($this->writingFixtures ?? null);
});

function reconOpen(object $test, string $kind): ConfiguratorRuleWritingDraft
{
    $rule = collect($test->reconGraph['rules'])->firstWhere('kind', $kind);
    $result = app(ConfiguratorRuleWritingDrafts::class)->openRule($test->reconActor, $test->reconOwner, (int) $rule['id'], (string) Str::uuid());

    return ConfiguratorRuleWritingDraft::findOrFail($result['draft']['id']);
}

function reconRemote(object $test, ConfiguratorRuleWritingDraft $draft): array
{
    $rule = collect(app(ConfiguratorDefinitionLoader::class)->draft($test->reconOwner->fresh())['rules'])->firstWhere('id', (string) $draft->origin_rule_id);
    $proposal = [...$rule, 'label' => 'remote label'];
    $proposal['conditions'][0]['operand'] = 'remote';
    if ($draft->kind->value === 'Mapping') {
        $proposal['sets'][0]['target_option_ids'] = [$test->reconGraph['attributes'][1]['options'][0]['id']];
    } else {
        $proposal['effects'][0]['display_value'] = 'remote hint';
    }

    return app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($test->reconActor, $test->reconOwner, $rule, $proposal, [])['saved_rule'];
}

function reconRecords(ConfiguratorRuleWritingDraft $draft): array
{
    return app(ConfiguratorRuleRecords::class)->read(ConfiguratorRule::findOrFail($draft->origin_rule_id));
}

test('Keep mine advances the stale baseline without losing either codec shortcut path', function (string $kind, bool $comment) {
    $draft = reconOpen($this, $kind);
    $source = ($comment ? "// my original layout\r\n" : '').$draft->source;
    app(ConfiguratorRuleWritingDrafts::class)->update($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, ['source' => $source, 'description' => 'keep description'], 1);
    $draft->refresh();
    $remote = reconRemote($this, $draft);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $before = reconRecords($draft);
    $capture = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision);
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $capture['snapshot'], [], 9);
    expect($result['acknowledgement'])->toMatchArray(['sequence' => 9, 'source' => $source, 'description' => 'keep description']);
    expect($result['draft']['base_rule'])->toEqual($remote);
    expect(reconRecords($draft))->toBe($before);
    $draft->refresh();
    $saved = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 10]])['items'][0];
    expect($saved['saved_rule']['conditions'][0]['operand'])->toBe('old');
    expect($saved['saved_rule']['conditions'][0]['id'])->toBe($remote['conditions'][0]['id']);
    expect($saved['draft']['last_saved_authoring']['source'])->toBe($source);
    expect($saved['draft']['bindings'])->not->toHaveKey('identity_rule');
    if ($kind === 'Mapping') {
        expect($saved['saved_rule']['sets'][0]['target_option_ids'])->toBe($this->reconGraph['rules'][0]['sets'][0]['target_option_ids']);
    } else {
        expect($saved['saved_rule']['effects'][0]['display_value'])->toBe('old hint');
    }
})->with(['Mapping', 'Advanced'])->with([false, true]);

test('explicitly checked sections replace only their syntax and never executable rows or capture', function (string $kind, int $mask) {
    $draft = reconOpen($this, $kind);
    $original = $draft->source;
    $mine = "// mine\r\n".str_replace("\n", "\r\n", $original)." // tail\r\n";
    app(ConfiguratorRuleWritingDrafts::class)->update($this->reconActor, $this->reconOwner, $draft->id, 1, ['source' => $mine, 'label' => 'mine label', 'description' => 'mine description'], 2);
    $draft->refresh();
    $remote = reconRemote($this, $draft);
    $sections = array_values(array_filter([($mask & 1) ? 'label' : null, ($mask & 2) ? 'conditions' : null, ($mask & 4) ? ($kind === 'Mapping' ? 'mapping' : 'effects') : null]));
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision)['snapshot'];
    $before = reconRecords($draft);
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, $sections, 3);
    expect($result['draft']['source'])->toStartWith("// mine\r\n")->toEndWith(" // tail\r\n");
    expect($result['draft']['source'])->toContain(($mask & 2) ? '"remote"' : '"old"');
    expect($result['draft']['label'])->toBe(($mask & 1) ? 'remote label' : 'mine label');
    expect($result['draft']['description'])->toBe('mine description');
    expect($result['draft']['last_saved_authoring'])->toBeNull();
    expect(reconRecords($draft))->toBe($before);
    $draft->refresh();
    $saved = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 4]])['items'][0]['saved_rule'];
    expect($saved['conditions'][0]['operand'])->toBe(($mask & 2) ? 'remote' : 'old');
    expect(array_column($saved[$kind === 'Mapping' ? 'sets' : 'effects'], 'id'))->toBe(array_column($remote[$kind === 'Mapping' ? 'sets' : 'effects'], 'id'));
})->with(['Mapping', 'Advanced'])->with(range(0, 7));

test('capture and declined refresh leave exact draft capture and executable rows untouched', function () {
    $draft = reconOpen($this, 'Mapping');
    $saved = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 1]]);
    $draft->refresh();
    app(ConfiguratorRuleWritingDrafts::class)->update($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, ['source' => '/when incomplete', 'description' => 'keep'], 2);
    $draft->refresh();
    reconRemote($this, $draft);
    $before = $draft->getRawOriginal();
    $records = reconRecords($draft);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision)['snapshot'];
    expect($draft->fresh()->getRawOriginal())->toBe($before);
    expect(fn () => $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, ['conditions'], 3))->toThrow(ValidationException::class);
    expect($draft->fresh()->getRawOriginal())->toBe($before);
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, ['label'], 4);
    expect($result['draft']['source'])->toBe('/when incomplete')->and($result['draft']['last_saved_authoring'])->toEqual($saved['items'][0]['draft']['last_saved_authoring']);
    expect(reconRecords($draft))->toBe($records);
});

test('later owned changes remain stale while Enabled and priority only changes survive explicit save', function (bool $owned) {
    $draft = reconOpen($this, 'Advanced');
    reconRemote($this, $draft);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision)['snapshot'];
    DB::table('configurator_rules')->where('id', $draft->origin_rule_id)->update($owned ? ['label' => 'third write'] : ['is_active' => false, 'priority' => 7]);
    $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, [], 1);
    $draft->refresh();
    $before = $draft->getRawOriginal();
    $records = reconRecords($draft);
    $action = fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 2]]);
    if ($owned) {
        expect($action)->toThrow(ValidationException::class);
        expect($draft->fresh()->getRawOriginal())->toBe($before);
        expect(reconRecords($draft))->toBe($records);
    } else {
        $saved = $action()['items'][0]['saved_rule'];
        expect($saved['is_active'])->toBeFalse()->and($saved['priority'])->toBe(7);
    }
})->with([true, false]);

test('repeated partial refresh uses prior identity baseline and retains unchecked autosaved edits', function (string $kind) {
    $draft = reconOpen($this, $kind);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    reconRemote($this, $draft);
    $capture = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision);
    $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $capture['snapshot'], ['conditions'], 1);
    $draft->refresh();
    $source = str_replace('"remote"', '"authored"', $draft->source);
    app(ConfiguratorRuleWritingDrafts::class)->update($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, ['source' => $source, 'description' => 'mine'], 2);
    $draft->refresh();
    $capture = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision);
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $capture['snapshot'], [$kind === 'Mapping' ? 'mapping' : 'effects'], 3);
    expect($result['draft']['source'])->toContain('"authored"');
    $draft->refresh();
    $saved = app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 4]])['items'][0]['saved_rule'];
    expect($saved['conditions'][0]['operand'])->toBe('authored');
    if ($kind === 'Advanced') {
        expect($saved['effects'][0]['display_value'])->toBe('remote hint');
    }
})->with(['Mapping', 'Advanced']);

test('missing unchecked children cannot be resurrected but their full latest section can be taken', function (string $kind) {
    $draft = reconOpen($this, $kind);
    $bucket = $kind === 'Mapping' ? 'sets' : 'effects';
    $latest = $draft->base_rule;
    $proposal = $latest;
    $proposal[$bucket][0]['id'] = 'new:replacement';
    app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($this->reconActor, $this->reconOwner, $latest, $proposal, []);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    $before = $draft->getRawOriginal();
    expect(fn () => $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, [], 1))->toThrow(ValidationException::class);
    expect($draft->fresh()->getRawOriginal())->toBe($before);
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, [$kind === 'Mapping' ? 'mapping' : 'effects'], 2);
    expect($result['draft']['bindings']['identity_rule'][$bucket][0]['id'])->not->toBe($latest[$bucket][0]['id']);
})->with(['Mapping', 'Advanced']);

test('discarded old references may be renamed while retained and current valid rebound references reject', function (string $mode) {
    $draft = reconOpen($this, 'Advanced');
    if ($mode === 'current') {
        $source = str_replace('/when all {', '/when all {'."\n".'  @A = #'.array_key_first($draft->bindings['dictionary']['A'][0]['values']), $draft->source);
        app(ConfiguratorRuleWritingDrafts::class)->update($this->reconActor, $this->reconOwner, $draft->id, 1, ['source' => $source], 1);
        $draft->refresh();
    }
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    if ($mode === 'discarded') {
        $rule = $draft->base_rule;
        $proposal = $rule;
        $proposal['effects'][0]['target_configurator_attribute_id'] = $this->reconGraph['attributes'][1]['id'];
        app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($this->reconActor, $this->reconOwner, $rule, $proposal, []);
        DB::table('attributes')->where('id', $this->reconGraph['attributes'][2]['attribute_id'])->update(['key' => 'renamed_C']);
    }
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision)['snapshot'];
    if ($mode !== 'discarded') {
        $index = $mode === 'current' ? 0 : 2;
        DB::table('attributes')->where('id', $this->reconGraph['attributes'][$index]['attribute_id'])->update(['key' => 'renamed']);
    }
    $before = $draft->getRawOriginal();
    if ($mode === 'discarded') {
        $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, ['effects'], 2);
        expect($result['draft']['source'])->toContain('/set-hint @B');
    } else {
        expect(fn () => $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, ['label'], 2))->toThrow(ValidationException::class);
        expect($draft->fresh()->getRawOriginal())->toBe($before);
    }
})->with(['discarded', 'retained', 'current']);

test('signature actor scope shared revision and explicit selection reject before changing the draft', function (string $failure) {
    $draft = reconOpen($this, 'Advanced');
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    $checked = [];
    $actor = $this->reconActor;
    $owner = $this->reconOwner;
    $revision = 1;
    if ($failure === 'tamper') {
        $snapshot['payload']['latest_rule']['label'] = 'tampered';
    }
    if ($failure === 'actor') {
        $actor = $this->reconActor->replicate()->forceFill(['id' => $actor->id + 900]);
    }
    if ($failure === 'scope') {
        $owner = $this->reconOwner->replicate()->forceFill(['id' => $owner->id + 900]);
    }
    if ($failure === 'revision') {
        $revision = 2;
    }
    if ($failure === 'CAS') {
        app(ConfiguratorRuleWritingDrafts::class)->update($actor, $owner, $draft->id, 1, ['source' => $draft->source, 'description' => 'other'], 1);
        $draft->refresh();
    }
    if ($failure === 'duplicate') {
        $checked = ['label', 'label'];
    }
    if ($failure === 'unknown') {
        $checked = ['enabled'];
    }
    if ($failure === 'wrong section') {
        $checked = ['mapping'];
    }
    if ($failure === 'no key') {
        config(['app.key' => '']);
    }
    if ($failure === 'invalid UTF8') {
        $snapshot['payload']['latest_source'] = chr(255);
    }
    if ($failure === 'oversized') {
        $snapshot['payload']['padding'] = str_repeat('x', 8388608);
    }
    $before = $draft->getRawOriginal();
    expect(fn () => $service->updateDraft($actor, $owner, $draft->id, $revision, $snapshot, $checked, 2))->toThrow(ValidationException::class);
    expect($draft->fresh()->getRawOriginal())->toBe($before);
})->with(['tamper', 'actor', 'scope', 'revision', 'CAS', 'duplicate', 'unknown', 'wrong section', 'no key', 'oversized', 'invalid UTF8']);

test('refresh no-op retains revision timestamps attribution and the complete capture', function () {
    $draft = reconOpen($this, 'Advanced');
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $capture = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1);
    $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $capture['snapshot'], [], 1);
    $draft->refresh();
    $capture = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision);
    $before = $draft->getRawOriginal();
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $capture['snapshot'], [], 2);
    expect($draft->fresh()->getRawOriginal())->toBe($before);
    expect($result['acknowledgement']['revision'])->toBe($draft->revision);
});

test('deleted target stays a logical Update and explicit save cannot recreate it', function () {
    $draft = reconOpen($this, 'Advanced');
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    DB::table('configurator_rules')->where('id', $draft->origin_rule_id)->delete();
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, ['label'], 1);
    expect($result['draft']['origin_rule_id'])->toBe($draft->origin_rule_id);
    $draft->refresh();
    expect(fn () => app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 2]]))->toThrow(ValidationException::class);
    expect(ConfiguratorRule::whereKey($draft->origin_rule_id)->exists())->toBeFalse();
});

test('an unrelated malformed Rule does not prevent target capture but an unsupported target declines safely', function (bool $target) {
    $draft = reconOpen($this, 'Mapping');
    $id = $target ? $draft->origin_rule_id : (int) $this->reconGraph['rules'][1]['id'];
    DB::table('mapping_sets')->insert(['rule_id' => $id, 'label' => 'invalid', 'sort_order' => 9, 'disallowed_target_behavior' => 'Unknown']);
    $before = $draft->getRawOriginal();
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    if ($target) {
        expect(fn () => $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1))->toThrow(ValidationException::class);
    } else {
        $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
        expect($snapshot['payload']['origin_rule_id'])->toBe($draft->origin_rule_id);
    }
    expect($draft->fresh()->getRawOriginal())->toBe($before);
})->with([false, true]);

test('final current and hybrid source enforce the exact 1 MiB bound without truncation', function (string $mode, bool $atBound) {
    $draft = reconOpen($this, 'Advanced');
    $source = '//'.str_repeat('x', 1048576 - strlen($draft->source) - 3 - ($atBound ? 3 : 0))."\n".$draft->source;
    $store = app(ConfiguratorRuleWritingDrafts::class);
    $store->update($this->reconActor, $this->reconOwner, $draft->id, 1, ['source' => $source], 1);
    $draft->refresh();
    if ($mode === 'hybrid') {
        app(SaveConfiguratorRuleWritingBatch::class)->handle($this->reconActor, $this->reconOwner, [['id' => $draft->id, 'expected_revision' => $draft->revision, 'sequence' => 2]]);
        $draft->refresh();
        $store->update($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, ['source' => $draft->bindings['source'] === $source ? substr($source, strpos($source, chr(10)) + 1) : $source], 2);
        $draft->refresh();
    }
    reconRemote($this, $draft);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision)['snapshot'];
    $before = $draft->getRawOriginal();
    if ($atBound) {
        $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, ['conditions'], 3);
        expect(strlen($mode === 'hybrid' ? $result['draft']['bindings']['source'] : $result['draft']['source']))->toBe(1048576);
    } else {
        expect(fn () => $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, ['conditions'], 3))->toThrow(ValidationException::class);
        expect($draft->fresh()->getRawOriginal())->toBe($before);
    }
})->with(['current', 'hybrid'])->with([false, true]);

test('zero label and exact quoted Product keys survive section refresh and later save', function () {
    $draft = reconOpen($this, 'Advanced');
    $latest = $draft->base_rule;
    $latest['label'] = '0';
    $latest['conditions'][0]['property_key'] = 'Connection Size.מידה';
    app(SaveConfiguratorDefinition::class)->changeRuleWithIdentities($this->reconActor, $this->reconOwner, $draft->base_rule, $latest, []);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    $result = $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, ['label', 'conditions'], 1);
    expect($result['draft']['label'])->toBe('0');
    expect($result['draft']['source'])->toContain('$"Connection Size.מידה"');
});

test('late draft failure rolls back once and uncertain commit returns no fabricated acknowledgement', function (bool $commitFailure) {
    $draft = reconOpen($this, 'Advanced');
    reconRemote($this, $draft);
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    $before = $draft->getRawOriginal();
    $attempts = 0;
    if ($commitFailure) {
        Event::listen(TransactionCommitted::class, function ($event) use (&$attempts) {
            if ($event->connection->transactionLevel() === 0) {
                $attempts++;
                throw new RuntimeException('Synthetic commit-response uncertainty');
            }
        });
    } else {
        ConfiguratorRuleWritingDraft::updating(function () use (&$attempts) {
            $attempts++;
            $exception = new PDOException('Synthetic timeout');
            $exception->errorInfo = ['HY000', 1205, 'Synthetic timeout'];
            throw $exception;
        });
    }
    try {
        $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, ['label'], 1);
        $this->fail('Expected classified transaction failure');
    } catch (CatalogWriteFailure $exception) {
        expect($exception->rolledBack)->toBe(! $commitFailure);
    }
    expect($attempts)->toBe(1);
    if ($commitFailure) {
        expect($draft->fresh()->label)->toBe('remote label');
    } else {
        expect($draft->fresh()->getRawOriginal())->toBe($before);
    }
})->with([false, true]);

test('reconciliation authorizes before protected reads and rejects ambient transactions', function () {
    $draft = reconOpen($this, 'Advanced');
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    $outsider = User::factory()->make(['email' => 'outsider@example.invalid']);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        expect(fn () => $service->capture($outsider, $this->reconOwner, $draft->id, 1))->toThrow(AuthorizationException::class);
        expect(fn () => $service->updateDraft($outsider, $this->reconOwner, $draft->id, 1, $snapshot, [], 0))->toThrow(AuthorizationException::class);
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
    DB::transaction(function () use ($service, $draft, $snapshot) {
        expect(fn () => $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1))->toThrow(LogicException::class);
        expect(fn () => $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, [], 0))->toThrow(LogicException::class);
    });
});

test('retained autosaved references cannot silently rebind before the displayed snapshot', function (string $binding) {
    $draft = reconOpen($this, 'Advanced');
    if ($binding === 'Attribute') {
        $source = $draft->source."\n/hide @A";
    } else {
        $valueId = DB::table('options')->where('id', $this->reconGraph['attributes'][0]['options'][0]['option_id'])->value('value_id');
        $semantic = DB::table('values')->where('id', $valueId)->value('semantic_id');
        $source = str_replace('/when all {', '/when all {'."\n".'  @A = #'.$semantic, $draft->source);
    }
    app(ConfiguratorRuleWritingDrafts::class)->update($this->reconActor, $this->reconOwner, $draft->id, 1, ['source' => $source], 1);
    $draft->refresh();
    if ($binding === 'Attribute') {
        DB::table('attributes')->where('id', $this->reconGraph['attributes'][0]['attribute_id'])->update(['key' => 'renamed_A']);
        DB::table('attributes')->where('id', $this->reconGraph['attributes'][1]['attribute_id'])->update(['key' => 'A']);
    } else {
        $otherValueId = DB::table('options')->where('id', $this->reconGraph['attributes'][0]['options'][1]['option_id'])->value('value_id');
        DB::table('values')->where('id', $valueId)->update(['semantic_id' => 'renamed_old_w5']);
        DB::table('values')->where('id', $otherValueId)->update(['semantic_id' => $semantic]);
    }
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, $draft->revision)['snapshot'];
    $before = $draft->getRawOriginal();
    expect(fn () => $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, $draft->revision, $snapshot, [], 2))->toThrow(ValidationException::class);
    expect($draft->fresh()->getRawOriginal())->toBe($before);
})->with(['Attribute', 'Value']);

test('the signed snapshot budget accepts exactly 8 MiB for integrity checks and rejects one byte more', function (bool $over) {
    $draft = reconOpen($this, 'Advanced');
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    $snapshot['payload']['padding'] = '';
    $size = strlen(json_encode($snapshot, JSON_THROW_ON_ERROR));
    $snapshot['payload']['padding'] = str_repeat('x', 8388608 - $size + ($over ? 1 : 0));
    try {
        $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, [], 1);
        $this->fail('Expected an oversized or tampered snapshot decline');
    } catch (ValidationException $exception) {
        expect($exception->errors()['snapshot'][0])->toBe($over ? 'The displayed snapshot exceeds the 8 MiB limit.' : 'This displayed Rule snapshot is invalid or belongs to another actor, owner or draft revision.');
    }
})->with([false, true]);

test('a linked target moved to a foreign Configurator cannot be captured or refreshed', function () {
    $draft = reconOpen($this, 'Advanced');
    $service = app(ConfiguratorRuleWritingReconciliation::class);
    $snapshot = $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1)['snapshot'];
    $other = Configurator::factory()->create();
    $before = $draft->getRawOriginal();
    DB::table('configurator_rules')->where('id', $draft->origin_rule_id)->update(['configurator_id' => $other->id]);
    try {
        expect(fn () => $service->capture($this->reconActor, $this->reconOwner, $draft->id, 1))->toThrow(ModelNotFoundException::class);
        expect(fn () => $service->updateDraft($this->reconActor, $this->reconOwner, $draft->id, 1, $snapshot, [], 1))->toThrow(ModelNotFoundException::class);
        expect($draft->fresh()->getRawOriginal())->toBe($before);
    } finally {
        DB::table('configurator_rules')->where('id', $draft->origin_rule_id)->update(['configurator_id' => $this->reconOwner->id]);
        $other->delete();
    }
});
