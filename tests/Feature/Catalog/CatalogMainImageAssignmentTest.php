<?php

use App\Actions\BatchCatalogChanges;
use App\Actions\SaveCatalogGroup;
use App\Actions\SaveCatalogMainImage;
use App\Actions\SaveCatalogProduct;
use App\DTO\CatalogAssetChange;
use App\Models\CatalogAsset;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetStorage;
use App\Services\CatalogAssetTargets;
use App\Services\ProductEditorData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('image targets retain inactive Products and nonleaf Groups for catalog administrators', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $parent = Group::factory()->create(['is_active' => false]);
    $child = Group::factory()->for($parent, 'parent')->create();
    $product = Product::factory()->for($child)->create(['is_active' => false]);
    $targets = app(CatalogAssetTargets::class);

    expect($targets->eligibleQuery($actor, 'product.main_image', [])->pluck('id')->all())->toBe([$product->id])
        ->and($targets->eligibleQuery($actor, 'group.main_image', [])->orderBy('id')->pluck('id')->all())->toBe([$parent->id, $child->id]);
});

test('image target queries retain exact Group and Configurator drawer membership', function () {
    $actor = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $configurator = Configurator::factory()->create();
    $group = Group::factory()->for($configurator)->create();
    $otherGroup = Group::factory()->create();
    $product = Product::factory()->for($group)->create();
    Product::factory()->for($otherGroup)->create();
    $targets = app(CatalogAssetTargets::class);

    expect($targets->eligibleQuery($actor, 'product.main_image', ['list_key' => 'group-products', 'parent_id' => $group->id])->pluck('id')->all())->toBe([$product->id])
        ->and($targets->eligibleQuery($actor, 'product.main_image', ['list_key' => 'configurator-products', 'parent_id' => $configurator->id])->pluck('id')->all())->toBe([$product->id])
        ->and($targets->eligibleQuery($actor, 'group.main_image', ['list_key' => 'configurator-groups', 'parent_id' => $configurator->id])->pluck('id')->all())->toBe([$group->id]);
});

test('image target queries reject unadmitted actors before returning records', function () {
    $actor = User::factory()->create(['email' => 'outsider@example.test']);

    expect(fn () => app(CatalogAssetTargets::class)->eligibleQuery($actor, 'product.main_image', []))
        ->toThrow(AuthorizationException::class);
});

test('image target policy rejects unsupported entity and file roles', function (string $target) {
    expect(fn () => app(CatalogAssetTargets::class)->profile($target))->toThrow(ValidationException::class);
})->with(['attribute.main_image', 'product.pdf', 'product.gallery', 'App\\Models\\Product', 'product', '']);

test('image target queries reject malformed and mismatched context without widening it', function (array $context) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);

    expect(fn () => app(CatalogAssetTargets::class)->eligibleQuery($actor, 'product.main_image', $context))
        ->toThrow(ValidationException::class);
})->with([
    'unknown key' => [['group_id' => 1]],
    'missing parent' => [['list_key' => 'group-products']],
    'wrong target model' => [['list_key' => 'configurator-groups', 'parent_id' => 1]],
    'unknown drawer' => [['list_key' => 'anything', 'parent_id' => 1]],
    'string parent' => [['list_key' => 'group-products', 'parent_id' => '1']],
    'nonpositive parent' => [['list_key' => 'group-products', 'parent_id' => 0]],
    'extra authority' => [['list_key' => 'group-products', 'parent_id' => 1, 'model' => Product::class]],
]);

test('image target policy keeps reference lookup independent of configured editor Properties', function () {
    $profile = app(CatalogAssetTargets::class)->profile('product.main_image');

    expect($profile['reference_field'])->toBe(['column' => 'profile_fields', 'key' => 'product_pic'])
        ->and($profile['mime_types'])->toBe(['image/jpeg', 'image/png', 'image/webp'])
        ->and($profile['max_bytes'])->toBe(3145728)
        ->and($profile['max_pixels'])->toBe(25000000)
        ->and($profile['conversion_sizes'])->toBe([160, 480, 960])
        ->and(app(CatalogAssetTargets::class)->profile('group.main_image')['reference_field'])->toBeNull();
});

test('a removed drawer parent fails instead of returning a global image target query', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    Product::factory()->create();
    $group->delete();

    expect(fn () => app(CatalogAssetTargets::class)->eligibleQuery($actor, 'product.main_image', ['list_key' => 'group-products', 'parent_id' => $group->id]))
        ->toThrow(ModelNotFoundException::class);
});

test('image drawer membership is resolved again after a product moves', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    $other = Group::factory()->create();
    $product = Product::factory()->for($group)->create();
    $targets = app(CatalogAssetTargets::class);
    $context = ['list_key' => 'group-products', 'parent_id' => $group->id];
    expect($targets->eligibleQuery($actor, 'product.main_image', $context)->whereKey($product->id)->exists())->toBeTrue();

    $product->update(['group_id' => $other->id]);

    expect($targets->eligibleQuery($actor, 'product.main_image', $context)->whereKey($product->id)->exists())->toBeFalse();
});

test('existing full record writers reject browser supplied main image pointers atomically', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create()->refresh();
    $group = $product->group;
    $beforeProduct = $product->getAttributes();
    $beforeGroup = $group->getAttributes();
    $data = [
        'product_code' => $product->product_code, 'product_name' => 'Must not save', 'description' => $product->description,
        ...ProductEditorData::formData($product), 'main_image_id' => 17,
    ];

    expect(fn () => app(SaveCatalogProduct::class)->handle($actor, $product, $data, SaveCatalogProduct::fingerprint($product), ProductEditorData::groupFingerprint($product)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(SaveCatalogGroup::class)->handle($actor, $group, ['name' => 'Must not save', 'main_image_id' => 17]))
        ->toThrow(ValidationException::class);
    expect($product->fresh()->getAttributes())->toBe($beforeProduct)
        ->and($group->fresh()->getAttributes())->toBe($beforeGroup);
});

function minimumAssignmentImage(User $actor, string $target): CatalogAsset
{
    $disk = catalogImageTestDisk('assignment-fixtures');
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($image);
    $disk->put('synthetic.png', ob_get_clean());
    imagedestroy($image);
    config(['catalog-assets.enabled' => true, 'catalog-assets.sources.fixture' => ['label' => 'Synthetic', 'path' => $disk->path(''), 'read_only' => true]]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', $target, [], false);
    do {
        $status = $sources->status($actor, $source->id);
        $sources->scanNext($actor, $source->id, 1, $status['revision']);
    } while (! $sources->status($actor, $source->id)['traversal_complete']);
    $sources->finalize($actor, $source->id, 1, $sources->status($actor, $source->id)['revision']);
    $entry = $sources->manifest($actor, $source->id, 1)['entries'][0];

    return app(CatalogAssetStorage::class)->prepare($actor, ['source_id' => $source->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']], $target, hash('sha256', $target.$source->id))['asset'];
}

test('a real image-only Group assignment and ordinary save preserve all independent Group facts', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $parent = Group::factory()->create();
    $group = Group::factory()->for($parent, 'parent')->create(['configurator_id' => Configurator::factory()->create()->id,
        'catalog_attributes' => ['application' => ['0', '001', ' 0 '], 'future_key' => ['Future']],
        'result_settings' => ['future' => false, 'catalog_presentation' => ['group_card_attribute_keys' => []]]])->refresh();
    $tags = GroupTagValue::factory()->count(2)->create();
    $group->tagValues()->attach([$tags[1]->id => ['position' => 0], $tags[0]->id => ['position' => 1]]);
    $asset = minimumAssignmentImage($actor, 'group.main_image');
    $before = $group->getAttributes();
    $fingerprint = CatalogAssetAssignments::fingerprint($group);
    app(SaveCatalogMainImage::class)->handle($actor, 'group.main_image', $group->id, [], $fingerprint, new CatalogAssetChange('set', $asset->id));
    $saved = $group->fresh();
    expect($saved->main_image_id)->toBe($asset->id)->and((int) $saved->catalog_revision)->toBe((int) $before['catalog_revision'] + 1);
    expect(Arr::except($saved->getAttributes(), ['main_image_id', 'catalog_revision', 'updated_at']))->toBe(Arr::except($before, ['main_image_id', 'catalog_revision', 'updated_at']));
    expect($saved->tagValues()->pluck('group_tag_values.id')->all())->toBe([$tags[1]->id, $tags[0]->id]);
    app(SaveCatalogGroup::class)->handle($actor, $saved, ['name' => 'Ordinary rename']);
    expect($saved->fresh()->main_image_id)->toBe($asset->id)->and($saved->fresh()->catalog_attributes)->toBe(['application' => ['0', '001', ' 0 '], 'future_key' => ['Future']]);
});

test('fresh locked Group CAS precedes image resolution and every ordinary core mutation', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create(['name' => 'Before fill'])->refresh();
    $fingerprint = CatalogAssetAssignments::fingerprint($group);
    $calls = [];
    $resolve = function (Model $locked) use (&$calls): CatalogAssetChange {
        expect(DB::transactionLevel())->toBeGreaterThan(0);
        $calls[] = $locked->name;

        return new CatalogAssetChange('remove');
    };
    config(['catalog-assets.enabled' => true]);
    app(SaveCatalogGroup::class)->handle($actor, $group, ['name' => 'After fill'], null, $fingerprint, $resolve);
    expect($calls)->toBe(['Before fill']);
    expect(fn () => app(SaveCatalogGroup::class)->handle($actor, $group, ['name' => 'Must not overwrite'], null, $fingerprint, $resolve))->toThrow(ValidationException::class);
    expect($calls)->toBe(['Before fill'])->and($group->fresh()->name)->toBe('After fill');
    expect(fn () => app(SaveCatalogGroup::class)->handle($actor, $group, ['name' => 'Ambiguous'], new CatalogAssetChange('keep'), null, $resolve))->toThrow(ValidationException::class);
});

test('Product image-only changes preserve raw properties Parts and Group identity and a normal edit keeps the pointer', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $product = Product::factory()->create(['profile_fields' => ['zero' => '0', 'identifier' => '001', 'spaced' => ' value ', 'product_pic' => '/synthetic/id'],
        'parts' => ['Part1' => '0', 'Future' => ' 001 ']])->refresh();
    $asset = minimumAssignmentImage($actor, 'product.main_image');
    $before = $product->getAttributes();
    $group = $product->group->fresh();
    $revision = (int) $group->catalog_revision;
    $saved = app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [],
        CatalogAssetAssignments::fingerprint($product), new CatalogAssetChange('set', $asset->id));
    expect(Arr::except($saved->getAttributes(), ['main_image_id', 'updated_at']))
        ->toBe(Arr::except($before, ['main_image_id', 'updated_at']))
        ->and((int) $group->fresh()->catalog_revision)->toBe($revision + 1);
    $same = app(SaveCatalogMainImage::class)->handle($actor, 'product.main_image', $product->id, [],
        CatalogAssetAssignments::fingerprint($saved), new CatalogAssetChange('set', $asset->id));
    expect((int) $group->fresh()->catalog_revision)->toBe($revision + 1);
    app(SaveCatalogProduct::class)->handle($actor, $same, ['product_code' => $same->product_code, 'product_name' => 'Ordinary edit', 'description' => $same->description,
        ...ProductEditorData::formData($same)], SaveCatalogProduct::fingerprint($same), ProductEditorData::groupFingerprint($same));
    expect($product->fresh()->main_image_id)->toBe($asset->id)->and($product->fresh()->profile_fields)->toBe($product->profile_fields)
        ->and($product->fresh()->parts)->toBe($product->parts);
});

test('a Group image revision invalidates an earlier ordinary batch preview', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create()->refresh();
    $asset = minimumAssignmentImage($actor, 'group.main_image');
    $batch = app(BatchCatalogChanges::class);
    $intent = ['operation' => 'edit', 'changes' => [['field' => 'card_show_labels', 'mode' => 'set', 'value' => true]]];
    $preview = $batch->preview($actor, 'groups', [$group->id], $intent);
    app(SaveCatalogMainImage::class)->handle($actor, 'group.main_image', $group->id, [],
        CatalogAssetAssignments::fingerprint($group), new CatalogAssetChange('set', $asset->id));
    $after = $group->fresh()->getAttributes();
    expect(fn () => $batch->apply($actor, 'groups', [$group->id], $intent, $preview['token']))->toThrow(ValidationException::class);
    expect($group->fresh()->getAttributes())->toBe($after)->and($group->fresh()->main_image_id)->toBe($asset->id);
});
