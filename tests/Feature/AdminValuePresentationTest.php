<?php

use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\RelationManagers\OptionsRelationManager as AttributeOptions;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager as IncludedOptions;
use App\Filament\Resources\Options\OptionResource;
use App\Filament\Resources\Options\Pages\CreateOption;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\OptionSelectionTable;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Filament\Resources\ValueSelectionTable;
use App\Livewire\Catalog\ItemListDrawer;
use App\Livewire\Catalog\WorkspaceTablePicker;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('Value tables expose saved tag buttons that filter immediately within their parent', function (string $surface) {
    $attribute = Attribute::factory()->create();
    $metal = Value::factory()->create(['tags' => ['metal']]);
    $polymer = Value::factory()->create(['tags' => ['polymer']]);
    $first = Option::factory()->for($attribute)->for($metal)->create();
    $second = Option::factory()->for($attribute)->for($polymer)->create();
    $inclusion = ConfiguratorAttribute::factory()->for($attribute)->create();
    $includedFirst = ConfiguratorOption::factory()->for($inclusion, 'configuratorAttribute')->for($first, 'option')->create();
    $includedSecond = ConfiguratorOption::factory()->for($inclusion, 'configuratorAttribute')->for($second, 'option')->create();
    $foreign = Option::factory()->for($metal)->create();
    $page = match ($surface) {
        'values' => Livewire::test(ListValues::class),
        'options' => Livewire::test(ListOptions::class),
        'attribute' => Livewire::test(AttributeOptions::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class]),
        'included' => Livewire::test(IncludedOptions::class, ['ownerRecord' => $inclusion, 'pageClass' => EditConfigurator::class]),
        'values-picker' => Livewire::test(WorkspaceTablePicker::class, ['tableConfiguration' => base64_encode(ValueSelectionTable::class), 'tableArguments' => ['owner_id' => 'create'], 'state' => []]),
        'options-picker' => Livewire::test(WorkspaceTablePicker::class, ['tableConfiguration' => base64_encode(OptionSelectionTable::class), 'tableArguments' => ['owner_id' => ConfiguratorAttribute::factory()->for($attribute)->create()->id], 'state' => []]),
        'options-drawer' => Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $attribute->id]),
        'included-drawer' => Livewire::test(ItemListDrawer::class, ['listKey' => 'inclusion-options', 'parentId' => $inclusion->id]),
    };
    [$visible, $hidden] = match ($surface) {
        'values', 'values-picker' => [$metal, $polymer],
        'included', 'included-drawer' => [$includedFirst, $includedSecond],
        default => [$first, $second],
    };

    $page->call('restoreTableBrowserProfile', ['tagFilters' => ['polymer', 'metal']])->assertHasNoErrors()
        ->assertSeeHtml('catalog-inline-tag-filter')
        ->set('tableFilters.tags.values', ['metal'])
        ->assertCanSeeTableRecords([$visible])->assertCanNotSeeTableRecords([$hidden]);
    expect(array_keys($page->instance()->inlineTagsForm->getFlatFields()['values']->getOptions()))->toBe(['polymer', 'metal']);
    $page->call('removeTableFilter', 'tags', 'values')
        ->assertCanSeeTableRecords([$visible, $hidden]);
    if (! in_array($surface, ['values', 'values-picker', 'options'], true)) {
        $page->assertCanNotSeeTableRecords([$foreign]);
    }
})->with(['values', 'options', 'attribute', 'included', 'values-picker', 'options-picker', 'options-drawer', 'included-drawer']);

test('clicking a quick tag leaves other pending modal filter changes untouched', function () {
    Value::factory()->create(['tags' => ['metal']]);
    Livewire::test(ListOptions::class)
        ->set('tableDeferredFilters.attribute_id.value', '123')
        ->set('tableFilters.tags.values', ['metal'])
        ->assertSet('tableDeferredFilters.attribute_id.value', '123')
        ->assertSet('tableDeferredFilters.tags.values', ['metal']);
});

test('Option titles identify the Code Value and Attribute while related selectors name the Master Value', function () {
    $attribute = Attribute::factory()->create(['label' => 'Body material']);
    $value = Value::factory()->create(['label' => 'Viton']);
    $option = Option::factory()->for($attribute)->for($value)->create(['code' => 'A0']);

    expect(OptionResource::getRecordTitle($option))->toBe('A0 - Viton | Body material');
    Livewire::test(CreateOption::class)
        ->assertFormFieldExists('value_id', fn (Select $field): bool => $field->getLabel() === 'Master Value');
    $table = Livewire::test(ListOptions::class)->instance()->getTable();
    expect($table->getColumn('value.label')->getLabel())->toBe('Value UI Name');
    $manager = Livewire::test(AttributeOptions::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class]);
    expect($manager->instance()->getTable()->getHeading())->toBe('Attribute Values — Body material');
});

test('nested Value tables keep independent tag label associations', function () {
    $attribute = Attribute::factory()->create();
    $option = Option::factory()->for($attribute)->create();
    $option->value->update(['tags' => ['metal']]);
    $parent = Livewire::test(ListOptions::class);
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-options', 'parentId' => $attribute->id]);

    expect($parent->instance()->inlineTagsForm->getFlatFields()['values']->getId())
        ->not->toBe($drawer->instance()->inlineTagsForm->getFlatFields()['values']->getId());
});

test('table pickers retain tag preferences and describe the selected object collection', function () {
    $attribute = Attribute::factory()->create(['label' => 'Body material']);
    $inclusion = ConfiguratorAttribute::factory()->for($attribute)->create(['label_override' => 'Local body']);
    $values = Livewire::test(WorkspaceTablePicker::class, ['tableConfiguration' => base64_encode(ValueSelectionTable::class), 'tableArguments' => ['owner_id' => 'create'], 'state' => []]);
    $options = Livewire::test(WorkspaceTablePicker::class, ['tableConfiguration' => base64_encode(OptionSelectionTable::class), 'tableArguments' => ['owner_id' => $inclusion->id], 'state' => []]);

    expect($values->instance()->getTable()->getHeading())->toBe('Master Values');
    expect($options->instance()->getTable()->getHeading())->toBe('Attribute Values — Local body');
    foreach ([$values, $options] as $page) {
        $actions = collect($page->instance()->getTable()->getHeaderActions())
            ->flatMap(fn ($action) => $action instanceof ActionGroup ? $action->getFlatActions() : [$action]);
        expect($actions->map->getName()->all())->toContain('tagFilterPreferences');
    }
});

test('removing a quick tag indicator preserves other pending filter drafts', function () {
    Value::factory()->create(['tags' => ['metal']]);
    Livewire::test(ListOptions::class)
        ->set('tableDeferredFilters.attribute_id.value', '123')
        ->set('tableFilters.tags.values', ['metal'])
        ->call('removeTableFilter', 'tags', 'values')
        ->assertSet('tableFilters.attribute_id.value', null)
        ->assertSet('tableDeferredFilters.attribute_id.value', '123')
        ->assertSet('tableFilters.tags.values', []);
});

test('included Value drawers name their effective Attribute context', function () {
    $attribute = Attribute::factory()->create(['label' => 'Body material']);
    $inclusion = ConfiguratorAttribute::factory()->for($attribute)->create(['label_override' => 'Local body']);

    Livewire::test(ItemListDrawer::class, ['listKey' => 'inclusion-options', 'parentId' => $inclusion->id])
        ->assertSee('Included Attribute Values — Local body');
});

test('inclusion review Option choices use the staged local names without saving shared records', function () {
    $configurator = Configurator::factory()->create();
    $attribute = Attribute::factory()->create(['label' => 'Body material']);
    $value = Value::factory()->create(['label' => 'Viton']);
    $option = Option::factory()->for($attribute)->for($value)->create(['code' => 'A0']);
    $page = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->mountAction(TestAction::make('includeMany')->table())
        ->set('mountedActions.0.data.selected', [$attribute->id]);
    $review = $page->get('mountedActions.0.data.review');
    $key = array_key_first($review);
    $optionKey = array_key_first($review[$key]['options']);
    $page->set('mountedActions.0.data.review.'.$key.'.label_override', 'Local body')
        ->set('mountedActions.0.data.review.'.$key.'.options.'.$optionKey.'.label_override', 'Local Viton');
    $schema = $page->instance()->getSchema($page->instance()->getMountedActionSchemaName());
    $choice = collect($schema->getFlatFields(withHidden: true))->first(fn ($field): bool => $field->getStatePath() === 'mountedActions.0.data.review.'.$key.'.options.'.$optionKey.'.option_id');

    expect($choice->getOptions()[$option->id])->toBe('A0 - Local Viton | Local body');
    expect($value->fresh()->label)->toBe('Viton')->and($attribute->fresh()->label)->toBe('Body material');
});
