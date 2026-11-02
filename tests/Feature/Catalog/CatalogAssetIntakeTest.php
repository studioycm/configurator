<?php

use App\Actions\ReviewCatalogAssets;
use App\Models\CatalogAssetImportSource;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetIntake;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetSources;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @return array<string, mixed> */
function intakeSourceDescriptor(int $count = 2, int $bytes = 10): array
{
    $entries = [];
    for ($i = 0; $i < $count; $i++) {
        $entries[] = ['entry_id' => (string) Str::ulid(), 'name' => 'image-'.$i.'.png', 'relative_path' => 'folder/image-'.$i.'.png', 'bytes' => $bytes];
    }

    return ['id' => (string) Str::ulid(), 'intake_id' => (string) Str::ulid(), 'file_count' => $count,
        'byte_count' => $count * $bytes, 'selection_sha256' => app(CatalogAssetReviewFiles::class)->digest($entries), 'entries' => $entries];
}

beforeEach(function () {
    catalogImageTestDisk('catalog-asset-imports');
    catalogImageTestDisk('tmp-for-tests');
    config(['catalog-assets.enabled' => true]);
});

test('batch Begin reconciles the same source identity without resetting copied progress', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $descriptor = intakeSourceDescriptor();
    $sources = app(CatalogAssetSources::class);

    $source = $sources->startUpload($actor, 'product.main_image', [], 'Batch source', $descriptor);
    $first = $descriptor['entries'][0];
    $sources->reserveUpload($actor, $source->id, $first['entry_id'], array_diff_key($first, ['entry_id' => true]));
    $before = $source->fresh()->getRawOriginal();
    $again = $sources->startUpload($actor, 'product.main_image', [], 'Batch source', $descriptor);

    expect($source->id)->toBe($descriptor['id']);
    expect($again->id)->toBe($source->id);
    expect($again->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('catalog_asset_import_sources', 1);
});

test('batch Begin rejects changed meaning for an existing source without modifying it', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $descriptor = intakeSourceDescriptor();
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Batch source', $descriptor);
    $before = $source->fresh()->getRawOriginal();

    expect(fn () => $sources->startUpload($actor, 'product.main_image', [], 'Another label', $descriptor))
        ->toThrow(ValidationException::class);

    expect($source->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('catalog_asset_import_sources', 1);
});

test('batch reservations enforce the smaller declared source count and byte budgets', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $descriptor = intakeSourceDescriptor(1);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'One file', $descriptor);
    $entry = $descriptor['entries'][0];

    expect(fn () => $sources->reserveUpload($actor, $source->id, $entry['entry_id'], ['name' => 'image-0.png', 'relative_path' => 'folder/image-0.png', 'bytes' => 11]))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseHas('catalog_asset_import_sources', ['id' => $source->id, 'unsettled_uploads' => 0]);
    $sources->reserveUpload($actor, $source->id, $entry['entry_id'], array_diff_key($entry, ['entry_id' => true]));
    $before = $source->fresh()->getRawOriginal();

    expect(fn () => $sources->reserveUpload($actor, $source->id, (string) Str::ulid(), ['name' => 'extra.png', 'relative_path' => 'extra.png', 'bytes' => 0]))
        ->toThrow(ValidationException::class);
    expect($source->fresh()->getRawOriginal())->toBe($before);
});

test('Finish cannot seal an empty fraction of a declared batch selection', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Pending files', intakeSourceDescriptor());
    $before = $source->fresh()->getRawOriginal();

    expect(fn () => $sources->sealUploads($actor, $source->id, 0))->toThrow(ValidationException::class);

    expect($source->fresh()->getRawOriginal())->toBe($before);
});

test('generic individual sources retain their existing newly generated identities', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);

    $first = $sources->startUpload($actor, 'product.main_image', [], 'Individual image upload');
    $second = $sources->startUpload($actor, 'product.main_image', [], 'Individual image upload');

    expect($first->id)->not->toBe($second->id);
    expect($first->source)->not->toHaveKey('intake');
    $this->assertDatabaseCount('catalog_asset_import_sources', 2);
});

/** @return array{token: string, product: Product} */
function intakeReviewLaunch(User $actor): array
{
    $product = Product::factory()->create();
    $launch = app(ReviewCatalogAssets::class)->freeze($actor, 'product.main_image', [], [$product->id], [
        ['column' => 'products.id', 'direction' => 'asc'],
    ]);

    return ['token' => $launch['token'], 'product' => $product];
}

/** @return array{name: string, relative_path: string, bytes: int} */
function intakeUploadedMetadata(UploadedFile $file, string $relative): array
{
    return ['name' => $file->getClientOriginalName(), 'relative_path' => $relative, 'bytes' => $file->getSize()];
}

test('intake issuance is pure and a copied selection becomes scan ready without assigning owners', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('valve.png', 40, 20);
    $metadata = intakeUploadedMetadata($file, 'models/valve.png');
    $disk = Storage::disk('catalog-asset-imports');
    $existingFiles = $disk->allFiles();
    $intake = app(CatalogAssetIntake::class);

    $issued = $intake->issue($actor, $launch['token'], 'Native batch', [$metadata]);

    $this->assertDatabaseCount('catalog_asset_import_sources', 0);
    expect($disk->allFiles())->toBe($existingFiles);
    $started = $intake->begin($actor, $launch['token'], $issued['intake_token']);
    expect($started['source_id'])->toBe($issued['source_id']);
    $entry = $intake->entry($actor, $launch['token'], $started['source_id'], 0);
    $copied = $intake->transfer($actor, $launch['token'], $entry['token'], $file);
    expect($copied['file_count'])->toBe(1);
    expect($copied['byte_count'])->toBe($metadata['bytes']);
    expect($copied['current_entry']['committed'])->toBeTrue();
    $finished = $intake->finish($actor, $launch['token'], $started['source_id'], $copied['revision']);
    expect($finished['stage'])->toBe('scanning');
    expect($finished['uploads_sealed'])->toBeTrue();
    expect($launch['product']->fresh()->main_image_id)->toBeNull();
    $this->assertDatabaseCount('catalog_assets', 0);
    $this->assertDatabaseCount('media', 0);
});

test('a lost successful transfer acknowledgement reconciles without another body or file count', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('same.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Lost acknowledgement', [intakeUploadedMetadata($file, 'a/same.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $intake->transfer($actor, $launch['token'], $entry['token'], $file);
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    $before = $source->getRawOriginal();
    $disk = Storage::disk('catalog-asset-imports');
    $filesBefore = $disk->allFiles();
    $tempBefore = Storage::disk('tmp-for-tests')->allFiles();

    $status = $intake->status($actor, $launch['token'], $source->id, $entry['entry_id']);
    $replayed = $intake->transfer($actor, $launch['token'], $entry['token'], null);

    expect($status['current_entry']['committed'])->toBeTrue();
    expect($replayed['file_count'])->toBe(1);
    expect($source->fresh()->getRawOriginal())->toBe($before);
    expect($disk->allFiles())->toBe($filesBefore);
    expect(Storage::disk('tmp-for-tests')->allFiles())->toBe($tempBefore);
});

test('batch HTTP transport rejects an unauthenticated request before source creation', function () {
    $this->postJson('/catalog-images/batch-upload')->assertUnauthorized();
    $this->assertDatabaseCount('catalog_asset_import_sources', 0);
});

test('an invalid first body does not reserve an intake slot or change source progress', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('expected.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Unchanged on rejection', [intakeUploadedMetadata($file, 'expected.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    $before = $source->getRawOriginal();

    expect(fn () => $intake->transfer($actor, $launch['token'], $entry['token'], UploadedFile::fake()->image('wrong.png', 40, 20)))
        ->toThrow(ValidationException::class, 'The image does not match its reserved name and bytes.');

    expect($source->fresh()->getRawOriginal())->toBe($before);
    expect(Storage::disk('tmp-for-tests')->allFiles())->toBe([]);
});

test('the signed batch HTTP endpoint copies a real file and reconciles a bodyless replay', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('http.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'HTTP selection', [intakeUploadedMetadata($file, 'http.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $this->actingAs($actor);

    $response = $this->post($entry['url'], ['launch_token' => $launch['token'], 'files' => [$file]], ['Accept' => 'application/json']);

    $response->assertJsonPath('current_entry.committed', true)->assertJsonPath('file_count', 1);
    $this->postJson($entry['url'], ['launch_token' => $launch['token']])->assertJsonPath('file_count', 1);
    expect($launch['product']->fresh()->main_image_id)->toBeNull();
    $this->assertDatabaseCount('catalog_assets', 0);
});

test('committed transfer cleanup cannot follow a replaced temporary directory', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('cleanup.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Confined cleanup', [intakeUploadedMetadata($file, 'cleanup.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $intake->transfer($actor, $launch['token'], $entry['token'], $file);
    $imports = Storage::disk('catalog-asset-imports');
    $receipt = json_decode($imports->get('intakes/'.$issued['source_id'].'/v1/'.$entry['entry_id'].'/binding.json'), true, flags: JSON_THROW_ON_ERROR);
    $temp = Storage::disk('tmp-for-tests');
    $outside = catalogImageTestDisk('catalog-intake-outside');
    $outside->put($receipt['basename'].'.json', 'must remain untouched');
    $directory = $temp->path($receipt['directory']);
    rmdir($directory);
    symlink($outside->path(''), $directory);

    try {
        expect(fn () => $intake->transfer($actor, $launch['token'], $entry['token'], null))->toThrow(DomainException::class, 'path_invalid');
        expect($outside->get($receipt['basename'].'.json'))->toBe('must remain untouched');
    } finally {
        unlink($directory);
    }
});

test('two same basename files in different folders remain distinct committed entries', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $files = [UploadedFile::fake()->image('valve.png', 40, 20), UploadedFile::fake()->image('valve.png', 20, 40)];
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Folders', [intakeUploadedMetadata($files[0], 'a/valve.png'), intakeUploadedMetadata($files[1], 'b/valve.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);

    foreach ($files as $index => $file) {
        $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], $index);
        $progress = $intake->transfer($actor, $launch['token'], $entry['token'], $file);
    }

    expect($progress['file_count'])->toBe(2);
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    expect(array_column(array_column(array_values($source->upload_ledger), 'entry'), 'relative_path'))->toBe(['a/valve.png', 'b/valve.png']);
    expect(array_unique(array_column(array_values($source->upload_ledger), 'reference_hash')))->toHaveCount(2);
});

test('an interrupted unbound copy retains its receipt and explicitly repairs only the same file', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('resume.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Interrupted copy', [intakeUploadedMetadata($file, 'resume.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $armed = true;
    CatalogAssetImportSource::updating(function ($source) use (&$armed, $entry): void {
        if ($armed && ($source->upload_ledger[$entry['entry_id']]['reference_hash'] ?? null) !== null) {
            $armed = false;
            throw new RuntimeException('Synthetic interruption before binding commits.');
        }
    });
    expect(fn () => $intake->transfer($actor, $launch['token'], $entry['token'], $file))
        ->toThrow(RuntimeException::class, 'Synthetic interruption before binding commits.');
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    expect($source->upload_ledger[$entry['entry_id']]['reference_hash'])->toBeNull();
    $imports = Storage::disk('catalog-asset-imports');
    $receiptPath = 'intakes/'.$source->id.'/v1/'.$entry['entry_id'].'/binding.json';
    $receiptBody = $imports->get($receiptPath);
    $receipt = json_decode($receiptBody, true, flags: JSON_THROW_ON_ERROR);
    $temp = Storage::disk('tmp-for-tests');
    $temp->put($receipt['directory'].'/'.$receipt['basename'], 'partial copy');
    $before = $source->getRawOriginal();

    $status = $intake->status($actor, $launch['token'], $source->id, $entry['entry_id']);

    expect($status['needs_file'])->toBeTrue();
    expect($source->fresh()->getRawOriginal())->toBe($before);
    expect($temp->get($receipt['directory'].'/'.$receipt['basename']))->toBe('partial copy');
    $continued = $intake->transfer($actor, $launch['token'], $entry['token'], $file);
    expect($continued['current_entry']['committed'])->toBeTrue();
    expect($continued['file_count'])->toBe(1);
    expect($imports->get($receiptPath))->toBe($receiptBody);
    expect($source->fresh()->upload_ledger[$entry['entry_id']]['reference_hash'])->toBe(hash('sha256', $receipt['reference']));
});

test('a bound missing copy is unresolved without replacing its bytes or source binding', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('bound.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Bound interrupted copy', [intakeUploadedMetadata($file, 'bound.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $armed = true;
    CatalogAssetImportSource::updating(function ($source) use (&$armed, $entry): void {
        if ($armed && ($source->upload_ledger[$entry['entry_id']]['status'] ?? null) === 'committed') {
            $armed = false;
            throw new RuntimeException('Synthetic interruption before committed response.');
        }
    });
    $intake->transfer($actor, $launch['token'], $entry['token'], $file);
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    expect($source->upload_ledger[$entry['entry_id']]['reference_hash'])->not->toBeNull();
    $receipt = json_decode(Storage::disk('catalog-asset-imports')->get('intakes/'.$source->id.'/v1/'.$entry['entry_id'].'/binding.json'), true, flags: JSON_THROW_ON_ERROR);
    $temp = Storage::disk('tmp-for-tests');
    $temp->delete($receipt['directory'].'/'.$receipt['basename']);
    $this->travel(config('catalog-assets.requests.claim_seconds') + 1)->seconds();
    $before = $source->fresh()->getRawOriginal();

    $status = $intake->status($actor, $launch['token'], $source->id, $entry['entry_id']);
    $continued = $intake->transfer($actor, $launch['token'], $entry['token'], $file);

    expect($status['recovery'])->toBe('unresolved');
    expect($continued['recovery'])->toBe('unresolved');
    expect($source->fresh()->getRawOriginal())->toBe($before);
    expect($temp->exists($receipt['directory'].'/'.$receipt['basename']))->toBeFalse();
    expect($launch['product']->fresh()->main_image_id)->toBeNull();
});

test('the durable receipt exists before a body copy fails and can resume with its original identity', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('write-failure.png', 40, 20);
    $failing = new class($file->getPathname(), $file->getClientOriginalName(), 'image/png', null, true) extends UploadedFile
    {
        public function storeAs(mixed $path, mixed $name = null, mixed $options = []): mixed
        {
            throw new RuntimeException('Synthetic native body storage failure.');
        }
    };
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Before body copy', [intakeUploadedMetadata($file, 'write-failure.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);

    expect(fn () => $intake->transfer($actor, $launch['token'], $entry['token'], $failing))->toThrow(RuntimeException::class, 'Synthetic native body storage failure.');

    $imports = Storage::disk('catalog-asset-imports');
    $path = 'intakes/'.$issued['source_id'].'/v1/'.$entry['entry_id'].'/binding.json';
    $receipt = $imports->get($path);
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    expect($source->upload_ledger[$entry['entry_id']]['reference_hash'])->toBeNull();
    expect($intake->status($actor, $launch['token'], $source->id, $entry['entry_id'])['needs_file'])->toBeTrue();
    expect($intake->transfer($actor, $launch['token'], $entry['token'], $file)['current_entry']['committed'])->toBeTrue();
    expect($imports->get($path))->toBe($receipt);
});

test('expired HTTP capabilities do not copy and same entry renewal preserves source identity', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($actor);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('renew.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Renew explicitly', [intakeUploadedMetadata($file, 'renew.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $first = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $before = CatalogAssetImportSource::findOrFail($issued['source_id'])->getRawOriginal();
    $this->travel(301)->seconds();

    $this->postJson($first['url'], ['launch_token' => $launch['token']])->assertUnauthorized();

    expect(CatalogAssetImportSource::findOrFail($issued['source_id'])->getRawOriginal())->toBe($before);
    $second = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    expect($second['entry_id'])->toBe($first['entry_id']);
    expect($second['expires_at'])->toBeGreaterThan($first['expires_at']);
    expect($intake->transfer($actor, $launch['token'], $second['token'], $file)['file_count'])->toBe(1);
});

test('a different frozen launch cannot use a transfer capability issued for the earlier launch', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $first = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('scope.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $first['token'], 'Exact invoking scope', [intakeUploadedMetadata($file, 'scope.png')]);
    $intake->begin($actor, $first['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $first['token'], $issued['source_id'], 0);
    $second = intakeReviewLaunch($actor);
    $before = CatalogAssetImportSource::findOrFail($issued['source_id'])->getRawOriginal();
    $files = Storage::disk('catalog-asset-imports')->allFiles();

    expect(fn () => $intake->transfer($actor, $second['token'], $entry['token'], $file))->toThrow(ValidationException::class, 'This transfer belongs to a different review.');

    expect(CatalogAssetImportSource::findOrFail($issued['source_id'])->getRawOriginal())->toBe($before);
    expect(Storage::disk('catalog-asset-imports')->allFiles())->toBe($files);
});

test('a complete retained copy rejects a different same name and size body before recovery', function (bool $bodyless) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $baseImage = UploadedFile::fake()->image('resume.png', 40, 20);
    $base = file_get_contents($baseImage->getPathname());
    $inputs = catalogImageTestDisk('intake-retry-input');
    foreach (['original' => 'a', 'substitute' => 'b'] as $name => $value) {
        $chunk = 'npAd'.$value;
        $inputs->put($name.'.png', substr($base, 0, -12).pack('N', 1).$chunk.pack('N', crc32($chunk)).substr($base, -12));
    }
    $original = new UploadedFile($inputs->path('original.png'), 'resume.png', 'image/png', null, true);
    $substitute = new UploadedFile($inputs->path('substitute.png'), 'resume.png', 'image/png', null, true);
    expect($substitute->getSize())->toBe($original->getSize());
    expect(hash_file('sha256', $substitute->getPathname()))->not->toBe(hash_file('sha256', $original->getPathname()));
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Exact retained body', [intakeUploadedMetadata($original, 'resume.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $armed = true;
    CatalogAssetImportSource::updating(function ($source) use (&$armed, $entry): void {
        if ($armed && ($source->upload_ledger[$entry['entry_id']]['reference_hash'] ?? null) !== null) {
            $armed = false;
            throw new RuntimeException('Synthetic interruption before binding commits.');
        }
    });
    expect(fn () => $intake->transfer($actor, $launch['token'], $entry['token'], $original))->toThrow(RuntimeException::class);
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    $before = $source->getRawOriginal();
    $imports = Storage::disk('catalog-asset-imports');
    $receiptPath = 'intakes/'.$source->id.'/v1/'.$entry['entry_id'].'/binding.json';
    $receiptBody = $imports->get($receiptPath);
    $receipt = json_decode($receiptBody, true, flags: JSON_THROW_ON_ERROR);
    $temp = Storage::disk('tmp-for-tests');
    $temporary = $receipt['directory'].'/'.$receipt['basename'];
    $bytesBefore = $temp->get($temporary);

    expect(fn () => $intake->transfer($actor, $launch['token'], $entry['token'], $substitute))
        ->toThrow(ValidationException::class, 'Continue requires the same image bytes.');

    expect($source->fresh()->getRawOriginal())->toBe($before);
    expect($imports->get($receiptPath))->toBe($receiptBody);
    expect($temp->get($temporary))->toBe($bytesBefore);
    expect($intake->transfer($actor, $launch['token'], $entry['token'], $bodyless ? null : $original)['current_entry']['committed'])->toBeTrue();
})->with(['retained original body' => false, 'verified retained bytes without body' => true]);

test('a corrupt durable binding reports unresolved without repairing or changing Source progress', function (string $field, mixed $value) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('receipt.png', 40, 20);
    $failing = new class($file->getPathname(), 'receipt.png', 'image/png', null, true) extends UploadedFile
    {
        public function storeAs(mixed $path, mixed $name = null, mixed $options = []): mixed
        {
            throw new RuntimeException('Synthetic interrupted receipt body.');
        }
    };
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Corrupt binding', [intakeUploadedMetadata($file, 'receipt.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    expect(fn () => $intake->transfer($actor, $launch['token'], $entry['token'], $failing))->toThrow(RuntimeException::class);
    $source = CatalogAssetImportSource::findOrFail($issued['source_id']);
    $before = $source->getRawOriginal();
    $imports = Storage::disk('catalog-asset-imports');
    $path = 'intakes/'.$source->id.'/v1/'.$entry['entry_id'].'/binding.json';
    $receipt = json_decode($imports->get($path), true, flags: JSON_THROW_ON_ERROR);
    $receipt[$field] = $value;
    $imports->put($path, json_encode($receipt, JSON_THROW_ON_ERROR));
    $receiptBefore = $imports->get($path);
    $temp = Storage::disk('tmp-for-tests');
    $tempBefore = $temp->allFiles();

    expect($intake->status($actor, $launch['token'], $source->id, $entry['entry_id'])['recovery'])->toBe('unresolved');
    expect($intake->transfer($actor, $launch['token'], $entry['token'], $file)['recovery'])->toBe('unresolved');

    expect($source->fresh()->getRawOriginal())->toBe($before);
    expect($imports->get($path))->toBe($receiptBefore);
    expect($temp->allFiles())->toBe($tempBefore);
})->with(['invalid signed reference' => ['reference', 'invalid-reference'], 'missing MIME classification' => ['mime_type', null]]);

test('status reconciles a copy completed between its initial row reads and shared entry lock', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $launch = intakeReviewLaunch($actor);
    $file = UploadedFile::fake()->image('status-race.png', 40, 20);
    $intake = app(CatalogAssetIntake::class);
    $issued = $intake->issue($actor, $launch['token'], 'Status race', [intakeUploadedMetadata($file, 'status-race.png')]);
    $intake->begin($actor, $launch['token'], $issued['intake_token']);
    $entry = $intake->entry($actor, $launch['token'], $issued['source_id'], 0);
    $reads = 0;
    $armed = true;
    CatalogAssetImportSource::retrieved(function ($source) use (&$reads, &$armed, $issued, $actor, $launch, $entry, $file, $intake): void {
        if (! $armed || $source->id !== $issued['source_id']) {
            return;
        }
        $reads++;
        if ($reads === 2) {
            $armed = false;
            expect($intake->transfer($actor, $launch['token'], $entry['token'], $file)['current_entry']['committed'])->toBeTrue();
        }
    });

    $status = $intake->status($actor, $launch['token'], $issued['source_id'], $entry['entry_id']);

    expect($armed)->toBeFalse();
    expect($status['current_entry']['committed'])->toBeTrue();
    expect($status['file_count'])->toBe(1);
    expect($status['needs_file'])->toBeFalse();
    expect($status['recovery'])->toBe('committed');
});
