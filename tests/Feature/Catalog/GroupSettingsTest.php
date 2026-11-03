<?php

use App\Actions\SaveCatalogGroup;
use App\Actions\SaveGroupSettings;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\GroupTagCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogDiscovery;
use App\Services\CatalogPolicy;
use App\Services\CatalogPresentation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->create(['email' => 'ycm@data4.work']);
});

test('legacy settings saves preserve raw presentation and future metadata through the real Group editor', function () {
    $raw = ['card_show_labels' => false, 'products_debounce_ms' => 0, 'future_root' => ['enabled' => false, 'zero' => 0],
        'catalog_presentation' => ['group_card_content' => [], 'group_card_attribute_keys' => ['application'], 'group_card_attribute_labels' => false, 'product_image_placement' => 'right', 'future_key' => ['x' => 0]]];
    $group = Group::factory()->create(['result_settings' => $raw, 'catalog_attributes' => ['application' => [' 001 ']]]);
    app(SaveGroupSettings::class)->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['cards_per_row' => 2]]));
    expect($group->fresh()->result_settings)->toMatchArray($raw);
    $before = $group->fresh()->result_settings;
    $this->actingAs($this->actor);
    Livewire::test(EditGroup::class, ['record' => $group->id])->set('data.name', 'Renamed without presentation edits')->call('save')->assertHasNoFormErrors();
    expect($group->fresh()->result_settings)->toBe($before);
    expect($group->fresh()->catalog_attributes)->toBe(['application' => [' 001 ']]);
});

test('full presentation replacement removes omitted known overrides while retaining future keys and explicit inherited values', function () {
    $service = app(CatalogPresentation::class);
    $group = Group::factory()->create(['result_settings' => ['future_root' => false, 'catalog_presentation' => [
        'group_image_placement' => 'right', 'product_image_scale' => 'large', 'future_key' => ['zero' => 0],
    ]]]);
    $writer = app(SaveGroupSettings::class);
    $group->refresh();
    $revision = $group->catalog_revision;
    $writer->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['catalog_presentation' => ['group_image_placement' => 'left', 'group_card_content' => []]]]), $service->groupFingerprint($group));
    $saved = $group->fresh();
    expect($saved->result_settings['catalog_presentation'])->toBe(['future_key' => ['zero' => 0], 'group_image_placement' => 'left', 'group_card_content' => []]);
    expect($saved->result_settings['future_root'])->toBeFalse();
    expect((int) $saved->catalog_revision)->toBe($revision + 1);
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $writer->handle($this->actor, $saved, groupSettingsInput(['result_settings' => ['catalog_presentation' => ['group_card_content' => [], 'group_image_placement' => 'left']]]), $service->groupFingerprint($saved));
    expect($writes)->toBeEmpty();
    expect($saved->fresh()->getAttributes())->toBe($saved->getAttributes());
});

test('narrow overrides merge reset and preserve exact category order without incidental legacy changes', function () {
    $service = app(CatalogPresentation::class);
    $writer = app(SaveGroupSettings::class);
    $categories = GroupTagCategory::factory()->count(2)->create();
    $group = Group::factory()->create(['result_settings' => ['card_show_labels' => false, 'products_debounce_ms' => 0,
        'catalog_presentation' => ['group_image_placement' => 'right', 'future_key' => false]]]);
    $group->refresh();
    $writer->patchPresentation($this->actor, $group, ['catalog_presentation' => ['group_card_tag_categories' => [$categories[1]->id, $categories[0]->id], 'group_card_content' => []]], ['group_image_placement'], $service->groupFingerprint($group));
    $saved = $group->fresh();
    expect($saved->result_settings)->toBe(['card_show_labels' => false, 'products_debounce_ms' => 0,
        'catalog_presentation' => ['future_key' => false, 'group_card_content' => [], 'group_card_tag_categories' => [$categories[1]->id, $categories[0]->id]]]);
    expect((int) $saved->catalog_revision)->toBe($group->catalog_revision + 1);
    $writer->patchPresentation($this->actor, $saved, [], ['group_card_tag_categories', 'group_card_content'], $service->groupFingerprint($saved));
    expect($saved->fresh()->result_settings['catalog_presentation'])->toBe(['future_key' => false]);
});

test('presentation writes reject stale missing malformed and unowned intent atomically', function () {
    $service = app(CatalogPresentation::class);
    $writer = app(SaveGroupSettings::class);
    $group = Group::factory()->create(['result_settings' => ['future' => 0]]);
    $group->refresh();
    $before = $group->getAttributes();
    $fingerprint = $service->groupFingerprint($group);
    $cases = [
        [['catalog_presentation' => ['group_image_placement' => 'right']], [], null],
        [['catalog_presentation' => ['group_image_placement' => 'right']], [], 'stale'],
        [['catalog_presentation' => null], [], $fingerprint],
        [['catalog_presentation' => ['unknown' => true]], [], $fingerprint],
        [['catalog_presentation' => ['group_card_content' => null]], [], $fingerprint],
        [['catalog_presentation' => ['group_card_tag_categories' => ['1']]], [], $fingerprint],
        [['catalog_presentation' => ['group_card_tag_categories' => [PHP_INT_MAX]]], [], $fingerprint],
        [[], ['unknown'], $fingerprint],
        [['catalog_presentation' => ['group_image_placement' => 'right']], ['group_image_placement'], $fingerprint],
    ];
    foreach ($cases as [$changes, $remove, $expected]) {
        expect(fn () => $writer->patchPresentation($this->actor, $group, $changes, $remove, $expected))->toThrow(ValidationException::class);
        expect($group->fresh()->getAttributes())->toBe($before);
    }
    expect(fn () => $writer->patchPresentation(User::factory()->create(), $group, ['catalog_presentation' => ['group_card_content' => []]], [], $fingerprint))->toThrow(AuthorizationException::class);
    $writer->patchPresentation($this->actor, $group, ['catalog_presentation' => ['group_card_content' => []]], [], $fingerprint);
    expect(fn () => $writer->patchPresentation($this->actor, $group, ['catalog_presentation' => ['group_card_content' => ['tags']]], [], $fingerprint))->toThrow(ValidationException::class);
    expect($group->fresh()->result_settings['catalog_presentation']['group_card_content'])->toBe([]);
});

test('physical branches reject Product image overrides but allow explicit cleanup and Group display overrides', function () {
    $service = app(CatalogPresentation::class);
    $writer = app(SaveGroupSettings::class);
    $parent = Group::factory()->create(['result_settings' => ['catalog_presentation' => ['product_image_scale' => 'large']]]);
    Group::factory()->create(['parent_id' => $parent->id]);
    $parent->refresh();
    foreach (['product_image_scale' => 'small', 'product_image_placement' => 'above'] as $key => $value) {
        expect(fn () => $writer->patchPresentation($this->actor, $parent, ['catalog_presentation' => [$key => $value]], [], $service->groupFingerprint($parent)))->toThrow(ValidationException::class);
    }
    $writer->patchPresentation($this->actor, $parent, ['catalog_presentation' => ['group_card_content' => []]], ['product_image_scale'], $service->groupFingerprint($parent));
    expect($parent->fresh()->result_settings['catalog_presentation'])->toBe(['group_card_content' => []]);
});

function groupSettingsInput(array $changes = []): array
{
    return array_replace(['filters' => [], 'sub_groups' => [], 'result_settings' => CatalogPolicy::RESULT_SETTINGS], $changes);
}

test('presentation patches preserve omitted raw settings and exact fields with false zero empty and no-op values', function () {
    $group = Group::factory()->create(['result_settings' => ['card_properties' => ['0', '001', '1', 'literal.field'], 'unknown_setting' => ['keep' => true]]]);
    $action = app(SaveGroupSettings::class);
    $before = $group->fresh()->getAttributes();
    $action->patchPresentation($this->actor, $group, []);
    expect($group->fresh()->getAttributes())->toBe($before);
    $action->patchPresentation($this->actor, $group, ['card_properties' => [], 'card_show_labels' => false, 'products_debounce_ms' => '0']);
    $expected = ['card_properties' => [], 'unknown_setting' => ['keep' => true], 'card_show_labels' => false, 'products_debounce_ms' => 0];
    $actual = $group->fresh()->result_settings;
    ksort($expected);
    ksort($actual);
    expect($actual)->toBe($expected);
    $saved = $group->fresh()->getAttributes();
    $action->patchPresentation($this->actor, $group, ['card_properties' => [], 'card_show_labels' => false, 'products_debounce_ms' => 0]);
    expect($group->fresh()->getAttributes())->toBe($saved);
    expect(fn () => $action->patchPresentation($this->actor, $group, ['unknown_setting' => []]))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($saved);
    expect(fn () => $action->patchPresentation(User::factory()->create(), $group, ['card_show_labels' => true]))->toThrow(AuthorizationException::class);
});

test('a presentation patch checks pagination relationships only when it supplies pagination changes', function () {
    $group = Group::factory()->create(['result_settings' => ['default_page_size' => 10, 'allow_page_size_change' => true, 'page_size_options' => [1, 2]]]);
    $action = app(SaveGroupSettings::class);
    $action->patchPresentation($this->actor, $group, ['cards_per_row' => 2]);
    expect($group->fresh()->result_settings['page_size_options'])->toBe([1, 2]);
    $before = $group->fresh()->getAttributes();
    expect(fn () => $action->patchPresentation($this->actor, $group, ['default_page_size' => 3]))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($before);
    $action->patchPresentation($this->actor, $group, ['default_page_size' => '2']);
    expect($group->fresh()->result_settings['default_page_size'])->toBe(2);
});

test('a narrow patch materializing an implicit presentation default retains the existing revision behavior', function () {
    $group = Group::factory()->create(['result_settings' => []]);
    $revision = (string) $group->fresh()->catalog_revision;
    app(SaveGroupSettings::class)->patchPresentation($this->actor, $group, ['card_show_labels' => false]);
    expect($group->fresh()->result_settings)->toBe(['card_show_labels' => false]);
    expect((string) $group->fresh()->catalog_revision)->toBe($revision);
});

test('a Group save rejects an intervening owner outside its planned lock set without changing metadata', function () {
    $group = Group::factory()->create(['configurator_id' => null]);
    $owner = Configurator::factory()->create();
    Group::whereKey($group->id)->update(['configurator_id' => $owner->id]);
    $before = $group->fresh()->getAttributes();

    expect(fn () => app(SaveCatalogGroup::class)->handle($this->actor, $group, ['name' => 'Must not overwrite', 'configurator_id' => null]))
        ->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($before);
});

test('editor Properties selection keeps exact ordered keys across partial group settings saves and can return to filters', function () {
    $group = Group::factory()->create();
    $keys = ['Custom.Field', '0', 'Pressure', 'pressure', 'צבע."חדש"'];
    $input = groupSettingsInput(['filters' => [['property_key' => 'Working_Pressure', 'label' => 'Pressure', 'values' => []]],
        'result_settings' => ['editor_properties' => $keys, 'card_properties' => ['Model']]]);

    app(SaveGroupSettings::class)->handle($this->actor, $group, $input);
    $filter = $group->filters()->sole();
    $input['filters'][0]['id'] = $filter->id;
    $input['result_settings'] = ['default_page_size' => 2];
    app(SaveGroupSettings::class)->handle($this->actor, $group, $input);

    expect($group->fresh()->result_settings)->toMatchArray(['editor_properties' => $keys, 'card_properties' => ['Model'], 'default_page_size' => 2]);
    expect($filter->fresh()->property_key)->toBe('Working_Pressure');

    $input['result_settings'] = ['editor_properties' => []];
    app(SaveGroupSettings::class)->handle($this->actor, $group, $input);
    expect($group->fresh()->result_settings['editor_properties'])->toBe([]);
});

test('invalid editor Properties selections reject the whole settings update', function (mixed $selection) {
    $group = Group::factory()->create(['result_settings' => ['editor_properties' => ['keep']]]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Original']);
    $before = [$group->fresh()->getAttributes(), $filter->fresh()->getAttributes()];
    $input = groupSettingsInput(['filters' => [['id' => $filter->id, 'property_key' => 'Working_Pressure', 'label' => 'Must not save', 'values' => []]],
        'result_settings' => ['editor_properties' => $selection]]);

    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))
        ->toThrow(fn (ValidationException $exception) => expect(collect(array_keys($exception->errors()))
            ->contains(fn (string $key): bool => str_starts_with($key, 'settings.result_settings.editor_properties')))->toBeTrue());

    expect([$group->fresh()->getAttributes(), $filter->fresh()->getAttributes()])->toBe($before);
})->with([
    'null list' => [null], 'scalar list' => ['Model'], 'associative list' => [['field' => 'Model']],
    'duplicate keys' => [['Model', 'Model']], 'non text key' => [[0]], 'empty key' => [['']],
    'overlong key' => [[str_repeat('x', 101)]], 'null byte key' => [["bad\0key"]],
]);

test('batch property selection appends missing filter drafts without replacing existing identities or labels', function () {
    $this->actingAs($this->actor);
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '16', 'Connection_Type' => 'Flange']]);
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
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10', 'Connection_Type' => 'Flange']]);
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '16', 'Connection_Type' => 'Threaded']]);
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
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10']]);
    $filter = GroupFilter::factory()->for($group)->create(['property_key' => 'Working_Pressure', 'label' => 'Original']);
    $input = groupSettingsInput(['filters' => [['id' => $filter->id, 'property_key' => 'Working_Pressure', 'label' => 'Changed', 'values' => []]], 'sub_groups' => [['label' => 'Stale', 'property_key' => 'Working_Pressure', 'allowed_values' => ['999']]]]);
    expect(fn () => app(SaveGroupSettings::class)->handle($this->actor, $group, $input))->toThrow(ValidationException::class);
    expect($filter->fresh()->label)->toBe('Original')->and($group->subGroups()->count())->toBe(0);
});

test('removed preset hide setting is rejected without saving group changes', function (bool $forceHide) {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10']]);
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
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10']]);
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
        $row['property_key'] = str_repeat('x', 101);
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
    'overlong property' => ['card_properties', [str_repeat('x', 101)]],
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
    Product::factory()->for($group)->create(['profile_fields' => ['Working_Pressure' => '10', 'Connection_Type' => 'Flange']]);
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

test('appearance retains typed values and layout through partial updates and unchanged saves', function (int $columns) {
    $group = Group::factory()->create();
    $action = app(SaveGroupSettings::class);
    $action->handle($this->actor, $group, groupSettingsInput(['result_settings' => [
        'card_only_differences' => '0', 'card_show_labels' => '1',
        'card_property_layout' => 'below_center', 'card_property_columns' => (string) $columns,
        'card_padding_block' => '0', 'card_padding_inline' => '20',
    ]]));
    $action->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['card_show_labels' => false]]));
    $revision = (string) $group->fresh()->catalog_revision;
    $before = $group->fresh()->getAttributes();
    $action->handle($this->actor, $group, groupSettingsInput(['result_settings' => ['card_show_labels' => false]]));

    expect($group->fresh()->result_settings)->toMatchArray([
        'card_only_differences' => false, 'card_show_labels' => false,
        'card_property_layout' => 'below_center', 'card_property_columns' => $columns,
        'card_padding_block' => 0, 'card_padding_inline' => 20,
        'default_page_size' => 10, 'page_size_options' => [1, 2, 10],
    ]);
    expect($revision)->toBe('3')->and((string) $group->fresh()->catalog_revision)->toBe($revision);
    expect($group->fresh()->getAttributes())->toBe($before);
    expect(CatalogPolicy::resultSettings($group->fresh()->result_settings)['card_property_columns'])->toBe($columns);
})->with([1, 2, 3]);

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
    'one to three property columns' => ['card_property_columns', 4],
    'integral property columns' => ['card_property_columns', 1.5],
    'nonnegative block padding' => ['card_padding_block', -1],
    'bounded block padding' => ['card_padding_block', 17],
    'integral inline padding' => ['card_padding_inline', 6.5],
    'bounded inline padding' => ['card_padding_inline', 21],
    'unknown appearance setting' => ['card_icon_mode', true],
]);
