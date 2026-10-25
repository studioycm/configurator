<?php

use Illuminate\Support\Facades\DB;

test('late named and on-demand connections are refused before opening a sqlite file', function (bool $onDemand) {
    $path = sys_get_temp_dir().'/consolidation-forbidden-'.bin2hex(random_bytes(6)).'.sqlite';
    $connection = ['driver' => 'sqlite', 'database' => $path];
    config(['database.connections.forbidden_alias' => $connection]);
    try {
        expect(fn () => ($onDemand ? DB::build($connection) : DB::connection('forbidden_alias'))->getPdo())
            ->toThrow(RuntimeException::class, 'Unsafe test database configuration');
        expect(file_exists($path))->toBeFalse();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
})->with(['named' => [false], 'on demand' => [true]]);

test('a safe runtime alias remains usable through the real database manager', function () {
    config(['database.connections.safe_alias' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    expect(DB::connection('safe_alias')->selectOne('select 7 as value')->value)->toBe(7);
});
