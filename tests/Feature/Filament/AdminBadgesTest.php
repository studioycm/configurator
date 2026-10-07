<?php

use App\Models\Attribute;
use App\Models\User;
use App\Services\AdminNavigationCounts;

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
