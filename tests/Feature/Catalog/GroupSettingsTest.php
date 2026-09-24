<?php

use App\Actions\SaveGroupSettings;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogDiscovery;
use App\Services\CatalogPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

function groupSettingsInput(array $changes = []): array
{
    return array_replace(['filters' => [], 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS], $changes);
}

test('group metadata saves atomically with stable identities and no writes on unchanged save', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10', 'Connection_Type' => 'Flange']]);
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '16', 'Connection_Type' => 'Threaded']]);
    $input = groupSettingsInput(['filters' => [['property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => [['value' => '16', 'label' => 'High'], ['value' => '10', 'label' => '']]]], 'sub_groups' => [['label' => '10 or 16', 'property_key' => 'Working_Pressure', 'allowed_values' => ['10', '16'], 'force_hide' => false]]]);
    app(SaveGroupSettings::class)->handle($this->actor, $group, $input);
    $filter = $group->filters()->sole();
    $preset = $group->subGroups()->sole();
    expect($filter->value_order)->toBe(['16', '10'])->and($filter->value_labels)->toBe([16 => 'High']);
    $input['filters'][0]['id'] = $filter->id;
    $input['sub_groups'][0]['id'] = $preset->id;
    $before = [$filter->getAttributes(), $preset->getAttributes(), $group->fresh()->getAttributes()];
    $this->travel(1)->minutes();
    app(SaveGroupSettings::class)->handle($this->actor, $group, $input);
    expect([$filter->fresh()->getAttributes(), $preset->fresh()->getAttributes(), $group->fresh()->getAttributes()])->toBe($before);
    $result = app(CatalogDiscovery::class)->prepare($group->id, []);
    expect($result->fields[0]['values'][0]['label'])->toBe('High');
});

test('invalid presets reject the whole metadata change while leaving the previous public result intact', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Original']);
    $input = groupSettingsInput(['filters' => [['id' => $filter->id, 'property_key' => 'Working_Pressure', 'label' => 'Changed', 'values' => []]], 'sub_groups' => [['label' => 'Stale', 'property_key' => 'Working_Pressure', 'allowed_values' => ['999'], 'force_hide' => false]]]);
    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))->toThrow(ValidationException::class);
    expect($filter->fresh()->label)->toBe('Original')->and($group->subGroups()->count())->toBe(0);
});

test('metadata rejects forged identities duplicate fields arbitrary paths and invalid result sizes', function (string $case) {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    $originalGroup = $group->fresh()->getAttributes();
    $input = groupSettingsInput();
    $row = ['property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => []];
    if ($case === 'foreign') {
        $row['id'] = GroupFilter::factory()->create()->id;
        $input['filters'] = [$row];
    }
    if ($case === 'duplicate') {
        $input['filters'] = [$row, $row];
    }
    if ($case === 'path') {
        $row['property_key'] = 'parts->Part1';
        $input['filters'] = [$row];
    }
    if ($case === 'cap') {
        $input['result_settings']['default_page_size'] = 101;
    }
    if ($case === 'allowlist') {
        $input['result_settings'] = ['default_page_size' => 10, 'allow_page_size_change' => true, 'page_size_options' => [1, 2]];
    }
    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))->toThrow(ValidationException::class);
    expect($group->filters()->count())->toBe(0)->and($group->fresh()->getAttributes())->toBe($originalGroup);
})->with(['foreign', 'duplicate', 'path', 'cap', 'allowlist']);

test('page settings retain access to all 23 products at sizes ten two and one', function () {
    $group = Group::factory()->create();
    Product::factory()->count(23)->for($group)->create();
    app(SaveGroupSettings::class)->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['default_page_size' => 10, 'allow_page_size_change' => true, 'page_size_options' => [1, 2, 10]]]));
    $service = app(CatalogDiscovery::class);
    $initial = $service->prepare($group->id, []);
    foreach ([10 => [10, 10, 3], 2 => [...array_fill(0, 11, 2), 1], 1 => array_fill(0, 23, 1)] as $size => $expectedPages) {
        $state = $service->prepare($group->id, $initial->state->toArray(), 'size', $size)->state->toArray();
        $ids = [];
        foreach ($expectedPages as $offset => $count) {
            $result = $service->prepare($group->id, $state, 'page', $offset + 1);
            expect($result->products->count())->toBe($count);
            $ids = [...$ids, ...$result->products->pluck('id')->all()];
        }
        expect(count(array_unique($ids)))->toBe(23);
    }
    app(SaveGroupSettings::class)->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['default_page_size' => 10, 'allow_page_size_change' => false, 'page_size_options' => [1, 2, 10]]]));
    expect($service->prepare($group->id, $state)->state->perPage)->toBe(10)->and($group->fresh()->result_settings['page_size_options'])->toBe([1, 2, 10]);
});

test('group settings authorize before accepting a submitted draft', function () {
    $group = Group::factory()->create();
    $outsider = User::factory()->create(['email' => 'visitor@example.test']);
    expect(fn () => app(SaveGroupSettings::class)->handle($outsider, $group, groupSettingsInput()))->toThrow(AuthorizationException::class);
});

test('card display settings save typed values and survive a pagination-only update', function (int|string $maximum) {
    $group = Group::factory()->create();
    $input = groupSettingsInput();
    $input['result_settings'] = array_replace($input['result_settings'], ['card_properties' => ['Model', 'Working_Pressure'], 'cards_per_row' => '3', 'max_results' => $maximum]);

    app(SaveGroupSettings::class)->handle($this->actor, $group, $input);
    app(SaveGroupSettings::class)->handle($this->actor, $group, groupSettingsInput(['result_settings' => [
        'default_page_size' => 2, 'allow_page_size_change' => false, 'page_size_options' => [1, 2, 10],
    ]]));

    expect($group->fresh()->result_settings)->toMatchArray([
        'card_properties' => ['Model', 'Working_Pressure'], 'cards_per_row' => 3,
        'max_results' => $maximum === 'all' ? 'all' : (int) $maximum, 'default_page_size' => 2,
    ]);
})->with(['all', '1', '24']);

test('invalid card display settings reject the entire draft', function (string $key, mixed $value) {
    $group = Group::factory()->create();
    $original = $group->fresh()->getAttributes();
    $input = groupSettingsInput();
    $input['result_settings'][$key] = $value;

    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))->toThrow(ValidationException::class);

    expect($group->fresh()->getAttributes())->toBe($original);
})->with([
    'unknown property' => ['card_properties', ['parts->Part1']],
    'duplicate property' => ['card_properties', ['Model', 'Model']],
    'properties must be a list' => ['card_properties', 'Model'],
    'zero columns' => ['cards_per_row', 0],
    'too many columns' => ['cards_per_row', 7],
    'zero threshold' => ['max_results', 0],
    'threshold above 24' => ['max_results', 25],
    'invalid threshold' => ['max_results', 'unlimited'],
]);

test('swapping filter properties keeps ids without transient unique conflicts', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10', 'Connection_Type' => 'Flange']]);
    $pressure = GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure']);
    $connection = GroupFilter::factory()->for($group)->create(['property_key' => 'Connection_Type']);
    app(SaveGroupSettings::class)->handle($this->actor, $group, groupSettingsInput(['filters' => [
        ['id' => $pressure->id, 'property_key' => 'Connection_Type', 'label' => 'Connection', 'values' => []],
        ['id' => $connection->id, 'property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => []],
    ]]));
    expect($pressure->fresh()->property_key)->toBe('Connection_Type')->and($connection->fresh()->property_key)->toBe('Working_Pressure');
});
