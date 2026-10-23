<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';

use App\Models\Group;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('mysql preserves exact legacy identities and enforces relationship restrictions', function () {
    $upper = Group::factory()->create(['legacy_id' => 'Aa']);
    $lower = Group::factory()->create(['legacy_id' => 'aa']);
    $product = Product::factory()->for($upper)->create(['legacy_id' => '0001']);

    expect($upper->id)->not->toBe($lower->id);
    expect($product->fresh()->legacy_id)->toBe('0001');
    expect(fn () => $upper->delete())->toThrow(QueryException::class);
});

test('mysql profile upgrade and rollback retain existing structured fields and numbered parts', function () {
    $product = Product::factory()->create(['profile_fields' => (object) [
        'Working_Pressure' => '001', 'Indc' => 'legacy', 'pressure' => 'custom case',
        'custom' => (object) ['empty' => (object) [], 'list' => [], 'enabled' => false, 'number' => 7],
    ], 'parts' => ['Part1' => '001', 'Part28' => '<unchanged>']]);
    $migration = require database_path('migrations/2026_10_23_110018_consolidate_product_profile_fields.php');

    $migration->down();

    $legacy = DB::table('products')->where('id', $product->id)->first();
    expect(json_decode($legacy->extra_data, flags: JSON_THROW_ON_ERROR))->toEqual((object) ['Indc' => 'legacy']);
    expect(json_decode($legacy->properties, flags: JSON_THROW_ON_ERROR)->custom->empty)->toBeInstanceOf(stdClass::class);
    expect(Schema::hasColumn('products', 'profile_fields'))->toBeFalse();

    $migration->up();

    $profile = json_decode(DB::table('products')->where('id', $product->id)->value('profile_fields'), flags: JSON_THROW_ON_ERROR);
    expect($profile->Working_Pressure)->toBe('001');
    expect($profile->Indc)->toBe('legacy');
    expect($profile->pressure)->toBe('custom case');
    expect($profile->custom->empty)->toBeInstanceOf(stdClass::class);
    expect($profile->custom->list)->toBe([]);
    expect($profile->custom->enabled)->toBeFalse();
    expect($profile->custom->number)->toBe(7);
    expect($product->fresh()->parts)->toBe(['Part1' => '001', 'Part28' => '<unchanged>']);
});
