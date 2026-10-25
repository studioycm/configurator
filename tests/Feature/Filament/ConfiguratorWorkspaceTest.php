<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Forms\Components\MappingSetsField;
use App\Filament\Resources\Configurators\ConfiguratorResource;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\Pages\ListConfigurators;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Configurators\Schemas\ConfiguratorAttributeForm;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorInclusionDrafts;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('workspace ordering and selection toggles preserve search and editor drafts', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->fillForm(['label_override' => 'Keep draft'], 'editorForm')
        ->set('tableSearch', 'A');

    expect($manager->instance()->getTable()->isSelectionEnabled())->toBeFalse()
        ->and(array_map(fn ($action) => $action->getName(), array_slice($manager->instance()->getTable()->getRecordActions(), 0, 3)))->toBe(['moveUp', 'moveDown', 'edit']);
    $manager->assertSet('showOrderControls', false)
        ->assertActionHidden(TestAction::make('moveDown')->table($attribute))
        ->callAction(TestAction::make('reorderRows')->table())->assertSet('showOrderControls', true)->assertSet('isTableReordering', false)
        ->assertActionVisible(TestAction::make('moveDown')->table($attribute))
        ->assertSet('tableSearch', 'A')->assertSet('editorData.label_override', 'Keep draft')
        ->callAction(TestAction::make('toggleSelection')->table())->assertSet('showSelection', true)
        ->set('selectedTableRecords', [(string) $attribute->id])->callAction(TestAction::make('toggleSelection')->table())
        ->assertSet('selectedTableRecords', [])->assertSet('editorData.label_override', 'Keep draft');

    foreach ([OptionsRelationManager::class => $attribute, RulesRelationManager::class => $configurator] as $class => $owner) {
        $table = Livewire::test($class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])->instance()->getTable();
        expect($table->isSelectionEnabled())->toBeFalse()
            ->and(array_map(fn ($action) => $action->getName(), array_slice($table->getRecordActions(), 0, 3)))->toBe(['moveUp', 'moveDown', 'edit']);
    }
});

test('Option moves use the full owner order preserve defaults and reject foreign rows and alternate sorting', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $options = $attribute->options()->orderBy('display_order')->get();
    $default = $attribute->default_configurator_option_id;
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->set('tableSearch', $options[1]->option->code)->call('moveOption', $options[1]->id, -1)->assertHasNoErrors();
    expect($attribute->options()->orderBy('display_order')->pluck('id')->all())->toBe([$options[1]->id, $options[0]->id])
        ->and($attribute->fresh()->default_configurator_option_id)->toBe($default);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $foreign = $configurator->attributes()->orderBy('display_order')->get()[1]->options()->first();
    $manager->call('moveOption', $foreign->id, -1)->assertHasErrors()
        ->set('tableSort', 'option.code:asc')->call('moveOption', $options[1]->id, 1)->assertHasErrors(['order']);
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
});

test('quick workspace filters and chip removal keep unrelated deferred constraints', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $pending = ['rules' => [['type' => 'label', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'pending']]]]];
    $manager->set('tableDeferredFilters.constraints', $pending)
        ->set('tableFilters.kind.value', 'Mapping')
        ->assertSet('tableDeferredFilters.constraints', $pending)->assertSet('tableDeferredFilters.kind.value', 'Mapping');
    expect($manager->get('tableFilters.constraints'))->not->toBe($pending);
    $manager->call('removeTableFilter', 'kind')->assertSet('tableDeferredFilters.constraints', $pending);
    expect($manager->get('tableFilters.constraints'))->not->toBe($pending);
});

test('inline Option drafts survive parent switches and separate saves merge only their owned fields', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $option = $attributes[0]->options()->orderBy('display_order')->first();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attributes[0]->id)->fillForm(['help_text' => 'Pending parent help'], 'editorForm')
        ->call('selectOption', $attributes[0]->id, $option->id)->fillForm(['label_override' => 'Pending Option label'], 'optionEditorForm')
        ->call('selectAttribute', $attributes[1]->id)->call('selectAttribute', $attributes[0]->id)
        ->assertSet('selectedOptionId', $option->id)->assertSet('optionEditorData.label_override', 'Pending Option label')
        ->assertSet('editorData.help_text', 'Pending parent help')->call('saveOptionEditor')->assertHasNoFormErrors(form: 'optionEditorForm');
    expect($option->fresh()->label_override)->toBe('Pending Option label')->and($attributes[0]->fresh()->help_text)->toBeNull();
    $manager->fillForm(['hint' => 'Unsaved Option hint'], 'optionEditorForm')->call('saveEditor')
        ->assertSet('optionEditorData.hint', 'Unsaved Option hint')->assertSet('selectedOptionId', $option->id);
    expect($attributes[0]->fresh()->help_text)->toBe('Pending parent help')->and($option->fresh()->hint)->toBeNull();
    $manager->call('closeEditor')->assertSet('optionEditorDrafts', [])->assertSet('selectedOptionId', null);
});

test('Option batch reconciliation keeps dirty drafts but discarding them allows a fresh save', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $options = $attribute->options()->orderBy('display_order')->get();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->call('selectOption', $attribute->id, $options[0]->id)
        ->call('selectOption', $attribute->id, $options[1]->id);
    $options[0]->update(['hint' => 'Saved batch hint']);
    $manager->dispatch('catalog-batch-applied', model: $options[0]::class, ids: [$options[0]->id])
        ->call('selectOption', $attribute->id, $options[0]->id)->assertSet('optionEditorData.hint', 'Saved batch hint')
        ->call('selectOption', $attribute->id, $options[1]->id)->assertSet('optionEditorDrafts', [])
        ->fillForm(['hint' => 'Pending hint'], 'optionEditorForm');
    $options[1]->update(['hint' => 'New batch hint']);
    $manager->dispatch('catalog-batch-applied', model: $options[1]::class, ids: [$options[1]->id])
        ->call('saveOptionEditor')->assertHasErrors(['optionEditorData'])
        ->call('closeOptionEditor')->call('selectOption', $attribute->id, $options[1]->id)
        ->assertSet('optionEditorData.hint', 'New batch hint')->call('saveOptionEditor')->assertHasNoErrors();
});

test('late batch inclusion errors point at the reviewed Option and preserve all staging rows', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $option = Option::factory()->create();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->mountAction(TestAction::make('includeMany')->table())->set('mountedActions.0.data.existing_attribute_ids', [$option->attribute_id]);
    $reviews = $manager->get('mountedActions.0.data.reviews');
    $key = array_key_first($reviews);
    $valueKey = array_key_first($reviews[$key]['values']);
    $manager->set("mountedActions.0.data.reviews.$key.default_ref", $reviews[$key]['values'][$valueKey]['ref']);
    $option->update(['is_active' => false]);
    $manager->callMountedAction()->assertHasErrors(["mountedActions.0.data.reviews.$key.values.$valueKey.option_id"])
        ->assertSet('mountedActions.0.data.existing_attribute_ids', [$option->attribute_id]);
    expect($configurator->attributes()->where('attribute_id', $option->attribute_id)->exists())->toBeFalse();
    $manager->call('unmountAction')->assertHasNoErrors();
});

test('batch inclusion appends display and code axes independently after existing gaps', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    foreach ($draft['attributes'] as $index => &$row) {
        $row['display_order'] = 10 + $index;
        $row['code_order'] = 30 + $index;
    }
    unset($row);
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $option = Option::factory()->create();
    $row = app(ConfiguratorInclusionDrafts::class)->attribute($option->attribute_id);
    $row['default_configurator_option_id'] = $row['options'][0]['id'];
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('includeAttributes', [$option->attribute_id], [$row])->assertHasNoErrors();
    $included = $configurator->attributes()->where('attribute_id', $option->attribute_id)->sole();
    expect($included->display_order)->toBe(13)->and($included->code_order)->toBe(33);
});

test('batch Attribute review restores staged Option identity and explicit default after deselection', function () {
    $configurator = Configurator::factory()->create();
    $first = Option::factory()->create();
    $second = Option::factory()->create();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->mountAction(TestAction::make('includeMany')->table())
        ->set('mountedActions.0.data.existing_attribute_ids', [$first->attribute_id, $second->attribute_id]);
    $review = $manager->get('mountedActions.0.data.reviews');
    $key = array_key_first($review);
    $optionKey = array_key_first($review[$key]['values']);
    $optionId = $review[$key]['values'][$optionKey]['ref'];
    $manager->set('mountedActions.0.data.reviews.'.$key.'.values.'.$optionKey.'.local_fields.hint', 'Keep this review hint')
        ->set('mountedActions.0.data.reviews.'.$key.'.default_ref', $optionId)
        ->set('mountedActions.0.data.existing_attribute_ids', [$second->attribute_id])
        ->set('mountedActions.0.data.existing_attribute_ids', [$second->attribute_id, $first->attribute_id]);
    $restored = collect($manager->get('mountedActions.0.data.reviews'))->firstWhere('attribute_id', $first->attribute_id);
    expect($restored['default_ref'])->toBe($optionId)
        ->and(array_values($restored['values'])[0]['ref'])->toBe($optionId)
        ->and(array_values($restored['values'])[0]['local_fields']['hint'])->toBe('Keep this review hint')
        ->and($configurator->attributes()->count())->toBe(0);
});

test('batch Attributes require explicit defaults and persist one complete ordered definition', function () {
    $configurator = Configurator::factory()->create();
    $first = Option::factory()->create(['code' => 'Q1']);
    $second = Option::factory()->create(['code' => 'Q2']);
    $rows = array_map(fn ($id) => app(ConfiguratorInclusionDrafts::class)->attribute($id), [$first->attribute_id, $second->attribute_id]);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);
    $manager->call('includeAttributes', [$first->attribute_id, $second->attribute_id], $rows)->assertHasErrors();
    expect($configurator->attributes()->count())->toBe(0);
    foreach ($rows as &$row) {
        $row['default_configurator_option_id'] = $row['options'][0]['id'];
    }
    unset($row);
    $manager->call('includeAttributes', [$first->attribute_id, $second->attribute_id], $rows)->assertHasNoErrors();
    expect($configurator->attributes()->count())->toBe(2);
    foreach ($configurator->attributes()->get() as $attribute) {
        expect($attribute->defaultOption->configurator_attribute_id)->toBe($attribute->id)
            ->and($attribute->options()->pluck('display_order')->all())->toBe([0]);
    }
});

test('batch inclusion preserves existing Options and defaults and rejects a foreign Option atomically', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->first();
    $default = $attribute->default_configurator_option_id;
    $a = Option::factory()->create(['attribute_id' => $attribute->attribute_id, 'code' => 'AX']);
    $b = Option::factory()->create(['attribute_id' => $attribute->attribute_id, 'code' => 'AY']);
    $foreign = Option::factory()->create(['code' => 'ZZ']);
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class]);
    $manager->call('includeOptions', [$a->id, $foreign->id])->assertHasErrors();
    expect($attribute->options()->where('option_id', $a->id)->exists())->toBeFalse();
    $manager->call('includeOptions', [$a->id, $b->id])->assertHasNoErrors();
    expect($attribute->options()->whereIn('option_id', [$a->id, $b->id])->count())->toBe(2)
        ->and($attribute->fresh()->default_configurator_option_id)->toBe($default);
});

test('configurator list links to the full edit page without embedding an editor', function () {
    $record = Configurator::factory()->create();
    $list = Livewire::test(ListConfigurators::class)->assertCanSeeTableRecords([$record]);

    expect($list->instance()->getTable()->getRecordUrl($record))->toBeNull();
    expect($list->instance()->getTable()->getAction('edit')->record($record)->getUrl())->toBe(ConfiguratorResource::getUrl('edit', ['record' => $record]));
    $this->get(ConfiguratorResource::getUrl('index', ['record' => $record->id]))->assertDontSeeLivewire(EditConfigurator::class);
});

test('attribute side editor saves its fields without overwriting options edited underneath it', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $option = $attribute->options()->first();
    $editor = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callAction(TestAction::make('edit')->table($attribute))->assertSet('selectedAttributeId', $attribute->id)
        ->fillForm(['label_override' => 'Local attribute label'], 'editorForm');
    $editor->call('selectOption', $attribute->id, $option->id)->fillForm(['label_override' => 'Local option label'], 'optionEditorForm')
        ->call('saveOptionEditor')->assertHasNoFormErrors(form: 'optionEditorForm');

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

    $manager->assertActionDoesNotExist(TestAction::make('include')->table())
        ->assertActionVisible(TestAction::make('includeMany')->table())
        ->callAction(TestAction::make('includeMany')->table(), data: ['existing_option_ids' => [$canonical->id]])->assertHasNoFormErrors();
    $included = $attribute->options()->where('option_id', $canonical->id)->sole();
    $ids = $attribute->options()->orderBy('display_order')->pluck('id')->all();
    $manager->call('reorderTable', array_reverse($ids))->assertHasNoErrors();
    expect($attribute->options()->orderBy('display_order')->pluck('id')->all())->toBe(array_reverse($ids));
    expect($attribute->fresh()->default_configurator_option_id)->toBe($default);
    $manager->callAction(TestAction::make('remove')->table($included))->assertHasNoFormErrors();
    expect($included->fresh())->toBeNull();
    $manager->mountAction(TestAction::make('remove')->table($attribute->options()->findOrFail($default)));
    expect($manager->instance()->getMountedAction()->getModalSubmitAction()->isDisabled())->toBeTrue();
    $manager->call('removeOption', $default)->assertHasErrors();
    expect($attribute->options()->whereKey($default)->exists())->toBeTrue();
});

test('rule side editor creates a staged rule and cancel leaves the saved definition intact', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $source = $saved['attributes'][0];
    $target = $saved['attributes'][1];
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callAction(TestAction::make('addMapping')->table())->assertSet('editorKind', 'Mapping');
    expect($configurator->rules()->count())->toBe(0);
    $manager->fillForm([
        'label' => 'New mapping', 'driver_configurator_attribute_id' => $source['id'], 'target_configurator_attribute_id' => $target['id'],
        'sets' => [['id' => 'new:set', 'label' => 'Allowed', 'source_option_ids' => [$source['options'][0]['id']], 'target_option_ids' => [$target['options'][0]['id']]]],
    ], 'editorForm')->call('saveEditor')->assertHasNoFormErrors(form: 'editorForm');
    $rule = $configurator->rules()->sole();
    $manager->callAction(TestAction::make('edit')->table($rule))->fillForm(['label' => 'Unsaved name'], 'editorForm')->call('closeEditor')->assertSet('editorKind', null);
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
        ->callAction(TestAction::make('edit')->table($rule))->set('editorData.condition_blocks', [['type' => 'unknown', 'data' => []]])
        ->call('saveEditor')->assertHasFormErrors(form: 'editorForm');
    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
});

test('switching attributes replaces the canonical attribute and table default markers', function () {
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
        ->assertFormFieldDoesNotExist('default_configurator_option_id', 'editorForm');
    $table = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $second, 'pageClass' => EditConfigurator::class]);
    foreach ($choices as $id) {
        $table->assertTableColumnStateSet('stored_default', $id === $second->default_configurator_option_id, $second->options()->findOrFail($id));
    }
});

test('Attribute A to B to A retains the unsaved local editor draft', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attributes[0]->id)->fillForm(['label_override' => 'Keep my draft'], 'editorForm')
        ->call('selectAttribute', $attributes[1]->id)->call('selectAttribute', $attributes[0]->id)
        ->assertSet('editorData.label_override', 'Keep my draft');
    expect($attributes[0]->fresh()->label_override)->toBeNull();
});

test('batch removal closes only the deleted Attribute editor and keeps other staged drafts', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $attributes = $owner->attributes()->orderBy('display_order')->get();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attributes[1]->id)->fillForm(['help_text' => 'Keep another draft'], 'editorForm')
        ->call('selectAttribute', $attributes[0]->id)->selectTableRecords([$attributes[0]])
        ->mountAction(TestAction::make('batchRemove')->table()->bulk())
        ->callMountedAction(['preview' => true])->callMountedAction()->assertHasNoFormErrors()
        ->assertSet('selectedAttributeId', null);
    expect($attributes[0]->fresh())->toBeNull();
    $manager->call('selectAttribute', $attributes[1]->id)->assertSet('editorData.help_text', 'Keep another draft');
});

test('clean Attribute editors reconcile successful batches and dirty editors cannot undo them', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $attribute = $owner->attributes()->first();
    $data = ['operation' => 'edit', 'changes' => [['field' => 'label_override', 'mode' => 'set', 'value' => 'Batch label']]];
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->selectTableRecords([$attribute])
        ->mountAction(TestAction::make('batchEdit')->table()->bulk())->fillForm($data)
        ->callMountedAction(['preview' => true])->callMountedAction()->assertHasNoFormErrors()
        ->assertSet('editorData.label_override', 'Batch label');
    $manager->fillForm(['help_text' => 'Unsaved help'], 'editorForm')->selectTableRecords([$attribute])
        ->mountAction(TestAction::make('batchEdit')->table()->bulk())->fillForm(['operation' => 'edit', 'changes' => [['field' => 'label_override', 'mode' => 'set', 'value' => 'New batch label']]])
        ->callMountedAction(['preview' => true])->callMountedAction()->assertHasNoFormErrors()
        ->assertSet('editorData.help_text', 'Unsaved help')->call('saveEditor')->assertHasErrors();
    expect($attribute->fresh()->label_override)->toBe('New batch label')->and($attribute->fresh()->help_text)->toBeNull();
    $manager->call('reloadEditor')->assertSet('editorData.label_override', 'New batch label');
});

test('Rule batch reconciliation preserves new-kind drafts and protects a dirty saved rule', function () {
    [$owner, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $owner, $draft);
    $rule = $owner->rules()->sole();
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Advanced')->fillForm(['label' => 'Keep new draft'], 'editorForm')
        ->call('selectRule', $rule->id)->selectTableRecords([$rule])
        ->mountAction(TestAction::make('batchEdit')->table()->bulk())->fillForm(['operation' => 'edit', 'changes' => [['field' => 'is_active', 'mode' => 'set', 'value' => false]]])
        ->callMountedAction(['preview' => true])->callMountedAction()->assertHasNoFormErrors()->assertSet('editorData.is_active', false);
    $manager->fillForm(['label' => 'Keep dirty saved rule'], 'editorForm')->selectTableRecords([$rule])
        ->mountAction(TestAction::make('batchEdit')->table()->bulk())->fillForm(['operation' => 'edit', 'changes' => [['field' => 'label', 'mode' => 'set', 'value' => 'Batch rule']]])
        ->callMountedAction(['preview' => true])->callMountedAction()->assertHasNoFormErrors()
        ->call('saveEditor')->assertActionMounted('reconcileRule')->assertSet('editorData.label', 'Keep dirty saved rule')
        ->call('unmountAction');
    expect($rule->fresh()->label)->toBe('Batch rule')->and($rule->fresh()->is_active)->toBeFalse();
    $manager->selectTableRecords([$rule])->mountAction(TestAction::make('batchRemove')->table()->bulk())
        ->callMountedAction(['preview' => true])->callMountedAction()->assertHasNoFormErrors()->assertSet('editorKind', null);
    expect($rule->fresh())->toBeNull();
    $manager->call('createRule', 'Advanced')->assertSet('editorData.label', 'Keep new draft');
});

test('Attribute inclusion uses the batch workspace and keeps staged Options when switching the selected Attribute', function () {
    $owner = Configurator::factory()->create();
    $a = Option::factory()->create(['code' => 'Q1']);
    $b = Option::factory()->create(['code' => 'Q2']);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => EditConfigurator::class])
        ->assertActionDoesNotExist(TestAction::make('include')->table())
        ->mountAction(TestAction::make('includeMany')->table())
        ->set('mountedActions.0.data.existing_attribute_ids', [$a->attribute_id]);
    $review = $manager->get('mountedActions.0.data.reviews');
    $key = array_key_first($review);
    $optionKey = array_key_first($review[$key]['values']);
    $optionId = $review[$key]['values'][$optionKey]['ref'];
    $manager->set('mountedActions.0.data.reviews.'.$key.'.values.'.$optionKey.'.local_fields.hint', 'Keep staged hint')
        ->set('mountedActions.0.data.reviews.'.$key.'.default_ref', $optionId)
        ->set('mountedActions.0.data.existing_attribute_ids', [$b->attribute_id])
        ->set('mountedActions.0.data.existing_attribute_ids', [$a->attribute_id]);
    $returned = collect($manager->get('mountedActions.0.data.reviews'))->firstWhere('attribute_id', $a->attribute_id);
    expect(array_values($returned['values'])[0]['local_fields']['hint'])->toBe('Keep staged hint')
        ->and(array_values($returned['values'])[0]['ref'])->toBe($optionId)
        ->and($returned['default_ref'])->toBe($optionId);
    expect($owner->attributes()->count())->toBe(0);
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

test('new rule kind drafts retain their independent unsaved fields', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('createRule', 'Mapping')->fillForm(['label' => 'Mapping draft'], 'editorForm')
        ->call('createRule', 'Advanced')->fillForm(['label' => 'Advanced draft'], 'editorForm')
        ->call('createRule', 'Mapping')->assertSet('editorData.label', 'Mapping draft')
        ->call('createRule', 'Advanced')->assertSet('editorData.label', 'Advanced draft');
    expect($configurator->rules()->count())->toBe(0);
    $manager->call('closeEditor')->assertSet('editorDrafts', [])->assertSet('editorKind', null);
});

test('viewing different unchanged Attributes does not create unsaved drafts', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attributes[0]->id)->call('selectAttribute', $attributes[1]->id)
        ->call('selectAttribute', $attributes[0]->id)->assertSet('editorDrafts', []);
});

test('saving a table default refreshes its marker without discarding an option draft', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $options = $attribute->options()->orderBy('display_order')->get();
    $editor = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->call('selectOption', $attribute->id, $options[0]->id)
        ->fillForm(['label_override' => 'Unsaved option'], 'optionEditorForm');
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class]);
    $pending = ['rules' => [['type' => 'hint', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'pending']]]]];
    $manager->set('tableFilters.availability.value', 'visible')->set('tableDeferredFilters.constraints', $pending)
        ->callAction(TestAction::make('setDefault')->table($options[1]))->assertHasNoErrors()
        ->assertSet('tableFilters.availability.value', 'visible')->assertSet('tableDeferredFilters.constraints', $pending)
        ->assertTableColumnStateSet('stored_default', false, $options[0])
        ->assertTableColumnStateSet('stored_default', true, $options[1]);
    $editor->dispatch('configurator-options-updated')->assertSet('optionEditorData.label_override', 'Unsaved option');
});

test('related option updates refresh the rule choices while preserving its draft', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $rule = $configurator->rules()->sole();
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $option = $attribute->options()->orderBy('display_order')->first();
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->callAction(TestAction::make('edit')->table($rule))->fillForm(['label' => 'Unsaved rule'], 'editorForm');
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->call('selectOption', $attribute->id, $option->id)
        ->fillForm(['label_override' => 'Updated option'], 'optionEditorForm')->call('saveOptionEditor')->assertHasNoErrors();

    $manager->dispatch('configurator-updated')->assertSchemaStateSet(['label' => 'Unsaved rule'], 'editorForm')
        ->assertFormFieldExists('sets', 'editorForm', fn (MappingSetsField $field): bool => $field->getSourceChoices()[$option->id] === 'A0 - Updated option | A');
    expect($rule->fresh()->label)->toBe('map');
});

test('V23 local subtitle link actions retain a custom draft through switches without signing UI helpers', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->firstOrFail();
    $options = $attribute->options()->orderBy('display_order')->get();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->call('selectOption', $attribute->id, $options[0]->id)
        ->fillForm(['_subtitle_text' => '  Pending subtitle  '], 'optionEditorForm')
        ->assertSet('optionEditorData.subtitle_mode', 'custom')
        ->assertSet('optionEditorData.display_value_override', '  Pending subtitle  ')
        ->callAction(TestAction::make('linkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->assertSet('optionEditorData.subtitle_mode', 'master')->assertSet('optionEditorData.display_value_override', null)
        ->call('selectOption', $attribute->id, $options[1]->id)->call('selectOption', $attribute->id, $options[0]->id)
        ->callAction(TestAction::make('unlinkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->assertSet('optionEditorData.subtitle_mode', 'custom')->assertSet('optionEditorData._subtitle_text', '  Pending subtitle  ');
    $manager->call('exportPreviewChanges')->assertReturned(function (array $capture): bool {
        $after = $capture['rows'][0]['after'];
        expect($after)->toHaveKey('subtitle_mode', 'custom')->not->toHaveKeys(['_subtitle_text', '_subtitle_custom_draft']);

        return true;
    })->call('saveOptionEditor')->assertHasNoErrors();
    expect($options[0]->fresh())->subtitle_mode->toBe('custom')->display_value_override->toBe('  Pending subtitle  ');
});

test('V23 linking back to a saved Master keeps only transient custom cache across switches', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['attributes'][0]['options'][0]['subtitle_mode'] = 'master';
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->firstOrFail();
    $options = $attribute->options()->orderBy('display_order')->get();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->call('selectOption', $attribute->id, $options[0]->id)
        ->callAction(TestAction::make('unlinkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->fillForm(['_subtitle_text' => 'Remember just in this session'], 'optionEditorForm')
        ->callAction(TestAction::make('linkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->call('selectOption', $attribute->id, $options[1]->id)->call('selectOption', $attribute->id, $options[0]->id)
        ->call('exportPreviewChanges')->assertReturned(fn (array $capture): bool => $capture['rows'] === [])
        ->callAction(TestAction::make('unlinkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->assertSet('optionEditorData._subtitle_text', 'Remember just in this session');
});

test('V23 nested local Value editor uses authoritative Master names and retains subtitle intent on save', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $sharedAttribute = Attribute::findOrFail($draft['attributes'][0]['attribute_id']);
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->mountAction(TestAction::make('includeMany')->table())->set('mountedActions.0.data.existing_attribute_ids', [$sharedAttribute->id]);
    $components = $manager->instance()->getSchema('mountedActionSchema0')->getFlatComponents(withHidden: true);
    $subtitle = collect($components)->first(fn ($component): bool => $component instanceof Field && $component->getName() === '_subtitle_text');
    expect($subtitle)->not->toBeNull();
    $manager->callAction(TestAction::make('linkSubtitle')->schemaComponent((string) str($subtitle->getKey())->after('mountedActionSchema0.'), 'mountedActionSchema0'));
    $review = $manager->get('mountedActions.0.data.reviews');
    $row = array_values($review)[0];
    $local = array_values($row['values'])[0];
    expect($local['local_fields']['subtitle_mode'])->toBe('master');
    expect($local['local_fields']['_subtitle_text'])->toBe(Option::with('value')->findOrFail($local['option_id'])->value->label);
    $reviewKey = array_key_first($review);
    $optionKey = array_key_first($row['values']);
    $other = array_values($row['values'])[1];
    $manager->set("mountedActions.0.data.reviews.$reviewKey.values.$optionKey.option_id", $other['option_id'])
        ->assertSet("mountedActions.0.data.reviews.$reviewKey.values.$optionKey.local_fields._subtitle_text", Option::with('value')->findOrFail($other['option_id'])->value->label)
        ->set("mountedActions.0.data.reviews.$reviewKey.values.$optionKey.option_id", $local['option_id']);
    $manager->set("mountedActions.0.data.reviews.$reviewKey.default_ref", $local['ref'])->callMountedAction()->assertHasNoFormErrors();
    expect($configurator->attributes()->firstOrFail()->options()->where('option_id', $local['option_id'])->firstOrFail())->subtitle_mode->toBe('master')->display_value_override->toBeNull();
});

test('V25 clean helper-only Value stashes refresh saved batch fields while retaining the unlink cache', function (bool $switchAway) {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['attributes'][0]['options'][0]['subtitle_mode'] = 'master';
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->firstOrFail();
    $options = $attribute->options()->orderBy('display_order')->get();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->call('selectOption', $attribute->id, $options[0]->id)
        ->callAction(TestAction::make('unlinkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->fillForm(['_subtitle_text' => 'Transient custom'], 'optionEditorForm')
        ->callAction(TestAction::make('linkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'));
    if ($switchAway) {
        $manager->call('selectOption', $attribute->id, $options[1]->id);
    }
    $options[0]->update(['hint' => 'Fresh batch hint']);
    $manager->dispatch('catalog-batch-applied', model: $options[0]::class, ids: [$options[0]->id]);
    if ($switchAway) {
        $manager->call('selectOption', $attribute->id, $options[0]->id);
    }
    $manager->assertSet('optionEditorData.hint', 'Fresh batch hint')
        ->call('exportPreviewChanges')->assertReturned(fn (array $capture): bool => $capture['rows'] === [])
        ->callAction(TestAction::make('unlinkSubtitle')->schemaComponent('_subtitle_text', 'optionEditorForm'))
        ->assertSet('optionEditorData._subtitle_text', 'Transient custom');
})->with(['active' => false, 'stashed' => true]);

test('V20 saved table defaults preserve active and stashed Attribute and Value edits through separate saves', function (bool $stash) {
    Option::factory()->count(3)->create();
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $attribute = $attributes[0];
    $options = $attribute->options()->orderBy('display_order')->get();
    expect($options[1]->id)->not->toBe($options[1]->option_id);
    $editor = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->fillForm(['help_text' => 'Pending Attribute help'], 'editorForm')
        ->call('selectOption', $attribute->id, $options[1]->id)->fillForm(['hint' => 'Other Value draft'], 'optionEditorForm')
        ->call('selectOption', $attribute->id, $options[0]->id)
        ->fillForm(['label_override' => 'Pending local name', '_subtitle_text' => 'Pending subtitle', 'hint' => 'Pending hint', 'hidden_by_default' => true, 'disabled_by_default' => true], 'optionEditorForm');
    if ($stash) {
        $editor->call('selectAttribute', $attributes[1]->id)->fillForm(['label_override' => 'Other Attribute draft'], 'editorForm');
    }
    $beforeState = array_intersect_key($editor->instance()->all(), array_flip(['editorData', 'editorDrafts', 'editorOriginals', 'previewOriginals', 'optionEditorData', 'optionEditorDrafts', 'optionEditorOriginals', 'selectedOptions', 'selectedOptionId']));
    $table = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class]);

    $table->callAction(TestAction::make('setDefault')->table($options[1]))->assertHasNoErrors()->assertNotDispatched('configurator-option-selected');
    $editor->dispatch('configurator-options-updated')->assertHasNoErrors();

    expect(array_intersect_key($editor->instance()->all(), $beforeState))->toBe($beforeState);
    expect($attribute->fresh()->default_configurator_option_id)->toBe($options[1]->id);
    $editor->call('selectAttribute', $attributes[1]->id)->call('selectAttribute', $attribute->id)
        ->assertSet('selectedOptionId', $options[0]->id)->assertSet('optionEditorData.label_override', 'Pending local name')
        ->assertSet('editorData.help_text', 'Pending Attribute help')
        ->call('saveOptionEditor')->assertHasNoErrors()->call('saveEditor')->assertHasNoErrors()
        ->call('selectOption', $attribute->id, $options[1]->id)->assertSet('optionEditorData.hint', 'Other Value draft');
    expect($options[0]->fresh())->label_override->toBe('Pending local name')->subtitle_mode->toBe('custom')->display_value_override->toBe('Pending subtitle')->hint->toBe('Pending hint')->hidden_by_default->toBeTrue()->disabled_by_default->toBeTrue();
    expect($attribute->fresh())->help_text->toBe('Pending Attribute help')->default_configurator_option_id->toBe($options[1]->id);
    expect($options[1]->fresh()->hint)->toBeNull();
})->with(['active' => false, 'stashed' => true]);

test('V20 workspace Attribute captures omit defaults symmetrically without losing active or stashed changes', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $attribute = $attributes[0];
    $options = $attribute->options()->orderBy('display_order')->get();
    $editor = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)->assertFormFieldDoesNotExist('default_configurator_option_id', 'editorForm')
        ->call('exportPreviewChanges')->assertReturned(fn (array $capture): bool => $capture['rows'] === []);
    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->callAction(TestAction::make('setDefault')->table($options[1]))->assertHasNoErrors();
    $editor->dispatch('configurator-options-updated')->call('exportPreviewChanges')->assertReturned(fn (array $capture): bool => $capture['rows'] === [])
        ->fillForm(['label_override' => 'Pending A'], 'editorForm')->call('selectAttribute', $attributes[1]->id)
        ->fillForm(['help_text' => 'Pending B'], 'editorForm');
    $captures = [];
    $editor->call('exportPreviewChanges')->assertReturned(function (array $capture) use (&$captures): bool {
        $captures[] = $capture;
        expect($capture['rows'])->toHaveCount(2);
        foreach ($capture['rows'] as $row) {
            expect($row['before'])->not->toHaveKey('default_configurator_option_id');
            expect($row['after'])->not->toHaveKey('default_configurator_option_id');
        }

        return true;
    });
    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->callAction(TestAction::make('setDefault')->table($options[0]))->assertHasNoErrors();
    $editor->dispatch('configurator-options-updated')->call('exportPreviewChanges')->assertReturned(fn (array $capture): bool => $capture === $captures[0]);
    expect($editor->get('editorData'))->not->toHaveKey('default_configurator_option_id');
    expect($editor->get('editorDrafts')[$attribute->id])->not->toHaveKey('default_configurator_option_id');
    foreach ($editor->get('previewOriginals') as $state) {
        expect($state)->not->toHaveKey('default_configurator_option_id');
    }
    $editor->call('selectAttribute', $attribute->id)->set('editorData.default_configurator_option_id', $options[1]->id)->call('saveEditor')->assertHasNoErrors();
    expect($attribute->fresh())->label_override->toBe('Pending A')->default_configurator_option_id->toBe($options[0]->id);
});

test('V21 current saved default is a true no-op including revisions notifications and definition events', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $default = $attribute->options()->findOrFail($attribute->default_configurator_option_id);
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class]);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $revision = $group->fresh()->catalog_revision;
    $updatedAt = $configurator->fresh()->updated_at;
    $this->travel(1)->minute();

    $manager->callAction(TestAction::make('setDefault')->table($default))->assertHasNoErrors()->assertNotNotified()
        ->assertNotDispatched('configurator-options-updated')->assertNotDispatched('configurator-updated');

    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
    expect($configurator->fresh()->updated_at->eq($updatedAt))->toBeTrue();
    expect($group->fresh()->catalog_revision)->toBe($revision);
});

test('V21 saved defaults reject other inclusions missing rows and foreign Configurators atomically', function (string $scope) {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $id = $attributes[1]->options()->first()->id;
    if ($scope === 'other Configurator') {
        $other = Configurator::factory()->create();
        app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $other, $draft);
        $id = $other->attributes()->first()->options()->first()->id;
    } elseif ($scope === 'missing') {
        $id = 999999;
    }
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());

    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attributes[0], 'pageClass' => EditConfigurator::class])
        ->call('setDefault', $id)->assertHasErrors()->assertNotNotified()->assertNotDispatched('configurator-updated');

    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
})->with(['same Configurator', 'other Configurator', 'missing']);

test('V21 direct default selection authorizes before querying membership', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class]);
    $this->actingAs(User::factory()->create());
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(fn () => $manager->instance()->setDefault($attribute->default_configurator_option_id))->toThrow(AuthorizationException::class);

    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

test('V21 saved Default cell names both states and retains runtime unavailable-default policy', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping()];
    $draft['attributes'][0]['options'][0]['display_order'] = 3;
    $draft['attributes'][0]['options'][1]['display_order'] = 8;
    $draft['attributes'][0]['options'][1]['hidden_by_default'] = true;
    $draft['attributes'][0]['options'][1]['disabled_by_default'] = true;
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $options = $attribute->options()->with('option.value')->orderBy('display_order')->get();
    $manager = Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class]);
    $manager->assertSee('saved-default-v1');
    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])->assertDontSee('saved-default-v1');
    expect(array_slice(array_keys($manager->instance()->getTable()->getVisibleColumns()), 0, 2))->toBe(['stored_default', 'option.code']);
    $column = $manager->instance()->getTable()->getColumn('stored_default');
    $html = (string) $column->record($options[1])->renderInLayout();
    expect($html)->toContain('button', 'wire:click.prevent.stop', 'Set '.$options[1]->option->value->label.' as default');

    $expected = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $expected['attributes'][0]['default_configurator_option_id'] = (string) $options[1]->id;
    $manager->callAction(TestAction::make('setDefault')->table($options[1]))->assertHasNoErrors();

    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($expected);
    expect($attribute->fresh()->default_configurator_option_id)->toBe($options[1]->id);
    $html = (string) $manager->instance()->getTable()->getColumn('stored_default')->record($options[1])->renderInLayout();
    expect($html)->toContain('Current default: '.$options[1]->option->value->label);
});

test('V21 standalone full Attribute forms retain an explicit default input while reviews use composed settings', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $manager = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])->call('selectAttribute', $attribute->id);
    $schema = Schema::make($manager->instance())->statePath('editorData')
        ->components(ConfiguratorAttributeForm::components($configurator, $attribute, withOptions: false));
    $fields = $schema->getFlatFields(withHidden: true);

    expect($fields['default_configurator_option_id'])->toBeInstanceOf(Select::class);
    expect(array_keys($fields['default_configurator_option_id']->getOptions()))->toBe($attribute->options()->orderBy('display_order')->pluck('id')->all());
    $review = Schema::make($manager->instance())->statePath('editorData')
        ->components(ConfiguratorAttributeForm::reviewComponents($configurator));
    expect($review->getFlatFields(withHidden: true))->toHaveKeys(['input_type', 'label_override', 'help_text', 'options'])->not->toHaveKey('default_configurator_option_id');
});
