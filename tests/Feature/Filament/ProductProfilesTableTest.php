<?php

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

test('products can be filtered by group', function () {
    $user = User::factory()->create([
        'email' => 'ycm@data4.work',
    ]);

    $this->actingAs($user);

    $primaryGroup = Group::factory()->create([
        'name' => 'Primary Category',
    ]);
    $secondaryGroup = Group::factory()->create([
        'name' => 'Secondary Category',
    ]);

    $matchingProfiles = Product::factory()
        ->count(2)
        ->for($primaryGroup, 'group')
        ->create();
    $nonMatchingProfile = Product::factory()
        ->for($secondaryGroup, 'group')
        ->create();

    Livewire::test(ListProducts::class)
        ->assertTableFilterExists('group_id')
        ->assertCanSeeTableRecords($matchingProfiles->concat([$nonMatchingProfile]))
        ->filterTable('group_id', $primaryGroup->getKey())
        ->assertCanSeeTableRecords($matchingProfiles)
        ->assertCanNotSeeTableRecords([$nonMatchingProfile]);
});
