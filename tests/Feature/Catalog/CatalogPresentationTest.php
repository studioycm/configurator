<?php

use App\Actions\SaveCatalogPresentationSettings;
use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogPresentation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('presentation storage starts empty and reads deterministic defaults without inserting', function () {
    expect(Schema::hasTable('catalog_presentation_settings'))->toBeTrue();
    expect(DB::transactionLevel())->toBe(1);
    $service = new CatalogPresentation;
    $expected = [
        'sidebar_mode' => 'tree', 'group_image_placement' => 'left', 'group_image_scale' => 'medium',
        'group_card_density' => 'compact', 'group_cards_per_row' => 3,
        'group_card_content' => ['description', 'tags'], 'group_card_tag_categories' => [],
        'tag_presentation' => 'cards', 'tag_columns' => 2, 'tag_density' => 'compact', 'tag_counts' => true,
        'branch_tag_retention' => 'keep_available', 'criteria_container' => 'responsive',
        'criteria_columns' => 'divided', 'criteria_choices' => 'checkbox',
        'product_image_placement' => 'left', 'product_image_scale' => 'medium',
        'product_page_layout' => 'compact_header', 'product_page_image_scale' => 'medium',
    ];

    expect($service->defaults())->toBe($expected);
    expect($service->current())->toBe(['version' => 'defaults:v1', 'settings' => $expected, 'recovery' => null]);
    $this->assertDatabaseCount('catalog_presentation_settings', 0);
});

test('presentation accepts every finite alternative including false and empty lists', function (string $key, array $values) {
    $service = new CatalogPresentation;
    foreach ($values as $value) {
        expect($service->validate(array_replace($service->defaults(), [$key => $value]))[$key])->toBe($value);
    }
})->with([
    ['sidebar_mode', ['tree', 'tags']],
    ['group_image_placement', ['above', 'below', 'left', 'right']],
    ['group_image_scale', ['small', 'medium', 'large']],
    ['group_card_density', ['compact', 'comfortable']],
    ['group_cards_per_row', [2, 3, 4]],
    ['group_card_content', [[], ['legacy_id', 'product_count', 'tags', 'description']]],
    ['group_card_tag_categories', [[]]],
    ['tag_presentation', ['cards', 'chip_grid', 'chips', 'rows']],
    ['tag_columns', [1, 2, 3]], ['tag_density', ['compact', 'comfortable']],
    ['tag_counts', [false, true]], ['branch_tag_retention', ['keep_available', 'clear']],
    ['criteria_container', ['responsive', 'drawer']], ['criteria_columns', ['plain', 'divided']],
    ['criteria_choices', ['checkbox', 'buttons']],
    ['product_image_placement', ['above', 'below', 'left', 'right']],
    ['product_image_scale', ['small', 'medium', 'large']],
    ['product_page_layout', ['compact_header', 'image_column']],
    ['product_page_image_scale', ['small', 'medium', 'large']],
]);

test('presentation rejects missing null unknown and incorrectly typed shared settings', function () {
    $service = new CatalogPresentation;
    $defaults = $service->defaults();
    foreach (array_keys($defaults) as $key) {
        $missing = $defaults;
        unset($missing[$key]);
        expect(fn () => $service->validate($missing))->toThrow(ValidationException::class);
        expect(fn () => $service->validate(array_replace($defaults, [$key => null])))->toThrow(ValidationException::class);
    }
    foreach ([
        ['unknown' => true], ['sidebar_mode' => 'focus'], ['sidebar_mode' => true],
        ['group_cards_per_row' => true], ['group_cards_per_row' => '3'], ['group_cards_per_row' => 3.0],
        ['group_cards_per_row' => 1], ['group_cards_per_row' => 5],
        ['tag_columns' => 0], ['tag_columns' => 4], ['tag_columns' => '2'],
        ['tag_counts' => '0'], ['tag_counts' => 1],
        ['group_card_content' => ['tags', 'tags']], ['group_card_content' => ['name']],
        ['group_card_content' => [1 => 'tags']], ['group_card_content' => [true]],
        ['group_card_tag_categories' => ['01']], ['group_card_tag_categories' => [true]],
        ['group_card_tag_categories' => [0]], ['group_card_tag_categories' => [1, 1]],
        ['group_card_tag_categories' => [1 => 2]],
    ] as $delta) {
        expect(fn () => $service->validate(array_replace($defaults, $delta)))->toThrow(ValidationException::class);
    }
});

test('stored presentation recovers individual bad fields without leaking raw metadata or writing', function () {
    $record = CatalogPresentationSettings::factory()->create(['id' => 1, 'settings' => [
        'sidebar_mode' => 'tags', 'tag_counts' => false, 'group_card_content' => [],
        'tag_columns' => 'huge-private-input', 'untrusted-private-name' => '<secret>',
    ]]);
    $before = $record->fresh()->getAttributes();
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    $current = (new CatalogPresentation)->current();

    expect($current['settings']['sidebar_mode'])->toBe('tags');
    expect($current['settings']['tag_counts'])->toBeFalse();
    expect($current['settings']['group_card_content'])->toBe([]);
    expect($current['settings']['tag_columns'])->toBe(2);
    expect($current['recovery']['defaulted_fields'])->toContain('tag_columns', 'criteria_container');
    expect($current['recovery']['ignored_unknown_count'])->toBe(1);
    expect(json_encode($current))->not->toContain('huge-private-input', 'untrusted-private-name', '<secret>');
    expect($record->fresh()->getAttributes())->toBe($before);
    expect($writes)->toBeEmpty();
});

test('malformed stored payload recovers and missing table errors remain visible', function () {
    CatalogPresentationSettings::factory()->create(['id' => 1, 'settings' => 'invalid']);
    $service = new CatalogPresentation;
    expect($service->current()['settings'])->toBe($service->defaults());
    expect($service->current()['recovery']['invalid_payload'])->toBeTrue();
    Schema::drop('catalog_presentation_settings');
    expect(fn () => $service->current(true))->toThrow(QueryException::class);
});

test('effective presentation uses only valid own overrides and does not mutate Group or Product data', function () {
    $category = GroupTagCategory::factory()->create();
    $parent = Group::factory()->create(['result_settings' => ['catalog_presentation' => ['group_image_placement' => 'above']]]);
    $group = Group::factory()->for($parent, 'parent')->create(['result_settings' => [
        'legacy' => ['0', '001'], 'catalog_presentation' => [
            'group_image_scale' => 'large', 'group_image_placement' => 'invalid', 'group_card_content' => [],
            'group_card_tag_categories' => [$category->id], 'product_image_placement' => 'below',
            'sidebar_mode' => 'tags', 'future' => ['opaque' => true],
        ],
    ]]);
    $product = Product::factory()->for($group)->create();
    $before = $group->fresh()->getAttributes();
    $productBefore = $product->fresh()->getAttributes();
    $service = new CatalogPresentation;
    $fingerprint = $service->groupFingerprint($group);

    $effective = $service->effective($group, $service->defaults(), [$category->id]);

    expect($effective['group_image_placement'])->toBe('left');
    expect($effective['group_image_scale'])->toBe('large');
    expect($effective['group_card_content'])->toBe([]);
    expect($effective['group_card_tag_categories'])->toBe([$category->id]);
    expect($effective['product_image_placement'])->toBe('below');
    expect($effective['sidebar_mode'])->toBe('tree');
    expect($group->fresh()->getAttributes())->toBe($before);
    expect($product->fresh()->getAttributes())->toBe($productBefore);
    $group->result_settings = ['catalog_presentation' => ['group_image_placement' => 'left']];
    expect($service->effective($group, $service->defaults())['group_image_placement'])->toBe('left');
    expect($service->groupFingerprint($group))->not->toBe($fingerprint);
    $rawFingerprint = $service->groupFingerprint($group);
    $group->catalog_revision = 'changed-revision';
    expect($service->groupFingerprint($group))->not->toBe($rawFingerprint);
    $group->id = 999;
    expect($service->groupFingerprint($group))->not->toBe($rawFingerprint);
});

test('shared writer authorizes validates current categories and preserves ordered selection and no-op identity', function () {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $viewer = User::factory()->create();
    $categories = GroupTagCategory::factory()->count(2)->create();
    $service = new CatalogPresentation;
    $writer = new SaveCatalogPresentationSettings($service);
    $settings = array_replace($service->defaults(), ['tag_counts' => false, 'group_card_tag_categories' => [$categories[1]->id, $categories[0]->id]]);
    expect(fn () => $writer->handle($viewer, $settings, 'defaults:v1'))->toThrow(AuthorizationException::class);
    expect(fn () => $writer->handle($manager, array_replace($settings, ['group_card_tag_categories' => [PHP_INT_MAX]]), 'defaults:v1'))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('catalog_presentation_settings', 0);

    $saved = $writer->handle($manager, $settings, 'defaults:v1');

    expect($saved->id)->toBe(1);
    expect($saved->settings)->toBe($settings);
    expect($saved->version)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    $before = $saved->getAttributes();
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $writer->handle($manager, $settings, $saved->version);
    expect($saved->fresh()->getAttributes())->toBe($before);
    expect($writes)->toBeEmpty();
    expect(fn () => $writer->handle($manager, array_replace($settings, ['sidebar_mode' => 'tags']), 'defaults:v1'))->toThrow(ValidationException::class);
    expect($saved->fresh()->settings)->toBe($settings);
});

test('explicit shared repair keeps valid preferences advances only display version and leaves business records unchanged', function () {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create();
    $groupBefore = $group->fresh()->getAttributes();
    $productBefore = $product->fresh()->getAttributes();
    $record = CatalogPresentationSettings::factory()->create(['settings' => ['sidebar_mode' => 'tags', 'tag_columns' => 9]]);
    $service = new CatalogPresentation;
    $current = $service->current();

    $saved = (new SaveCatalogPresentationSettings($service))->handle($manager, $current['settings'], $current['version']);

    expect($saved->version)->not->toBe($record->version);
    expect($saved->settings['sidebar_mode'])->toBe('tags');
    expect($saved->settings['tag_columns'])->toBe(2);
    expect($service->current()['recovery'])->toBeNull();
    expect($group->fresh()->getAttributes())->toBe($groupBefore);
    expect($product->fresh()->getAttributes())->toBe($productBefore);
});

test('invalid ordered metadata recovers only that field and missing own override inherits shared intent', function () {
    $service = new CatalogPresentation;
    $settings = array_replace($service->defaults(), [
        'group_image_placement' => 'right', 'group_card_content' => ['tags', 'tags'],
        'group_card_tag_categories' => [PHP_INT_MAX],
    ]);
    CatalogPresentationSettings::factory()->create(['settings' => $settings]);
    $current = $service->current();
    expect($current['settings']['group_image_placement'])->toBe('right');
    expect($current['settings']['group_card_content'])->toBe(['description', 'tags']);
    expect($current['settings']['group_card_tag_categories'])->toBe([]);
    $group = Group::factory()->make(['result_settings' => ['catalog_presentation' => ['group_card_content' => []]]]);

    expect($service->effective($group, $current['settings'])['group_image_placement'])->toBe('right');
    expect($service->effective($group, $current['settings'])['group_card_content'])->toBe([]);
    $group->result_settings = ['catalog_presentation' => ['group_image_placement' => 'left']];
    expect($service->effective($group, $current['settings'])['group_image_placement'])->toBe('left');
    $group->result_settings = ['catalog_presentation' => []];
    expect($service->effective($group, $current['settings'])['group_image_placement'])->toBe('right');
});

test('repairing invalid scalar types remains a real change even when loose values would compare equal', function () {
    $manager = User::factory()->create(['email' => 'ycm@data4.work']);
    $service = new CatalogPresentation;
    $settings = array_replace($service->defaults(), ['tag_counts' => false]);
    $record = CatalogPresentationSettings::factory()->create(['settings' => array_replace($settings, ['tag_columns' => '2', 'tag_counts' => 0])]);

    $saved = (new SaveCatalogPresentationSettings($service))->handle($manager, $settings, $record->version);

    expect($saved->version)->not->toBe($record->version);
    expect($saved->fresh()->settings['tag_columns'])->toBe(2);
    expect($saved->fresh()->settings['tag_counts'])->toBeFalse();
});

test('effective presentation uses resolved categories without SQL and preserves fallback and ordered own preferences', function () {
    $categories = GroupTagCategory::factory()->count(3)->create();
    $availableCategoryIds = $categories->pluck('id')->all();
    $service = new CatalogPresentation;
    $shared = array_replace($service->defaults(), [
        'group_image_placement' => 'right', 'group_card_tag_categories' => [$categories[1]->id, $categories[0]->id],
        'tag_counts' => false,
    ]);
    $group = Group::factory()->make(['result_settings' => ['catalog_presentation' => [
        'group_image_placement' => 'invalid', 'group_card_content' => [],
        'group_card_tag_categories' => [$categories[2]->id, $categories[0]->id],
    ]]]);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $effective = $service->effective($group, $shared, $availableCategoryIds);

    expect($effective['group_card_tag_categories'])->toBe([$categories[2]->id, $categories[0]->id]);
    expect($effective['group_image_placement'])->toBe('right');
    expect($effective['group_card_content'])->toBe([]);
    expect($effective['tag_counts'])->toBeFalse();
    $group->result_settings = ['catalog_presentation' => ['group_card_tag_categories' => [PHP_INT_MAX]]];
    expect($service->effective($group, $shared, $availableCategoryIds)['group_card_tag_categories'])->toBe([$categories[1]->id, $categories[0]->id]);
    $group->result_settings = ['catalog_presentation' => ['group_card_tag_categories' => []]];
    expect($service->effective($group, $shared, $availableCategoryIds)['group_card_tag_categories'])->toBe([]);
    expect($queries)->toBeEmpty();
});

test('category resolution is batched once and reused by pure projections without selecting unrelated categories', function () {
    $categories = GroupTagCategory::factory()->count(4)->create();
    $service = new CatalogPresentation;
    $shared = array_replace($service->defaults(), ['group_card_tag_categories' => [$categories[1]->id, $categories[0]->id]]);
    $groups = [
        Group::factory()->make(['result_settings' => ['catalog_presentation' => ['group_card_tag_categories' => [$categories[2]->id, $categories[0]->id]]]]),
        Group::factory()->make(['result_settings' => ['catalog_presentation' => ['group_card_tag_categories' => [PHP_INT_MAX]]]]),
        Group::factory()->make(['result_settings' => ['catalog_presentation' => ['group_card_tag_categories' => ['01']]]]),
    ];
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $context = $service->resolveCategoryIds($shared, $groups);
    $effective = array_map(fn (Group $group): array => $service->effective($group, $shared, $context), $groups);

    expect($context)->toBe([$categories[0]->id, $categories[1]->id, $categories[2]->id]);
    expect($effective[0]['group_card_tag_categories'])->toBe([$categories[2]->id, $categories[0]->id]);
    expect($effective[1]['group_card_tag_categories'])->toBe([$categories[1]->id, $categories[0]->id]);
    expect($effective[2]['group_card_tag_categories'])->toBe([$categories[1]->id, $categories[0]->id]);
    expect($queries)->toHaveCount(1);
    expect($service->resolveCategoryIds($service->defaults()))->toBe([]);
    expect($queries)->toHaveCount(1);
});

test('pure projection requires explicit resolved context for nonempty references instead of silently querying or dropping them', function () {
    $service = new CatalogPresentation;
    $shared = array_replace($service->defaults(), ['group_card_tag_categories' => [1]]);
    $group = Group::factory()->make();

    expect(fn () => $service->effective($group, $shared))->toThrow(LogicException::class);
    expect(fn () => $service->effective($group, $shared, ['1']))->toThrow(InvalidArgumentException::class);
    expect($service->effective($group, $shared, [])['group_card_tag_categories'])->toBe([]);
});
