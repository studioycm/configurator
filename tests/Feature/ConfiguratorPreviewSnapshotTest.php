<?php

use App\Actions\SaveCanonicalDefinition;
use App\Actions\SaveConfiguratorDefinition;
use App\Actions\SaveConfiguratorOption;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Models\Configurator;
use App\Models\ConfiguratorOption;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorEngine;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

function previewSnapshotFixture(): array
{
    test()->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $capture = app(ConfiguratorEditorChanges::class)->capture(auth()->user(), $configurator->id, [
        ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Current snapshot description']],
    ]);

    return [$configurator, $product, $saved, $capture];
}

test('an established Preview write rollback keeps the captured draft and reports a retryable editing state', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $pending = true;
    Event::listen('eloquent.saved: '.Configurator::class, function () use (&$pending): void {
        if ($pending) {
            throw new RuntimeException('Injected write failure after SQL');
        }
    });
    try {
        $preview->call('saveChanges', [$capture])->assertReturned(fn (array $result): bool => $result['status'] === 'rolled_back')
            ->assertSet('saveReceipt', null)->assertSet('previewCaptures', [$capture])->assertNotDispatched('configurator-preview-saved');
        expect($configurator->fresh()->description)->toBeNull();
    } finally {
        $pending = false;
    }
});

test('a Preview transaction exit failure is unknown and current-state inspection does not fabricate a receipt', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $pending = true;
    Event::listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$pending, $configurator): void {
        if ($pending && $event->connection->transactionLevel() === 1 && $configurator->fresh()->description === 'Current snapshot description') {
            throw new RuntimeException('Injected transaction exit failure');
        }
    });
    try {
        $preview->call('saveChanges', [$capture])->assertReturned(fn (array $result): bool => $result['status'] === 'unknown')
            ->assertSet('saveReceipt', null)->assertNotDispatched('configurator-preview-saved');
        expect($configurator->fresh()->description)->toBe('Current snapshot description');
    } finally {
        $pending = false;
    }
    $preview->call('inspectSaveOutcome', [$capture])->assertReturned(fn (array $result): bool => $result['status'] === 'review')
        ->assertSet('saveOutcomeReview.changes', [])->assertSet('saveReceipt', null)->assertSet('testedFingerprint', null)
        ->assertNotDispatched('configurator-preview-saved');
    $preview->call('finishSaveOutcomeReview')->assertSet('saveOutcomeReview', null)->assertSet('testedFingerprint', null);
    expect($configurator->fresh()->description)->toBe('Current snapshot description');
});

test('unknown new Rule inspection lists current saved Rules without identifying or acknowledging the staged Rule', function () {
    [$configurator, $product, $saved] = previewSnapshotFixture();
    $rule = fixtureMapping('unconfirmed');
    $rule['driver_configurator_attribute_id'] = $saved['attributes'][0]['id'];
    $rule['target_configurator_attribute_id'] = $saved['attributes'][1]['id'];
    $rule['sets'][0]['source_option_ids'] = [$saved['attributes'][0]['options'][0]['id']];
    $rule['sets'][0]['target_option_ids'] = [$saved['attributes'][1]['options'][1]['id']];
    $capture = app(ConfiguratorEditorChanges::class)->capture(auth()->user(), $configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, [...$saved, 'rules' => [$rule]]);
    $persisted = $configurator->rules()->sole();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('inspectSaveOutcome', [$capture])->assertSet('saveOutcomeReview.new_rules', true)
        ->assertSet('saveOutcomeReview.changes.0.new', true)->assertSet('saveOutcomeReview.matches', false)
        ->assertSet('saveOutcomeReview.saved_rules.0.id', (string) $persisted->id)->assertSet('saveReceipt', null)
        ->assertSee(__('configurator.preview.unknown_new_rule_help'))->assertNotDispatched('configurator-preview-saved');
    $preview->call('finishSaveOutcomeReview')->assertSet('testedFingerprint', null);
    expect($configurator->rules()->count())->toBe(1);
});

test('unknown inspection reports a removed Rule without converting its draft into a new Rule', function () {
    [$configurator, $product, $saved] = previewSnapshotFixture();
    $rule = fixtureAdvanced('removed', [], 'C', 'SetHint', [], 'Old hint');
    $rule['effects'][0]['target_configurator_attribute_id'] = $saved['attributes'][2]['id'];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, [...$saved, 'rules' => [$rule]]);
    $current = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())['rules'][0];
    $capture = app(ConfiguratorEditorChanges::class)->capture(auth()->user(), $configurator->id, [['type' => 'rule', 'id' => $current['id'], 'before' => $current, 'after' => [...$current, 'label' => 'My retained draft']]]);
    $configurator->rules()->delete();
    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('inspectSaveOutcome', [$capture])
        ->assertSet('saveOutcomeReview.unavailable', [['type' => 'rule', 'id' => $current['id']]])
        ->assertSet('saveOutcomeReview.new_rules', false)->assertSet('saveReceipt', null)->assertSee(__('configurator.preview.outcome_unavailable'));
    expect($configurator->rules()->count())->toBe(0);
});

test('an exact numeric-looking operand reaches Preview saving and engine evaluation unchanged', function () {
    [$configurator, $product, $saved] = previewSnapshotFixture();
    $product->update(['profile_fields' => ['lot.code' => '001']]);
    $condition = [...fixtureCondition('literal'), 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null,
        'property_key' => 'lot.code', 'operand' => '001', 'option_ids' => []];
    $rule = fixtureAdvanced('literal-rule', [$condition], 'B', 'SetHint', [], 'Exact literal matched');
    $target = $saved['attributes'][1]['id'];
    $rule['effects'][0]['target_configurator_attribute_id'] = $target;
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, [...$saved, 'rules' => [$rule]]);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $engine = app(ConfiguratorEngine::class);
    expect($engine->evaluate($loader->forPreview(auth()->user(), $configurator->id, $product->id))->attributes[$target]['hint'])->toBe('Exact literal matched');
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectRule', $configurator->rules()->sole()->id);
    $blocks = $manager->get('editorData.condition_blocks');
    $blocks[array_key_first($blocks)]['data']['operand_text'] = '1';
    $manager->fillForm(['condition_blocks' => $blocks], 'editorForm');
    $capture = $manager->instance()->exportPreviewChanges();
    expect($capture['rows'])->toHaveCount(1);
    expect(app(ConfiguratorEditorChanges::class)->prepare(auth()->user(), $configurator, [$capture])['data']['rules'][0]['conditions'][0]['operand'])->toBe('1');
    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('saveChanges', [$capture])->assertHasNoErrors()->assertReturned(fn (array $result): bool => $result['status'] === 'committed');

    expect($loader->draft($configurator->fresh())['rules'][0]['conditions'][0]['operand'])->toBe('1');
    expect($engine->evaluate($loader->forPreview(auth()->user(), $configurator->id, $product->id))->attributes[$target]['hint'])->toBeNull();
});

test('review and inspection preserve the exact tested snapshot until an explicit rebuild', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $attribute = $saved['attributes'][0];
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $snapshot = $preview->get('previewView');
    expect($snapshot)->toBeArray()->toHaveKey('configuration_code', 'A0-B0-C0');
    $runtime = $preview->get('runtime');
    $sourceRuntimes = $preview->get('sourceRuntimes');
    $configurator->attributes()->findOrFail($attribute['id'])->options()->findOrFail($attribute['options'][0]['id'])->update(['hidden_by_default' => true]);

    $preview->call('reviewChanges', [$capture])->assertActionMounted('reviewChanges')
        ->assertSet('previewSource', 'saved')->assertSet('definitionStale', true)
        ->call('inspectState', $attribute['id'], $attribute['options'][0]['id'])
        ->assertSet('previewView', $snapshot)->assertSet('runtime', $runtime)->assertSet('sourceRuntimes', $sourceRuntimes)
        ->call('clearInspection')->assertSet('previewView', $snapshot)
        ->call('rebuildPreview', [$capture], 'saved');

    expect($preview->get('previewView')['configuration_code'])->toBe('A1-B0-C0');
});

test('an invalid current Rule remains reviewable alongside every other unsaved editor', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $rule = fixtureAdvanced('incomplete', [], 'B', 'SetHint', [], '');
    $rule['effects'][0]['target_configurator_attribute_id'] = $saved['attributes'][1]['id'];
    $invalid = app(ConfiguratorEditorChanges::class)->capture(auth()->user(), $configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('rebuildPreview', [$capture, $invalid], 'unsaved')->assertReturned(false)
        ->call('reviewChanges', [$capture, $invalid])->assertActionMounted('reviewChanges');

    expect($preview->get('previewChanges'))->toHaveCount(2);
    expect($preview->get('previewView')['status'])->toBe('unavailable');
    expect($preview->instance()->canAttemptSave())->toBeFalse();
    expect($preview->get('editorPreviewStale'))->toBeFalse();
    expect($configurator->fresh()->description)->toBeNull();
});

test('save validates immediately and retains a receipt that prevents a second write before reconciliation', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('saveChanges', [$capture])->assertHasNoErrors()->assertSet('previewSource', 'saved')
        ->assertDispatched('configurator-preview-saved');
    $receipt = $preview->get('saveReceipt');
    expect($configurator->fresh()->description)->toBe('Current snapshot description');
    expect($receipt['status'])->toBe('reconcile_pending');
    $preview->call('saveChanges', [$capture]);
    expect($preview->get('saveReceipt')['operation_id'])->toBe($receipt['operation_id']);

    $preview->call('finishPreviewSave', $receipt['operation_id'])->assertSet('saveReceipt.status', 'reconciled');
});

test('choices keep the evaluated capture identity after newer edits are collected by Review', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $changes = app(ConfiguratorEditorChanges::class);
    $newer = $changes->capture(auth()->user(), $configurator->id, [['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Newer untested description']]]);
    $attribute = $saved['attributes'][0];
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('rebuildPreview', [$capture], 'unsaved');
    $oldHash = $preview->get('previewView')['inventory_hash'];

    $preview->call('reviewChanges', [$newer])->assertSet('editorPreviewStale', true)
        ->call('selectOption', $attribute['id'], $attribute['options'][1]['id'])->assertSet('editorPreviewStale', true);
    expect($preview->get('previewView')['inventory_hash'])->toBe($oldHash)->not->toBe($preview->get('inventoryHash'));
    $preview->call('rebuildPreview', [$newer], 'unsaved')->assertSet('editorPreviewStale', false);
    expect($preview->get('previewView')['inventory_hash'])->toBe($preview->get('inventoryHash'));
});

test('an unavailable definition retains inspection inventory without invented selections and clears it without a Product', function (bool $hiddenDefault) {
    [$configurator, $product, $saved] = previewSnapshotFixture();
    $attribute = $configurator->attributes()->findOrFail($saved['attributes'][0]['id']);
    if ($hiddenDefault) {
        $attribute->options()->findOrFail($saved['attributes'][0]['options'][0]['id'])->option()->update(['is_hidden' => true]);
    } else {
        $attribute->attribute()->update(['is_active' => false]);
    }

    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $view = $preview->get('previewView');
    expect($view['status'])->toBe('unavailable')->and($view['configuration_code'])->toBeNull();
    expect($view['inspection']['attributes'])->toHaveCount(3);
    foreach ($view['inspection']['attributes'] as $attribute) {
        expect($attribute['selected_id'])->toBeNull();
        foreach ($attribute['options'] as $value) {
            expect($value['legal'])->toBeNull()->and($value['selected'])->toBeFalse();
        }
    }
    $preview->call('chooseProduct', null);
    expect($preview->get('previewView')['inspection']['attributes'])->toBe([]);
})->with(['hidden default' => true, 'inactive Attribute' => false]);

test('Review preserves the unsaved inventory after the Product is removed or reassigned', function (bool $remove) {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    if ($remove) {
        $product->delete();
    } else {
        $product->group->update(['configurator_id' => null]);
    }

    $preview->call('reviewChanges', [$capture])->assertActionMounted('reviewChanges')->assertSet('definitionStale', true);
    expect($preview->get('previewChanges'))->toHaveCount(1);
    expect($configurator->fresh()->description)->toBeNull();
})->with(['removed' => true, 'reassigned' => false]);

class PreviewWithRefreshFailure extends ConfiguratorPreview
{
    protected function evaluate(array $intent): void
    {
        if ($this->previewSource === 'saved' && $this->saveReceipt !== null) {
            throw new RuntimeException('Simulated post-commit preview failure after source transition');
        }
        parent::evaluate($intent);
    }
}

test('a failed post-commit preview refresh retains the successful receipt and does not reject or repeat the write', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $preview = Livewire::test(PreviewWithRefreshFailure::class, ['configuratorId' => $configurator->id]);

    $preview->call('saveChanges', [$capture])->assertReturned(fn (array $outcome): bool => $outcome['status'] === 'committed')
        ->assertDispatched('configurator-preview-saved');
    $receipt = $preview->get('saveReceipt');
    expect($receipt['refresh_error'])->not->toBeNull();
    expect($receipt['captures'])->toBe([$capture]);
    expect($preview->get('previewSource'))->toBe('unsaved');
    expect($preview->get('previewView')['source'])->toBe('unsaved');
    expect($preview->get('previewData'))->not->toBeNull();
    expect($preview->get('runtime'))->toBe($preview->get('sourceRuntimes')['unsaved']);
    expect($configurator->fresh()->description)->toBe('Current snapshot description');
    $preview->call('saveChanges', [$capture]);
    expect($preview->get('saveReceipt')['operation_id'])->toBe($receipt['operation_id']);
});

test('a legal saved test cannot authorize conflicting current edits to save', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $attribute = $saved['attributes'][1];
    $rule = fixtureAdvanced('block-every-value', [], 'B', 'HideOptions', array_column($attribute['options'], 'id'));
    $rule['effects'][0]['target_configurator_attribute_id'] = $attribute['id'];
    $conflict = app(ConfiguratorEditorChanges::class)->capture(auth()->user(), $configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    expect($preview->get('previewView')['is_complete'])->toBeTrue();

    $preview->call('saveChanges', [$capture, $conflict])->assertReturned(fn (array $outcome): bool => $outcome['status'] === 'rejected')
        ->assertSet('previewSource', 'unsaved')->assertSet('saveReceipt', null);
    expect($preview->get('previewChanges'))->toHaveCount(2);
    expect($configurator->fresh()->description)->toBeNull();
    expect($configurator->rules()->count())->toBe(0);
});

test('Review footer retains a complete save event handler in the rendered button attribute', function () {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('reviewChanges', [$capture]);
    $document = new DOMDocument;
    $action = $preview->instance()->getMountedAction()->getExtraModalFooterActions()['savePreviewChanges'];
    @$document->loadHTML($action->toHtml());
    $handlers = [];
    foreach ($document->getElementsByTagName('button') as $button) {
        $handler = $button->getAttribute('x-on:click');
        if (str_contains($handler, 'configurator-preview-save-request')) {
            $handlers[] = $handler;
        }
    }
    expect($handlers)->toHaveCount(1);
    expect($handlers[0])->toContain($preview->instance()->getId())->toEndWith(' }))');
});

test('a tested invalid Overview is current error state rather than a stale retryable preview', function () {
    [$configurator, $product, $saved] = previewSnapshotFixture();
    $capture = app(ConfiguratorEditorChanges::class)->capture(auth()->user(), $configurator->id, [['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'name' => '']]]);
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('rebuildPreview', [$capture], 'unsaved')->assertReturned(false);
    expect($preview->get('previewErrors'))->not->toBeEmpty();
    expect($preview->instance()->canAttemptSave())->toBeFalse();
    expect($preview->get('editorPreviewStale'))->toBeFalse();
    expect($configurator->fresh()->name)->toBe($saved['name']);
});

test('Save rejects absent Product or absent pending changes without writing', function (bool $withoutProduct) {
    [$configurator, $product, $saved, $capture] = previewSnapshotFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    if ($withoutProduct) {
        $preview->call('chooseProduct', null);
    }
    $preview->call('saveChanges', $withoutProduct ? [$capture] : [])->assertReturned(fn (array $outcome): bool => $outcome['status'] === 'rejected');
    expect($preview->get('saveReceipt'))->toBeNull();
    expect($configurator->fresh()->description)->toBeNull();
})->with(['no Product' => true, 'no pending changes' => false]);

test('V25 linked Master rename stays outside local dirty state and the immutable Preview until rebuild', function () {
    [$configurator, $product, $saved] = previewSnapshotFixture();
    $attribute = $saved['attributes'][0];
    $option = $attribute['options'][0];
    $owner = $configurator->attributes()->findOrFail($attribute['id']);
    app(SaveConfiguratorOption::class)->handle(auth()->user(), $owner, (int) $option['id'], ['label_override' => 'Local main', 'subtitle_mode' => 'master']);
    $master = Option::with('value')->findOrFail($option['option_id'])->value;
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $owner->id)->call('selectOption', $owner->id, (int) $option['id']);
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $snapshot = $preview->get('previewView');

    app(SaveCanonicalDefinition::class)->handle(auth()->user(), $master, ['label' => 'Future Master name', 'semantic_id' => $master->semantic_id, 'description' => $master->description, 'tags' => $master->tags ?? []]);

    $manager->call('exportPreviewChanges')->assertReturned(fn (array $capture): bool => $capture['rows'] === []);
    $preview->call('inspectState', $attribute['id'], $option['id'])->assertSet('previewView', $snapshot)
        ->call('rebuildPreview');
    expect($snapshot['inspection']['attributes'][$attribute['id']]['options'][$option['id']]['display_value'])->not->toBe('Future Master name');
    expect($preview->get('previewView')['inspection']['attributes'][$attribute['id']]['options'][$option['id']])->toMatchArray(['label' => 'Local main', 'display_value' => 'Future Master name']);
});

test('V25 Preview tested changes persist subtitle intent once with immutable signed captures', function () {
    [$configurator, $product, $saved] = previewSnapshotFixture();
    $option = $saved['attributes'][0]['options'][0];
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture(auth()->user(), $configurator->id, [['type' => 'value', 'id' => $option['id'], 'before' => $option, 'after' => [...$option, 'label_override' => 'Local main', 'subtitle_mode' => 'master', 'display_value_override' => null]]]);
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('rebuildPreview', [$capture], 'unsaved');
    $tested = $preview->get('previewCaptures');
    $preview->call('saveChanges', [$capture])->assertHasNoErrors();
    $receipt = $preview->get('saveReceipt');

    expect(ConfiguratorOption::findOrFail($option['id']))->subtitle_mode->toBe('master')->display_value_override->toBeNull();
    expect($tested)->toBe([$capture]);
    $preview->call('saveChanges', [$capture]);
    expect($preview->get('saveReceipt')['operation_id'])->toBe($receipt['operation_id']);
});
