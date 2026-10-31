<?php

use App\Actions\SaveGroupTags;
use App\Models\CatalogPresentationSettings;
use App\Models\Group;
use App\Models\GroupTagCategory;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('group taxonomy has its own stable vocabulary and preserves ordinary transaction isolation', function () {
    expect(Schema::hasTable('group_tag_categories'))->toBeTrue()
        ->and(Schema::hasTable('group_tag_values'))->toBeTrue()
        ->and(Schema::hasTable('group_tag_value'))->toBeTrue()
        ->and(DB::transactionLevel())->toBe(1);

    $group = Group::factory()->create();
    expect($group->tagValues)->toBeInstanceOf(Collection::class)->toBeEmpty();
});

test('vocabulary editing preserves exact labels and immutable value category identity', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $save = app(SaveGroupTags::class);
    $category = $save->saveCategory($actor, null, ['label' => 'Pressure', 'sort_order' => 2]);
    $other = GroupTagCategory::factory()->create();
    foreach (['0', '001', 'Zero', 'zero', 'מים'] as $label) {
        $value = $save->saveValue($actor, null, ['group_tag_category_id' => $category->id, 'label' => $label, 'sort_order' => 0]);
        expect($value->label)->toBe($label);
    }
    $value = $category->values()->first();
    $id = $value->id;
    $updated = $save->saveValue($actor, $value, ['label' => 'Renamed', 'sort_order' => 3]);
    expect($updated->id)->toBe($id)->and($updated->group_tag_category_id)->toBe($category->id);
    expect(fn () => $save->saveValue($actor, $updated, ['group_tag_category_id' => $other->id, 'label' => 'Moved', 'sort_order' => 0]))
        ->toThrow(ValidationException::class);
    expect($updated->fresh()->label)->toBe('Renamed');
    $save->saveCategory($actor, $category, ['label' => 'Working pressure', 'sort_order' => 1]);
    expect($category->fresh()->label)->toBe('Working pressure');
});

test('category and value label limits count characters and reject out of range writes without changing valid rows', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $save = app(SaveGroupTags::class);
    $categoryLabel = str_repeat('א', 100);
    $valueLabel = str_repeat('界', 150);
    $category = $save->saveCategory($actor, null, ['label' => $categoryLabel, 'sort_order' => 0]);
    $value = $save->saveValue($actor, null, ['group_tag_category_id' => $category->id, 'label' => $valueLabel, 'sort_order' => 0]);
    expect($category->fresh()->label)->toBe($categoryLabel)
        ->and($value->fresh()->label)->toBe($valueLabel);
    $categoryCount = GroupTagCategory::query()->count();
    $valueCount = GroupTagValue::query()->count();
    $categoryBefore = $category->fresh()->getAttributes();
    $valueBefore = $value->fresh()->getAttributes();
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    foreach ([null, $category] as $target) {
        expect(fn () => $save->saveCategory($actor, $target, ['label' => $categoryLabel.'א', 'sort_order' => 1]))
            ->toThrow(ValidationException::class);
    }
    foreach ([null, $value] as $target) {
        expect(fn () => $save->saveValue($actor, $target, ['group_tag_category_id' => $category->id, 'label' => $valueLabel.'界', 'sort_order' => 1]))
            ->toThrow(ValidationException::class);
    }
    expect($writes)->toBeEmpty()
        ->and(GroupTagCategory::query()->count())->toBe($categoryCount)
        ->and(GroupTagValue::query()->count())->toBe($valueCount)
        ->and($category->fresh()->getAttributes())->toBe($categoryBefore)
        ->and($value->fresh()->getAttributes())->toBe($valueBefore);
});

test('ordered multi-value assignment uses fresh fingerprints without changing Group or Product settings', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $values = GroupTagValue::factory()->count(3)->create();
    $save = app(SaveGroupTags::class);
    $before = $group->fresh()->getAttributes();
    $product = Product::factory()->for($group)->create(['profile_fields' => ['Pressure' => '001']]);
    $productBefore = $product->fresh()->getAttributes();
    $empty = $save->fingerprint($group);
    $save->assign($actor, $group, [$values[2]->id, $values[0]->id], $empty);
    expect($group->tagValues()->pluck('group_tag_values.id')->all())->toBe([$values[2]->id, $values[0]->id])
        ->and($group->fresh()->getAttributes())->toBe($before)
        ->and($product->fresh()->getAttributes())->toBe($productBefore);
    $fingerprint = $save->fingerprint($group);
    expect($fingerprint)->not->toBe($empty);
    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $save->assign($actor, $group, [$values[2]->id, $values[0]->id], $fingerprint);
    expect($writes)->toBeEmpty();
    expect(fn () => $save->assign($actor, $group, [$values[1]->id], $empty))->toThrow(ValidationException::class);
    expect($save->fingerprint($group))->toBe($fingerprint);
    $save->assign($actor, $group, [], $fingerprint);
    expect($group->tagValues()->count())->toBe(0);
});

test('taxonomy write operations deny non-managers and reject malformed or unsupported input', function () {
    $save = app(SaveGroupTags::class);
    $viewer = User::factory()->create();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $category = GroupTagCategory::factory()->create();
    $group = Group::factory()->create();
    expect(fn () => $save->saveCategory($viewer, null, ['label' => 'x', 'sort_order' => 0]))->toThrow(AuthorizationException::class);
    expect(fn () => $save->saveValue($viewer, null, ['group_tag_category_id' => $category->id, 'label' => 'x', 'sort_order' => 0]))->toThrow(AuthorizationException::class);
    expect(fn () => $save->assign($viewer, $group, [], $save->fingerprint($group)))->toThrow(AuthorizationException::class);
    foreach ([['label' => ' ', 'sort_order' => 0], ['label' => "\u{00a0}\u{2003}", 'sort_order' => 0], ['label' => 'x', 'sort_order' => true], ['label' => str_repeat('x', 101), 'sort_order' => 0], ['label' => 'x', 'sort_order' => -1], ['label' => 'x', 'sort_order' => 0, 'extra' => true]] as $data) {
        expect(fn () => $save->saveCategory($actor, null, $data))->toThrow(ValidationException::class);
    }
    $value = GroupTagValue::factory()->create();
    foreach ([[$value->id, $value->id], [0], ['01'], [true], [PHP_INT_MAX]] as $ids) {
        expect(fn () => $save->assign($actor, $group, $ids, $save->fingerprint($group)))->toThrow(ValidationException::class);
    }
    expect($group->tagValues()->count())->toBe(0);
});

test('assigned vocabulary cannot be deleted through database cascades', function () {
    $group = Group::factory()->create();
    $value = GroupTagValue::factory()->create();
    $group->tagValues()->attach($value->id, ['position' => 0]);
    expect(fn () => $value->delete())->toThrow(QueryException::class);
});

test('taxonomy removal requires explicit unassignment and empty categories and preserves catalog state', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $writer = app(SaveGroupTags::class);
    $group = Group::factory()->create(['result_settings' => ['future' => false]]);
    $product = Product::factory()->for($group)->create();
    $before = $group->fresh()->getAttributes();
    $productBefore = $product->fresh()->getAttributes();
    $category = GroupTagCategory::factory()->create();
    $value = GroupTagValue::factory()->for($category, 'category')->create();
    $writer->assign($actor, $group, [$value->id], $writer->fingerprint($group));
    expect(fn () => $writer->removeCategory($actor, $category))->toThrow(ValidationException::class);
    expect(fn () => $writer->removeValue($actor, $value))->toThrow(ValidationException::class);
    $this->assertModelExists($category);
    $this->assertModelExists($value);
    $writer->assign($actor, $group, [], $writer->fingerprint($group));
    $writer->removeValue($actor, $value);
    $writer->removeCategory($actor, $category);
    $this->assertModelMissing($value);
    $this->assertModelMissing($category);
    expect($group->fresh()->getAttributes())->toBe($before);
    expect($product->fresh()->getAttributes())->toBe($productBefore);
    $this->assertDatabaseCount('catalog_presentation_settings', 0);
});

test('category removal checks fresh raw shared and Group presentation references without clearing them', function (string $scope) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $category = GroupTagCategory::factory()->create();
    $group = Group::factory()->create(['result_settings' => ['future' => 0]]);
    $raw = ['group_card_tag_categories' => [$category->id], 'future' => false];
    if ($scope === 'shared') {
        $settings = CatalogPresentationSettings::factory()->create(['settings' => $raw]);
    } else {
        $group->update(['result_settings' => ['future' => 0, 'catalog_presentation' => $raw]]);
    }
    $before = $group->fresh()->getAttributes();
    expect(fn () => app(SaveGroupTags::class)->removeCategory($actor, $category))->toThrow(ValidationException::class);
    $this->assertModelExists($category);
    expect($group->fresh()->getAttributes())->toBe($before);
    if ($scope === 'shared') {
        expect($settings->fresh()->settings)->toBe($raw);
    }
})->with(['shared', 'group']);

test('taxonomy removal reauthorizes every call including unassigned empty records', function () {
    $viewer = User::factory()->create();
    $category = GroupTagCategory::factory()->create();
    $value = GroupTagValue::factory()->for($category, 'category')->create();
    $writer = app(SaveGroupTags::class);
    expect(fn () => $writer->removeValue($viewer, $value))->toThrow(AuthorizationException::class);
    expect(fn () => $writer->removeCategory($viewer, $category))->toThrow(AuthorizationException::class);
    $this->assertModelExists($category);
    $this->assertModelExists($value);
});
