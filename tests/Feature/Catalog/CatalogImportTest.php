<?php

require_once dirname(__DIR__, 2).'/CatalogImportFixtures.php';

use App\Actions\ImportCatalogProducts;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

test('canonical import preserves all positional buckets without decoding or guessing a parent', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = catalogFixtureCsv([catalogFixtureRow()]);
    $review = app(ImportCatalogProducts::class)->handle($actor, $path);
    $this->assertDatabaseCount('products', 0);

    $result = applyCatalogFixture($actor, $path);
    $product = Product::query()->sole();

    expect($review['mode'])->toBe('dry-run');
    expect($result['totals']['created'])->toBe(1);
    expect($product->legacy_id)->toBe('00017');
    expect($product->product_code)->toBe('0042-Aa');
    expect($product->product_name)->toBe('Test air valve');
    expect($product->group->legacy_id)->toBe('2144');
    expect($product->group_id)->not->toBe(2144);
    expect($product->group->parent_id)->toBeNull();
    expect($product->properties)->toHaveCount(18)->toMatchArray(['Working_Pressure' => '25 bar', 'Connection_Size' => '1″']);
    expect($product->parts)->toHaveCount(28)->toMatchArray(['Part1' => 'Outlet<Polypropylene>', 'Part2' => '&lt;still encoded&gt;', 'Part20' => 'Slot 20', 'Part28' => 'Slot 28']);
    expect($product->extra_data)->toHaveCount(15)->toMatchArray(['Indc' => '0206', '_unnamed_column_48' => '', 'product_supplier' => 'a:1:{i:0;s:1:"0";}', 'with_s50c' => '']);
});

test('reimport keeps ids and app metadata while owned blanks win and absent products survive', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    applyCatalogFixture($actor, catalogFixtureCsv([catalogFixtureRow(), catalogFixtureRow('18', '0043')]));
    $product = Product::query()->where('legacy_id', '00017')->firstOrFail();
    $product->update(['properties' => [...$product->properties, 'app_note' => 'keep'], 'parts' => [...$product->parts, 'app_slot_note' => 'keep'], 'extra_data' => [...$product->extra_data, 'app_note' => 'keep']]);
    $parent = Group::factory()->create(['name' => 'Reviewed test parent']);
    $configurator = Configurator::factory()->create();
    $product->group->update(['parent_id' => $parent->id, 'configurator_id' => $configurator->id]);
    $row = catalogFixtureRow();
    $row[4] = '';
    $row[7] = '';
    $row[18] = '';
    $row[43] = '';
    $path = catalogFixtureCsv([$row]);

    $result = applyCatalogFixture($actor, $path);
    $repeat = applyCatalogFixture($actor, $path);

    expect($result['totals'])->toMatchArray(['updated' => 1, 'absent' => 1]);
    expect($repeat['totals'])->toMatchArray(['updated' => 0, 'unchanged' => 1, 'absent' => 1]);
    expect($product->fresh()->id)->toBe($product->id);
    expect($product->fresh()->description)->toBe('');
    expect($product->fresh()->properties)->toMatchArray(['Working_Pressure' => '', 'app_note' => 'keep']);
    expect($product->fresh()->parts)->toMatchArray(['Part1' => '', 'app_slot_note' => 'keep']);
    expect($product->fresh()->extra_data)->toMatchArray(['Indc' => '', 'app_note' => 'keep']);
    expect($product->group->fresh()->parent_id)->toBe($parent->id);
    expect($product->group->fresh()->configurator_id)->toBe($configurator->id);
    $this->assertDatabaseCount('products', 2);
});

test('duplicate or conflicting source identities fail before any catalog writes', function (string $conflict) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $first = catalogFixtureRow();
    $second = catalogFixtureRow('18', '0043');
    if ($conflict === 'identity') {
        $second[0] = $first[0];
    } elseif ($conflict === 'code') {
        $second[3] = $first[3];
    } else {
        $second[2] = 'Conflicting Group';
    }

    expect(fn () => applyCatalogFixture($actor, catalogFixtureCsv([$first, $second])))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('groups', 0);
    $this->assertDatabaseCount('products', 0);
})->with(['identity', 'code', 'group']);

test('another legacy product cannot be overwritten through a matching code', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $existing = Product::factory()->create(['legacy_id' => 'other', 'product_code' => '0042-Aa']);

    expect(fn () => applyCatalogFixture($actor, catalogFixtureCsv([catalogFixtureRow()])))->toThrow(ValidationException::class);

    expect($existing->fresh()->legacy_id)->toBe('other');
    $this->assertDatabaseCount('products', 1);
    $this->assertDatabaseCount('groups', 1);
});

test('apply rejects changed source or map hashes and unauthorized actors', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = catalogFixtureCsv([catalogFixtureRow()]);
    $action = app(ImportCatalogProducts::class);
    $review = $action->handle($actor, $path);

    expect(fn () => $action->handle($actor, $path, true, 'wrong', $review['map_hash'], parentsHash: $review['parents_hash']))->toThrow(ValidationException::class);
    expect(fn () => $action->handle($actor, $path, true, $review['source_hash'], 'wrong', parentsHash: $review['parents_hash']))->toThrow(ValidationException::class);
    expect(fn () => $action->handle(User::factory()->create(), $path))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('products', 0);
});

test('schema changes and malformed row widths are rejected', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $row = catalogFixtureRow();
    array_pop($row);

    expect(fn () => applyCatalogFixture($actor, catalogFixtureCsv([$row])))->toThrow(ValidationException::class);
    expect(fn () => applyCatalogFixture($actor, catalogFixtureCsv([catalogFixtureRow()], ['unexpected'])))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('products', 0);
});

test('explicit reviewed parent metadata creates the hierarchy without copying legacy ids into foreign keys', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);

    applyCatalogFixture($actor, catalogFixtureCsv([catalogFixtureRow()]), [
        '2144' => ['parent_legacy_id' => 'parent-1', 'parent_name' => 'Reviewed parent'],
    ]);

    $group = Group::query()->where('legacy_id', '2144')->sole();
    expect($group->parent->legacy_id)->toBe('parent-1');
    expect($group->parent->name)->toBe('Reviewed parent');
});

test('the audited private corpus imports 501 products and a repeat changes none', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = storage_path('app/imports/ari/d060.groups_2144.normalized.csv');
    expect(hash_file('sha256', $path))->toBe('5a8adbd4439bb1f020ab625d84fa6fde3a62ce67fbd74f94cf2b2e3b19758f72');

    $result = applyCatalogFixture($actor, $path);
    $repeat = applyCatalogFixture($actor, $path);

    expect($result['totals']['created'])->toBe(501);
    expect($repeat['totals'])->toMatchArray(['created' => 0, 'updated' => 0, 'unchanged' => 501]);
    $this->assertDatabaseCount('products', 501);
    $this->assertDatabaseCount('groups', 1);
});

test('the import command defaults to dry-run and requires reviewed hashes to apply', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = catalogFixtureCsv([catalogFixtureRow()]);

    $this->artisan('catalog:import-products', ['source' => $path, '--actor' => $actor->id])
        ->expectsOutputToContain('Mode: dry-run')->assertSuccessful();
    $this->assertDatabaseCount('products', 0);
    $this->artisan('catalog:import-products', ['source' => $path, '--actor' => $actor->id, '--apply' => true])
        ->assertFailed();
    $this->assertDatabaseCount('products', 0);
});

test('apply rejects parent metadata added changed or removed after review', function (array $reviewed, array $submitted) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = catalogFixtureCsv([catalogFixtureRow()]);
    $action = app(ImportCatalogProducts::class);
    $review = $action->handle($actor, $path, parents: $reviewed);

    expect(fn () => $action->handle($actor, $path, true, $review['source_hash'], $review['map_hash'], $submitted, $review['parents_hash'] ?? null))
        ->toThrow(ValidationException::class, 'Apply requires the exact reviewed parent metadata SHA-256 hash. Run a new dry-run.');

    $this->assertDatabaseCount('groups', 0);
    $this->assertDatabaseCount('products', 0);
})->with([
    'added' => [[], ['2144' => ['parent_legacy_id' => 'parent-a', 'parent_name' => 'Parent A']]],
    'changed' => [['2144' => ['parent_legacy_id' => 'parent-a', 'parent_name' => 'Parent A']], ['2144' => ['parent_legacy_id' => 'parent-b', 'parent_name' => 'Parent B']]],
    'removed' => [['2144' => ['parent_legacy_id' => 'parent-a', 'parent_name' => 'Parent A']], []],
]);

test('apply requires parent approval even when parent metadata is empty', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = catalogFixtureCsv([catalogFixtureRow()]);
    $action = app(ImportCatalogProducts::class);
    $review = $action->handle($actor, $path);

    expect(fn () => $action->handle($actor, $path, true, $review['source_hash'], $review['map_hash']))
        ->toThrow(ValidationException::class, 'Apply requires the exact reviewed parent metadata SHA-256 hash. Run a new dry-run.');

    $this->assertDatabaseCount('groups', 0);
    $this->assertDatabaseCount('products', 0);
});

test('the command applies reviewed parent metadata with its reported hash', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = catalogFixtureCsv([catalogFixtureRow()]);
    $parents = ['2144' => ['parent_legacy_id' => 'parent-a', 'parent_name' => 'Parent A']];
    $review = app(ImportCatalogProducts::class)->handle($actor, $path, parents: $parents);
    $parentsPath = tempnam(sys_get_temp_dir(), 'catalog-parents-');
    file_put_contents($parentsPath, json_encode(['2144' => array_reverse($parents['2144'], true)], JSON_THROW_ON_ERROR));
    $this->beforeApplicationDestroyed(fn () => unlink($parentsPath));
    expect($review['parents'] ?? null)->toBe($parents);

    $this->artisan('catalog:import-products', ['source' => $path, '--actor' => $actor->id, '--apply' => true,
        '--source-hash' => $review['source_hash'], '--map-hash' => $review['map_hash'],
        '--parents' => $parentsPath, '--parents-hash' => $review['parents_hash'],
    ])->expectsOutputToContain('Parents SHA-256: '.$review['parents_hash'])->assertSuccessful();

    expect(Product::query()->sole()->group->parent->legacy_id)->toBe('parent-a');
});
