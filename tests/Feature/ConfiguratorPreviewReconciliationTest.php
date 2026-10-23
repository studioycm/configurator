<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Livewire\Catalog\ConfiguratorOverview;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorRuleDraft;
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
