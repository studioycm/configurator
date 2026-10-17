<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorRuleDraft;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function previewWorkspaceFixture(): array
{
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $attribute = $saved['attributes'][0];
    $capture = app(ConfiguratorEditorChanges::class)->capture($configurator->id, [
        ['type' => 'attribute', 'id' => $attribute['id'], 'before' => $attribute, 'after' => [...$attribute, 'label_override' => 'Tested temporary label']],
        ['type' => 'overview', 'id' => 'overview', 'before' => $saved, 'after' => [...$saved, 'description' => 'Tested description']],
    ]);

    return [$configurator, $product, $saved, $capture];
}

test('Preview can rebuild from unsaved edits and saved data without writing either mode', function () {
    [$configurator, $product, $saved, $capture] = previewWorkspaceFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('rebuildPreview', [$capture], 'unsaved')->assertReturned(true)
        ->assertSet('previewSource', 'unsaved')->assertSee('Tested temporary label')->assertSee('A0-B0-C0')
        ->call('rebuildPreview', [$capture], 'saved')->assertReturned(true)->assertSet('previewSource', 'saved')
        ->assertDontSeeText('Tested temporary label')->assertSee('A0-B0-C0');

    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
});

test('preview modes retain independent test selections', function () {
    [$configurator, $product, $saved, $capture] = previewWorkspaceFixture();
    $a = $saved['attributes'][0];
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('selectOption', $a['id'], $a['options'][1]['id']);

    $preview->call('rebuildPreview', [$capture], 'unsaved')->assertSee('A1-B0-C0')
        ->call('selectOption', $a['id'], $a['options'][0]['id'])->assertSee('A0-B0-C0')
        ->call('rebuildPreview', [$capture], 'saved')->assertSee('A1-B0-C0')
        ->call('rebuildPreview', [$capture], 'unsaved')->assertSee('A0-B0-C0');
});

test('invalid unsaved edits show errors without silently displaying saved configuration', function () {
    [$configurator, $product, $saved] = previewWorkspaceFixture();
    $rule = app(ConfiguratorRuleDraft::class)->fromRule(fixtureMapping('invalid'));
    $capture = app(ConfiguratorEditorChanges::class)->capture($configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('rebuildPreview', [$capture], 'unsaved')->assertReturned(false)->assertDontSee('A0-B0-C0')
        ->assertSet('previewSource', 'unsaved')->assertSet('previewData', null)
        ->call('reviewChanges', [$capture])->assertNotified(__('configurator.preview.test_errors'));

    expect($configurator->rules()->count())->toBe(0);
});

test('review lists every pending change and cancellation saves nothing', function () {
    [$configurator, $product, $saved, $capture] = previewWorkspaceFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('reviewChanges', [$capture])->assertActionMounted('saveTestedChanges')
        ->assertMountedActionModalSee(['Tested temporary label', 'Tested description', $product->product_code, 'A0-B0-C0', __('configurator.preview.unsaved_source')]);
    $preview->call('unmountAction');

    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($saved);
});

test('Preview validation identifies the Rule and nested field that needs correction', function () {
    [$configurator, $product, $saved] = previewWorkspaceFixture();
    $rule = fixtureAdvanced('seat-guidance', [], 'B', 'SetHint', [], '');
    $rule['label'] = 'Seat guidance';
    $rule['effects'][0]['target_configurator_attribute_id'] = $saved['attributes'][1]['id'];
    $capture = app(ConfiguratorEditorChanges::class)->capture($configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('rebuildPreview', [$capture], 'unsaved')->assertReturned(false)
        ->assertSee('Seat guidance')->assertSee('Effects 1')->assertSee('Text');
});

test('Product switching explains that both preview modes start with fresh choices', function () {
    [$configurator, $product] = previewWorkspaceFixture();
    $other = Product::factory()->for($product->group)->create();

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('chooseProduct', $other->id)
        ->assertSee(__('configurator.preview.product_reset', ['code' => $other->product_code]))
        ->call('chooseProduct', null)->assertSet('productResetNotice', null);
});

test('invalid Mapping editor data includes the Rule and set before definition compilation', function () {
    [$configurator] = previewWorkspaceFixture();
    $rule = app(ConfiguratorRuleDraft::class)->fromRule(fixtureMapping('territory-mapping'));
    $rule['label'] = 'Territory mapping';
    $rule['sets'][0]['disallowed_target_behavior'] = 'Invalid';
    $capture = app(ConfiguratorEditorChanges::class)->capture($configurator->id, [['type' => 'rule', 'id' => $rule['id'], 'before' => [], 'after' => $rule]]);

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('rebuildPreview', [$capture], 'unsaved')->assertReturned(false)
        ->assertSee('Territory mapping')->assertSee('Allowed-value sets 1');
});

test('confirmed tested edits save together and become the saved preview', function () {
    [$configurator, $product, $saved, $capture] = previewWorkspaceFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('reviewChanges', [$capture])->assertActionMounted('saveTestedChanges')->callMountedAction()
        ->assertHasNoErrors()->assertSet('previewSource', 'saved')->assertSet('previewChanges', [])
        ->assertSee('Tested temporary label')->assertDispatched('configurator-preview-saved');

    expect($configurator->fresh()->description)->toBe('Tested description');
    expect($configurator->attributes()->findOrFail($saved['attributes'][0]['id'])->label_override)->toBe('Tested temporary label');
});

test('a saved change after confirmation rejects the entire preview save', function () {
    [$configurator, $product, $saved, $capture] = previewWorkspaceFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('reviewChanges', [$capture]);
    $configurator->update(['description' => 'Saved elsewhere']);

    $preview->callMountedAction()->assertHasErrors()->assertSet('previewSource', 'unsaved');

    expect($configurator->fresh()->description)->toBe('Saved elsewhere');
    expect($configurator->attributes()->findOrFail($saved['attributes'][0]['id'])->label_override)->toBeNull();
});

test('Preview blocks incomplete configurations while allowing expected replacement notices', function () {
    [$configurator, $product, $saved] = previewWorkspaceFixture();
    $b = $saved['attributes'][1];
    $a = $saved['attributes'][0];
    $changes = app(ConfiguratorEditorChanges::class);
    $block = fixtureAdvanced('block-all', [], 'B', 'DisableOptions', array_column($b['options'], 'id'));
    $block['effects'][0]['target_configurator_attribute_id'] = $b['id'];
    $capture = $changes->capture($configurator->id, [['type' => 'rule', 'id' => $block['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($block)]]);
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);

    $preview->call('reviewChanges', [$capture])->assertActionNotMounted('saveTestedChanges')->assertNotified(__('configurator.preview.test_errors'));
    expect($configurator->rules()->count())->toBe(0);

    $block['effects'][0]['option_ids'] = [$b['options'][0]['id']];
    $capture = $changes->capture($configurator->id, [['type' => 'rule', 'id' => $block['id'], 'before' => [], 'after' => app(ConfiguratorRuleDraft::class)->fromRule($block)]]);
    $preview->call('reviewChanges', [$capture])->assertActionMounted('saveTestedChanges')->callMountedAction()->assertHasNoErrors();
    expect($configurator->rules()->count())->toBe(1);
});

test('a newly tested Rule becomes a saved editor without remaining as a duplicate temporary Rule', function () {
    [$configurator, $product, $saved] = previewWorkspaceFixture();
    $c = $saved['attributes'][2];
    $rule = fixtureAdvanced('new-rule-save', [], 'C', 'SetHint', [], 'Tested hint');
    $rule['effects'][0]['target_configurator_attribute_id'] = $c['id'];
    $state = app(ConfiguratorRuleDraft::class)->fromRule($rule);
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Advanced')->fillForm($state, 'editorForm');
    $capture = null;
    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    $preview->call('reviewChanges', [$capture])->callMountedAction()->assertHasNoErrors();
    $newRule = $configurator->rules()->sole();
    $newRules = [$rule['id'] => app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())['rules'][0]];

    $manager->dispatch('configurator-preview-saved', configuratorId: $configurator->id, captures: [$capture], newRules: $newRules)
        ->assertSet('selectedRuleId', $newRule->id)->call('exportPreviewChanges')->assertReturned(fn (array $capture): bool => $capture['rows'] === []);
});

test('Preview capture rebuild review and confirmation reauthorize against the current user', function () {
    [$configurator, $product, $saved, $capture] = previewWorkspaceFixture();
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('reviewChanges', [$capture]);
    $this->actingAs(User::factory()->create());

    $preview->callMountedAction()->assertForbidden();
    expect($configurator->fresh()->description)->toBeNull();
    expect($configurator->attributes()->findOrFail($saved['attributes'][0]['id'])->label_override)->toBeNull();
});
