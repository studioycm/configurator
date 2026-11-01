<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Filament\Pages\CatalogImages;
use App\Models\CatalogAsset;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('actual Images host commits only owned image pointers and reconciles a lost continuation without replay', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $prefix = getenv('CATALOG_IMAGES_FIXTURE_PREFIX');
    $root = getenv('CATALOG_IMAGES_TEST_ROOT');
    if (! is_string($prefix) || preg_match('/\AIMG-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1
        || ! is_string($root) || ! str_starts_with($root, sys_get_temp_dir().'/catalog-images-')
        || ! is_dir($root) || is_link($root) || realpath($root) !== $root
        || (new Filesystem)->allFiles($root, hidden: true) !== []) {
        throw new RuntimeException('This nonresetting host proof requires its approved prefix and empty canonical private scratch root.');
    }
    config(['catalog-assets.enabled' => true, 'catalog-assets.testing_root' => $root, 'cache.default' => 'array', 'media-library.temporary_directory' => $root.'/media-tmp']);
    foreach (['local', 'public', 'catalog-assets', 'catalog-asset-imports', 'workspace-engine-source'] as $disk) {
        catalogImageTestDisk($disk);
    }
    $image = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    Storage::disk('workspace-engine-source')->put($prefix.'.png', $bytes);
    config(['catalog-assets.sources.fixture' => ['label' => $prefix, 'path' => Storage::disk('workspace-engine-source')->path(''), 'read_only' => true]]);
    $tables = ['users', 'groups', 'products', 'catalog_assets', 'catalog_asset_import_sources', 'media'];
    $beforeCounts = collect($tables)->mapWithKeys(fn ($table): array => [$table => DB::table($table)->count()])->all();
    $matches = User::query()->whereRaw('LOWER(email) = ?', ['ycm@data4.work'])->limit(2)->get();
    if ($matches->count() > 1 || ($matches->isNotEmpty() && $matches->first()->email !== 'ycm@data4.work')) {
        throw new RuntimeException('Conflicting manager identity; no existing User may be changed.');
    }
    $createdActor = $matches->isEmpty();
    expect(DB::table('catalog_asset_import_sources')->where('source->label', $prefix)->exists())->toBeFalse();
    $actor = null;
    $group = null;
    $products = [];
    $sources = [];
    try {
        $actor = $matches->first() ?? User::factory()->create(['email' => 'ycm@data4.work', 'name' => $prefix.'-manager']);
        $actorBefore = $actor->fresh()->getAttributes();
        $this->actingAs($actor);
        expect(Group::where('name', $prefix.'-group')->exists())->toBeFalse();
        $group = Group::factory()->create(['name' => $prefix.'-group', 'catalog_attributes' => ['application' => ['0', '001']]])->refresh();
        $products[] = Product::factory()->for($group)->create(['product_code' => $prefix.'-A', 'legacy_id' => $prefix.'-a', 'profile_fields' => ['product_pic' => 'synthetic', 'keep' => ['0', '001']]])->refresh();
        $products[] = Product::factory()->for($group)->create(['product_code' => $prefix.'-B', 'legacy_id' => $prefix.'-b'])->refresh();
        $original = $products[0]->getAttributes();
        $page = Livewire::test(CatalogImages::class)->set('imageSearch.group_ids', [$group->id])->set('imageSearch.image_state', 'missing');
        $page->call('inspectProductImage', $products[0]->id, $page->instance()->imageBrowserContext());
        $choose = function () use ($page, &$sources): void {
            $page->call('catalogMainImageChooseSource', 'alias:fixture');
            $sources[] = $page->get('catalogMainImageSourceProgress')['source_id'];
            for ($step = 0; $step < 12 && $page->get('catalogMainImageSourceProgress')['stage'] !== 'ready'; $step++) {
                $page->call('catalogMainImageAdvanceSource');
            }
            $candidates = $page->instance()->catalogMainImageCandidates();
            expect($candidates['total'])->toBe(1);
            $page->call('catalogMainImageSelectCandidate', $candidates['entries'][0]['entry_id'])->assertSet('data.image_intent.operation', 'set');
        };
        $choose();
        $page->call('navigateImageRecord', 'next', $page->instance()->imageBrowserContext())->assertSet('imageRecordId', $products[0]->id);
        $expected = $page->instance()->workspaceImageEditorContext();
        $token = $page->get('imagePendingTransition')['token'];
        $page->call('prepareWorkspaceImageSave', $expected, $token)->assertHasNoErrors();
        $lostSnapshot = $page->snapshot;
        $page->call('saveWorkspaceImage', $expected, $token)->assertHasNoErrors()->assertSet('imageRecordId', $products[1]->id);
        $saved = $products[0]->fresh()->getAttributes();
        expect($saved['main_image_id'])->not->toBeNull()->and(array_diff_key($saved, array_flip(['main_image_id', 'updated_at'])))
            ->toBe(array_diff_key($original, array_flip(['main_image_id', 'updated_at'])));
        $page->snapshot = $lostSnapshot;
        $page->call('reconcileWorkspaceImageSave', $expected, $token)->assertHasNoErrors()->assertSet('imageRecordId', $products[1]->id);
        expect($products[0]->fresh()->getAttributes())->toBe($saved);
        $page->call('switchImageTarget', 'group.main_image', $page->instance()->imageBrowserContext());
        $page->call('inspectGroupImage', $group->id, $page->instance()->imageBrowserContext());
        $groupBefore = $group->fresh()->getAttributes();
        $choose();
        $expected = $page->instance()->workspaceImageEditorContext();
        $page->call('prepareWorkspaceImageSave', $expected)->call('saveWorkspaceImage', $expected)->assertHasNoErrors();
        expect($group->fresh()->main_image_id)->not->toBeNull()
            ->and(array_diff_key($group->fresh()->getAttributes(), array_flip(['main_image_id', 'catalog_revision', 'updated_at'])))
            ->toBe(array_diff_key($groupBefore, array_flip(['main_image_id', 'catalog_revision', 'updated_at'])))
            ->and($actor->fresh()->getAttributes())->toBe($actorBefore)->and(DB::transactionLevel())->toBe(0);
    } finally {
        // Recover only this uniquely labelled fixture's allocations if a response failed before recording its source ID.
        if ($actor !== null) {
            $sources = DB::table('catalog_asset_import_sources')->where('actor_id', $actor->id)
                ->where('source->label', $prefix)->where('source->alias', 'fixture')->pluck('id')->all();
        }
        $assetIds = $actor === null || $sources === [] ? [] : CatalogAsset::query()->where('created_by', $actor->id)->whereIn('source->source_id', $sources)->pluck('id')->all();
        $media = DB::table('media')->where('model_type', (new CatalogAsset)->getMorphClass())->whereIn('model_id', $assetIds)->get(['id', 'disk', 'conversions_disk']);
        foreach ($products as $product) {
            DB::table('products')->where('id', $product->id)->where('group_id', $group?->id)->where('product_code', 'like', $prefix.'-%')->delete();
        }
        if ($group !== null) {
            DB::table('groups')->where('id', $group->id)->where('name', $prefix.'-group')->delete();
        }
        DB::table('catalog_assets')->whereIn('id', $assetIds)->update(['original_media_id' => null]);
        DB::table('media')->whereIn('id', $media->pluck('id')->all())->where('model_type', (new CatalogAsset)->getMorphClass())->delete();
        DB::table('catalog_assets')->whereIn('id', $assetIds)->where('created_by', $actor?->id)->delete();
        DB::table('catalog_asset_import_sources')->whereIn('id', $sources)->where('actor_id', $actor?->id)->delete();
        if ($createdActor && $actor !== null) {
            DB::table('users')->where('id', $actor->id)->where('name', $prefix.'-manager')->where('email', 'ycm@data4.work')->delete();
        }
        foreach ($media as $item) {
            expect($item->disk)->toBe('catalog-assets')->and($item->conversions_disk)->toBe('catalog-assets');
            Storage::disk('catalog-assets')->deleteDirectory((string) $item->id);
        }
    }
    foreach ($beforeCounts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    expect(DB::transactionLevel())->toBe(0);
});
