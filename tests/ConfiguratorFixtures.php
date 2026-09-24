<?php

use App\DTO\ConfiguratorDefinition;
use App\Models\Attribute;
use App\Models\Configurator;
use App\Models\Option;
use App\Services\ConfiguratorDefinitionCompiler;

/** @return array{array<string, mixed>, array<int, array<string, mixed>>, array<int, array<string, mixed>>} */
function compiledDefinitionFixture(): array
{
    $attributes = [];
    $canonicalAttributes = [];
    $canonicalOptions = [];
    foreach (['A', 'B', 'C'] as $index => $letter) {
        $canonicalAttributes[$index + 1] = ['key' => $letter, 'label' => $letter];
        $options = [];
        foreach ([0, 1] as $number) {
            $id = $index * 2 + $number + 1;
            $canonicalOptions[$id] = ['attribute_id' => $index + 1, 'code' => $letter.$number, 'label' => 'Value '.$letter.$number];
            $options[] = ['id' => 'new:'.$letter.$number, 'option_id' => $id, 'display_order' => $number, 'label_override' => null, 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false];
        }
        $attributes[] = ['id' => 'new:'.$letter, 'attribute_id' => $index + 1, 'display_order' => $index, 'code_order' => $index, 'label_override' => null, 'input_type' => 'toggle', 'help_text' => null, 'default_configurator_option_id' => null, 'options' => $options];
    }

    return [['name' => 'Synthetic fixture', 'description' => null, 'context_schema' => ['territory' => [], 'application' => []], 'policy_overrides' => [], 'attributes' => $attributes, 'rules' => []], $canonicalAttributes, $canonicalOptions];
}

/** @return array<string, mixed> */
function fixtureMapping(string $id = 'map', string $driver = 'A', string $target = 'B'): array
{
    return ['id' => 'new:'.$id, 'label' => $id, 'kind' => 'Mapping', 'is_active' => true, 'priority' => 0, 'driver_configurator_attribute_id' => 'new:'.$driver, 'target_configurator_attribute_id' => 'new:'.$target, 'conditions' => [], 'effects' => [], 'sets' => [
        ['id' => 'new:'.$id.'set', 'label' => null, 'sort_order' => 0, 'source_option_ids' => ['new:'.$driver.'0'], 'target_option_ids' => ['new:'.$target.'1']],
    ]];
}

/** @return array<string, mixed> */
function fixtureCondition(string $id, string $attribute = 'A', string $option = 'A0'): array
{
    return ['id' => 'new:'.$id, 'source_kind' => 'SelectionOption', 'source_configurator_attribute_id' => 'new:'.$attribute, 'property_key' => null, 'context_dimension' => null, 'operator' => 'Equals', 'operand' => null, 'option_ids' => ['new:'.$option]];
}

/** @return array<string, mixed> */
function fixtureAdvanced(string $id, array $conditions, string $target = 'B', string $kind = 'HideAttribute', array $optionIds = [], ?string $value = null): array
{
    return ['id' => 'new:'.$id, 'label' => $id, 'kind' => 'Advanced', 'is_active' => true, 'priority' => 0, 'driver_configurator_attribute_id' => null, 'target_configurator_attribute_id' => null, 'conditions' => $conditions, 'sets' => [], 'effects' => [
        ['id' => 'new:'.$id.'effect', 'target_configurator_attribute_id' => 'new:'.$target, 'kind' => $kind, 'target_scope' => $optionIds === [] ? 'Attribute' : 'Options', 'option_ids' => $optionIds, 'display_value' => $value],
    ]];
}

/** @return array{Configurator, array<string, mixed>} */
function canonicalDefinitionFixture(): array
{
    $configurator = Configurator::factory()->create();
    $attributes = [];
    foreach (['A', 'B', 'C'] as $index => $letter) {
        $attribute = Attribute::factory()->create(['key' => $letter, 'label' => $letter]);
        $options = [];
        foreach ([0, 1] as $number) {
            $option = Option::factory()->for($attribute)->create(['code' => $letter.$number]);
            $options[] = ['id' => 'new:'.$letter.$number, 'option_id' => $option->id, 'display_order' => $number,
                'label_override' => null, 'display_value_override' => null, 'hint' => null, 'hidden_by_default' => false, 'disabled_by_default' => false];
        }
        $attributes[] = ['id' => 'new:'.$letter, 'attribute_id' => $attribute->id, 'display_order' => $index, 'code_order' => $index,
            'label_override' => null, 'input_type' => 'toggle', 'help_text' => null, 'default_configurator_option_id' => null, 'options' => $options];
    }

    return [$configurator, ['name' => 'Synthetic definition', 'description' => null, 'context_schema' => ['territory' => [], 'application' => []], 'policy_overrides' => [], 'attributes' => $attributes, 'rules' => []]];
}

function retainedCapabilityDefinition(?Closure $alter = null): ConfiguratorDefinition
{
    [$data, $attributes, $options] = compiledDefinitionFixture();
    $data['attributes'] = array_slice($data['attributes'], 0, 2);
    $attributes[1] = ['key' => 'body', 'label' => 'Body'];
    $attributes[2] = ['key' => 'seal', 'label' => 'Seal'];
    foreach ([1 => ['DI', 'Ductile Iron'], 2 => ['CS', 'Carbon Steel'], 3 => ['EP', 'EPDM'], 4 => ['VT', 'Viton']] as $id => [$code, $label]) {
        $options[$id]['code'] = $code;
        $options[$id]['label'] = $label;
    }
    if ($alter !== null) {
        $alter($data, $attributes, $options);
    }

    return app(ConfiguratorDefinitionCompiler::class)->compile($data, $attributes, $options);
}

function retainedContextDefinition(): ConfiguratorDefinition
{
    return retainedCapabilityDefinition(function (array &$data, array &$attributes, array &$options) {
        $options[5] = ['attribute_id' => 2, 'code' => 'NB', 'label' => 'NBR'];
        $data['attributes'][1]['options'][] = [...$data['attributes'][1]['options'][1], 'id' => 'new:B2', 'option_id' => 5, 'display_order' => 2];
        $data['attributes'][1]['options'][0]['label_override'] = 'EP';
        $data['attributes'][1]['options'][0]['hint'] = 'Base hint';
        $data['context_schema']['territory'] = [['value' => 'Europe', 'label' => 'Europe'], ['value' => 'USA', 'label' => 'USA']];
        $context = ['id' => 'new:europe', 'source_kind' => 'Territory', 'source_configurator_attribute_id' => null, 'property_key' => null, 'context_dimension' => 'territory', 'operator' => 'Equals', 'operand' => 'Europe', 'option_ids' => []];
        $rule = fixtureAdvanced('effects', [fixtureCondition('body'), $context], 'B', 'HideOptions', ['new:B2']);
        $rule['priority'] = 10;
        foreach (['DisableOptions' => [null, 'new:B1'], 'SetLabel' => ['EPDM Premium', 'new:B0'], 'SetDisplayValue' => ['EPDM Premium', 'new:B0'], 'SetHint' => ['Rule hint', 'new:B0']] as $kind => [$value, $id]) {
            $rule['effects'][] = ['id' => 'new:'.$kind, 'target_configurator_attribute_id' => 'new:B', 'kind' => $kind, 'target_scope' => 'Options', 'option_ids' => [$id], 'display_value' => $value];
        }
        $inactive = fixtureAdvanced('inactive', [], 'B', 'AllowOptions', ['new:B2']);
        $inactive['is_active'] = false;
        $inactive['priority'] = 100;
        $data['rules'] = [$rule, $inactive];
    });
}
