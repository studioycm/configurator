<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Models\ConfiguratorRule;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Support\Facades\DB;

if (defined('CATALOG_MYSQL_TEST_RUN') && getenv('CATALOG_MYSQL_TEST_PROFILE') !== 'rules') {
    throw new RuntimeException('Writing Loader tests require the dedicated rules profile.');
}
require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

test('selected preloaded Rule serialization is exact and query free', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    [$owner, $graph] = canonicalDefinitionFixture();
    $graph['rules'] = [fixtureMapping(), [...fixtureAdvanced('advanced', [fixtureCondition('condition')], 'C'), 'priority' => 1]];
    app(SaveConfiguratorDefinition::class)->handle($actor, $owner, $graph);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $rows = $loader->draft($owner->fresh())['rules'];
    $rules = ConfiguratorRule::where('configurator_id', $owner->id)->with(['conditionGroups', 'conditions.optionReferences', 'effects.optionReferences', 'mappingSets.sources', 'mappingSets.targets'])->get();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        foreach ($rules as $rule) {
            expect($loader->rule($rule))->toBe(collect($rows)->firstWhere('id', (string) $rule->id));
        }
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
    $unloaded = ConfiguratorRule::findOrFail($rules[0]->id);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        expect(fn () => $loader->rule($unloaded))->toThrow(LogicException::class);
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
});

test('aggregate Loader query count is independent of Rule count with all child relations populated', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    [$owner, $graph] = canonicalDefinitionFixture();
    $graph['rules'] = [fixtureMapping(), [...fixtureAdvanced('advanced', [fixtureCondition('condition')], 'C'), 'priority' => 1]];
    $writer = app(SaveConfiguratorDefinition::class);
    $writer->handle($actor, $owner, $graph);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $countQueries = function () use ($loader, $owner): int {
        $fresh = $owner->fresh();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $loader->draft($fresh);

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    };
    $count = $countQueries();
    $graph = $loader->draft($owner->fresh());
    $map = [...$graph['rules'][0], 'id' => 'new:second', 'priority' => 2];
    $map['sets'][0]['id'] = 'new:second_set';
    $advanced = [...$graph['rules'][1], 'id' => 'new:third', 'priority' => 3];
    $advanced['conditions'][0]['id'] = 'new:third_condition';
    $advanced['effects'][0]['id'] = 'new:third_effect';
    $graph['rules'] = [...$graph['rules'], $map, $advanced];
    $writer->handle($actor, $owner, $graph);
    expect($countQueries())->toBe($count)->and($count)->toBe(11);
});

test('Loader preserves explicit Mapping and Advanced child projections and types', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    [$owner, $graph] = canonicalDefinitionFixture();
    $map = fixtureMapping();
    $map['sets'][0] += ['disallowed_target_behavior' => 'Hide'];
    $map['sets'][0]['label'] = 'first set';
    $map['sets'][] = [...$map['sets'][0], 'id' => 'new:second', 'label' => null, 'sort_order' => 4, 'source_option_ids' => ['new:A1'], 'target_option_ids' => ['new:B0'], 'disallowed_target_behavior' => 'Disable'];
    $advanced = fixtureAdvanced('advanced', [fixtureCondition('flat'), ['id' => 'new:any', 'operator' => 'Any', 'conditions' => [fixtureCondition('one'), fixtureCondition('two', 'A', 'A1')]]], 'C', 'SetHint', [], 'hint');
    $advanced['priority'] = 1;
    $advanced['effects'][] = [...$advanced['effects'][0], 'id' => 'new:second_effect', 'kind' => 'HideAttribute', 'display_value' => null];
    $graph['rules'] = [$map, $advanced];
    app(SaveConfiguratorDefinition::class)->handle($actor, $owner, $graph);
    $rules = ConfiguratorRule::where('configurator_id', $owner->id)->orderBy('id')->get();
    $attrs = DB::table('configurator_attributes')->where('configurator_id', $owner->id)->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all();
    $opts = DB::table('configurator_options')->whereIn('configurator_attribute_id', $attrs)->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all();
    $sets = DB::table('mapping_sets')->where('rule_id', $rules[0]->id)->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all();
    $conditions = DB::table('rule_conditions')->where('rule_id', $rules[1]->id)->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all();
    $group = (string) DB::table('rule_condition_groups')->where('rule_id', $rules[1]->id)->value('id');
    $effects = DB::table('rule_effects')->where('rule_id', $rules[1]->id)->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all();
    $predicate = fn (string $id, string $option): array => ['id' => $id, 'source_kind' => 'SelectionOption', 'property_key' => null, 'context_dimension' => null, 'operator' => 'Equals', 'operand' => null, 'source_configurator_attribute_id' => $attrs[0], 'option_ids' => [$option]];
    $expected = [
        ['id' => (string) $rules[0]->id, 'label' => 'map', 'kind' => 'Mapping', 'is_active' => true, 'priority' => 0, 'driver_configurator_attribute_id' => $attrs[0], 'target_configurator_attribute_id' => $attrs[1], 'conditions' => [], 'effects' => [], 'sets' => [
            ['id' => $sets[0], 'label' => 'first set', 'sort_order' => 0, 'disallowed_target_behavior' => 'Hide', 'source_option_ids' => [$opts[0]], 'target_option_ids' => [$opts[3]]],
            ['id' => $sets[1], 'label' => null, 'sort_order' => 4, 'disallowed_target_behavior' => 'Disable', 'source_option_ids' => [$opts[1]], 'target_option_ids' => [$opts[2]]],
        ]],
        ['id' => (string) $rules[1]->id, 'label' => 'advanced', 'kind' => 'Advanced', 'is_active' => true, 'priority' => 1, 'driver_configurator_attribute_id' => null, 'target_configurator_attribute_id' => null, 'conditions' => [$predicate($conditions[0], $opts[0]), ['id' => $group, 'operator' => 'Any', 'conditions' => [$predicate($conditions[1], $opts[0]), $predicate($conditions[2], $opts[1])]]], 'effects' => [
            ['id' => $effects[0], 'kind' => 'SetHint', 'target_scope' => 'Attribute', 'display_value' => 'hint', 'target_configurator_attribute_id' => $attrs[2], 'option_ids' => []],
            ['id' => $effects[1], 'kind' => 'HideAttribute', 'target_scope' => 'Attribute', 'display_value' => null, 'target_configurator_attribute_id' => $attrs[2], 'option_ids' => []],
        ], 'sets' => []],
    ];
    expect(app(ConfiguratorDefinitionLoader::class)->draft($owner->fresh())['rules'])->toBe($expected);
});
