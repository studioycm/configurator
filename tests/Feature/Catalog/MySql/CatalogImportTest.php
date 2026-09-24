<?php

require_once dirname(__DIR__, 3).'/bootstrap-mysql.php';
require_once dirname(__DIR__, 3).'/CatalogImportFixtures.php';

use App\Actions\ImportCatalogProducts;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\Process;

test('a late mysql uniqueness failure rolls back groups and every product', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $path = catalogFixtureCsv([catalogFixtureRow(), catalogFixtureRow('18', '0043')]);
    Product::creating(function (Product $product): void {
        if ($product->legacy_id === '18') {
            $product->legacy_id = '00017';
        }
    });
    try {
        expect(fn () => applyCatalogFixture($actor, $path))->toThrow(QueryException::class);
    } finally {
        Event::forget('eloquent.creating: '.Product::class);
    }

    $this->assertDatabaseCount('groups', 0);
    $this->assertDatabaseCount('products', 0);
});

test('a separate process cannot apply while the shared import lock is held', function () {
    config(['cache.default' => 'file']);
    $path = catalogFixtureCsv([catalogFixtureRow()]);
    $lock = Cache::lock(ImportCatalogProducts::lockName(), 30);
    expect($lock->get())->toBeTrue();
    $code = <<<'PHP'
    require 'vendor/autoload.php';
    require 'tests/bootstrap-mysql.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    assertCatalogMySqlSafety($app);
    $actor = new App\Models\User(['email' => 'ycm@data4.work']);
    try {
        app(App\Actions\ImportCatalogProducts::class)->handle($actor, $argv[1], true, hash_file('sha256', $argv[1]), App\Services\CatalogImportParser::mapHash(), parentsHash: hash('sha256', '[]'));
        exit(1);
    } catch (Illuminate\Contracts\Cache\LockTimeoutException) {
        echo 'Concurrent apply refused';
    }
    PHP;
    $process = new Process([PHP_BINARY, '-r', $code, $path], base_path(), ['CACHE_STORE' => 'file']);
    $process->setTimeout(15);
    try {
        $process->run();
        expect($process->isSuccessful())->toBeTrue();
        expect($process->getOutput())->toBe('Concurrent apply refused');
    } finally {
        $lock->release();
    }
    $this->assertDatabaseCount('groups', 0);
    $this->assertDatabaseCount('products', 0);
});
