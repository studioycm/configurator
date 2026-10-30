<?php

use App\Models\Group;
use App\Models\Product;
use App\Services\CatalogSnapshots;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** @return array{status:int,output:string} */
function snapshotTerminal(string $group, bool $invalidate = false): array
{
    $input = new ArrayInput(['command' => 'catalog:snapshot', 'group' => $group, '--invalidate' => $invalidate, '--no-ansi' => true]);
    $output = new BufferedOutput;
    $status = app(Kernel::class)->handle($input, $output);

    return ['status' => $status, 'output' => $output->fetch()];
}

test('snapshot terminal rejects malformed IDs without changing data', function () {
    $group = Group::factory()->create();
    DB::disableQueryLog();

    $result = snapshotTerminal('not-an-id', true);

    expect($result['status'])->toBe(2);
    expect($result['output'])->toContain('Provide a positive internal Group ID.');
    expect((string) $group->fresh()->catalog_revision)->toBe('1');
    expect(DB::logging())->toBeFalse();
});

test('snapshot terminal renders rejected Group admission before invalidation', function (string $kind) {
    $group = Group::factory()->create(['is_active' => $kind !== 'unavailable']);
    if ($kind === 'branch') {
        Group::factory()->create(['parent_id' => $group->id]);
    }
    $snapshots = app(CatalogSnapshots::class);
    $key = $snapshots->key($group->id);
    Cache::put($key, ['unchanged' => true]);
    DB::disableQueryLog();

    $result = snapshotTerminal((string) $group->id, true);

    expect($result['status'])->toBe(1);
    expect(trim($result['output']))->not->toBeEmpty();
    expect((string) $group->fresh()->catalog_revision)->toBe('1');
    expect(Cache::get($key))->toBe(['unchanged' => true]);
    expect(DB::logging())->toBeFalse();
})->with(['unavailable', 'branch']);

test('snapshot terminal returns valid leaf JSON and disables diagnostic query logging', function () {
    $group = Group::factory()->create();
    Product::factory()->for($group)->create();
    DB::disableQueryLog();

    $result = snapshotTerminal((string) $group->id);

    expect($result['status'])->toBe(0);
    $data = json_decode($result['output'], true, flags: JSON_THROW_ON_ERROR);
    expect($data)->toMatchArray(['group_id' => (string) $group->id, 'revision' => '1', 'products' => 1]);
    expect($data['json_bytes'])->toBeGreaterThan(0);
    expect(DB::logging())->toBeFalse();
});

test('snapshot terminal reports lock contention and retains already applied invalidation', function (bool $invalidate) {
    $group = Group::factory()->create();
    $snapshots = app(CatalogSnapshots::class);
    $key = $snapshots->key($group->id);
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->andThrow(new LockTimeoutException);
    $cache = Mockery::mock(Cache::getFacadeRoot());
    $cache->shouldReceive('lock')->once()->with($key.':rebuild', 60)->andReturn($lock);
    Cache::swap($cache);
    DB::disableQueryLog();

    $result = snapshotTerminal((string) $group->id, $invalidate);

    expect($result['status'])->toBe(1);
    expect($result['output'])->toContain(__('catalog.messages.the_catalog_is_being_prepared_please_retry'));
    expect((string) $group->fresh()->catalog_revision)->toBe($invalidate ? '2' : '1');
    expect(Cache::has($key))->toBeFalse();
    expect(DB::logging())->toBeFalse();
})->with([false, true]);

test('snapshot terminal renders unexpected failures without misclassifying them as contention', function () {
    $group = Group::factory()->create();
    $key = app(CatalogSnapshots::class)->key($group->id);
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->andThrow(new RuntimeException('Unexpected snapshot cache failure'));
    $cache = Mockery::mock(Cache::getFacadeRoot());
    $cache->shouldReceive('lock')->once()->with($key.':rebuild', 60)->andReturn($lock);
    Cache::swap($cache);
    DB::disableQueryLog();

    $result = snapshotTerminal((string) $group->id);

    expect($result['status'])->toBe(1);
    expect($result['output'])->toContain('Unexpected snapshot cache failure');
    expect($result['output'])->not->toContain(__('catalog.messages.the_catalog_is_being_prepared_please_retry'));
    expect((string) $group->fresh()->catalog_revision)->toBe('1');
    expect(DB::logging())->toBeFalse();
});
