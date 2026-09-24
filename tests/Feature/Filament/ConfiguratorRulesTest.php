<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Models\MappingSet;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleDraft;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($this->actor);
});

function ruleEditorFixture(): array
{
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);

    return [$configurator, app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())];
}

test('highest displayed rule receives highest priority and incomplete permutations cannot change it', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping('first'), [...fixtureMapping('second', 'B', 'C'), 'priority' => 1]];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $ids = $configurator->rules()->orderBy('id')->pluck('id')->all();
    app(SaveConfiguratorDefinition::class)->reorder($this->actor, $configurator, 'rules', $ids);
    expect($configurator->rules()->orderByDesc('priority')->pluck('id')->all())->toBe($ids);
    expect(fn () => app(SaveConfiguratorDefinition::class)->reorder($this->actor, $configurator, 'rules', [$ids[0]]))->toThrow(ValidationException::class);
    expect($configurator->rules()->orderByDesc('priority')->pluck('id')->all())->toBe($ids);
});

test('typed rule drafts round trip one level groups and reject unknown and recursive builder blocks', function () {
    $rule = fixtureAdvanced('label', [['id' => 'new:any', 'operator' => 'Any', 'conditions' => [fixtureCondition('a')]]], 'B', 'SetLabel', [], 'Visible label');
    $adapter = app(ConfiguratorRuleDraft::class);
    expect($adapter->toRule($adapter->fromRule($rule)))->toEqual($rule);
    $draft = $adapter->fromRule($rule);
    $draft['condition_blocks'][0]['type'] = 'unknown';
    expect(fn () => $adapter->toRule($draft))->toThrow(ValidationException::class);
    $draft = $adapter->fromRule($rule);
    $draft['condition_blocks'][0]['data']['conditions'][0]['conditions'] = [];
    expect(fn () => $adapter->toRule($draft))->toThrow(ValidationException::class);
});

test('matrix saves complete sets preserves identities and accepts partial coverage with overlapping targets', function () {
    [$configurator, $data] = ruleEditorFixture();
    $rule = $configurator->rules()->sole();
    $originalSet = $rule->mappingSets()->sole()->id;
    $draft = app(ConfiguratorRuleDraft::class)->fromRule($data['rules'][0]);
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $manager->callTableAction('edit', $rule)->fillForm($draft, 'editorForm')->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm');
    expect($rule->mappingSets()->sole()->id)->toBe($originalSet);
    $draft['sets'][] = ['id' => 'new:other', 'label' => 'Overlapping target', 'source_option_ids' => [$data['attributes'][0]['options'][1]['id']], 'target_option_ids' => $draft['sets'][0]['target_option_ids']];
    $manager->callTableAction('edit', $rule)->fillForm($draft, 'editorForm')->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm');
    expect($rule->mappingSets()->count())->toBe(2)->and($rule->mappingSets()->orderBy('sort_order')->first()->id)->toBe($originalSet);
});

test('invalid matrix leaves the entire saved aggregate unchanged and keeps the submitted draft', function (string $failure) {
    [$configurator, $before] = ruleEditorFixture();
    $rule = $configurator->rules()->sole();
    $draft = app(ConfiguratorRuleDraft::class)->fromRule($before['rules'][0]);
    $draft['label'] = 'Unsaved repair';
    if ($failure === 'empty') {
        $draft['sets'][0]['target_option_ids'] = [];
    } elseif ($failure === 'duplicate') {
        $draft['sets'][] = [...$draft['sets'][0], 'id' => 'new:duplicate'];
    } elseif ($failure === 'driver') {
        $draft['driver_configurator_attribute_id'] = $before['attributes'][2]['id'];
    } elseif ($failure === 'cycle') {
        $draft['target_configurator_attribute_id'] = $draft['driver_configurator_attribute_id'];
        $draft['sets'][0]['target_option_ids'] = $draft['sets'][0]['source_option_ids'];
    } else {
        $draft['sets'][0]['id'] = '999999';
    }
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $rule)->fillForm($draft, 'editorForm')->call('saveEditor')->assertHasFormErrors(form: 'editorForm');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
    expect($manager->get('editorData.label'))->toBe('Unsaved repair');
})->with(['empty', 'duplicate', 'driver', 'cycle', 'foreign set']);

test('rule editor direct methods reject foreign owners kind conversions and unauthorized callers', function () {
    [$configurator, $data] = ruleEditorFixture();
    $rule = $configurator->rules()->sole();
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $draft = app(ConfiguratorRuleDraft::class)->fromRule($data['rules'][0]);
    $draft['kind'] = 'Advanced';
    $manager->call('saveRule', $rule->id, 'Mapping', $draft)->assertHasErrors(['kind']);
    $manager->call('removeRule', 999999)->assertHasErrors(['rule']);
    $this->actingAs(User::factory()->create());
    $manager->call('moveRule', $rule->id, 1)->assertForbidden();
    expect($rule->fresh())->not->toBeNull();
});

test('a late rule persistence failure rolls back earlier row changes', function () {
    [$configurator, $before] = ruleEditorFixture();
    $after = $before;
    $after['rules'][0]['label'] = 'Must roll back';
    $after['rules'][0]['sets'][0]['label'] = 'Fail late';
    MappingSet::updating(function (): void {
        throw new RuntimeException('Synthetic late failure');
    });
    try {
        expect(fn () => app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $after))->toThrow(RuntimeException::class, 'Synthetic late failure');
    } finally {
        MappingSet::flushEventListeners();
    }
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
});

test('advanced action persists typed context and grouped option conditions with stable child IDs', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['context_schema']['territory'] = [['value' => 'EU', 'label' => 'Europe']];
    $context = ['id' => 'new:territory', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'EU', 'option_ids' => []];
    $data['rules'] = [fixtureAdvanced('presentation', [$context, ['id' => 'new:any', 'operator' => 'Any', 'conditions' => [fixtureCondition('choice')]]], 'B', 'SetHint', ['new:B1'], 'A scoped hint')];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $draft = app(ConfiguratorRuleDraft::class)->fromRule($before['rules'][0]);
    $draft['label'] = 'Edited advanced rule';
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $manager->callTableAction('edit', $configurator->rules()->sole())->fillForm($draft, 'editorForm')->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm');
    $after = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    expect($after['rules'][0]['conditions'])->toBe($before['rules'][0]['conditions'])
        ->and($after['rules'][0]['effects'])->toBe($before['rules'][0]['effects'])->and($after['rules'][0]['label'])->toBe('Edited advanced rule');
});

test('a forged unknown Builder block cannot be discarded by schema dehydration into a broader rule', function () {
    [$configurator, $before] = ruleEditorFixture();
    $draft = app(ConfiguratorRuleDraft::class)->fromRule($before['rules'][0]);
    $draft['condition_blocks'] = [['type' => 'unknown', 'data' => ['anything' => true]]];
    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('saveRule', $configurator->rules()->sole()->id, 'Mapping', $draft)->assertHasErrors(['condition_blocks.0']);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
});

test('reordering clears search and exposes the complete owner list', function () {
    [$configurator, $data] = ruleEditorFixture();
    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->set('tableSearch', 'not a matching rule')->call('toggleTableReordering')
        ->assertSet('tableSearch', '')->assertCanSeeTableRecords($configurator->rules);
});

test('context operator changes keep the previous operand visible until explicitly cleared', function (string $operator, mixed $operand, string $nextOperator, string $staleField, mixed $cleared, string $nextField, mixed $nextValue, string $staleKey) {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['context_schema']['territory'] = [['value' => 'EU', 'label' => 'Europe']];
    $context = ['id' => 'new:territory', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => $operator, 'operand' => $operand, 'option_ids' => []];
    $data['rules'] = [fixtureAdvanced('context-hint', [$context], 'B', 'SetHint', ['new:B1'], 'A scoped hint')];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $configurator->rules()->sole());
    $blockKey = array_key_first($manager->get('editorData.condition_blocks'));
    $path = 'editorData.condition_blocks.'.$blockKey.'.data.';

    $manager->set($path.'operator', $nextOperator)->set($path.$nextField, $nextValue)
        ->call('saveEditor')->assertHasFormErrors(form: 'editorForm');
    expect($manager->errors()->get(rtrim($path, '.')))->toBe(['Clear the previous operand explicitly before changing its source or scalar/list operator.']);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
    $schema = 'editorForm';
    $componentKey = collect($manager->instance()->{$schema}->getFlatComponents(withHidden: true))
        ->keys()->first(fn ($key): bool => is_string($key) && str_ends_with($key, '.'.$staleKey));
    expect($componentKey)->not->toBeNull();
    $manager->assertSchemaComponentVisible($componentKey, 'editorForm')
        ->set($path.$staleField, $cleared)->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm');

    $after = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    expect($after['rules'][0]['conditions'][0])->toMatchArray(['operator' => $nextOperator, 'operand' => $nextValue]);
})->with([
    'single to list' => ['Equals', 'EU', 'In', 'operand_text', null, 'operand_list', ['EU'], 'context-value'],
    'list to single' => ['In', ['EU'], 'Equals', 'operand_list', [], 'operand_text', 'EU', 'context-values'],
]);
