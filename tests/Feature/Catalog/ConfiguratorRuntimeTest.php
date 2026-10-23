<?php

use App\Actions\SaveCanonicalOption;
use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\ConfiguratorPreview;
use App\Livewire\Catalog\ProductConfigurator;
use App\Models\Group;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

test('runtime loads trusted Product facts and current assignment and discards foreign prior state', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $condition = ['id' => 'new:pressure', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => 'Working_Pressure', 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '10', 'option_ids' => []];
    $data['rules'] = [fixtureAdvanced('pressure', [$condition], 'B', 'AllowOptions', ['new:B1'])];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10']]);
    $loader = app(ConfiguratorDefinitionLoader::class);
    $engine = app(ConfiguratorEngine::class);
    $result = $engine->evaluate($loader->forProduct($product->id, ['properties' => ['Working_Pressure' => 'forged'], 'configuration_code' => 'forged']));
    expect($result->configurationCode)->toBe('A0-B1-C0')->and($result->state())->not->toHaveKeys(['properties', 'configuration_code']);
    $copy = app(SaveConfiguratorDefinition::class)->duplicate($this->actor, $configurator, 'New assignment');
    $group->update(['configurator_id' => $copy->id]);
    $changed = $engine->evaluate($loader->forProduct($product->id, $result->state()));
    expect($changed->configuratorId)->toBe($copy->id)->and(array_intersect(array_keys($changed->selections), array_keys($result->selections)))->toBe([])->and(array_column($changed->diagnostics, 'code'))->toContain('definition_changed');
    $group->update(['configurator_id' => null]);
    $unassigned = $engine->evaluate($loader->forProduct($product->id, $changed->state()));
    expect($unassigned->configurationCode)->toBeNull()->and($unassigned->selections)->toBe([]);
});

test('each request observes canonical code and availability edits before accepting stale input', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create();
    $loader = app(ConfiguratorDefinitionLoader::class);
    $engine = app(ConfiguratorEngine::class);
    $first = $engine->evaluate($loader->forProduct($product->id));
    $attribute = $configurator->attributes()->orderBy('display_order')->first();
    $local = $attribute->options()->orderBy('display_order')->first();
    app(SaveCanonicalOption::class)->handle($this->actor, $local->option, $local->option->attribute_id, $local->option->value_id, '00');
    expect($engine->evaluate($loader->forProduct($product->id, $first->state()))->configurationCode)->toBe('00-B0-C0');
    $draft = $loader->draft($configurator->fresh());
    $draft['attributes'][0]['options'][0]['disabled_by_default'] = true;
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft);
    $updated = $engine->evaluate($loader->forProduct($product->id, $first->state(), ['kind' => 'SelectOption', 'attribute_id' => (string) $attribute->id, 'option_id' => (string) $local->id]));
    expect($updated->configurationCode)->toBe('A1-B0-C0')->and(array_column($updated->diagnostics, 'code'))->toContain('selection_rejected');
});

test('an invalid current definition withholds code without breaking the Product page boundary', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    DB::table('configurator_attributes')->where('configurator_id', $configurator->id)->update(['code_order' => 0]);
    $result = app(ConfiguratorEngine::class)->evaluate(app(ConfiguratorDefinitionLoader::class)->forProduct($product->id));
    expect($result->isComplete)->toBeFalse()->and($result->configurationCode)->toBeNull()->and(array_column($result->diagnostics, 'code'))->toContain('invalid_definition');
});

test('rules read all profile fields independently of the groups discovery filters', function () {
    [$configurator, $data] = canonicalDefinitionFixture();
    $key = 'custom.other"field';
    $condition = ['id' => 'new:custom', 'source_kind' => 'ProductProperty', 'source_configurator_attribute_id' => null, 'property_key' => $key, 'context_dimension' => null, 'operator' => 'Equals', 'operand' => '0', 'option_ids' => []];
    $data['rules'] = [fixtureAdvanced('custom', [$condition], 'B', 'AllowOptions', ['new:B1'])];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $data);
    $group = Group::factory()->create(['configurator_id' => $configurator->id]);
    $product = Product::factory()->for($group)->create(['profile_fields' => [$key => '0']]);

    $input = app(ConfiguratorDefinitionLoader::class)->forProduct($product->id, ['profile_fields' => [$key => 'forged']]);
    $result = app(ConfiguratorEngine::class)->evaluate($input);

    expect($group->filters()->count())->toBe(0)->and($input->properties)->toBe([$key => '0'])
        ->and($result->configurationCode)->toBe('A0-B1-C0')->and($result->state())->not->toHaveKeys(['profile_fields', 'properties']);
});

/** @return array{DOMDocument, DOMXPath} */
function catalogRuntimeControlDocument(string $html): array
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return [$document, new DOMXPath($document)];
}

test('catalog native select and radio controls preserve effective escaped text and hide default metadata', function () {
    $this->actingAs($this->actor);
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['attributes'][0]['input_type'] = 'select';
    $draft['attributes'][0]['label_override'] = 'Attribute <script>unsafe()</script>';
    $draft['attributes'][1]['options'][1]['disabled_by_default'] = true;
    $hidden = Option::factory()->create(['attribute_id' => $draft['attributes'][1]['attribute_id'], 'code' => 'BH']);
    $draft['attributes'][1]['options'][] = [...$draft['attributes'][1]['options'][1], 'id' => 'new:BH', 'option_id' => $hidden->id, 'hidden_by_default' => true, 'display_order' => 2];
    $draft['rules'] = [fixtureAdvanced('label', [], 'A', 'SetLabel', ['new:A0'], 'Effective <b>label</b>'), fixtureAdvanced('hint', [], 'A', 'SetHint', ['new:A0'], 'Effective hint'), fixtureAdvanced('display', [], 'A', 'SetDisplayValue', ['new:A0'], 'Effective display'), fixtureAdvanced('hide-c', [], 'C')];
    foreach ($draft['rules'] as $index => &$rule) {
        $rule['priority'] = $index;
    }
    unset($rule);
    Option::findOrFail($draft['attributes'][0]['options'][0]['option_id'])->value->update(['semantic_id' => 'private_a0_identity']);
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft);
    $product = Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());

    $component = Livewire::test(ProductConfigurator::class, ['productId' => $product->id])
        ->assertSee('Effective <b>label</b>')->assertSee('Effective hint')->assertSee('Effective display')->assertDontSee('<script>unsafe()</script>', false)->assertDontSee('private_a0_identity');
    [, $xpath] = catalogRuntimeControlDocument($component->html());
    $selects = $xpath->query('//select');
    $radios = $xpath->query('//input[@type="radio"]');

    expect($selects)->toHaveCount(1);
    expect($selects->item(0)->textContent)->toContain('Effective <b>label</b>')->not->toContain(' · A0');
    expect($radios)->toHaveCount(2);
    expect($radios->item(0)->getAttribute('name'))->toBe('configuration-'.$component->instance()->getId().'-'.$saved['attributes'][1]['id']);
    expect($radios->item(1)->hasAttribute('disabled'))->toBeTrue();
    expect($xpath->query('//script'))->toHaveCount(0);

    $component->call('selectOption', $saved['attributes'][0]['id'], $saved['attributes'][0]['options'][1]['id'])->assertSee('A1-B0');
    $component->call('selectOption', $saved['attributes'][1]['id'], $saved['attributes'][1]['options'][1]['id'])->assertSet('runtime.selections.'.$saved['attributes'][1]['id'], $saved['attributes'][1]['options'][0]['id']);
});

test('Preview catalog adapter shares select and radio behavior while all-state identities remain distinct', function () {
    $this->actingAs($this->actor);
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['attributes'][0]['input_type'] = 'select';
    $draft['attributes'][0]['options'][1]['hidden_by_default'] = true;
    $draft['attributes'][1]['options'][1]['disabled_by_default'] = true;
    $draft['rules'] = [fixtureAdvanced('hide-c', [], 'C')];
    app(SaveConfiguratorDefinition::class)->handle($this->actor, $configurator, $draft);
    Product::factory()->for(Group::factory()->create(['configurator_id' => $configurator->id]))->create();
    $saved = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());

    $component = Livewire::test(ConfiguratorPreview::class, ['configuratorId' => $configurator->id]);
    [, $xpath] = catalogRuntimeControlDocument($component->html());
    $catalog = '//div[@*[name()="wire:key"]="preview-catalog-presentation"]';
    $all = '//div[@*[name()="wire:key"]="preview-all-presentation"]';

    expect($xpath->query($catalog.'//select/option'))->toHaveCount(1);
    expect($xpath->query($catalog.'//input[@type="radio"]'))->toHaveCount(2);
    expect($xpath->query($all.'//input[@type="radio"]'))->toHaveCount(6);
    expect($xpath->query($catalog.'//input[@type="radio"]')->item(0)->getAttribute('name'))->toStartWith('preview-catalog-');
    expect($xpath->query($all.'//input[@type="radio"]')->item(0)->getAttribute('name'))->toStartWith('preview-all-');
    expect($xpath->query($catalog.'//input[@type="radio"]')->item(1)->hasAttribute('disabled'))->toBeTrue();
    expect($component->get('previewView.inspection.attributes.'.$saved['attributes'][2]['id'].'.state'))->toBe('hidden');

    $component->call('selectOption', $saved['attributes'][0]['id'], $saved['attributes'][0]['options'][1]['id'])
        ->assertSet('runtime.selections.'.$saved['attributes'][0]['id'], $saved['attributes'][0]['options'][0]['id']);
});
