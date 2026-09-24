<?php

use App\Actions\SaveCatalogGroup;
use App\Models\Configurator;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('products keep source identities separate from internal relationships and searchable codes', function () {
    $group = Group::factory()->create(['legacy_id' => '2144']);
    $product = Product::factory()->for($group)->create([
        'legacy_id' => '00017', 'legacy_group_id' => '2144',
        'product_code' => '0017-Aa', 'product_name' => 'Air valve',
        'properties' => ['pressure' => '0'], 'parts' => ['Part1' => '<metal>'],
        'extra_data' => ['unknown' => ''],
    ]);

    $stored = Product::query()->where('product_code', '0017-Aa')->orderBy('product_code')->firstOrFail();

    expect($stored->group->is($group))->toBeTrue();
    expect($stored->name)->toBe('0017-Aa');
    expect($stored->legacy_id)->toBe('00017');
    expect($stored->properties)->toBe(['pressure' => '0']);
    expect($stored->parts)->toBe(['Part1' => '<metal>']);
    expect($stored->extra_data)->toBe(['unknown' => '']);
    expect(Schema::hasColumn('products', 'name'))->toBeFalse();
});

test('catalog management uses the existing panel admission decision', function () {
    $admitted = User::factory()->create(['email' => 'ycm@data4.work']);
    $other = User::factory()->create();

    foreach ([$admitted, $other] as $user) {
        expect(Gate::forUser($user)->allows('manage-catalog'))
            ->toBe($user->canAccessPanel(Filament::getPanel('admin')));
    }

    expect(fn () => app(SaveCatalogGroup::class)->handle($other, null, ['name' => 'Denied']))
        ->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('groups', 0);

    $group = app(SaveCatalogGroup::class)->handle($admitted, null, ['name' => 'Allowed']);
    expect($group->name)->toBe('Allowed');
});

test('group changes reject cycles without modifying the tree', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $parent = Group::factory()->create();
    $child = Group::factory()->for($parent, 'parent')->create();

    expect(fn () => app(SaveCatalogGroup::class)->handle($actor, $parent, ['name' => $parent->name, 'parent_id' => $child->id]))
        ->toThrow(ValidationException::class);

    expect($parent->fresh()->parent_id)->toBeNull();
    expect($child->fresh()->parent_id)->toBe($parent->id);
});

test('groups containing products or assigned configurators cannot become parents', function (bool $hasProduct) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $parent = Group::factory()->create();
    if ($hasProduct) {
        Product::factory()->for($parent)->create();
    } else {
        $parent->update(['configurator_id' => Configurator::factory()->create()->id]);
    }

    expect(fn () => app(SaveCatalogGroup::class)->handle($actor, null, ['name' => 'Invalid child', 'parent_id' => $parent->id]))
        ->toThrow(ValidationException::class);

    expect($parent->children()->exists())->toBeFalse();
})->with([true, false]);

test('a branch cannot receive a configurator assignment', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $parent = Group::factory()->create();
    Group::factory()->for($parent, 'parent')->create();
    $configurator = Configurator::factory()->create();

    expect(fn () => app(SaveCatalogGroup::class)->handle($actor, $parent, ['name' => $parent->name, 'configurator_id' => $configurator->id]))
        ->toThrow(ValidationException::class);

    expect($parent->fresh()->configurator_id)->toBeNull();
});

test('obsolete domain routes are unavailable on the fresh catalog installation', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));

    $this->get('/admin/product-profiles')->assertNotFound();
    $this->get('/admin/config-engine-demo')->assertNotFound();
    $this->get('/admin')->assertOk();
});
