<?php

use App\Models\Attribute;
use App\Models\CatalogContextSettings;
use App\Models\Configurator;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\Option;
use App\Models\RuleCondition;
use App\Models\RuleConditionGroup;
use App\Models\RuleEffect;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorRuleExchange;
use Illuminate\Support\Facades\DB;

function ruleExchangeAttribute(Configurator $configurator, string $key, string $semanticId, string $code): array
{
    $attribute = Attribute::factory()->create(['key' => $key, 'label' => 'שם זהה']);
    $included = ConfiguratorAttribute::factory()->for($configurator)->for($attribute)->create();
    $value = Value::factory()->create(['semantic_id' => $semanticId, 'label' => 'שם זהה']);
    $option = Option::factory()->for($attribute)->for($value)->create(['code' => $code]);

    return [$included, ConfiguratorOption::factory()->for($included, 'configuratorAttribute')->for($option)->create()];
}

test('saved Mapping rules export complete sets and gates with semantic identities and fixed core headers', function () {
    $configurator = Configurator::factory()->create();
    [$driver, $source] = ruleExchangeAttribute($configurator, 'body', 'stst316', 'Ss');
    [$target, $allowed] = ruleExchangeAttribute($configurator, 'bolt', 'duplex_5a', 'Du');
    $rule = ConfiguratorRule::factory()->for($configurator)->create(['kind' => 'Mapping', 'is_active' => false, 'driver_configurator_attribute_id' => $driver->id, 'target_configurator_attribute_id' => $target->id]);
    $gate = RuleCondition::factory()->for($rule, 'rule')->create(['operand' => ' 001|x\\y ']);
    $first = $rule->mappingSets()->create(['label' => 'קבוצה', 'sort_order' => 7, 'disallowed_target_behavior' => 'Hide']);
    $second = $rule->mappingSets()->create(['label' => null, 'sort_order' => 11, 'disallowed_target_behavior' => 'Disable']);
    foreach ([$first, $second] as $set) {
        $setSource = $set->is($first) ? $source : ConfiguratorOption::factory()->for($driver, 'configuratorAttribute')->create();
        $set->sources()->create(['rule_id' => $rule->id, 'configurator_option_id' => $setSource->id]);
        $set->targets()->create(['rule_id' => $rule->id, 'configurator_option_id' => $allowed->id]);
    }

    $snapshot = app(ConfiguratorRuleExchange::class)->snapshot($rule);
    $rows = $snapshot['rows'];

    expect(array_keys($rows[0]))->toBe(explode(',', 'format_version,import,operation,configurator_id,rule_key,rule_id,expected_revision,rule_label,rule_kind,enabled,priority,row_key,row_type,row_order,condition_group,group_operator,condition_source,condition_field,condition_operator,condition_values,target_attribute_key,effect_kind,target_scope,target_values,display_value,mapping_label,mapping_order,other_values'));
    expect(array_column($rows, 'row_type'))->toBe(['Condition', 'MappingSet', 'MappingSet']);
    expect($rows[0]['condition_values'])->toBe(' 001|x\\y ');
    expect($rows[0]['row_key'])->toBe('c_'.$gate->id);
    expect($rows[1])->toMatchArray(['format_version' => 'rules-rows-v1', 'import' => '0', 'operation' => 'Update', 'enabled' => '0', 'condition_source' => 'SelectionOption', 'condition_field' => 'body', 'condition_values' => 'stst316', 'target_attribute_key' => 'bolt', 'target_values' => 'duplex_5a', 'mapping_label' => 'קבוצה', 'mapping_order' => '7', 'other_values' => 'Hide']);
    expect($rows[2])->toMatchArray(['mapping_label' => '', 'mapping_order' => '11', 'other_values' => 'Disable', 'row_order' => '2']);
    $draft = app(ConfiguratorDefinitionLoader::class)->draft($configurator)['rules'][0];
    expect($rows[0]['expected_revision'])->toBe(hash('sha256', json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
    expect(array_column($snapshot['lookups'], 'value_semantic_id'))->toContain('stst316', 'duplex_5a');
});

test('Advanced rows retain every effect grouped predicates SelectionCode and exact Product strings', function () {
    $configurator = Configurator::factory()->create();
    [$attribute, $option] = ruleExchangeAttribute($configurator, 'body', 'first_id', '01');
    $rule = ConfiguratorRule::factory()->for($configurator)->create();
    RuleCondition::factory()->for($rule, 'rule')->create(['operator' => 'Equals', 'operand' => '']);
    RuleCondition::factory()->for($rule, 'rule')->create(['operator' => 'In', 'operand' => ['', 'a|b', 'c\\d'], 'sort_order' => 1]);
    $group = RuleConditionGroup::factory()->for($rule, 'rule')->create(['operator' => 'Any', 'sort_order' => 3]);
    $selection = RuleCondition::factory()->for($rule, 'rule')->create(['source_kind' => 'SelectionCode', 'source_configurator_attribute_id' => $attribute->id, 'condition_group_id' => $group->id, 'property_key' => null, 'operand' => null]);
    $selection->optionReferences()->create(['configurator_option_id' => $option->id]);
    RuleCondition::factory()->for($rule, 'rule')->create(['source_kind' => 'Territory', 'context_dimension' => 'territory', 'property_key' => null, 'operand' => 'ישראל', 'sort_order' => 2]);
    foreach (['AllowOptions', 'ExcludeOptions', 'HideOptions', 'DisableOptions', 'HideAttribute', 'SetLabel', 'SetDisplayValue', 'SetHint'] as $kind) {
        $effect = RuleEffect::factory()->for($rule, 'rule')->create(['target_configurator_attribute_id' => $attribute->id, 'kind' => $kind, 'target_scope' => $kind === 'HideAttribute' ? 'Attribute' : 'Options', 'display_value' => str_starts_with($kind, 'Set') ? "=כותרת\n 001 " : null]);
        if ($kind !== 'HideAttribute') {
            $effect->optionReferences()->create(['configurator_option_id' => $option->id]);
        }
    }

    $rows = app(ConfiguratorRuleExchange::class)->snapshot($rule)['rows'];

    expect($rows)->toHaveCount(12);
    expect($rows[0]['condition_values'])->toBe('');
    expect($rows[1]['condition_values'])->toBe('|a\\|b|c\\\\d');
    expect($rows[2])->toMatchArray(['condition_source' => 'Territory', 'condition_field' => 'territory', 'condition_values' => 'ישראל']);
    expect($rows[3])->toMatchArray(['condition_source' => 'SelectionCode', 'condition_values' => 'first_id', 'condition_group' => 'g_'.$group->id, 'group_operator' => 'Any']);
    expect(array_column(array_slice($rows, 4), 'effect_kind'))->toBe(['AllowOptions', 'ExcludeOptions', 'HideOptions', 'DisableOptions', 'HideAttribute', 'SetLabel', 'SetDisplayValue', 'SetHint']);
    expect($rows[8]['target_values'])->toBe('');
    expect($rows[9]['display_value'])->toBe("=כותרת\n 001 ");
});

test('list cells preserve escaped separators and reject malformed escapes', function () {
    $exchange = app(ConfiguratorRuleExchange::class);
    expect($exchange->encodeList(['', 'a|b', 'c\\d', ' 001 ']))->toBe('|a\\|b|c\\\\d| 001 ');
    expect($exchange->decodeList('|a\\|b|c\\\\d| 001 '))->toBe(['', 'a|b', 'c\\d', ' 001 ']);
    expect(fn () => $exchange->decodeList('bad\\x'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $exchange->decodeList('bad\\'))->toThrow(InvalidArgumentException::class);
});

test('helpers describe only the referenced Values and Lookups resolve local context choices and hidden globals', function () {
    $configurator = Configurator::factory()->create(['context_schema' => ['territory' => [['value' => 'LOCAL', 'label' => 'Local']], 'application' => []], 'hidden_context_options' => ['territory' => ['USA'], 'application' => []]]);
    [$attribute, $option] = ruleExchangeAttribute($configurator, 'body', 'selected_value', '01');
    $other = ConfiguratorOption::factory()->for($attribute, 'configuratorAttribute')->create();
    $rule = ConfiguratorRule::factory()->for($configurator)->create();
    $condition = RuleCondition::factory()->for($rule, 'rule')->create(['source_kind' => 'SelectionOption', 'source_configurator_attribute_id' => $attribute->id, 'property_key' => null, 'operand' => null]);
    $condition->optionReferences()->create(['configurator_option_id' => $option->id]);
    CatalogContextSettings::current()->update(['choices' => ['territory' => [['value' => 'USA', 'label' => 'United States']], 'application' => []]]);

    $snapshot = app(ConfiguratorRuleExchange::class)->snapshot($rule);

    expect($snapshot['helpers'][0]['helper_source_labels_snapshot'])->toContain('שם זהה', 'selected_value');
    expect($snapshot['helpers'][0]['helper_reference_codes_snapshot'])->toBe('body.selected_value = 01 (שם זהה)');
    expect(array_column($snapshot['lookups'], 'literal'))->toContain('LOCAL', 'HideOptions', 'SelectionCode')->not->toContain('USA');
    expect(array_column($snapshot['lookups'], 'field'))->toContain('condition_source', 'condition_operator', 'effect_kind');
});

test('rules in the same job reuse one consistent Configurator aggregate snapshot', function () {
    $rule = ConfiguratorRule::factory()->create();
    RuleCondition::factory()->for($rule, 'rule')->create();
    $second = ConfiguratorRule::factory()->create(['configurator_id' => $rule->configurator_id, 'priority' => 1]);
    RuleCondition::factory()->for($second, 'rule')->create();
    $exchange = app(ConfiguratorRuleExchange::class);
    DB::enableQueryLog();

    $exchange->snapshot($rule);
    $exchange->snapshot($second);

    $definitionReads = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "configurator_rules"'));
    DB::disableQueryLog();
    expect($definitionReads)->toHaveCount(1);
});

test('a missing semantic identity fails the whole snapshot instead of emitting ambiguous Lookups', function () {
    $configurator = Configurator::factory()->create();
    [$attribute, $option] = ruleExchangeAttribute($configurator, 'body', 'valid_id', '01');
    $rule = ConfiguratorRule::factory()->for($configurator)->create();
    RuleCondition::factory()->for($rule, 'rule')->create();
    DB::table('values')->where('id', $option->option->value_id)->update(['semantic_id' => '']);

    expect(fn () => app(ConfiguratorRuleExchange::class)->snapshot($rule))->toThrow(RuntimeException::class, 'semantic ID');
});
