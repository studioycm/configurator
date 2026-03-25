<?php

declare(strict_types=1);

use App\Models\FileAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('returns an absolute url for media stored on the public disk', function () {
    Storage::fake('public');

    $attachment = FileAttachment::factory()->forCatalogGroup()->create();

    $attachment
        ->addMedia(UploadedFile::fake()->image('photo.jpg'))
        ->toMediaCollection('default', 'public');

    $url = $attachment->refresh()->file_path;

    expect($url)
        ->toBeString()
        ->toStartWith(url('/storage/'))
        ->toContain('/storage/');
});

it('falls back to a legacy storage url when no media exists', function () {
    Storage::fake('public');

    $attachment = FileAttachment::factory()->forCatalogGroup()->create([
        'file_path' => 'demo/catalog/sample.pdf',
    ]);

    expect($attachment->file_path)->toBe(url('/storage/demo/catalog/sample.pdf'));
});
