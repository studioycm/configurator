<?php

use App\Actions\BatchCatalogChanges;
use App\Actions\SaveGroupSettings;
use App\DTO\ConfiguratorEvaluationInput;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\SubGroup;
use App\Models\User;
use App\Services\CatalogPolicy;
use App\Services\ConfiguratorDefinitionCompiler;
use App\Services\ConfiguratorEngine;
use App\Services\ProductFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

test('card settings retain custom literal profile field references', function () {
    expect(CatalogPolicy::resultSettings(['card_properties' => ['Custom.Field', '0', 'pressure "label"']])['card_properties'])
        ->toBe(['Custom.Field', '0', 'pressure "label"']);
});

test('field suggestions combine mapped observed and saved keys within the relevant product scope', function () {
    $configurator = Configurator::factory()->create();
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    Product::factory()->for($group)->create(['profile_fields' => ['Custom.Field' => '001', '0' => 'zero'], 'parts' => ['PrivatePart' => 'metal']]);
    Product::factory()->create(['profile_fields' => ['ForeignField' => 'hidden']]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'SavedMissing']);

    $keys = ProductFields::keys(groupId: $group->id);

    expect($keys)->toContain('Working_Pressure', 'product_supplier', 'Custom.Field', '0', 'SavedMissing')
        ->not->toContain('ForeignField', 'PrivatePart');
    expect(ProductFields::keys(configuratorId: $configurator->id))->toContain('Custom.Field', 'SavedMissing')
        ->not->toContain('ForeignField');
});

test('field suggestions refresh after an edit without losing saved references', function () {
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create(['profile_fields' => ['Before' => 'value']]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Before']);
    expect(ProductFields::keys(groupId: $group->id))->toContain('Before');

    $product->update(['profile_fields' => ['After' => 'value']]);

    expect(ProductFields::keys(groupId: $group->id))->toContain('Before', 'After');
});

test('rules can use a custom profile field independently of group filters', function () {
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $condition = ['id' => 'new:custom', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null,
        'property_key' => 'Custom.Field', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '001', 'option_ids' => []];
    $data['rules'] = [fixtureAdvanced('custom', [$condition], 'B', 'ExcludeOptions', ['new:B0'])];

    $definition = app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
    $result = app(ConfiguratorEngine::class)->evaluate(new ConfiguratorEvaluationInput($definition, properties: ['Custom.Field' => '001']));

    expect($result->attributes['new:B']['legal'])->toBe(['new:B1']);
});

test('groups can configure more than eighteen valid profile field references', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $filters = array_map(fn (int $number): array => ['property_key' => 'Custom'.$number, 'label' => 'Field '.$number, 'values' => []], range(1, 19));

    app(SaveGroupSettings::class)->handle($actor, $group, ['filters' => $filters, 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS]);

    expect($group->filters()->count())->toBe(19);
});

test('the group editor can discover and save a custom product field as a filter', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['profile_fields' => ['Custom.Field' => '001']]);

    Livewire::test(EditGroup::class, ['record' => $group->id])
        ->set('data.filter_properties_to_add', ['Custom.Field'])
        ->call('save')->assertHasNoFormErrors();

    expect($group->filters()->sole()->property_key)->toBe('Custom.Field');
});

test('unchanged saved filter values survive removal of the last product field occurrence', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'Custom.Field', 'value_order' => ['001'], 'value_labels' => ['001' => 'Original']]);
    $data = ['filters' => [['id' => $filter->id, 'property_key' => 'Custom.Field', 'label' => $filter->label,
        'values' => [['value' => '001', 'label' => 'Original']]]], 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS];

    app(SaveGroupSettings::class)->handle($actor, $group, $data);

    expect($filter->fresh()->value_order)->toBe(['001'])->and($filter->fresh()->value_labels)->toBe(['001' => 'Original']);
    $data['filters'][0]['values'][] = ['value' => 'UnobservedNew', 'label' => ''];
    expect(fn () => app(SaveGroupSettings::class)->handle($actor, $group, $data))->toThrow(ValidationException::class);
});

test('the group editor retains saved preset values after their last product occurrence disappears', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $group = Group::factory()->create();
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Custom.Field', 'allowed_values' => ['retained']]);

    Livewire::test(EditGroup::class, ['record' => $group->id])
        ->fillForm(['name' => 'Updated group name'])
        ->call('save')->assertHasNoFormErrors();

    expect($group->fresh()->name)->toBe('Updated group name');
    expect($preset->fresh()->allowed_values)->toBe(['retained']);
    expect(fn () => app(SaveGroupSettings::class)->handle($actor, $group, [
        'filters' => [], 'sub_groups' => [['id' => $preset->id, 'label' => $preset->label,
            'property_key' => 'Custom.Field', 'allowed_values' => ['retained', 'unobserved new value']]],
        'result_settings' => CatalogPolicy::RESULT_SETTINGS,
    ]))->toThrow(ValidationException::class);
});

test('the group editor does not transfer retained preset values to another property or group', function (bool $foreignPreset) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $group = Group::factory()->create();
    $preset = SubGroup::factory()->for($group)->create(['property_key' => 'Custom.Field', 'allowed_values' => ['retained']]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'Other.Field']);
    $foreign = SubGroup::factory()->create(['property_key' => 'Custom.Field', 'allowed_values' => ['retained']]);
    $originalName = $group->name;
    $editor = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $rowKey = array_key_first($editor->get('data.catalog_settings.sub_groups'));

    $editor->fillForm(['name' => 'Must stay a draft'])
        ->set('data.catalog_settings.sub_groups.'.$rowKey.($foreignPreset ? '.id' : '.property_key'), $foreignPreset ? $foreign->id : 'Other.Field')
        ->call('save')->assertHasFormErrors(['catalog_settings.sub_groups.'.$rowKey.'.allowed_values.0']);

    expect($group->fresh()->name)->toBe($originalName);
    expect($preset->fresh()->property_key)->toBe('Custom.Field')->and($preset->fresh()->allowed_values)->toBe(['retained']);
})->with(['changed property' => [false], 'foreign preset' => [true]]);

test('renaming a filter does not collide with another valid profile field name', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $first = GroupFilter::factory()->for($group)->create(['property_key' => 'Before']);
    $second = GroupFilter::factory()->for($group)->create(['property_key' => '__moving_'.$first->id]);

    app(SaveGroupSettings::class)->handle($actor, $group, [
        'filters' => [
            ['id' => $first->id, 'property_key' => 'After', 'label' => 'Renamed', 'values' => []],
            ['id' => $second->id, 'property_key' => $second->property_key, 'label' => 'Unchanged', 'values' => []],
        ], 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS,
    ]);

    expect($first->fresh()->property_key)->toBe('After')->and($second->fresh()->property_key)->toBe('__moving_'.$first->id);
});

test('batch description previews do not scan profile fields for each selected group', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $groups = Group::factory()->count(3)->create();
    $action = app(BatchCatalogChanges::class);
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'description', 'mode' => 'set', 'value' => 'Updated']]];
    $measure = function (array $ids) use ($action, $actor, $intent): int {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $action->preview($actor, 'groups', $ids, $intent);

            return count(array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'products')));
        } finally {
            DB::disableQueryLog();
        }
    };

    $single = $measure([$groups->first()->id]);

    expect($single)->toBe(0)->and($measure($groups->modelKeys()))->toBe(0);
});
