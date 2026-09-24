<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';

use App\Models\Group;
use App\Models\Product;
use Illuminate\Database\QueryException;

test('mysql preserves exact legacy identities and enforces relationship restrictions', function () {
    $upper = Group::factory()->create(['legacy_id' => 'Aa']);
    $lower = Group::factory()->create(['legacy_id' => 'aa']);
    $product = Product::factory()->for($upper)->create(['legacy_id' => '0001']);

    expect($upper->id)->not->toBe($lower->id);
    expect($product->fresh()->legacy_id)->toBe('0001');
    expect(fn () => $upper->delete())->toThrow(QueryException::class);
});
