<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
assertCatalogMySqlSafety();

use App\Filament\Pages\CatalogImages;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetRecordSearch;
use App\Services\CatalogAssetRecordSequence;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('Images browsing uses actual engine exact key collation tuple order and scoped reads without changing retained data', function () {
    assertCatalogMySqlSafety(app());
    expect(DB::transactionLevel())->toBe(0);
    $prefix = getenv('CATALOG_IMAGES_FIXTURE_PREFIX');
    if (! is_string($prefix) || preg_match('/\AIMG-[a-zA-Z0-9-]{8,48}\z/', $prefix) !== 1) {
        throw new RuntimeException('Images browsing proof requires its approved unique fixture prefix.');
    }
    config(['catalog-assets.enabled' => true, 'cache.default' => 'array']);
    $initialCounts = collect(['users', 'groups', 'products', 'catalog_assets', 'catalog_asset_import_sources', 'media'])
        ->mapWithKeys(fn ($table): array => [$table => DB::table($table)->count()])->all();
    DB::beginTransaction();
    try {
        $email = 'ycm@data4.work';
        $actors = User::query()->whereRaw('LOWER(email) = ?', [$email])->limit(2)->get();
        if ($actors->count() > 1 || ($actors->isNotEmpty() && $actors->first()->email !== $email)) {
            throw new RuntimeException('Conflicting manager identity; no existing User may be changed.');
        }
        $actor = $actors->first() ?? User::factory()->create(['email' => $email, 'name' => $prefix.'-manager']);
        $this->actingAs($actor);
        expect(Group::where('name', $prefix.'-scope')->exists())->toBeFalse();
        $group = Group::factory()->create(['name' => $prefix.'-scope']);
        $otherGroup = Group::factory()->create(['name' => $prefix.'-outside']);
        $a = Product::factory()->for($group)->create(['legacy_id' => $prefix.'-a', 'product_code' => $prefix.'-D60-EL-LP6-001', 'profile_fields' => ['product_pic' => '/ari/Exact-001', 'Product_pic' => 'wrong-key']]);
        $b = Product::factory()->for($group)->create(['legacy_id' => $prefix.'-b', 'product_code' => $prefix.'-D60-EL-P10-06', 'profile_fields' => []]);
        $c = Product::factory()->for($group)->create(['legacy_id' => $prefix.'-c', 'product_code' => $prefix.'-D40-HY-P10-02', 'profile_fields' => ['product_pic' => 6]]);
        Product::factory()->for($otherGroup)->create(['legacy_id' => $prefix.'-d', 'product_code' => $prefix.'-D60-EL-LP6-004']);
        $before = [$actor->fresh()->getAttributes(), $group->fresh()->getAttributes(), $a->fresh()->getAttributes()];
        $search = app(CatalogAssetRecordSearch::class);
        $scope = Product::query()->where('group_id', $group->id);
        expect($search->apply($scope, $search->validate(['query' => 'el 6'], 'product'), 'product')->orderBy('id')->pluck('id')->all())->toBe([$a->id, $b->id]);
        expect($search->apply($scope, $search->validate(['query' => 'Exact-001', 'targets' => ['reference']], 'product'), 'product')->pluck('id')->all())->toBe([$a->id]);
        expect($search->apply($scope, $search->validate(['query' => 'wrong-key', 'targets' => ['reference']], 'product'), 'product')->count())->toBe(0);
        expect($search->apply($scope, $search->validate(['exact_codes' => strtolower($a->product_code)], 'product'), 'product')->count())->toBe(0);
        expect($search->apply($scope, $search->validate(['exact_codes' => $a->product_code], 'product'), 'product')->pluck('id')->all())->toBe([$a->id]);
        $sort = $search->sortDescriptor('product', 'product_code:asc');
        $sequence = app(CatalogAssetRecordSequence::class);
        expect($sequence->boundary($scope, 'next', $sort, 2)['record_id'])->toBe($c->id)
            ->and($sequence->neighbor($scope, $c->id, 'next', $sort, 2)['record_id'])->toBe($a->id)
            ->and($sequence->neighbor($scope, $a->id, 'next', $sort, 2))->toBe(['record_id' => $b->id, 'position' => 3, 'total' => 3, 'page' => 2]);
        $page = Livewire::test(CatalogImages::class)->set('imageSearch.group_ids', [$group->id]);
        $page->call('checkImageMatches', $page->instance()->imageBrowserContext())->assertCount('selectedTableRecords', 3);
        $page->call('inspectProductImage', $a->id, $page->instance()->imageBrowserContext())->assertSet('imageRecordId', $a->id);
        expect([$actor->fresh()->getAttributes(), $group->fresh()->getAttributes(), $a->fresh()->getAttributes()])->toBe($before);
        expect(DB::transactionLevel())->toBe(1);
    } finally {
        DB::rollBack();
    }
    expect(DB::transactionLevel())->toBe(0);
    foreach ($initialCounts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
    expect(Group::where('name', $prefix.'-scope')->exists())->toBeFalse();
});
