<?php

use App\Actions\SaveConfiguratorDefinition;
use App\Livewire\Catalog\ItemListDrawer;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\User;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ItemListOrdering;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once dirname(__DIR__, 2).'/ConfiguratorFixtures.php';

beforeEach(function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
});

test('drawer moves and drag use the complete owner list while preserving search and scope', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $ids = $configurator->attributes()->orderBy('display_order')->pluck('id')->all();
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'configurator-attributes', 'parentId' => $configurator->id])
        ->set('tableSearch', 'B')->call('moveItem', $ids[1], -1)->assertHasNoErrors()
        ->assertSet('tableSearch', 'B')->call('toggleTableReordering')->assertSet('isTableReordering', true);
    expect($drawer->instance()->getTableRecords())->toHaveCount(3);
    $drawer->call('reorderTable', array_reverse($ids))->assertHasNoErrors()
        ->call('toggleTableReordering')->assertSet('tableSearch', 'B')->assertSet('isTableReordering', false);
    expect($configurator->attributes()->orderBy('display_order')->pluck('id')->all())->toBe(array_reverse($ids));
    $drawer->call('reorderTable', [$ids[0]])->assertHasErrors('order');
    expect($configurator->attributes()->orderBy('display_order')->pluck('id')->all())->toBe(array_reverse($ids));
});

test('option order preserves defaults and rejects foreign rows', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attributes = $configurator->attributes()->orderBy('display_order')->get();
    $owner = $attributes[0];
    $ids = $owner->options()->orderBy('display_order')->pluck('id')->all();
    $default = $owner->default_configurator_option_id;
    $service = app(ItemListOrdering::class);
    $service->reorder(auth()->user(), 'inclusion-options', $owner->id, array_reverse($ids));
    expect($owner->options()->orderBy('display_order')->pluck('id')->all())->toBe(array_reverse($ids))
        ->and($owner->fresh()->default_configurator_option_id)->toBe($default);
    expect(fn () => $service->move(auth()->user(), 'inclusion-options', $owner->id, $attributes[1]->options()->first()->id, -1))->toThrow(ValidationException::class);
});

test('mapping order preserves set identities memberships and rejects incomplete and duplicate orders', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $rule = fixtureMapping();
    $rule['sets'][] = [...$rule['sets'][0], 'id' => 'new:second-set', 'sort_order' => 1, 'source_option_ids' => ['new:A1']];
    $draft['rules'] = [$rule];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $rule = $configurator->rules()->first();
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator)['rules'][0]['sets'];
    $ids = array_column($before, 'id');
    $service = app(ItemListOrdering::class);
    $service->reorder(auth()->user(), 'rule-mappings', $rule->id, array_reverse($ids));
    $after = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh())['rules'][0]['sets'];
    foreach ($after as $set) {
        $previous = collect($before)->firstWhere('id', $set['id']);
        expect($set['source_option_ids'])->toBe($previous['source_option_ids'])->and($set['target_option_ids'])->toBe($previous['target_option_ids']);
    }
    expect(array_column($after, 'id'))->toBe(array_reverse($ids));
    foreach ([[$ids[0]], [$ids[0], $ids[0]], [$ids[0], 'foreign']] as $invalid) {
        expect(fn () => $service->reorder(auth()->user(), 'rule-mappings', $rule->id, $invalid))->toThrow(ValidationException::class);
    }
});

test('card ordering changes only the stored selected properties and shows complete array rows during drag', function () {
    $settings = ['card_properties' => ['Model', 'WT', 'B'], 'cards_per_row' => 3, 'custom_future_setting' => ['keep' => true]];
    $group = Group::factory()->create(['result_settings' => $settings]);
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'group-card-properties', 'parentId' => $group->id])
        ->set('tableSearch', 'WT')->call('toggleTableReordering');
    expect($drawer->instance()->getTableRecords())->toHaveCount(3);
    $drawer->call('reorderTable', ['B', 'WT', 'Model'])->assertHasNoErrors()->call('toggleTableReordering')->assertSet('tableSearch', 'WT');
    expect($group->fresh()->result_settings)->toBe([...$settings, 'card_properties' => ['B', 'WT', 'Model']]);
    $drawer->call('reorderTable', ['B', 'WT'])->assertHasErrors('order');
    $drawer->call('moveItem', 'Model', -1)->assertHasNoErrors();
    expect($group->fresh()->result_settings['card_properties'])->toBe(['B', 'Model', 'WT']);
});

test('mixed-owner lists have no ordering controls and ordering requires catalog permission', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $attribute = $configurator->attributes()->first();
    $drawer = Livewire::test(ItemListDrawer::class, ['listKey' => 'attribute-inclusions', 'parentId' => $attribute->attribute_id]);
    expect($drawer->instance()->rowOrderingActions())->toBe([])->and($drawer->instance()->getTable()->isReorderable())->toBeFalse();
    $this->actingAs(User::factory()->create());
    expect(fn () => app(ItemListOrdering::class)->reorder(auth()->user(), 'configurator-attributes', $configurator->id, []))->toThrow(AuthorizationException::class);
});

test('Rule ordering uses descending priority and leaves complete definitions intact', function () {
    [$configurator, $draft] = canonicalDefinitionFixture();
    $draft['rules'] = [fixtureMapping('first'), [...fixtureMapping('second'), 'priority' => 1]];
    app(SaveConfiguratorDefinition::class)->handle(auth()->user(), $configurator, $draft);
    $service = app(ItemListOrdering::class);
    $ids = $service->ids(auth()->user(), 'configurator-rules', $configurator->id);
    $before = app(ConfiguratorDefinitionLoader::class)->draft($configurator);
    $service->reorder(auth()->user(), 'configurator-rules', $configurator->id, array_reverse($ids));
    expect($service->ids(auth()->user(), 'configurator-rules', $configurator->id))->toBe(array_reverse($ids));
    $after = app(ConfiguratorDefinitionLoader::class)->draft($configurator->fresh());
    expect($after['attributes'])->toBe($before['attributes']);
    foreach ($after['rules'] as $rule) {
        $previous = collect($before['rules'])->firstWhere('id', $rule['id']);
        unset($previous['priority'], $rule['priority']);
        expect($rule)->toBe($previous);
    }
});

test('card order rejects stale sets and does not materialize an empty fallback or change related settings', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['Model', 'WT']]]);
    $filter = GroupFilter::factory()->for($group)->create();
    $filterBefore = $filter->fresh()->getAttributes();
    $revision = (int) $group->fresh()->catalog_revision;
    $service = app(ItemListOrdering::class);
    $service->reorder(auth()->user(), 'group-card-properties', $group->id, ['WT', 'Model']);
    expect($filter->fresh()->getAttributes())->toBe($filterBefore)->and((int) $group->fresh()->catalog_revision)->toBe($revision + 1);
    $group->update(['result_settings' => ['card_properties' => []]]);
    expect(fn () => $service->reorder(auth()->user(), 'group-card-properties', $group->id, ['Model', 'WT']))->toThrow(ValidationException::class);
    $service->reorder(auth()->user(), 'group-card-properties', $group->id, []);
    expect($group->fresh()->result_settings)->toBe(['card_properties' => []]);
});
