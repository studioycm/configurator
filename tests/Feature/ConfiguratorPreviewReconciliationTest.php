<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Livewire\Catalog\ConfiguratorOverview;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Models\ConfiguratorOption;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorRuleDraft;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

test('editor acknowledgment is scoped and repeated successful receipts preserve later text', function (string $editor) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $field = $editor === 'overview' ? 'description' : ($editor === 'attributes' ? 'label_override' : 'label');
    $schema = $editor === 'overview' ? 'form' : 'editorForm';
    if ($editor === 'overview') {
        $manager = Livewire::test(ConfiguratorOverview::class, ['configuratorId' => $configurator->id]);
    } elseif ($editor === 'attributes') {
        $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
            ->call('selectAttribute', (int) $saved['attributes'][0]['id']);
    } else {
        $rule = fixtureAdvanced('pending-rule', [], 'C', 'SetHint', [], 'Saved hint');
        $rule['effects'][0]['target_configurator_attribute_id'] = $saved['attributes'][2]['id'];
        $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
            ->call('createRule', 'Advanced')->fillForm(app(ConfiguratorRuleDraft::class)->fromRule($rule), 'editorForm');
    }
    $manager->fillForm([$field => 'Tested text'], $schema);
    $capture = null;
    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator->fresh(), [$capture]);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $prepared['data']);
    $newRules = $editor === 'rules' ? [$capture['rows'][0]['id'] => ['id' => (string) $configurator->rules()->sole()->id]] : [];
    $manager->fillForm([$field => 'Later text'], $schema);
    $operationId = 'saved-operation';
    $componentId = $manager->instance()->getId();

    $manager->call('acceptPreviewSave', $configurator->id, [$capture], $newRules, $operationId)
        ->assertDispatched('configurator-preview-editor-reconciled', fn (string $event, array $detail): bool => $detail === ['configuratorId' => $configurator->id, 'operationId' => $operationId, 'componentId' => $componentId])
        ->assertSet('lastAppliedPreviewSave', $operationId)
        ->assertSchemaStateSet([$field => 'Later text'], $schema);
    $manager->fillForm([$field => 'Even later text'], $schema);
    $configurator->update(['name' => 'Changed after acknowledgment']);
    $state = $manager->get($editor === 'overview' ? 'previewOriginal' : 'previewOriginals');

    $manager->call('acceptPreviewSave', $configurator->id, [$capture], $newRules, $operationId)
        ->assertDispatched('configurator-preview-editor-reconciled', fn (string $event, array $detail): bool => $detail['operationId'] === $operationId && $detail['componentId'] === $componentId)
        ->assertSchemaStateSet([$field => 'Even later text'], $schema);

    expect($manager->get($editor === 'overview' ? 'previewOriginal' : 'previewOriginals'))->toBe($state);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())[$editor === 'overview' ? 'description' : 'name'])->toBe($editor === 'overview' ? 'Tested text' : 'Changed after acknowledgment');
    if ($editor === 'rules') {
        expect($configurator->rules()->count())->toBe(1);
        expect($manager->get('selectedRuleId'))->toBe($configurator->rules()->sole()->id);
    }
})->with(['overview', 'attributes', 'rules']);

test('editors do not acknowledge another Configurator or invalid signed captures', function (string $editor) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $class = match ($editor) {
        'overview' => ConfiguratorOverview::class,
        'attributes' => AttributesRelationManager::class,
        'rules' => RulesRelationManager::class,
    };
    $manager = Livewire::test($class, $editor === 'overview' ? ['configuratorId' => $configurator->id] : ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);

    $manager->call('acceptPreviewSave', $configurator->id + 1, [], [], 'wrong-configurator')
        ->assertNotDispatched('configurator-preview-editor-reconciled');
    $manager->call('acceptPreviewSave', $configurator->id, [['configurator_id' => $configurator->id, 'rows' => [], 'signature' => 'invalid']], [], 'invalid-capture')
        ->assertHasErrors(['preview'])->assertNotDispatched('configurator-preview-editor-reconciled');
    expect($manager->get('lastAppliedPreviewSave'))->toBeNull();
})->with(['overview', 'attributes', 'rules']);

test('V25 subtitle reconciliation preserves later custom edits through repeat-safe receipts', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator);
    $attribute = $saved['attributes'][0];
    $value = $attribute['options'][0];
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', (int) $attribute['id'])->call('selectOption', (int) $attribute['id'], (int) $value['id'])
        ->callAction(TestAction::make('linkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'));
    $capture = null;
    $manager->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;

        return true;
    });
    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator, [$capture]);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $prepared['data']);
    $manager->callAction(TestAction::make('unlinkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->fillForm(['_subtitle_text' => 'Later custom'], 'optionEditorForm')
        ->call('acceptPreviewSave', $configurator->id, [$capture], [], 'subtitle-operation')
        ->call('acceptPreviewSave', $configurator->id, [$capture], [], 'subtitle-operation')
        ->assertSet('optionEditorData.subtitle_mode', 'custom')->assertSet('optionEditorData.display_value_override', 'Later custom');
    expect(ConfiguratorOption::findOrFail($value['id'])->subtitle_mode)->toBe('master');
});

test('V20 tested settings captures preserve a newer table default and repeat-safe receipts preserve later drafts', function (bool $stash) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $attribute = $attributes[0];
    $options = $attribute->options()->orderBy('display_order')->get();
    $editor = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->fillForm(['help_text' => 'Tested help'], 'editorForm')
        ->call('selectOption', $attribute->id, $options[0]->id);
    $capture = null;
    $editor->call('exportPreviewChanges')->assertReturned(function (array $result) use (&$capture): bool {
        $capture = $result;
        foreach ($capture['rows'] as $row) {
            if ($row['type'] === 'attribute') {
                expect($row['before'])->not->toHaveKey('default_configurator_option_id');
                expect($row['after'])->not->toHaveKey('default_configurator_option_id');
            }
        }

        return true;
    });
    $preview = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])->call('rebuildPreview', [$capture], 'unsaved')->assertReturned(true);
    $view = $preview->get('previewView');
    $evaluatedCaptures = $preview->get('previewCaptures');
    $hash = app(ConfiguratorEditorChanges::class)->captureHash($configurator->id, [$capture]);

    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->callAction(TestAction::make('setDefault')->table($options[1]))->assertHasNoErrors();
    $preview->dispatch('configurator-updated')->assertSet('definitionStale', true)->assertSet('testedFingerprint', null)
        ->assertSet('previewView', $view)->assertSet('previewCaptures', $evaluatedCaptures);
    expect(app(ConfiguratorEditorChanges::class)->captureHash($configurator->id, [$capture]))->toBe($hash);
    $editor->fillForm(['help_text' => 'Later Attribute help'], 'editorForm')->fillForm(['label_override' => 'Later Value name'], 'optionEditorForm');
    if ($stash) {
        $editor->call('selectAttribute', $attributes[1]->id)->fillForm(['help_text' => 'Unrelated later help'], 'editorForm');
    }
    $preview->call('saveChanges', [$capture])->assertReturned(fn (array $result): bool => $result['status'] === 'committed');
    $receipt = $preview->get('saveReceipt');
    expect($receipt['captures'])->toBe([$capture]);
    expect($attribute->fresh())->help_text->toBe('Tested help')->default_configurator_option_id->toBe($options[1]->id);
    $beforeReconcile = $editor->get('optionEditorDrafts');
    $editor->call('acceptPreviewSave', $configurator->id, $receipt['captures'], $receipt['newRules'], $receipt['operation_id'])
        ->call('acceptPreviewSave', $configurator->id, $receipt['captures'], $receipt['newRules'], $receipt['operation_id']);
    expect($editor->get('previewOriginals')['attribute:'.$attribute->id])->not->toHaveKey('default_configurator_option_id');
    expect($editor->get('optionEditorDrafts'))->toBe($beforeReconcile);
    $editor->call('selectAttribute', $attribute->id)->assertSet('editorData.help_text', 'Later Attribute help')
        ->assertSet('optionEditorData.label_override', 'Later Value name')->call('saveEditor')->assertHasNoErrors();
    expect($attribute->fresh())->help_text->toBe('Later Attribute help')->default_configurator_option_id->toBe($options[1]->id);
    $preview->call('saveChanges', [$capture]);
    expect($preview->get('saveReceipt'))->toBe($receipt);
})->with(['active' => false, 'stashed' => true]);

test('V20 legacy default-changing captures retain stale conflicts after a newer table choice', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    $third = Option::factory()->create(['attribute_id' => $data['attributes'][0]['attribute_id'], 'code' => 'A2']);
    $data['attributes'][0]['options'][] = [...$data['attributes'][0]['options'][0], 'id' => 'new:A2', 'option_id' => $third->id, 'display_order' => 2];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $row = $saved['attributes'][0];
    $attribute = $configurator->attributes()->findOrFail($row['id']);
    $options = $attribute->options()->orderBy('display_order')->get();
    $changes = app(ConfiguratorEditorChanges::class);
    $capture = $changes->capture($configurator->id, [['type' => 'attribute', 'id' => $row['id'], 'before' => $row, 'after' => [...$row, 'default_configurator_option_id' => (string) $options[1]->id]]]);
    expect($changes->prepare($configurator->fresh(), [$capture])['data']['attributes'][0]['default_configurator_option_id'])->toBe((string) $options[1]->id);

    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->callAction(TestAction::make('setDefault')->table($options[2]))->assertHasNoErrors();

    $inspection = $changes->inspect($configurator->fresh(), [$capture]);
    expect($inspection['errors'])->not->toBe([]);
    expect(fn () => $changes->prepare($configurator->fresh(), [$capture]))->toThrow(ValidationException::class);
    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('saveChanges', [$capture])->assertReturned(fn (array $result): bool => $result['status'] === 'rejected')->assertNotDispatched('configurator-preview-saved');
    expect($attribute->fresh()->default_configurator_option_id)->toBe($options[2]->id);
    expect($changes->savedRows($configurator->id, [$capture])[0])->toBe($capture['rows'][0]);
});

test('V20 legacy full receipts cannot restore a workspace default field or discard later settings', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $editor = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])->call('selectAttribute', $attribute->id);
    $row = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())['attributes'][0];
    $capture = app(ConfiguratorEditorChanges::class)->capture($configurator->id, [['type' => 'attribute', 'id' => $row['id'], 'before' => $row, 'after' => [...$row, 'help_text' => 'Saved legacy help']]]);
    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator->fresh(), [$capture]);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $prepared['data']);

    $editor->fillForm(['help_text' => 'Later help'], 'editorForm')->call('acceptPreviewSave', $configurator->id, [$capture], [], 'legacy-operation');

    expect($editor->get('previewOriginals')['attribute:'.$attribute->id])->not->toHaveKey('default_configurator_option_id');
    $editor->assertSet('editorData.help_text', 'Later help')->call('exportPreviewChanges')->assertReturned(function (array $capture): bool {
        expect($capture['rows'][0]['before'])->not->toHaveKey('default_configurator_option_id');
        expect($capture['rows'][0]['after'])->not->toHaveKey('default_configurator_option_id');

        return true;
    });
});
