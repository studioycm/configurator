<?php

use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\OptionSelectionTable;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Models\Attribute;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorRule;
use App\Models\Option;
use App\Models\User;
use App\Models\Value;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TableSelect\Livewire\TableSelectLivewireComponent;
use Filament\Forms\Components\ToggleButtons;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('one scoped search distinguishes a code from a value and keeps hidden searchable fields', function () {
    $attribute = Attribute::factory()->create(['key' => 'secret-key']);
    $a = Option::factory()->create(['attribute_id' => $attribute->id, 'code' => 'A1', 'value_id' => Value::factory()->create(['label' => 'Flange'])->id]);
    $b = Option::factory()->create(['code' => 'B1', 'value_id' => Value::factory()->create(['label' => 'A1'])->id]);
    $table = Livewire::test(ListOptions::class)->searchTable('A1')->assertCanSeeTableRecords([$a, $b]);
    $table->set('tableSearchScope', 'code')->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    $table->set('tableSearchScope', 'value.label')->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
    $table->set('tableSearchScope', 'untrusted.sql')->assertCanNotSeeTableRecords([$a, $b]);
});

test('scoped search cannot escape a relation manager parent even when the term matches foreign rows', function () {
    $parent = Attribute::factory()->create();
    $own = Option::factory()->create(['attribute_id' => $parent->id, 'code' => 'A1']);
    $foreign = Option::factory()->create(['code' => 'A2']);
    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $parent, 'pageClass' => EditAttribute::class])
        ->set('tableSearchScope', 'code')->searchTable('A')
        ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
});

test('native constraints combine Code and related Value with AND and OR', function () {
    $flange = Value::factory()->create(['label' => 'Flange']);
    $a = Option::factory()->create(['code' => 'A1', 'value_id' => $flange->id]);
    $b = Option::factory()->create(['code' => 'B1', 'value_id' => $flange->id]);
    $c = Option::factory()->create(['code' => 'A2']);
    $code = ['type' => 'code', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'A']]];
    $value = ['type' => 'value.label', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'Flange']]];
    $page = Livewire::test(ListOptions::class)->filterTable('constraints', ['rules' => [$code, $value]])->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b, $c]);
    $page->filterTable('constraints', ['rules' => [['type' => 'or', 'data' => ['groups' => [['rules' => [$code]], ['rules' => [$value]]]]]]])->assertCanSeeTableRecords([$a, $b, $c]);
});

test('embedded record selection supports the same scoped search without altering native selection state', function () {
    $inclusion = ConfiguratorAttribute::factory()->create();
    $a = Option::factory()->create(['attribute_id' => $inclusion->attribute_id, 'code' => 'A1', 'value_id' => Value::factory()->create(['label' => 'Flange'])->id]);
    $b = Option::factory()->create(['attribute_id' => $inclusion->attribute_id, 'code' => 'B1', 'value_id' => Value::factory()->create(['label' => 'A1'])->id]);
    $table = Livewire::test(TableSelectLivewireComponent::class, ['tableConfiguration' => base64_encode(OptionSelectionTable::class), 'tableArguments' => ['owner_id' => $inclusion->id], 'state' => []])
        ->searchTable('A1')->assertCanSeeTableRecords([$a, $b]);
    $table->set('tableFilters.workspaceSearch.scope', 'code')->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    $table->assertSet('state', []);
    $table->set('tableFilters.workspaceSearch.scope', ['untrusted'])->assertCanNotSeeTableRecords([$a, $b])->assertSet('state', []);
});

test('Option picker filters inherited tags immediately while retaining native checkbox selection', function () {
    $inclusion = ConfiguratorAttribute::factory()->create();
    $tagged = Option::factory()->create(['attribute_id' => $inclusion->attribute_id, 'value_id' => Value::factory()->create(['tags' => ['metal']])->id]);
    $other = Option::factory()->create(['attribute_id' => $inclusion->attribute_id, 'value_id' => Value::factory()->create(['tags' => ['polymer']])->id]);
    $foreign = Option::factory()->create(['value_id' => Value::factory()->create(['tags' => ['metal']])->id]);
    Livewire::test(TableSelectLivewireComponent::class, ['tableConfiguration' => base64_encode(OptionSelectionTable::class), 'tableArguments' => ['owner_id' => $inclusion->id], 'state' => [$other->id]])
        ->set('tableFilters.tags.values', ['metal'])->assertCanSeeTableRecords([$tagged])->assertCanNotSeeTableRecords([$other, $foreign])
        ->assertSet('state', [$other->id]);
});

test('quick relationship choices combine with advanced constraints and All clears only the quick choice', function () {
    $parent = Attribute::factory()->create();
    $flange = Value::factory()->create(['label' => 'Flange']);
    $a = Option::factory()->for($parent)->for($flange, 'value')->create(['code' => 'A1']);
    $b = Option::factory()->for($parent)->create(['code' => 'B1']);
    $c = Option::factory()->for($flange, 'value')->create(['code' => 'A2']);
    $page = Livewire::test(ListOptions::class);
    expect($page->instance()->getTable()->getFilter('attribute_id')->getSchemaComponents()[0])->toBeInstanceOf(ToggleButtons::class);
    expect(array_key_last($page->instance()->getTable()->getFilters()))->toBe('constraints');
    $page->filterTable('attribute_id', $parent->id)
        ->filterTable('constraints', ['rules' => [['type' => 'code', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'A']]]]])
        ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b, $c]);
    $page->filterTable('attribute_id', '')->assertCanSeeTableRecords([$a, $c])->assertCanNotSeeTableRecords([$b]);
});

test('six relationship choices use buttons and a seventh preserves the searchable select and its result limit', function () {
    Attribute::factory()->count(6)->create();
    $page = Livewire::test(ListOptions::class);
    expect($page->instance()->getTable()->getFilter('attribute_id')->getSchemaComponents()[0])->toBeInstanceOf(ToggleButtons::class);
    $seventh = Attribute::factory()->create(['label' => 'Seventh choice']);
    $option = Option::factory()->for($seventh)->create();
    $page = Livewire::test(ListOptions::class);
    $filter = $page->instance()->getTable()->getFilter('attribute_id');
    expect($filter->getSchemaComponents()[0])->toBeInstanceOf(Select::class);
    expect($filter->getOptionsLimit())->toBe(50);
    $page->filterTable('attribute_id', $seventh->id)->assertCanSeeTableRecords([$option]);
});

test('quick rule kind and activation filters retain owner boundaries and the advanced builder', function () {
    $enabled = ConfiguratorRule::factory()->create(['label' => 'Flange enabled']);
    $disabled = ConfiguratorRule::factory()->create(['configurator_id' => $enabled->configurator_id, 'label' => 'Flange disabled', 'is_active' => false]);
    $mapping = ConfiguratorRule::factory()->create(['configurator_id' => $enabled->configurator_id, 'label' => 'Flange mapping', 'kind' => 'Mapping']);
    $foreign = ConfiguratorRule::factory()->create(['label' => 'Flange foreign']);
    $page = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $enabled->configurator, 'pageClass' => EditConfigurator::class]);
    $page->assertSet('tableDeferredFilters.kind.value', fn ($state): bool => $state === '')
        ->assertSet('tableDeferredFilters.is_active.value', fn ($state): bool => $state === '');
    $page->filterTable('kind', 'Advanced')->filterTable('is_active', '0')
        ->filterTable('constraints', ['rules' => [['type' => 'label', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'Flange']]]]])
        ->assertCanSeeTableRecords([$disabled])->assertCanNotSeeTableRecords([$enabled, $mapping, $foreign]);
    $page->filterTable('is_active', '')->assertCanSeeTableRecords([$enabled, $disabled])->assertCanNotSeeTableRecords([$mapping, $foreign]);
});

test('tag filters remain buttons beyond six choices and match any selected tag', function () {
    $a = Value::factory()->create(['label' => 'Flange alpha', 'tags' => ['alpha']]);
    $b = Value::factory()->create(['label' => 'Flange beta', 'tags' => ['beta']]);
    $c = Value::factory()->create(['label' => 'Flange gamma', 'tags' => ['gamma', 'delta', 'epsilon', 'zeta']]);
    $page = Livewire::test(ListValues::class);
    expect($page->instance()->getTable()->getFilter('tags')->getSchemaComponents()[0])->toBeInstanceOf(ToggleButtons::class);
    $page->filterTable('tags', ['values' => ['alpha', 'beta']])
        ->assertCanSeeTableRecords([$a, $b])->assertCanNotSeeTableRecords([$c]);
    $seventh = Value::factory()->create(['tags' => ['seventh']]);
    $page = Livewire::test(ListValues::class);
    $field = $page->instance()->getTable()->getFilter('tags')->getSchemaComponents()[0];
    expect($field)->toBeInstanceOf(ToggleButtons::class);
    expect($field->getOptions())->toHaveKey('seventh');
    $page->filterTable('tags', ['values' => ['seventh']])
        ->assertCanSeeTableRecords([$seventh])->assertCanNotSeeTableRecords([$a, $b, $c]);
});
