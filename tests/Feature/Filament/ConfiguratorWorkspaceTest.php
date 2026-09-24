<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Forms\Components\MappingSetsField;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Models\Configurator;
use App\Models\Option;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('configurator list links to the full edit page without embedding an editor', function () {
    $record = Configurator::factory()->create();
    $list = Livewire::test(ListConfigurators::class)->assertCanSeeTableRecords([$record]);

    expect($list->instance()->getTable()->getRecordUrl($record))->toBe(ConfiguratorResource::getUrl('edit', ['record' => $record]));
    $this->get(ConfiguratorResource::getUrl('index', ['record' => $record->id]))->assertDontSeeLivewire(EditConfigurator::class);
});

test('attribute side editor saves its fields without overwriting options edited underneath it', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $option = $attribute->options()->first();
    $editor = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $attribute)->assertSet('selectedAttributeId', $attribute->id)
        ->fillForm(['label_override' => 'Local attribute label'], 'editorForm');
    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $option, data: ['label_override' => 'Local option label'])->assertHasNoTableActionErrors();

    $editor->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm');

    expect($attribute->fresh()->label_override)->toBe('Local attribute label');
    expect($option->fresh()->label_override)->toBe('Local option label');
    expect($attribute->attribute->label)->toBe('A');
});

test('related options can be included reordered and removed while preserving the stored default', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $default = $attribute->default_configurator_option_id;
    $canonical = Option::factory()->create(['attribute_id' => $attribute->attribute_id, 'code' => 'AX']);
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class]);

    $manager->callTableAction('include', data: ['option_id' => $canonical->id])->assertHasNoTableActionErrors();
    $included = $attribute->options()->where('option_id', $canonical->id)->sole();
    $ids = $attribute->options()->orderBy('display_order')->pluck('id')->all();
    $manager->call('reorderTable', array_reverse($ids))->assertHasNoErrors();
    expect($attribute->options()->orderBy('display_order')->pluck('id')->all())->toBe(array_reverse($ids));
    expect($attribute->fresh()->default_configurator_option_id)->toBe($default);
    $manager->callTableAction('remove', $included)->assertHasNoTableActionErrors();
    expect($included->fresh())->toBeNull();
    $manager->callTableAction('remove', $attribute->options()->findOrFail($default))->assertHasTableActionErrors();
    expect($attribute->options()->whereKey($default)->exists())->toBeTrue();
});

test('rule side editor creates a staged rule and cancel leaves the saved definition intact', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $source = $saved['attributes'][0];
    $target = $saved['attributes'][1];
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('addMapping')->assertSet('editorKind', 'Mapping');
    expect($configurator->rules()->count())->toBe(0);
    $manager->fillForm([
        'label' => 'New mapping', 'driver_configurator_attribute_id' => $source['id'], 'target_configurator_attribute_id' => $target['id'],
        'sets' => [['id' => 'new:set', 'label' => 'Allowed', 'source_option_ids' => [$source['options'][0]['id']], 'target_option_ids' => [$target['options'][0]['id']]]],
    ], 'editorForm')->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm');
    $rule = $configurator->rules()->sole();
    $manager->callTableAction('edit', $rule)->fillForm(['label' => 'Unsaved name'], 'editorForm')->call('closeEditor')->assertSet('editorKind', null);
    expect($rule->fresh()->label)->toBe('New mapping');
});

test('side editors reject foreign selections and reauthorize subsequent requests', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $other = Configurator::factory()->create();
    $attribute = $configurator->attributes()->first();
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $other, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->assertNotFound();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])->call('selectAttribute', $attribute->id);
    $this->actingAs(User::factory()->create());
    $manager->call('saveEditor')->assertForbidden();
});

test('option editor rejects foreign rows and shared identity changes and reauthorizes writes', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $option = $attributes[0]->options()->first();
    $foreign = $attributes[1]->options()->first();
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attributes[0], 'pageClass' => EditConfigurator::class]);

    $manager->call('saveOption', $foreign->id, ['label_override' => 'Wrong owner'])->assertHasErrors(['option']);
    $manager->call('removeOption', $foreign->id)->assertHasErrors(['option']);
    $manager->call('saveOption', $option->id, ['option_id' => $foreign->option_id])->assertHasErrors();
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
    $this->actingAs(User::factory()->create());
    $manager->call('saveOption', $option->id, ['label_override' => 'Unauthorized'])->assertForbidden();
});

test('rule side editor does not drop invalid hidden builder blocks before saving', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $rule = $configurator->rules()->sole();

    Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $rule)->set('editorData.condition_blocks', [['type' => 'unknown', 'data' => []]])
        ->call('saveEditor')->assertHasFormErrors(form: 'editorForm');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
});

test('switching attributes replaces the canonical attribute and stored default choices', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $second = $attributes[1];
    $choices = $second->options()->orderBy('display_order')->pluck('id')->all();

    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attributes[0]->id);
    $manager->instance()->editorForm->getComponents();
    $manager->instance()->selectAttribute($second->id);
    $manager
        ->assertFormFieldExists('attribute_id', 'editorForm', fn (Select $field): bool => array_key_exists($second->attribute_id, $field->getOptions()))
        ->assertFormFieldExists('default_configurator_option_id', 'editorForm', fn (Select $field): bool => array_keys($field->getOptions()) === $choices);
});

test('switching rule kinds replaces mapping fields with advanced effects and back again', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);

    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Mapping');
    $manager->instance()->editorForm->getComponents();
    $manager->instance()->createRule('Advanced');
    $manager->assertFormFieldExists('effects', 'editorForm', fn (Field $field): bool => $field instanceof Repeater);
    $manager->instance()->createRule('Mapping');
    $manager->assertFormFieldExists('sets', 'editorForm', fn (Field $field): bool => $field instanceof MappingSetsField);
});

test('saving an attribute refreshes the related default marker without discarding an option draft', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $options = $attribute->options()->orderBy('display_order')->get();
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->mountTableAction('edit', $options[0])->fillForm(['label_override' => 'Unsaved option']);

    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $attribute)->fillForm(['default_configurator_option_id' => $options[1]->id], 'editorForm')
        ->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm')
        ->assertDispatched('configurator-attribute-updated', attributeId: $attribute->id);
    $manager->dispatch('configurator-attribute-updated', attributeId: $attribute->id)
        ->assertTableColumnStateSet('stored_default', false, $options[0])
        ->assertTableColumnStateSet('stored_default', true, $options[1])
        ->assertActionMounted(TestAction::make('edit')->table($options[0]))->assertSchemaStateSet(['label_override' => 'Unsaved option']);
});

test('related option updates refresh the rule choices while preserving its draft', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $rule = $configurator->rules()->sole();
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $option = $attribute->options()->orderBy('display_order')->first();
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $rule)->fillForm(['label' => 'Unsaved rule'], 'editorForm');
    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->callTableAction('edit', $option, data: ['label_override' => 'Updated option'])->assertHasNoTableActionErrors();

    $manager->dispatch('configurator-updated')->assertSchemaStateSet(['label' => 'Unsaved rule'], 'editorForm')
        ->assertFormFieldExists('sets', 'editorForm', fn (MappingSetsField $field): bool => $field->getSourceChoices()[$option->id] === 'A0 · Updated option');
    expect($rule->fresh()->label)->toBe('map');
});
