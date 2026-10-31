<?php

use App\Livewire\Catalog\GroupShow;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCards;
use App\Services\CatalogProductComparison;
use App\Services\CatalogSnapshots;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{User,Group,Product,Product} */
function savedProductComparisonFixture(): array
{
    $actor = User::factory()->create();
    $keys = ['same', 'zero', 'leading', 'missing', 'invalid', 'markup', 'space', 'Case', '0', '001'];
    $group = Group::factory()->create(['result_settings' => ['card_properties' => $keys, 'editor_properties' => ['secret'], 'card_only_differences' => true, 'card_show_labels' => false]]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'zero', 'label' => 'Exact zero', 'sort_order' => 1]);
    GroupFilter::factory()->for($group)->create(['property_key' => 'same', 'label' => 'Same field', 'sort_order' => 0]);
    $a = Product::factory()->for($group)->create(['product_code' => 'COMPARE-A', 'profile_fields' => ['same' => 'equal', 'zero' => '0', 'leading' => '001', 'invalid' => true, 'markup' => '<private markup>', 'space' => ' x ', 'Case' => 'a', '0' => 'literal0', '001' => 'literal001', 'secret' => 'never exposed'], 'parts' => ['Part1' => 'never parts']]);
    $b = Product::factory()->for($group)->create(['product_code' => 'COMPARE-B', 'profile_fields' => ['same' => 'equal', 'zero' => '00', 'leading' => '1', 'missing' => '', 'invalid' => null, 'markup' => 'other', 'space' => 'x', 'Case' => 'A', '0' => 'literal0', '001' => 'literal001']]);

    return [$actor, $group, $a, $b];
}

test('comparison returns only configured saved properties with exact values Differences first and full Same context', function () {
    [$actor, $group, $a, $b] = savedProductComparisonFixture();
    $before = [$group->fresh()->getAttributes(), $a->fresh()->getAttributes(), $b->fresh()->getAttributes()];
    $result = app(CatalogProductComparison::class)->get($actor, $group->id, [(string) $b->id, (string) $a->id]);
    expect(array_column($result['products'], 'id'))->toBe([(string) $b->id, (string) $a->id])
        ->and(array_column($result['differences'], 'key'))->toBe(['zero', 'leading', 'markup', 'space', 'Case'])
        ->and(array_column($result['same'], 'key'))->toBe(['same', 'missing', 'invalid', '0', '001'])
        ->and($result['differences'][0])->toBe(['key' => 'zero', 'label' => 'Exact zero', 'values' => ['00', '0']])
        ->and($result['differences'][1]['values'])->toBe(['1', '001'])
        ->and($result['differences'][3]['values'])->toBe(['x', ' x '])
        ->and($result['same'][1]['values'])->toBe([null, null])
        ->and(json_encode($result))->not->toContain('never exposed', 'never parts', 'profile_fields', 'remembered', 'configuration');
    expect([$group->fresh()->getAttributes(), $a->fresh()->getAttributes(), $b->fresh()->getAttributes()])->toBe($before);
});

test('comparison fallback uses Group filter order and labels rather than editor fields and rereads facts and settings', function () {
    [$actor, $group, $a, $b] = savedProductComparisonFixture();
    $group->update(['result_settings' => ['editor_properties' => ['secret']]]);
    $result = app(CatalogProductComparison::class)->get($actor, $group->id, [$a->id, $b->id]);
    expect(array_column($result['differences'], 'key'))->toBe(['zero'])->and(array_column($result['same'], 'key'))->toBe(['same']);
    $b->update(['profile_fields' => ['same' => 'changed', 'zero' => '0']]);
    $result = app(CatalogProductComparison::class)->get($actor, $group->id, [$a->id, $b->id]);
    expect($result['differences'][0])->toBe(['key' => 'same', 'label' => 'Same field', 'values' => ['equal', 'changed']])
        ->and($result['same'][0]['key'])->toBe('zero');
    $group->filters()->delete();
    $result = app(CatalogProductComparison::class)->get($actor, $group->id, [$a->id, $b->id]);
    expect($result['differences'])->toBe([])->and($result['same'])->toBe([]);
});

test('comparison rejects malformed identity lists before reading saved facts', function (array $ids) {
    [$actor, $group] = savedProductComparisonFixture();
    expect(fn () => app(CatalogProductComparison::class)->get($actor, $group->id, $ids))->toThrow(ValidationException::class);
})->with([[[]], [['1']], [['1', '1']], [['01', '2']], [[true, 2]], [[1.0, 2]], [['1e0', '2']], [[-1, 2]], [['99999999999999999999999999', '2']], [['1', '2', '3']], [['first' => 1, 'second' => 2]]]);

test('comparison refuses unavailable owners and never returns foreign stale facts', function (string $change) {
    [$actor, $group, $a, $b] = savedProductComparisonFixture();
    app(CatalogProductComparison::class)->get($actor, $group->id, [$a->id, $b->id]);
    match ($change) {
        'inactive' => $b->update(['is_active' => false]),
        'deleted' => $b->delete(),
        'reassigned' => $b->update(['group_id' => Group::factory()->create()->id]),
        'branch' => Group::factory()->for($group, 'parent')->create(),
        'ancestry' => $group->update(['parent_id' => Group::factory()->create(['is_active' => false])->id]),
        'hidden_configurator' => $group->update(['configurator_id' => Configurator::factory()->create(['is_active' => false, 'disabled_group_behavior' => 'hide'])->id]),
    };
    try {
        app(CatalogProductComparison::class)->get($actor, $group->id, [$a->id, $b->id]);
        $this->fail('Unavailable comparison must fail.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404)->and($exception->getMessage())->not->toContain('never exposed', 'COMPARE-B');
    }
})->with(['inactive', 'deleted', 'reassigned', 'branch', 'ancestry', 'hidden_configurator']);

test('comparison rechecks actors and bounds queries independently of property count', function () {
    [$actor, $group, $a, $b] = savedProductComparisonFixture();
    DB::enableQueryLog();
    DB::flushQueryLog();
    app(CatalogProductComparison::class)->get($actor, $group->id, [$a->id, $b->id]);
    $selects = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));
    DB::disableQueryLog();
    expect(count($selects))->toBeLessThanOrEqual(6);
    $actor->delete();
    try {
        app(CatalogProductComparison::class)->get($actor, $group->id, [$a->id, $b->id]);
        $this->fail('Deleted actor must fail.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(401);
    }
});

test('native comparison command uses locked Group identity and JSON reply without replacing filter state', function () {
    [$actor, $group, $a, $b] = savedProductComparisonFixture();
    $this->actingAs($actor);
    $component = Livewire::test(GroupShow::class, ['group' => $group]);
    $componentId = $component->instance()->getId();
    $component->call('loadComparison', [(string) $a->id, (string) $b->id], 'comparison:1')
        ->assertReturned(fn (array $reply): bool => $reply['requestId'] === 'comparison:1' && $reply['componentId'] === $componentId && $reply['groupId'] === (string) $group->id && $reply['pairIds'] === [(string) $a->id, (string) $b->id])
        ->assertSet('groupId', (string) $group->id);
    expect($component->effects)->not->toHaveKey('html');
    $component->call('loadComparison', [$a->id, $b->id], ['bad'])->assertReturned(null);
    expect($component->effects['returnsMeta'][0]['errors'])->toHaveKey('requestId');
    $component->call('loadComparison', 'bad', 'comparison:2')->assertReturned(null);
    expect($component->effects['returnsMeta'][0]['errors'])->toHaveKey('productIds');
    $foreign = Product::factory()->create();
    $component->call('loadComparison', [$a->id, $foreign->id], 'comparison:3')->assertReturned(null);
    expect($component->effects['returnsMeta'][0]['status'])->toBe(404)->and($component->effects)->not->toHaveKey('html');
});

test('Group comparison controls stay outside the single Product link and dialog keeps semantic ordered sections', function () {
    [$actor, $group] = savedProductComparisonFixture();
    $this->actingAs($actor);
    $snapshot = app(CatalogSnapshots::class)->get($group->id);
    $cards = app(CatalogCards::class)->get($group->id, ['version' => 1, 'filters' => [], 'subGroupId' => null, 'precedence' => []], $snapshot->revision(), 'comparison-cards');
    $document = new DOMDocument;
    @$document->loadHTML(implode('', $cards['htmlChunks']));
    $xpath = new DOMXPath($document);
    expect($xpath->query('//article/a')->length)->toBe(2)
        ->and($xpath->query('//article/a//button')->length)->toBe(0)
        ->and($xpath->query('//article/button[@data-comparison-product]')->length)->toBe(2);
    $html = $this->get(route('catalog.groups.show', $group))->assertOk()->getContent();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//dialog[@data-comparison-dialog]')->length)->toBe(1)
        ->and($xpath->query('//dialog//table//thead/tr/th[@scope="col"]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-catalog-filters]')->length)->toBe(1);
    expect($html)->toContain(__('catalog.comparison.differences'), __('catalog.comparison.same'), 'catalogFilters', 'catalogProductComparison')
        ->not->toContain('wire:click="choose', 'catalog_attributes');
});
