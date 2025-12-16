<?php

use App\Models\CatalogGroup;
use App\Models\ConfigProfile;
use App\Models\ConfigurationPart;
use App\Models\ConfigurationSpecification;
use App\Models\Part;
use App\Models\ProductConfiguration;
use App\Models\ProductProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('model relationships resolve correctly with explicit foreign/local keys', function () {
    $parentGroup = CatalogGroup::factory()->create();
    $childGroup = CatalogGroup::factory()->create([
        'parent_id' => $parentGroup->id,
    ]);

    expect($childGroup->parent?->is($parentGroup))->toBeTrue();
    expect($parentGroup->children->contains($childGroup))->toBeTrue();

    $productProfile = ProductProfile::factory()->create([
        'catalog_group_id' => $parentGroup->id,
    ]);

    expect($productProfile->catalogGroup?->is($parentGroup))->toBeTrue();
    expect($parentGroup->refresh()->productProfiles->contains($productProfile))->toBeTrue();

    $configProfile = ConfigProfile::factory()->create([
        'product_profile_id' => $productProfile->id,
    ]);

    expect($configProfile->productProfile?->is($productProfile))->toBeTrue();
    expect($productProfile->refresh()->configProfiles->contains($configProfile))->toBeTrue();

    $productConfiguration = ProductConfiguration::factory()->create([
        'product_profile_id' => $productProfile->id,
    ]);

    expect($productConfiguration->productProfile?->is($productProfile))->toBeTrue();
    expect($productProfile->refresh()->productConfigurations->contains($productConfiguration))->toBeTrue();

    $part = Part::factory()->create();
    $configurationPart = ConfigurationPart::factory()->create([
        'product_configuration_id' => $productConfiguration->id,
        'part_id' => $part->id,
    ]);

    expect($configurationPart->productConfiguration?->is($productConfiguration))->toBeTrue();
    expect($configurationPart->part?->is($part))->toBeTrue();
    expect($productConfiguration->refresh()->configurationParts->contains($configurationPart))->toBeTrue();
    expect($part->refresh()->configurationParts->contains($configurationPart))->toBeTrue();

    $configurationSpecification = ConfigurationSpecification::factory()->create([
        'product_configuration_id' => $productConfiguration->id,
    ]);

    expect($configurationSpecification->productConfiguration?->is($productConfiguration))->toBeTrue();
    expect($productConfiguration->refresh()->configurationSpecifications->contains($configurationSpecification))->toBeTrue();
});
