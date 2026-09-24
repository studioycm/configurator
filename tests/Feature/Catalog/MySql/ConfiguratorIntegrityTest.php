<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Models\Attribute;
use App\Models\ConfiguratorAttribute;
use App\Models\ConfiguratorOption;
use App\Models\ConfiguratorRule;
use App\Models\MappingSet;
use App\Models\MappingSetSource;
use App\Models\Option;
use App\Models\RuleCondition;
use App\Models\RuleConditionGroup;
use Illuminate\Database\QueryException;

test('mysql enforces exact canonical code identity and rejects invalid persisted codes', function () {
    $attribute = Attribute::factory()->create();
    foreach (['Aa', 'aa', '00'] as $code) {
        Option::factory()->for($attribute)->create(['code' => $code]);
    }
    expect(Option::orderBy('id')->pluck('code')->all())->toBe(['Aa', 'aa', '00']);
    expect(fn () => Option::factory()->for($attribute)->create(['code' => 'Aa']))->toThrow(QueryException::class);

});

test('mysql default membership foreign key rejects an option from another inclusion', function () {
    $first = ConfiguratorAttribute::factory()->create();
    $second = ConfiguratorAttribute::factory()->create();
    $option = ConfiguratorOption::factory()->for($second, 'configuratorAttribute')->create();
    expect(fn () => $first->update(['default_configurator_option_id' => $option->id]))->toThrow(QueryException::class);
});

test('mysql rejects malformed canonical code at persistence', function (string $code) {
    expect(fn () => Option::factory()->create(['code' => $code]))->toThrow(QueryException::class);
})->with([' A', 'A', 'AA ', 'é0', 'A-']);

test('mysql restricts canonical removal and cross rule ownership', function () {
    $inclusion = ConfiguratorAttribute::factory()->create();
    $option = ConfiguratorOption::factory()->for($inclusion, 'configuratorAttribute')->create();
    expect(fn () => $option->option->delete())->toThrow(QueryException::class);
    $rule = ConfiguratorRule::factory()->create();
    $other = ConfiguratorRule::factory()->create();
    $group = RuleConditionGroup::factory()->create(['rule_id' => $rule->id]);
    expect(fn () => RuleCondition::factory()->create(['rule_id' => $other->id, 'condition_group_id' => $group->id]))->toThrow(QueryException::class);
    $set = MappingSet::factory()->create(['rule_id' => $rule->id]);
    expect(fn () => MappingSetSource::factory()->create(['mapping_set_id' => $set->id, 'rule_id' => $other->id, 'configurator_option_id' => $option->id]))->toThrow(QueryException::class);
});
