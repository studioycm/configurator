<?php

use App\FileAttachmentType;
use App\Models\CatalogGroup;
use App\Models\ConfigurationPart;
use App\Models\FileAttachment;
use App\Models\Part;
use App\Models\ProductConfiguration;
use App\Models\ProductProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('attachable models can resolve main and gallery images via file attachments', function () {
    $group = CatalogGroup::factory()->create();

    $groupMain = FileAttachment::factory()->create([
        'attachable_type' => CatalogGroup::class,
        'attachable_id' => $group->id,
        'title' => 'Main',
        'file_path' => 'demo/catalog/group/main.jpg',
        'file_type' => FileAttachmentType::MainImage,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    $groupGallery = FileAttachment::factory()->create([
        'attachable_type' => CatalogGroup::class,
        'attachable_id' => $group->id,
        'title' => 'Gallery 1',
        'file_path' => 'demo/catalog/group/gallery-1.jpg',
        'file_type' => FileAttachmentType::GalleryImage,
        'sort_order' => 1,
        'is_primary' => false,
    ]);

    expect($group->refresh()->mainImage?->is($groupMain))->toBeTrue();
    expect($group->refresh()->galleryImages->pluck('id')->all())->toContain($groupGallery->id);

    $profile = ProductProfile::factory()->create([
        'catalog_group_id' => $group->id,
    ]);

    $profileMain = FileAttachment::factory()->create([
        'attachable_type' => ProductProfile::class,
        'attachable_id' => $profile->id,
        'title' => 'Main',
        'file_path' => 'demo/products/profile/main.jpg',
        'file_type' => FileAttachmentType::MainImage,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    expect($profile->refresh()->mainImage?->is($profileMain))->toBeTrue();

    $configuration = ProductConfiguration::factory()->create([
        'product_profile_id' => $profile->id,
    ]);

    $configurationMain = FileAttachment::factory()->create([
        'attachable_type' => ProductConfiguration::class,
        'attachable_id' => $configuration->id,
        'title' => 'Main',
        'file_path' => 'demo/configurations/main.jpg',
        'file_type' => FileAttachmentType::MainImage,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    expect($configuration->refresh()->mainImage?->is($configurationMain))->toBeTrue();

    $part = Part::factory()->create();

    $partGallery = FileAttachment::factory()->create([
        'attachable_type' => Part::class,
        'attachable_id' => $part->id,
        'title' => 'Gallery 1',
        'file_path' => 'demo/parts/gallery-1.jpg',
        'file_type' => FileAttachmentType::GalleryImage,
        'sort_order' => 1,
        'is_primary' => false,
    ]);

    expect($part->refresh()->galleryImages->pluck('id')->all())->toContain($partGallery->id);

    $configurationPart = ConfigurationPart::factory()->create([
        'product_configuration_id' => $configuration->id,
        'part_id' => $part->id,
    ]);

    $configurationPartMain = FileAttachment::factory()->create([
        'attachable_type' => ConfigurationPart::class,
        'attachable_id' => $configurationPart->id,
        'title' => 'Main',
        'file_path' => 'demo/configuration-parts/main.jpg',
        'file_type' => FileAttachmentType::MainImage,
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    expect($configurationPart->refresh()->mainImage?->is($configurationPartMain))->toBeTrue();
});
