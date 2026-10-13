<?php

use App\Filament\Resources\Products\ProductResource;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminNavigationCounts;
use Illuminate\Support\Facades\DB;

test('resource totals are authorized and invalidated after creation and deletion', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $counts = app(AdminNavigationCounts::class);
    $counts->forget(Attribute::class);
    expect($counts->total(Attribute::class))->toBe('0');
    $attribute = Attribute::factory()->create();
    expect($counts->total(Attribute::class))->toBe('1');
    $attribute->delete();
    expect($counts->total(Attribute::class))->toBe('0');
    $this->actingAs(User::factory()->create());
    expect($counts->total(Attribute::class))->toBeNull();
});

test('Product resource badges reuse the shared cache outside write transactions', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    Product::factory()->create();
    app(AdminNavigationCounts::class)->forget(Product::class);
    $connection = DB::connection();
    DB::shouldReceive('transactionLevel')->andReturn(0);
    DB::shouldReceive('connection')->andReturn($connection);
    $connection->enableQueryLog();

    expect(ProductResource::getNavigationBadge())->toBe('1');
    expect(ProductResource::getNavigationBadge())->toBe('1');

    $queries = collect($connection->getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'count(*)') && str_contains($query['query'], 'products'));
    $connection->disableQueryLog();
    expect($queries)->toHaveCount(1);
});
