<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';

use App\Models\Group;
use App\Models\GroupFilter;
use App\Models\RuleCondition;
use App\Models\SubGroup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('mysql profile references retain exact comparison length nullability and filter uniqueness', function () {
    $columns = DB::table('information_schema.columns')->where('table_schema', DB::connection()->getDatabaseName())
        ->whereIn('table_name', ['group_filters', 'sub_groups', 'rule_conditions'])->where('column_name', 'property_key')->get()->keyBy('TABLE_NAME');

    foreach (['group_filters', 'sub_groups', 'rule_conditions'] as $table) {
        expect($columns[$table]->COLLATION_NAME)->toBe('utf8mb4_0900_bin');
        expect((int) $columns[$table]->CHARACTER_MAXIMUM_LENGTH)->toBe(100);
        expect($columns[$table]->IS_NULLABLE)->toBe($table === 'rule_conditions' ? 'YES' : 'NO');
    }

    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Pressure']);
    GroupFilter::factory()->for($group)->create(['property_key' => 'pressure']);
    SubGroup::factory()->for($group)->create(['property_key' => 'Pressure']);
    SubGroup::factory()->for($group)->create(['property_key' => 'pressure']);
    RuleCondition::factory()->create(['property_key' => null]);

    expect(GroupFilter::query()->where('group_id', $group->id)->where('property_key', 'Pressure')->count())->toBe(1);
    expect(SubGroup::query()->where('group_id', $group->id)->where('property_key', 'Pressure')->count())->toBe(1);
    expect(fn () => GroupFilter::factory()->for($group)->create(['property_key' => 'Pressure']))->toThrow(QueryException::class);
});

test('mysql reference rollback refuses colliding keys before changing any column', function () {
    $group = Group::factory()->create();
    GroupFilter::factory()->for($group)->create(['property_key' => 'Pressure']);
    GroupFilter::factory()->for($group)->create(['property_key' => 'pressure']);
    $migration = require database_path('migrations/2026_10_23_110454_preserve_exact_product_field_references.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'colliding');

    $collations = DB::table('information_schema.columns')->where('table_schema', DB::connection()->getDatabaseName())
        ->whereIn('table_name', ['group_filters', 'sub_groups', 'rule_conditions'])->where('column_name', 'property_key')->pluck('COLLATION_NAME')->all();
    expect($collations)->toBe(['utf8mb4_0900_bin', 'utf8mb4_0900_bin', 'utf8mb4_0900_bin']);
    expect(GroupFilter::query()->where('group_id', $group->id)->count())->toBe(2);
    expect(Schema::hasColumn('products', 'profile_fields'))->toBeTrue();
});
