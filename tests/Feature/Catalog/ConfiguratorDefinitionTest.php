<?php

use App\Actions\SaveCanonicalOption;
use App\Actions\SaveConfiguratorDefinition;
use App\Models\Attribute;
use App\Models\ConfiguratorOption;
use App\Models\Group;
use App\Models\MappingSetSource;
use App\Models\Option;
use App\Models\RuleEffect;
use App\Models\User;
use App\Models\Value;
use App\Services\ConfiguratorDefinitionLoader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

test('canonical code validation preserves exact case and leading zeros', function () {
    $attribute = Attribute::factory()->create();
    foreach (['Aa', 'aa', '00'] as $code) {
        $saved = app(SaveCanonicalOption::class)->handle($this->actor, null, $attribute->id, Value::factory()->create()->id, $code);
        expect($saved->fresh()->code)->toBe($code);
    }
    expect(fn () => app(SaveCanonicalOption::class)->handle($this->actor, null, $attribute->id, Value::factory()->create()->id, 'Aa'))->toThrow(ValidationException::class);
});

test('canonical codes reject whitespace non ascii and invalid lengths', function (string $code) {
    expect(fn () => app(SaveCanonicalOption::class)->handle($this->actor, null, Attribute::factory()->create()->id, Value::factory()->create()->id, $code))->toThrow(ValidationException::class);
    expect(Option::count())->toBe(0);
})->with([' A', 'AA ', 'A', 'ABC', 'é0', 'A-', '']);

test('definition saves resolve staged defaults and keep local ids and stored defaults through reorder', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $saved = app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $included = $saved->attributes()->orderBy('display_order')->get();
    $first = $included->first();
    $default = $first->default_configurator_option_id;
    expect($included)->toHaveCount(3)->and($first->options()->whereKey($default)->sole()->option->code)->toBe('A0');
    app(SaveConfiguratorDefinition::class)->reorder($this->actor, $saved, 'attributes', $included->pluck('id')->reverse()->values()->all());
    expect($first->fresh()->default_configurator_option_id)->toBe($default)->and($saved->attributes()->count())->toBe(3);
    expect(fn () => app(SaveConfiguratorDefinition::class)->reorder($this->actor, $saved, 'code', [$first->id]))->toThrow(ValidationException::class);
});

test('complete saves retain surviving rule rows and membership references without rewriting unchanged data', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping(), fixtureAdvanced('advanced', [['id' => 'new:group', 'operator' => 'Any', 'conditions' => [fixtureCondition('condition', 'B', 'B1')]]], 'C', 'DisableOptions', ['new:C0'])];
    $data['rules'][1]['priority'] = 1;
    $action = app(SaveConfiguratorDefinition::class);
    $action->handle($this->actor, $configurator, $data);
    $draft = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $tables = ['configurators', 'configurator_attributes', 'configurator_options', 'configurator_rules', 'rule_condition_groups', 'rule_conditions', 'rule_condition_options', 'rule_effects', 'rule_effect_options', 'mapping_sets', 'mapping_set_sources', 'mapping_set_targets'];
    $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    $this->travel(1)->minutes();
    $action->handle($this->actor, $configurator, $draft);
    foreach ($tables as $table) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($before[$table]);
    }
});

test('removing referenced options or defaults rejects the entire draft and an explicit repair succeeds', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping()];
    $action = app(SaveConfiguratorDefinition::class);
    $action->handle($this->actor, $configurator, $data);
    $draft = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $original = $draft;
    $removedId = $draft['attributes'][0]['options'][0]['id'];
    array_shift($draft['attributes'][0]['options']);
    $draft['name'] = 'Should roll back';
    expect(fn () => $action->handle($this->actor, $configurator, $draft))->toThrow(ValidationException::class);
    $draft['attributes'][0]['default_configurator_option_id'] = $draft['attributes'][0]['options'][0]['id'];
    expect(fn () => $action->handle($this->actor, $configurator, $draft))->toThrow(ValidationException::class);
    expect($configurator->fresh()->name)->toBe($original['name'])->and(ConfiguratorOption::find($removedId))->not->toBeNull();
    $draft['rules'] = [];
    $action->handle($this->actor, $configurator, $draft);
    expect(ConfiguratorOption::find($removedId))->toBeNull()->and($configurator->rules()->count())->toBe(0);
});

test('duplicate reuses canonical identity remaps every local reference and starts unassigned', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $data['rules'] = [fixtureMapping(), fixtureAdvanced('advanced', [fixtureCondition('condition', 'B', 'B1')], 'C', 'HideOptions', ['new:C0'])];
    $data['rules'][1]['priority'] = 1;
    $action = app(SaveConfiguratorDefinition::class);
    $action->handle($this->actor, $configurator, $data);
    Group::factory()->create(['configurator_id' => $configurator->id]);
    $copy = $action->duplicate($this->actor, $configurator, 'Independent copy');
    $loader = app(ConfiguratorDefinitionLoader::class);
    $compiled = $loader->load($copy->id);
    expect($copy->groups()->count())->toBe(0)->and(Option::count())->toBe(6)->and($copy->attributes()->count())->toBe(3)->and($copy->rules()->count())->toBe(2);
    expect(array_intersect(array_keys($compiled->attributes), array_keys($loader->load($configurator->id)->attributes)))->toBe([]);
    foreach ($compiled->attributes as $attribute) {
        expect($attribute->options)->toHaveKey($attribute->defaultOptionId);
    }
    $foreign = $loader->draft($configurator->fresh());
    $foreign['attributes'][0]['id'] = (string) $copy->attributes()->first()->id;
    expect(fn () => $action->handle($this->actor, $configurator, $foreign))->toThrow(ValidationException::class);
});

test('mapping source moves preserve source association ids and can overlap target sets', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $rule = fixtureMapping();
    $rule['sets'][] = ['id' => 'new:second', 'label' => 'Second', 'sort_order' => 1, 'source_option_ids' => ['new:A1'], 'target_option_ids' => ['new:B1']];
    $data['rules'] = [$rule];
    $action = app(SaveConfiguratorDefinition::class);
    $action->handle($this->actor, $configurator, $data);
    $before = MappingSetSource::orderBy('id')->pluck('configurator_option_id', 'id')->all();
    $draft = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    [$draft['rules'][0]['sets'][0]['source_option_ids'], $draft['rules'][0]['sets'][1]['source_option_ids']] = [$draft['rules'][0]['sets'][1]['source_option_ids'], $draft['rules'][0]['sets'][0]['source_option_ids']];
    $action->handle($this->actor, $configurator, $draft);
    expect(MappingSetSource::orderBy('id')->pluck('configurator_option_id', 'id')->all())->toBe($before);
    expect(MappingSetSource::where('configurator_option_id', $draft['rules'][0]['sets'][0]['source_option_ids'][0])->sole()->mapping_set_id)->toBe((int) $draft['rules'][0]['sets'][0]['id']);
});

test('late persistence failure rolls back earlier inclusion changes', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $action = app(SaveConfiguratorDefinition::class);
    $action->handle($this->actor, $configurator, $data);
    $draft = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    $draft['attributes'][0]['label_override'] = 'Must roll back';
    $rule = fixtureAdvanced('late', [], 'C', 'SetLabel', [], 'Text');
    $rule['effects'][0]['target_configurator_attribute_id'] = $draft['attributes'][2]['id'];
    $draft['rules'] = [$rule];
    RuleEffect::saving(function (): void {
        throw new LogicException('Synthetic persistence failure');
    });
    try {
        expect(fn () => $action->handle($this->actor, $configurator, $draft))->toThrow(LogicException::class);
    } finally {
        RuleEffect::flushEventListeners();
    }
    expect($configurator->attributes()->orderBy('display_order')->first()->label_override)->toBeNull()->and($configurator->rules()->count())->toBe(0);
});

test('definition mutations require the existing catalog gate', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $outsider = User::factory()->create();
    expect(fn () => app(SaveConfiguratorDefinition::class)->handle($outsider, $configurator, $data))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveConfiguratorDefinition::class)->duplicate($outsider, $configurator, 'No'))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveConfiguratorDefinition::class)->reorder($outsider, $configurator, 'attributes', []))->toThrow(AuthorizationException::class);
    expect($configurator->attributes()->count())->toBe(0);
});
