<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Legacy\Models\OptionRule;

beforeEach(function () {
    Schema::create('legacy_cast_roundtrips', function (Blueprint $table): void {
        $table->id();
        $table->text('allowed_option_ids')->nullable();
    });
});

test('the legacy consumer stores encoded normalized IDs and reloads the same values', function (mixed $input, ?string $stored, array $expected) {
    $rule = (new OptionRule)->setTable('legacy_cast_roundtrips');
    $rule->timestamps = false;
    $rule->allowed_option_ids = $input;
    $rule->save();

    expect(DB::table('legacy_cast_roundtrips')->where('id', $rule->id)->value('allowed_option_ids'))->toBe($stored)
        ->and($rule->fresh()->allowed_option_ids)->toBe($expected);
})->with([
    'nonempty' => [[3, '2', 3, '001'], '[1,2,3]', [1, 2, 3]],
    'empty' => [[], '[]', []],
    'null' => [null, null, []],
]);

test('the legacy consumer retains its lenient malformed and scalar read behavior', function (string $stored, array $expected) {
    $rule = (new OptionRule)->setTable('legacy_cast_roundtrips');
    $rule->timestamps = false;
    $rule->allowed_option_ids = [];
    $rule->save();
    DB::table('legacy_cast_roundtrips')->where('id', $rule->id)->update(['allowed_option_ids' => $stored]);

    expect($rule->fresh()->allowed_option_ids)->toBe($expected);
})->with([
    'malformed' => ['broken-json', []],
    'scalar' => ['4', []],
    'lenient integers' => ['["3",2,3,null,false,true]', [0, 1, 2, 3]],
]);
