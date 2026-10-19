<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Filament\Forms\Components\MappingSetsField;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Filament\Resources\Configurators\RelationManagers\AttributesRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Configurators\RelationManagers\RulesRelationManager;
use App\Filament\Resources\Options\Pages\ListOptions;
use App\Filament\Resources\Values\Pages\ListValues;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

require_once dirname(__DIR__).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

function adminValueIdentificationFixture(): array
{
    [$configurator, $data] = canonicalDefinitionFixture();
    Option::findOrFail($data['attributes'][0]['options'][0]['option_id'])->value->update(['label' => 'Viton', 'semantic_id' => 'viton_body']);
    Option::findOrFail($data['attributes'][0]['options'][1]['option_id'])->value->update(['label' => 'Viton', 'semantic_id' => 'viton_seal']);
    Option::findOrFail($data['attributes'][1]['options'][1]['option_id'])->value->update(['label' => 'Polypropylene', 'semantic_id' => 'polypropylene_float']);
    $data['attributes'][0]['options'][0]['label_override'] = 'Local Viton';
    $data['rules'] = [
        [...fixtureMapping(), 'conditions' => [fixtureCondition('mapping-condition')]],
        [...fixtureAdvanced('advanced', [fixtureCondition('advanced-condition')], 'B', 'SetHint', ['new:B1'], 'Existing hint'), 'priority' => 1],
    ];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $data);

    return [$configurator, $configurator->attributes()->orderBy('display_order')->first()];
}

test('Admin library tables distinguish and search Values with the same UI Name', function () {
    $body = Value::factory()->create(['label' => 'Viton', 'semantic_id' => 'viton_body']);
    $seal = Value::factory()->create(['label' => 'Viton', 'semantic_id' => 'viton_seal']);
    $bodyOption = Option::factory()->for($body)->create();
    $sealOption = Option::factory()->for($seal)->create();

    Livewire::test(ListValues::class)->assertTableColumnExists('semantic_id')
        ->assertSee('viton_body')->assertSee('viton_seal')->searchTable('viton_seal')
        ->assertCanSeeTableRecords([$seal])->assertCanNotSeeTableRecords([$body]);
    Livewire::test(ListOptions::class)->assertTableColumnExists('value.semantic_id')
        ->searchTable('viton_body')->assertCanSeeTableRecords([$bodyOption])->assertCanNotSeeTableRecords([$sealOption]);
});

test('Admin Configurator Values keep local names and add their Master Value identities', function () {
    [$configurator, $attribute] = adminValueIdentificationFixture();
    $option = $attribute->options()->orderBy('display_order')->first();

    Livewire::test(OptionsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditConfigurator::class])
        ->assertTableColumnExists('option.value.semantic_id')
        ->assertTableColumnFormattedStateSet('option.value.label', 'Local Viton', $option)
        ->searchTable('viton_body')->assertCanSeeTableRecords([$option]);

    Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)
        ->assertFormFieldExists('default_configurator_option_id', 'editorForm', fn (Select $field): bool => $field->getOptions()[$option->id] === 'A0 - Local Viton | A')
        ->call('selectOption', $attribute->id, $option->id)
        ->assertSee('A0 - Local Viton | A')
        ->assertSchemaStateSet(['label_override' => 'Local Viton'], 'optionEditorForm');
});

test('Mapping and Advanced Rule choices identify Values while preserving stored references', function () {
    [$configurator, $attribute] = adminValueIdentificationFixture();
    $source = $attribute->options()->orderBy('display_order')->first();
    $target = $configurator->attributes()->where('display_order', 1)->sole()->options()->where('display_order', 1)->sole();
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $manager = Livewire::test(RulesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class]);

    foreach ($configurator->rules()->get() as $rule) {
        $manager->call('selectRule', $rule->id);
        $conditionKey = array_key_first($manager->get('editorData.condition_blocks'));
        $conditionField = collect($manager->instance()->editorForm->getFlatFields(withHidden: true))
            ->first(fn ($field): bool => $field->getStatePath() === 'editorData.condition_blocks.'.$conditionKey.'.data.option_ids');
        expect($conditionField)->toBeInstanceOf(Select::class);
        expect($conditionField->getOptions()[$source->id])->toBe('A0 - Local Viton | A');

        if ($rule->kind === 'Mapping') {
            $manager->assertFormFieldExists('sets', 'editorForm', fn (MappingSetsField $field): bool => $field->getSourceChoices()[$source->id] === 'A0 - Local Viton | A' && $field->getTargetChoices()[$target->id] === 'B1 - Polypropylene | B');
        } else {
            $effectKey = array_key_first($manager->get('editorData.effects'));
            $manager->assertFormFieldExists('effects.'.$effectKey.'.option_ids', 'editorForm', fn (Select $field): bool => $field->getOptions()[$target->id] === 'B1 - Polypropylene | B');
        }

        $manager->call('saveEditor')->assertHasNoErrors();
    }

    expect(app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh()))->toBe($before);
});

test('dashboard Product configurator keeps semantic IDs private while Admin Preview offers selected Value identities', function () {
    [$configurator] = adminValueIdentificationFixture();
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();

    Livewire::test(ProductConfigurator::class, ['productId' => $product->id])
        ->assertSee('Local Viton')->assertSee('Polypropylene')->assertSee('A0-B1-C0')
        ->assertDontSee('viton_body')->assertDontSee('viton_seal')->assertDontSee('polypropylene_float');

    Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id])
        ->call('chooseProduct', $product->id)
        ->assertSee('Local Viton')->assertSee('Polypropylene')->assertSee('A0-B1-C0')
        ->assertSee('viton_body')->assertSee('polypropylene_float')->assertDontSee('viton_seal');
});

test('Configurator Option labels follow local Attribute names and unsaved Value name overrides', function () {
    [$configurator, $attribute] = adminValueIdentificationFixture();
    $attribute->update(['label_override' => 'Local body']);
    $option = $attribute->options()->orderBy('display_order')->first();
    $page = Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $configurator, 'pageClass' => EditConfigurator::class])
        ->call('selectAttribute', $attribute->id)
        ->assertFormFieldExists('default_configurator_option_id', 'editorForm', fn (Select $field): bool => $field->getOptions()[$option->id] === 'A0 - Local Viton | Local body')
        ->set('editorData.label_override', 'Draft body')
        ->assertFormFieldExists('default_configurator_option_id', 'editorForm', fn (Select $field): bool => $field->getOptions()[$option->id] === 'A0 - Local Viton | Draft body');
    expect($attribute->fresh()->label_override)->toBe('Local body');
});
