<?php

use App\Actions\SaveGroupSettings;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogDiscovery;
use App\Services\CatalogPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

function groupSettingsInput(array $changes = []): array
{
    return array_replace(['filters' => [], 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS], $changes);
}

test('batch property selection appends missing filter drafts without replacing existing identities or labels', function () {
    $this->actingAs($this->actor);
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '16', 'Connection_Type' => 'Flange']]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Custom pressure', 'value_order' => ['16'], 'value_labels' => ['16' => 'High']]);
    $page = Livewire::test(EditGroup::class, ['record' => $group->id]);
    $before = $page->get('data.catalog_settings.filters');
    $page->set('data.filter_properties_to_add', ['Working_Pressure', 'Connection_Type']);
    $rows = array_values($page->get('data.catalog_settings.filters'));
    expect($rows)->toHaveCount(2)->and($rows[0])->toBe(array_values($before)[0])->and($rows[1]['property_key'])->toBe('Connection_Type')->and($rows[1]['values'])->toBe([['value' => 'Flange', 'label' => '']]);
    expect($filter->fresh()->label)->toBe('Custom pressure')->and($group->filters()->count())->toBe(1);
});

test('group metadata saves atomically with stable identities and no writes on unchanged save', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10', 'Connection_Type' => 'Flange']]);
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '16', 'Connection_Type' => 'Threaded']]);
    $input = groupSettingsInput(['filters' => [['property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => [['value' => '16', 'label' => 'High'], ['value' => '10', 'label' => '']]]], 'sub_groups' => [['label' => '10 or 16', 'property_key' => 'Working_Pressure', 'allowed_values' => ['10', '16']]]]);
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
    $input = groupSettingsInput(['filters' => [['id' => $filter->id, 'property_key' => 'Working_Pressure', 'label' => 'Changed', 'values' => []]], 'sub_groups' => [['label' => 'Stale', 'property_key' => 'Working_Pressure', 'allowed_values' => ['999']]]]);
    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))->toThrow(ValidationException::class);
    expect($filter->fresh()->label)->toBe('Original')->and($group->subGroups()->count())->toBe(0);
});

test('removed preset hide setting is rejected without saving group changes', function (bool $forceHide) {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['properties' => ['Working_Pressure' => '10']]);
    $input = groupSettingsInput(['sub_groups' => [[
        'label' => 'Pressure preset',
        'property_key' => 'Working_Pressure',
        'allowed_values' => ['10'],
        'force_hide' => $forceHide,
    ]]]);

    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseMissing('sub_groups', ['group_id' => $group->id]);
})->with([false, true]);

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

test('card delay accepts nonnegative steps of one hundred and rejects other values', function (mixed $delay, bool $valid) {
    $group = Group::factory()->create();
    $data = groupSettingsInput(['result_settings' => ['products_debounce_ms' => $delay]]);
    if (! $valid) {
        expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $data))->toThrow(ValidationException::class);
        expect((string) $group->fresh()->catalog_revision)->toBe('1');

        return;
    }
    app(SaveGroupSettings::class)->handle($this->actor, $group, $data);
    expect($group->fresh()->result_settings['products_debounce_ms'])->toBe((int) $delay);
})->with([[0, true], [100, true], [300, true], [-100, false], [50, false], [100.5, false], ['invalid', false]]);

test('missing appearance settings resolve to compact defaults without rewriting the group', function () {
    $group = Group::factory()->create();
    $before = $group->fresh()->getAttributes();

    expect(CatalogPolicy::resultSettings($group->result_settings))->toMatchArray([
        'card_only_differences' => true, 'card_show_labels' => false,
        'card_property_layout' => 'inline_space_between', 'card_property_columns' => 2,
        'card_padding_block' => 4, 'card_padding_inline' => 6,
    ]);
    expect($group->fresh()->getAttributes())->toBe($before);
});

test('appearance retains typed values and layout through partial updates and unchanged saves', function () {
    $group = Group::factory()->create();
    $action = app(SaveGroupSettings::class);
    $action->handle($this->actor, $group, groupSettingsInput(['result_settings' => [
        'card_only_differences' => '0', 'card_show_labels' => '1',
        'card_property_layout' => 'below_center', 'card_property_columns' => '1',
        'card_padding_block' => '0', 'card_padding_inline' => '20',
    ]]));
    $action->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['card_show_labels' => false]]));
    $revision = (string) $group->fresh()->catalog_revision;
    $before = $group->fresh()->getAttributes();
    $action->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['card_show_labels' => false]]));

    expect($group->fresh()->result_settings)->toMatchArray([
        'card_only_differences' => false, 'card_show_labels' => false,
        'card_property_layout' => 'below_center', 'card_property_columns' => 1,
        'card_padding_block' => 0, 'card_padding_inline' => 20,
        'default_page_size' => 10, 'page_size_options' => [1, 2, 10],
    ]);
    expect($revision)->toBe('3')->and((string) $group->fresh()->catalog_revision)->toBe($revision);
    expect($group->fresh()->getAttributes())->toBe($before);
});

test('saving implicit appearance defaults preserves the legacy group revision', function () {
    $group = Group::factory()->create(['result_settings' => ['max_results' => 6, 'cards_per_row' => 6]]);

    app(SaveGroupSettings::class)->handle($this->actor, $group, groupSettingsInput([
        'result_settings' => CatalogPolicy::resultSettings($group->result_settings),
    ]));

    expect((string) $group->fresh()->catalog_revision)->toBe('1');
    expect($group->fresh()->result_settings)->toMatchArray([
        'max_results' => 6, 'cards_per_row' => 6,
        'card_only_differences' => true, 'card_property_layout' => 'inline_space_between',
    ]);
});

test('invalid appearance rejects the complete group draft', function (string $key, mixed $value) {
    $group = Group::factory()->create();
    $filter = GroupFilter::factory()->for($group)->create(['label' => 'Saved label']);
    $before = $group->fresh()->getAttributes();
    $input = groupSettingsInput([
        'filters' => [['id' => $filter->id, 'property_key' => $filter->property_key, 'label' => 'Discard this change', 'values' => []]],
        'result_settings' => [$key => $value],
    ]);

    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($before);
    expect($filter->fresh()->label)->toBe('Saved label');
})->with([
    'differences is boolean' => ['card_only_differences', 'false'],
    'labels is boolean' => ['card_show_labels', 'yes'],
    'layout is bounded' => ['card_property_layout', 'custom'],
    'one or two property columns' => ['card_property_columns', 3],
    'integral property columns' => ['card_property_columns', 1.5],
    'nonnegative block padding' => ['card_padding_block', -1],
    'bounded block padding' => ['card_padding_block', 17],
    'integral inline padding' => ['card_padding_inline', 6.5],
    'bounded inline padding' => ['card_padding_inline', 21],
    'unknown appearance setting' => ['card_icon_mode', true],
]);
