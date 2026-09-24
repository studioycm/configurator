<?php

use App\Models\Group;
use App\Models\Product;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertRedirect(route('catalog.index'));
    $this->get(route('catalog.index'))->assertOk();
});

test('catalog pages require login and ordinary users cannot administer the catalog', function () {
    $group = Group::factory()->create();
    $product = Product::factory()->for($group)->create();
    foreach ([route('catalog.index'), route('catalog.groups.show', $group), route('catalog.products.show', $product), '/catalog'] as $url) {
        $this->get($url)->assertRedirect('/login');
    }
    $this->actingAs(User::factory()->create());
    $this->get(route('catalog.groups.show', $group))->assertOk();
    $this->get(route('catalog.products.show', $product))->assertOk();
    $this->get('/admin/groups')->assertForbidden();
});
