<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function withLegacyProductProfileSchema(Closure $assertions): void
{
    $connection = config('database.default');
    config(['database.default' => 'product_profile_migration', 'database.connections.product_profile_migration' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::purge('product_profile_migration');

    try {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('groups')->insert(['id' => 1]);
        $original = require database_path('migrations/2026_09_23_171255_create_products_table.php');
        $original->up();
        $migration = require database_path('migrations/2026_10_23_110018_consolidate_product_profile_fields.php');
        $assertions($migration);
    } finally {
        DB::purge('product_profile_migration');
        config(['database.default' => $connection]);
    }
}

function insertLegacyProductProfile(int $id, string $properties, string $extraData): void
{
    DB::table('products')->insert([
        'id' => $id, 'legacy_id' => (string) $id, 'legacy_group_id' => 'source-group', 'group_id' => 1,
        'product_code' => 'CODE-'.$id, 'product_name' => 'Product', 'description' => '',
        'properties' => $properties, 'extra_data' => $extraData, 'parts' => '{"Part1":"001","Part28":"<unchanged>"}',
        'created_at' => '2026-10-17 12:00:00', 'updated_at' => '2026-10-17 12:00:00',
    ]);
}

test('profile migration preserves exact keys nested JSON types and untouched product values', function () {
    withLegacyProductProfileSchema(function (Migration $migration): void {
        insertLegacyProductProfile(1, '{"Pressure":"001","pressure":"25 bar","shared":{"empty":{},"list":[],"enabled":false,"quantity":1,"nothing":null},"0":"zero","עברית":"yes"}', '{"product_supplier":"legacy","shared":{"nothing":null,"quantity":1,"enabled":false,"list":[],"empty":{}},"a.b":"literal","a\\\\b":"slash"}');

        $migration->up();

        expect(Schema::hasColumn('products', 'profile_fields'))->toBeTrue();
        $product = DB::table('products')->sole();
        $profile = json_decode($product->profile_fields, flags: JSON_THROW_ON_ERROR);
        expect($profile)->toEqual((object) [
            'Pressure' => '001', 'pressure' => '25 bar',
            'shared' => (object) ['empty' => (object) [], 'list' => [], 'enabled' => false, 'quantity' => 1, 'nothing' => null],
            '0' => 'zero', 'עברית' => 'yes', 'product_supplier' => 'legacy', 'a.b' => 'literal', 'a\\b' => 'slash',
        ]);
        expect($profile->shared->empty)->toBeInstanceOf(stdClass::class);
        expect($profile->shared->list)->toBe([]);
        expect($profile->shared->enabled)->toBeFalse();
        expect($profile->shared->quantity)->toBe(1);
        expect($product->parts)->toBe('{"Part1":"001","Part28":"<unchanged>"}');
        expect($product->product_code)->toBe('CODE-1');
        expect($product->updated_at)->toBe('2026-10-17 12:00:00');
        expect(Schema::hasColumn('products', 'properties'))->toBeFalse();
        expect(Schema::hasColumn('products', 'extra_data'))->toBeFalse();
    });
});

test('profile migration rejects a later conflicting key before any schema or row changes', function (string $propertyValue, string $extraValue) {
    withLegacyProductProfileSchema(function (Migration $migration) use ($propertyValue, $extraValue): void {
        insertLegacyProductProfile(1, '{"safe":"keep"}', '{}');
        insertLegacyProductProfile(2, '{"shared":'.$propertyValue.'}', '{"shared":'.$extraValue.'}');

        expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'product 2');

        expect(Schema::hasColumn('products', 'profile_fields'))->toBeFalse();
        expect(DB::table('products')->where('id', 1)->value('properties'))->toBe('{"safe":"keep"}');
        expect(DB::table('products')->where('id', 2)->value('extra_data'))->toBe('{"shared":'.$extraValue.'}');
    });
})->with([
    'text versus number' => ['1', '"1"'],
    'object versus list' => ['{}', '[]'],
    'null versus blank' => ['null', '""'],
    'nested type difference' => ['{"enabled":false}', '{"enabled":"false"}'],
    'different ordered list' => ['["a","b"]', '["b","a"]'],
]);

test('profile rollback retains every current value and restores known legacy extra fields', function () {
    withLegacyProductProfileSchema(function (Migration $migration): void {
        insertLegacyProductProfile(1, '{"Working_Pressure":"25 bar"}', '{"Indc":"001"}');
        $migration->up();
        expect(Schema::hasColumn('products', 'profile_fields'))->toBeTrue();
        DB::table('products')->where('id', 1)->update(['profile_fields' => '{"Working_Pressure":"25 bar","Indc":"001","custom":{"empty":{},"list":[]},"001":"number key","indc":false}']);

        $migration->down();

        $product = DB::table('products')->sole();
        expect(json_decode($product->properties, flags: JSON_THROW_ON_ERROR))->toEqual((object) [
            'Working_Pressure' => '25 bar', 'custom' => (object) ['empty' => (object) [], 'list' => []], '001' => 'number key', 'indc' => false,
        ]);
        expect(json_decode($product->extra_data, flags: JSON_THROW_ON_ERROR))->toEqual((object) ['Indc' => '001']);
        expect(Schema::hasColumn('products', 'profile_fields'))->toBeFalse();

        $migration->up();

        expect(json_decode(DB::table('products')->where('id', 1)->value('profile_fields'), flags: JSON_THROW_ON_ERROR))
            ->toEqual((object) ['Working_Pressure' => '25 bar', 'Indc' => '001', 'custom' => (object) ['empty' => (object) [], 'list' => []], '001' => 'number key', 'indc' => false]);
    });
});

test('profile migration rejects values that cannot roundtrip through PHP before changing the schema', function () {
    withLegacyProductProfileSchema(function (Migration $migration): void {
        insertLegacyProductProfile(1, '{"quantity":1e9999}', '{}');

        expect(fn () => $migration->up())->toThrow(JsonException::class);

        expect(Schema::hasColumn('products', 'profile_fields'))->toBeFalse();
        expect(DB::table('products')->where('id', 1)->value('properties'))->toBe('{"quantity":1e9999}');
    });
});

test('profile migration refuses numeric precision loss before any schema change', function (string $number) {
    withLegacyProductProfileSchema(function (Migration $migration) use ($number): void {
        insertLegacyProductProfile(1, '{"custom":{"number":'.$number.'},"text":"9223372036854775809 1e9999"}', '{}');

        expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'numeric');

        expect(Schema::hasColumn('products', 'profile_fields'))->toBeFalse();
        expect(DB::table('products')->where('id', 1)->value('properties'))->toBe('{"custom":{"number":'.$number.'},"text":"9223372036854775809 1e9999"}');
    });
})->with([
    'integer beyond PHP precision' => ['9223372036854775809'],
    'decimal beyond PHP precision' => ['0.1234567890123456789012345'],
    'decimal underflow' => ['1e-9999'],
]);
