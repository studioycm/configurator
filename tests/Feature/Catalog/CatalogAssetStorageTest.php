<?php

use App\Models\CatalogAsset;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetFiles;
use App\Services\CatalogAssetSourceFiles;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function preparationPng(int $width = 80, int $height = 40): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 15, 130, 170));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/** @return array{0: User, 1: array<string, mixed>, 2: string} */
function preparationSource(string $bytes, string $name = 'valve.png', string $target = 'product.main_image'): array
{
    catalogImageTestDisk('catalog-asset-imports');
    catalogImageTestDisk('catalog-assets');
    catalogImageTestDisk('catalog-preparation-fixtures');
    config(['catalog-assets.enabled' => true, 'catalog-assets.sources' => ['fixture' => ['label' => 'Synthetic source',
        'path' => Storage::disk('catalog-preparation-fixtures')->path(''), 'read_only' => true]]]);
    Storage::disk('catalog-preparation-fixtures')->put($name, $bytes);
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', $target, [], false);
    do {
        $status = $sources->status($actor, $source->id);
        if (! $status['traversal_complete']) {
            $sources->scanNext($actor, $source->id, 1, $status['revision']);
        }
    } while (! $sources->status($actor, $source->id)['traversal_complete']);
    $sources->finalize($actor, $source->id, 1, $sources->status($actor, $source->id)['revision']);
    $entry = $sources->manifest($actor, $source->id, 1)['entries'][0];

    return [$actor, ['source_id' => $source->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']], hash('sha256', 'synthetic-import')];
}

test('managed preparation copies a verified original without changing sources or catalog owners', function () {
    [$actor, $choice, $key] = preparationSource($bytes = preparationPng());
    $group = Group::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $before = [$group->fresh()->getAttributes(), $product->fresh()->getAttributes()];
    Queue::fake();
    config(['media-library.prefix' => 'legacy-prefix']);

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('ready');
    $asset = $result['asset'];
    expect($asset)->toBeInstanceOf(CatalogAsset::class);
    expect($asset->preparation_status)->toBe('ready');
    $media = $asset->originalMedia;
    expect($media->collection_name)->toBe('original');
    expect($media->disk)->toBe('catalog-assets');
    expect($media->getPathRelativeToRoot())->toBe($media->id.'/valve.png');
    expect(Storage::disk('catalog-assets')->get($media->getPathRelativeToRoot()))->toBe($bytes);
    expect(Storage::disk('catalog-preparation-fixtures')->get('valve.png'))->toBe($bytes);
    foreach ([160, 480, 960] as $size) {
        expect($media->hasGeneratedConversion('image-'.$size))->toBeTrue();
        $dimensions = getimagesize($media->getPath('image-'.$size));
        expect([$dimensions[0], $dimensions[1]])->toBe([80, 40]);
    }
    expect($asset->verified_metadata['sha256'])->toBe(hash('sha256', $bytes));
    expect($asset->preparation_token)->toBeNull();
    expect([$group->fresh()->getAttributes(), $product->fresh()->getAttributes()])->toBe($before);
    Queue::assertNothingPushed();
});

test('preparation refuses an ambient transaction before creating claims or files', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    expect(DB::transactionLevel())->toBe(0);
    DB::beginTransaction();
    try {
        expect(fn () => app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key))->toThrow(LogicException::class);
    } finally {
        DB::rollBack();
    }
    $this->assertDatabaseCount('catalog_assets', 0);
    Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
});

test('same import key reuses its verified immutable winner without making another media row', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $service = app(CatalogAssetStorage::class);
    $first = $service->prepare($actor, $choice, 'product.main_image', $key);
    $second = $service->prepare($actor, $choice, 'product.main_image', $key);

    expect($second['outcome'])->toBe('ready');
    expect($second['asset']->id)->toBe($first['asset']->id);
    expect($second['asset']->original_media_id)->toBe($first['asset']->original_media_id);
    $this->assertDatabaseCount('catalog_assets', 1);
    $this->assertDatabaseCount('media', 1);
});

test('preparation validates the exact selected source shape target and reviewed digest', function (string $fault) {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $target = 'product.main_image';
    match ($fault) {
        'extra' => $choice['path'] = '/tmp/arbitrary.png',
        'digest' => $choice['sha256'] = str_repeat('0', 64),
        'version' => $choice['scan_version'] = 2,
        'entry' => $choice['entry_id'] = (string) Str::ulid(),
        'target' => $target = 'group.main_image',
    };

    expect(fn () => app(CatalogAssetStorage::class)->prepare($actor, $choice, $target, $key))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('catalog_assets', 0);
    Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
})->with(['extra', 'digest', 'version', 'entry', 'target']);

test('preparation authorization is required before any managed artifact is created', function (string $fault) {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    if ($fault === 'feature') {
        config(['catalog-assets.enabled' => false]);
    } else {
        $actor->update(['email' => 'outsider@example.test']);
    }

    expect(fn () => app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key))
        ->toThrow($fault === 'feature' ? NotFoundHttpException::class : AuthorizationException::class);
    $this->assertDatabaseCount('catalog_assets', 0);
    Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
})->with(['feature', 'permission']);

test('conversion failure after managed copy preserves one ready original and reports honest warnings', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    app()->instance(FileManipulator::class, new class extends FileManipulator
    {
        public function createDerivedFiles(Media $media, array $onlyConversionNames = [], bool $onlyMissing = false, bool $withResponsiveImages = false, bool $queueAll = false): void
        {
            throw new RuntimeException('Synthetic derivative failure.');
        }
    });

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('ready');
    expect($result['warnings'])->toBe(['conversion_incomplete']);
    $media = $result['asset']->originalMedia;
    Storage::disk('catalog-assets')->assertExists($media->getPathRelativeToRoot());
    expect($result['asset']->verified_metadata['conversions'])->toBe(['image-160' => false, 'image-480' => false, 'image-960' => false]);
    $this->assertDatabaseCount('media', 1);
});

test('systemic partial-copy failure pauses without marking source data as failed', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    app()->instance(Filesystem::class, new class(app('filesystem')) extends Filesystem
    {
        public function add(string $file, Media $media, ?string $targetFileName = null): bool
        {
            Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), 'partial');
            throw UnableToWriteFile::atLocation($media->getPathRelativeToRoot(), 'Synthetic storage outage.');
        }
    });

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('paused');
    expect($result['code'])->toBe('systemic_error');
    expect($result['next_action'])->toBe('operator_recovery');
    expect($result['asset'])->toBeNull();
    expect(CatalogAsset::query()->sole()->original_media_id)->toBeNull();
    expect(CatalogAsset::query()->sole()->preparation_status)->toBe('preparing');
    expect(CatalogAsset::query()->sole()->error_code)->toBeNull();
    expect(Media::query()->sole()->collection_name)->toBe('preparation-attempts');
});

test('ready winner corruption pauses for operator recovery without rebuilding its identity', function (string $fault) {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $service = app(CatalogAssetStorage::class);
    $asset = $service->prepare($actor, $choice, 'product.main_image', $key)['asset'];
    $media = $asset->originalMedia;
    if ($fault === 'missing') {
        Storage::disk('catalog-assets')->delete($media->getPathRelativeToRoot());
    } elseif ($fault === 'bytes') {
        Storage::disk('catalog-assets')->put($media->getPathRelativeToRoot(), 'corrupt');
    } elseif ($fault === 'disk') {
        $media->disk = 'public';
        $media->save();
    } elseif ($fault === 'owner') {
        $media->model_id = CatalogAsset::factory()->create()->id;
        $media->save();
    } elseif ($fault === 'metadata') {
        $asset->forceFill(['verified_metadata' => ['sha256' => $asset->sha256]])->save();
    }
    $snapshot = $asset->fresh()->getAttributes();
    $mediaCount = Media::query()->count();

    $result = $service->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('paused');
    expect($result['code'])->toBe('ready_asset_invalid');
    expect($asset->fresh()->getAttributes())->toBe($snapshot);
    expect(Media::query()->count())->toBe($mediaCount);
})->with(['missing', 'bytes', 'disk', 'owner', 'metadata']);

test('a changed source blocks reuse of a cached ready asset', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $service = app(CatalogAssetStorage::class);
    $asset = $service->prepare($actor, $choice, 'product.main_image', $key)['asset'];
    $snapshot = $asset->fresh()->getAttributes();
    Storage::disk('catalog-preparation-fixtures')->put('valve.png', preparationPng(81, 41));

    $result = $service->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('failed');
    expect($result['code'])->toBe('source_stale');
    expect($asset->fresh()->getAttributes())->toBe($snapshot);
    $this->assertDatabaseCount('media', 1);
});

test('a live preparation returns busy without copying or changing its lease', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $asset = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key)['asset'];
    $asset->forceFill(['preparation_status' => 'preparing', 'original_media_id' => null,
        'preparation_token' => $token = (string) Str::ulid(), 'preparation_expires_at' => now()->addMinute()])->save();
    $snapshot = $asset->fresh()->getAttributes();
    $before = Storage::disk('catalog-asset-imports')->allFiles();

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('busy');
    expect($asset->fresh()->getAttributes())->toBe($snapshot);
    expect(Storage::disk('catalog-asset-imports')->allFiles())->toBe($before);
});

test('a reused import key must retain exact source provenance even for identical file bytes', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $service = app(CatalogAssetStorage::class);
    $service->prepare($actor, $choice, 'product.main_image', $key);
    $source = CatalogAssetImportSource::query()->findOrFail($choice['source_id']);
    $source->forceFill(['source' => [...$source->source, 'label' => 'Changed label only']])->save();
    $other = app(CatalogAssetSources::class)->startScan($actor, 'fixture', 'product.main_image', [], false);
    app(CatalogAssetSources::class)->scanNext($actor, $other->id, 1, 0);
    app(CatalogAssetSources::class)->finalize($actor, $other->id, 1, 1);
    $entry = app(CatalogAssetSources::class)->manifest($actor, $other->id, 1)['entries'][0];
    $otherChoice = ['source_id' => $other->id, 'scan_version' => 1, 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']];

    expect(fn () => $service->prepare($actor, $otherChoice, 'product.main_image', $key))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('catalog_assets', 1);
    $this->assertDatabaseCount('media', 1);
});

test('permission revocation during independent file preparation is rechecked before media publication', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $files = new class(app(CatalogAssetSourceFiles::class), $actor->id) extends CatalogAssetFiles
    {
        public function __construct(CatalogAssetSourceFiles $files, private int $actorId)
        {
            parent::__construct($files);
        }

        public function workingCopy(array $selection, string $destination, array $policy): array
        {
            $result = parent::workingCopy($selection, $destination, $policy);
            User::query()->whereKey($this->actorId)->update(['email' => 'revoked@example.test']);

            return $result;
        }
    };
    app()->instance(CatalogAssetFiles::class, $files);

    expect(fn () => app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('media', 0);
    expect(CatalogAsset::query()->sole()->preparation_status)->toBe('preparing');
    Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
});

test('header-valid undecodable source never reaches managed media', function () {
    $png = preparationPng();
    $headerOnly = substr($png, 0, 33);
    [$actor, $choice, $key] = preparationSource($headerOnly);

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('failed');
    expect($result['code'])->toBe('image_decode_failed');
    $this->assertDatabaseCount('media', 0);
    Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
});

test('reconciliation never hydrates retained attempts belonging to other preparation tokens', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $service = app(CatalogAssetStorage::class);
    $asset = $service->prepare($actor, $choice, 'product.main_image', $key)['asset'];
    $old = $asset->originalMedia;
    $unrelatedIds = [];
    for ($index = 0; $index < 12; $index++) {
        $attempt = $old->replicate(['uuid']);
        $attempt->collection_name = 'preparation-attempts';
        $attempt->custom_properties = [...$attempt->custom_properties, 'preparation_token' => (string) Str::ulid()];
        $attempt->save();
        $unrelatedIds[] = $attempt->id;
    }
    $asset->forceFill(['preparation_status' => 'failed', 'original_media_id' => null])->save();
    $hydrated = [];
    Event::listen('eloquent.retrieved: '.Media::class, function (Media $media) use (&$hydrated): void {
        $hydrated[] = $media->id;
    });

    $result = $service->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('ready');
    expect(array_intersect($hydrated, $unrelatedIds))->toBe([]);
});

test('stored source evidence cannot change scalar types under the same import identity', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $service = app(CatalogAssetStorage::class);
    $asset = $service->prepare($actor, $choice, 'product.main_image', $key)['asset'];
    $source = $asset->source;
    $source['entry']['bytes'] = (string) $source['entry']['bytes'];
    $asset->forceFill(['source' => $source])->save();

    expect(fn () => $service->prepare($actor, $choice, 'product.main_image', $key))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('media', 1);
});

test('ready media evidence cannot change scalar types while retaining a usable winner', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    $service = app(CatalogAssetStorage::class);
    $asset = $service->prepare($actor, $choice, 'product.main_image', $key)['asset'];
    $media = $asset->originalMedia;
    $properties = $media->custom_properties;
    $properties['scan_version'] = (string) $properties['scan_version'];
    $media->custom_properties = $properties;
    $media->save();

    $result = $service->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('paused');
    expect($result['code'])->toBe('ready_asset_invalid');
    $this->assertDatabaseCount('media', 1);
});

test('managed preparation supports each accepted content format', function (string $format, string $mime) {
    $image = imagecreatetruecolor(24, 12);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 160));
    ob_start();
    match ($format) {
        'jpg' => imagejpeg($image), 'png' => imagepng($image), 'webp' => imagewebp($image)
    };
    $bytes = ob_get_clean();
    imagedestroy($image);
    [$actor, $choice, $key] = preparationSource($bytes, 'synthetic.'.$format);

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('ready');
    expect($result['asset']->originalMedia->mime_type)->toBe($mime);
    expect(Storage::disk('catalog-assets')->get($result['asset']->originalMedia->getPathRelativeToRoot()))->toBe($bytes);
})->with(['jpeg' => ['jpg', 'image/jpeg'], 'png' => ['png', 'image/png'], 'webp' => ['webp', 'image/webp']]);

test('larger images downscale proportionally and retain private working originals', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng(1200, 600));

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('ready');
    foreach ([160, 480, 960] as $size) {
        $dimensions = getimagesize($result['asset']->originalMedia->getPath('image-'.$size));
        expect([$dimensions[0], $dimensions[1]])->toBe([$size, intdiv($size, 2)]);
    }
    $working = array_values(array_filter(Storage::disk('catalog-asset-imports')->allFiles(), fn (string $path): bool => str_contains($path, '/preparation/')));
    expect($working)->toHaveCount(1);
    expect(fileperms(Storage::disk('catalog-asset-imports')->path($working[0])) & 0777)->toBe(0600);
});

test('preparation enforces the configured three MiB boundary', function (int $extra) {
    $bytes = str_pad(preparationPng(), 3145728 + $extra, "\0");
    if ($extra === 0) {
        [$actor, $choice, $key] = preparationSource($bytes);
    } else {
        [$actor, $choice, $key] = preparationSource(preparationPng());
        Storage::disk('catalog-preparation-fixtures')->put('valve.png', $bytes);
    }

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe($extra === 0 ? 'ready' : 'failed');
    if ($extra === 0) {
        expect($result['asset']->verified_metadata['bytes'])->toBe(3145728);
    } else {
        $this->assertDatabaseCount('media', 0);
        Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
    }
})->with(['exact bytes' => 0, 'one byte over' => 1]);

test('preparation admits exactly twenty five megapixels and rejects an oversized header before decoding', function (bool $over) {
    if ($over) {
        [$actor, $choice, $key] = preparationSource(preparationPng());
        $png = preparationPng();
        $png = substr_replace($png, pack('N', 5000).pack('N', 5001), 16, 8);
        $png = substr_replace($png, pack('N', crc32(substr($png, 12, 17))), 29, 4);
        Storage::disk('catalog-preparation-fixtures')->put('valve.png', $png);
    } else {
        [$actor, $choice, $key] = preparationSource(preparationPng(5000, 5000));
    }

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe($over ? 'failed' : 'ready');
    if ($over) {
        $this->assertDatabaseCount('media', 0);
        Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
    } else {
        expect([$result['asset']->verified_metadata['width'], $result['asset']->verified_metadata['height']])->toBe([5000, 5000]);
    }
})->with(['exact pixels' => false, 'oversized header' => true]);

test('a source growing during the private copy is bounded at one byte beyond the image cap', function () {
    [$actor, $choice, $key] = preparationSource(str_pad(preparationPng(), 3145728, "\0"));
    $files = new class(app(CatalogAssetSourceFiles::class)) extends CatalogAssetFiles
    {
        public int $readBytes = 0;

        protected function readSourceChunk(mixed $handle, int $length): string
        {
            $chunk = parent::readSourceChunk($handle, $length);
            if ($this->readBytes === 0) {
                file_put_contents(stream_get_meta_data($handle)['uri'], 'x', FILE_APPEND);
            }
            $this->readBytes += strlen($chunk);

            return $chunk;
        }
    };
    app()->instance(CatalogAssetFiles::class, $files);

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('failed');
    expect($result['code'])->toBe('image_too_large');
    expect($files->readBytes)->toBe(3145729);
    $this->assertDatabaseCount('media', 0);
    Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
});

test('multiple exact owned attempt rows stop diagnostically rather than choosing the first', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    app()->instance(FileManipulator::class, new class extends FileManipulator
    {
        public function createDerivedFiles(Media $media, array $onlyConversionNames = [], bool $onlyMissing = false, bool $withResponsiveImages = false, bool $queueAll = false): void
        {
            $duplicate = $media->replicate(['uuid']);
            $duplicate->save();
        }
    });

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('failed');
    expect($result['code'])->toBe('attempt_ambiguous');
    expect(CatalogAsset::query()->sole()->original_media_id)->toBeNull();
    expect(Media::query()->where('collection_name', 'preparation-attempts')->count())->toBe(2);
});

class PreparationOtherConnectionMedia extends Media
{
    protected $connection = 'catalog-image-media-test';
}

test('preparation refuses a different effective Media connection before claims or file work', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    config(['database.connections.catalog-image-media-test' => config('database.connections.'.config('database.default')),
        'media-library.media_model' => PreparationOtherConnectionMedia::class]);

    expect(fn () => app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key))->toThrow(LogicException::class);
    $this->assertDatabaseCount('catalog_assets', 0);
    Storage::disk('catalog-assets')->assertDirectoryEmpty('/');
});

test('unexplained managed-byte corruption remains an integrity failure rather than a storage outage', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    app()->instance(Filesystem::class, new class(app('filesystem')) extends Filesystem
    {
        public function add(string $file, Media $media, ?string $targetFileName = null): bool
        {
            Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), 'unexpected corrupt bytes');

            return true;
        }
    });

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('failed');
    expect($result['code'])->toBe('image_invalid');
    expect(CatalogAsset::query()->sole()->preparation_status)->toBe('failed');
    expect(CatalogAsset::query()->sole()->original_media_id)->toBeNull();
});

test('failure-journal database outage pauses without claiming that the failure was persisted', function () {
    [$actor, $choice, $key] = preparationSource(preparationPng());
    app()->instance(CatalogAssetFiles::class, new class(app(CatalogAssetSourceFiles::class)) extends CatalogAssetFiles
    {
        public function workingCopy(array $selection, string $destination, array $policy): array
        {
            parent::workingCopy($selection, $destination, $policy);
            DB::connection()->beforeExecuting(function (string $sql, array $bindings): void {
                if (str_starts_with(strtolower($sql), 'update') && str_contains($sql, 'catalog_assets') && in_array('failed', $bindings, true)) {
                    throw new QueryException(DB::getDefaultConnection(), $sql, $bindings, new PDOException('Synthetic journal database outage.'));
                }
            });
            throw new DomainException('image_invalid');
        }
    });

    $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);

    expect($result['outcome'])->toBe('paused');
    expect($result['code'])->toBe('systemic_error');
    expect($result['next_action'])->toBe('operator_recovery');
    expect(CatalogAsset::query()->sole()->preparation_status)->toBe('preparing');
    expect(CatalogAsset::query()->sole()->error_code)->toBeNull();
    $this->assertDatabaseCount('media', 0);
});

test('managed preparation filesystem confinement rejects a symlink swapped after valid source review', function (string $kind) {
    $bytes = preparationPng();
    [$actor, $choice, $key] = preparationSource($bytes);
    $product = Product::factory()->create()->refresh();
    $before = $product->getAttributes();
    $disk = Storage::disk('catalog-preparation-fixtures');
    $outside = catalogImageTestDisk('outside-preparation');
    $outside->put('valve.png', $bytes);
    $root = rtrim($disk->path(''), '/');
    $original = $disk->path('valve.png');
    if ($kind === 'file') {
        unlink($original);
        expect(symlink($outside->path('valve.png'), $original))->toBeTrue();
    } else {
        expect(rename($root, $root.'-retained'))->toBeTrue();
        expect(symlink(rtrim($outside->path(''), '/'), $root))->toBeTrue();
    }
    try {
        $result = app(CatalogAssetStorage::class)->prepare($actor, $choice, 'product.main_image', $key);
        expect($result['outcome'])->toBe('failed')->and($result['code'])->toBe('path_invalid')
            ->and(CatalogAsset::count())->toBe(0)->and(Media::count())->toBe(0)
            ->and(Storage::disk('catalog-assets')->allFiles())->toBe([])
            ->and($product->fresh()->getAttributes())->toBe($before)
            ->and($outside->get('valve.png'))->toBe($bytes);
    } finally {
        unlink($kind === 'file' ? $original : $root);
        if ($kind !== 'file') {
            rename($root.'-retained', $root);
        }
    }
})->with(['file', 'root directory']);
