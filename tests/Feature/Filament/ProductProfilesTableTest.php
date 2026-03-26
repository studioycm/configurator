<?php

use App\Filament\Resources\ProductProfiles\Pages\ListProductProfiles;
use App\Models\CatalogGroup;
use App\Models\ProductProfile;
use App\Models\User;
use Livewire\Livewire;

test('product profiles can be filtered by catalog group', function () {
    $user = User::factory()->create([
        'email' => 'ycm@data4.work',
    ]);

    $this->actingAs($user);

    $primaryGroup = CatalogGroup::factory()->create([
        'name' => 'Primary Category',
    ]);
    $secondaryGroup = CatalogGroup::factory()->create([
        'name' => 'Secondary Category',
    ]);

    $matchingProfiles = ProductProfile::factory()
        ->count(2)
        ->for($primaryGroup, 'catalogGroup')
        ->create();
    $nonMatchingProfile = ProductProfile::factory()
        ->for($secondaryGroup, 'catalogGroup')
        ->create();

    Livewire::test(ListProductProfiles::class)
        ->assertTableFilterExists('catalog_group_id')
        ->assertCanSeeTableRecords($matchingProfiles->concat([$nonMatchingProfile]))
        ->filterTable('catalog_group_id', $primaryGroup->getKey())
        ->assertCanSeeTableRecords($matchingProfiles)
        ->assertCanNotSeeTableRecords([$nonMatchingProfile]);
});
