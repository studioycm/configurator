<?php

use App\Models\CatalogAsset;
use App\Models\CatalogAssetImportSource;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetSourceFiles;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetTargets;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function catalogSourcePng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3ioAAAAASUVORK5CYII=');
}

function finishCatalogSourceScan(CatalogAssetSources $sources, User $actor, CatalogAssetImportSource $source): array
{
    for ($step = 0; $step < 20; $step++) {
        $status = $sources->status($actor, $source->id);
        if ($status['traversal_complete'] || in_array($status['stage'], ['failed', 'stale'], true)) {
            return $status;
        }
        $sources->scanNext($actor, $source->id, $status['scan_version'], $status['revision']);
    }
    throw new RuntimeException('Fixture source did not finish within its bounded steps.');
}

function failCatalogSourceJournalUpdates(): void
{
    DB::connection()->beforeExecuting(function (string $sql, array $bindings): void {
        if (str_starts_with(strtolower($sql), 'update') && str_contains($sql, 'catalog_asset_import_sources') && in_array('failed', $bindings, true)) {
            throw new QueryException(DB::getDefaultConnection(), $sql, $bindings, new PDOException('Synthetic source failure-journal database outage.'));
        }
    });
}

beforeEach(function () {
    catalogImageTestDisk('catalog-asset-imports');
    catalogImageTestDisk('catalog-source-fixtures');
    catalogImageTestDisk('tmp-for-tests');
    config([
        'catalog-assets.enabled' => true,
        'catalog-assets.sources' => ['fixture' => ['label' => 'Test source', 'path' => Storage::disk('catalog-source-fixtures')->path(''), 'read_only' => true]],
        'livewire.temporary_file_upload.disk' => 'local',
        'livewire.temporary_file_upload.directory' => 'livewire-tmp',
    ]);
});

test('source commands require an enabled feature and admitted actor before creating a journal', function () {
    $actor = User::factory()->create(['email' => 'outsider@example.test']);
    $sources = app(CatalogAssetSources::class);
    expect(fn () => $sources->startUpload($actor, 'product.main_image', [], 'New source'))->toThrow(AuthorizationException::class);
    config(['catalog-assets.enabled' => false]);
    $actor->update(['email' => 'ycm@data4.work']);
    expect(fn () => $sources->startUpload($actor, 'product.main_image', [], 'New source'))->toThrow(NotFoundHttpException::class);
    $this->assertDatabaseCount('catalog_asset_import_sources', 0);
});

test('source mutation refuses ambient transactions before journal or filesystem work', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    expect(DB::transactionLevel())->toBe(0);
    DB::beginTransaction();
    try {
        expect(fn () => app(CatalogAssetSources::class)->startUpload($actor, 'product.main_image', [], 'New source'))
            ->toThrow(LogicException::class);
    } finally {
        DB::rollBack();
    }
    $this->assertDatabaseCount('catalog_asset_import_sources', 0);
    Storage::disk('catalog-asset-imports')->assertDirectoryEmpty('/');
});

test('sources retain their actor target and exact owner context without exposing private state', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $other = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Uploaded photos');

    expect($source->source['target_key'])->toBe('product.main_image')
        ->and($source->source['owner_context'])->toBe([]);
    expect(fn () => $sources->status($other, $source->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $sources->startScan($actor, $source->id, 'group.main_image', [], false))->toThrow(ValidationException::class);
    $status = $sources->status($actor, $source->id);
    expect($status['revision'])->toBe(0)
        ->and(array_intersect(['source', 'cursor', 'upload_ledger', 'scan_token', 'manifest_path', 'root'], array_keys($status)))->toBe([]);
    $actor->update(['email' => 'outsider@example.test']);
    expect(fn () => $sources->status($actor, $source->id))->toThrow(AuthorizationException::class);
});

test('configured folders scan incrementally reconcile old revisions and finalize an immutable verified manifest', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('D60EL01.png', catalogSourcePng());
    Storage::disk('catalog-source-fixtures')->put('notes.txt', 'ignored source bytes');
    config(['catalog-assets.requests.scan_new_nodes' => 1]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);

    expect(fn () => $sources->finalize($actor, $source->id, 1, 0))->toThrow(ValidationException::class);
    $first = $sources->scanNext($actor, $source->id, 1, 0);
    $replay = $sources->scanNext($actor, $source->id, 1, 0);
    expect($first['revision'])->toBe(1)
        ->and($replay['revision'])->toBe(1)
        ->and($replay['file_count'])->toBe($first['file_count']);
    $status = finishCatalogSourceScan($sources, $actor, $source);
    $ready = $sources->finalize($actor, $source->id, 1, $status['revision']);
    $manifest = $sources->manifest($actor, $source->id, 1);
    $after = $source->fresh();
    foreach ([$after->manifest_path, ...array_column($after->cursor['fragments'], 'path')] as $privateArtifact) {
        $absolute = Storage::disk('catalog-asset-imports')->path($privateArtifact);
        clearstatcache(true, $absolute);
        expect(fileperms($absolute) & 0777)->toBe(0600);
    }

    expect($ready['stage'])->toBe('ready')
        ->and($manifest['source_id'])->toBe($source->id)
        ->and($manifest['scan_version'])->toBe(1)
        ->and($manifest['entries'])->toHaveCount(2)
        ->and(collect($manifest['entries'])->where('status', 'valid'))->toHaveCount(1)
        ->and($ready['file_count'])->toBe(2)
        ->and($ready['byte_count'])->toBe(strlen(catalogSourcePng()) + strlen('ignored source bytes'))
        ->and(hash('sha256', Storage::disk('catalog-asset-imports')->get($after->manifest_path)))->toBe($after->manifest_sha256);
    $sources->finalize($actor, $source->id, 1, $status['revision']);
    expect($source->fresh()->manifest_sha256)->toBe($after->manifest_sha256);
    $this->assertDatabaseCount('catalog_assets', 0);
    $this->assertDatabaseCount('media', 0);
});

test('source file and byte caps reject an oversized aggregate rather than publishing a partial ready source', function (string $limit, int $maximum) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    Storage::disk('catalog-source-fixtures')->put('two.txt', 'unsupported counts too');
    config(['catalog-assets.limits.'.$limit => $maximum]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);

    $result = $sources->scanNext($actor, $source->id, 1, 0);

    expect($result['stage'])->toBe('failed')
        ->and($result['code'])->toBe('source_limit')
        ->and($source->fresh()->manifest_path)->toBeNull();
    expect(fn () => $sources->manifest($actor, $source->id, 1))->toThrow(ValidationException::class);
})->with(['file count' => ['source_files', 1], 'byte count' => ['source_bytes', 1]]);

test('a live scan lease returns busy without advancing or counting a request', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $token = (string) Str::ulid();
    $source->forceFill(['scan_token' => $token, 'scan_expires_at' => now()->addMinute()])->save();
    Log::spy();

    $result = $sources->scanNext($actor, $source->id, 1, 0);

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('error');

    expect($result['outcome'])->toBe('busy')
        ->and($result['revision'])->toBe(0)
        ->and($result['file_count'])->toBe(0)
        ->and($source->fresh()->scan_token)->toBe($token);
});

test('a changed traversal becomes stale and rescan creates a separate immutable instance', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    Storage::disk('catalog-source-fixtures')->put('two.png', catalogSourcePng());
    config(['catalog-assets.requests.scan_new_nodes' => 1]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $sources->scanNext($actor, $source->id, 1, 0);
    touch(Storage::disk('catalog-source-fixtures')->path(''), now()->addSeconds(5)->timestamp);

    $stale = $sources->scanNext($actor, $source->id, 1, 1);
    $replacement = $sources->startScan($actor, $source->id, 'product.main_image', [], false);

    expect($stale['stage'])->toBe('stale')
        ->and($stale['code'])->toBe('source_stale')
        ->and($replacement->id)->not->toBe($source->id)
        ->and($replacement->scan_version)->toBe(2)
        ->and($source->fresh()->manifest_path)->toBeNull();
});

test('finalization verifies fragment hashes and never accepts a modified working artifact', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $status = finishCatalogSourceScan($sources, $actor, $source);
    $fragment = $source->fresh()->cursor['fragments'][0];
    Storage::disk('catalog-asset-imports')->put($fragment['path'], '{}');

    $result = $sources->finalize($actor, $source->id, 1, $status['revision']);

    expect($result['stage'])->toBe('failed')
        ->and($result['code'])->toBe('artifact_invalid')
        ->and($source->fresh()->manifest_path)->toBeNull();
});

test('upload reservations are idempotent measured once and cannot be sealed before settlement', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Folder upload');
    $entry = (string) Str::ulid();
    $metadata = ['name' => 'D60EL01.png', 'relative_path' => 'D60/D60EL01.png', 'bytes' => 1];
    $first = $sources->reserveUpload($actor, $source->id, $entry, $metadata);
    $replay = $sources->reserveUpload($actor, $source->id, $entry, $metadata);
    expect($first['revision'])->toBe($replay['revision'])->and($replay['unsettled_uploads'])->toBe(1);
    expect(fn () => $sources->reserveUpload($actor, $source->id, $entry, [...$metadata, 'name' => 'different.png']))->toThrow(ValidationException::class);
    expect(fn () => $sources->sealUploads($actor, $source->id, $replay['revision']))->toThrow(ValidationException::class);
    Storage::disk('tmp-for-tests')->put('livewire-tmp/opaque-upload.png', catalogSourcePng());
    $reference = TemporaryUploadedFile::signPath('opaque-upload.png');

    $committed = $sources->commitUpload($actor, $source->id, $entry, $reference);
    $retry = $sources->commitUpload($actor, $source->id, $entry, $reference);

    expect($sources->status($actor, $source->id, $entry)['current_entry'])->toBe(['entry_id' => $entry, 'status' => 'committed', 'committed' => true]);
    expect($committed['file_count'])->toBe(1)
        ->and($committed['byte_count'])->toBe(strlen(catalogSourcePng()))
        ->and($committed['unsettled_uploads'])->toBe(0)
        ->and($retry['revision'])->toBe($committed['revision'])
        ->and($retry['file_count'])->toBe(1);
    expect(fn () => $sources->commitUpload($actor, $source->id, $entry, TemporaryUploadedFile::signPath('other.png')))->toThrow(ValidationException::class);
    $sources->sealUploads($actor, $source->id, $committed['revision']);
    $status = finishCatalogSourceScan($sources, $actor, $source);
    $sources->finalize($actor, $source->id, 1, $status['revision']);
    $manifest = $sources->manifest($actor, $source->id, 1);
    expect($manifest['entries'])->toHaveCount(1)
        ->and($manifest['entries'][0]['entry_id'])->toBe($entry)
        ->and($manifest['entries'][0]['relative_path'])->toBe('D60/D60EL01.png')
        ->and($manifest['entries'][0]['sha256'])->toBe(hash('sha256', catalogSourcePng()));
    $preview = $sources->preview($actor, $source->id, 1, $entry);
    expect(stream_get_contents($preview['stream']))->toBe(catalogSourcePng());
    fclose($preview['stream']);
});

test('uploads reject raw temporary names and unsafe reservation paths before accepting bytes', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Folder upload');
    $entry = (string) Str::ulid();
    expect(fn () => $sources->reserveUpload($actor, $source->id, $entry, ['name' => 'x.png', 'relative_path' => '../x.png', 'bytes' => 1]))->toThrow(ValidationException::class);
    $sources->reserveUpload($actor, $source->id, $entry, ['name' => 'x.png', 'relative_path' => 'x.png', 'bytes' => 1]);
    Storage::disk('tmp-for-tests')->put('livewire-tmp/x.png', catalogSourcePng());

    expect(fn () => $sources->commitUpload($actor, $source->id, $entry, 'x.png'))->toThrow(ValidationException::class);
    expect($source->fresh()->file_count)->toBe(0);
});

test('measured uploaded bytes cannot overrun reserved aggregate capacity', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    config(['catalog-assets.limits.source_bytes' => 10]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Folder upload');
    $entry = (string) Str::ulid();
    $sources->reserveUpload($actor, $source->id, $entry, ['name' => 'x.png', 'relative_path' => 'x.png', 'bytes' => 1]);
    Storage::disk('tmp-for-tests')->put('livewire-tmp/x.png', catalogSourcePng());

    $result = $sources->commitUpload($actor, $source->id, $entry, TemporaryUploadedFile::signPath('x.png'));

    expect($result['stage'])->toBe('failed')
        ->and($result['code'])->toBe('source_limit')
        ->and($source->fresh()->manifest_path)->toBeNull();
});

test('private source previews reverify bytes and digest rather than trusting a ready manifest', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $status = finishCatalogSourceScan($sources, $actor, $source);
    $sources->finalize($actor, $source->id, 1, $status['revision']);
    $entry = $sources->manifest($actor, $source->id, 1)['entries'][0]['entry_id'];
    $preview = $sources->preview($actor, $source->id, 1, $entry);
    expect($preview['mime_type'])->toBe('image/png')->and(stream_get_contents($preview['stream']))->toBe(catalogSourcePng());
    fclose($preview['stream']);
    Storage::disk('catalog-source-fixtures')->put('one.png', 'changed');

    expect(fn () => $sources->preview($actor, $source->id, 1, $entry))->toThrow(NotFoundHttpException::class);
});

test('an expired scan handler cannot publish progress or failure over a newer claim', function (bool $failOldHandler) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $realFiles = app(CatalogAssetSourceFiles::class);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $newToken = (string) Str::ulid();
    $files = Mockery::mock(CatalogAssetSourceFiles::class)->makePartial();
    $files->shouldReceive('scan')->once()->andReturnUsing(function (...$arguments) use ($realFiles, $source, $newToken, $failOldHandler): array {
        expect(DB::transactionLevel())->toBe(0);
        $result = $realFiles->scan(...$arguments);
        $source->fresh()->forceFill(['scan_token' => $newToken, 'scan_expires_at' => now()->addMinute(), 'scan_revision' => 1])->save();
        if ($failOldHandler) {
            throw new DomainException('source_stale');
        }

        return $result;
    });
    $sources = new CatalogAssetSources(app(CatalogAssetTargets::class), $files);

    $result = $sources->scanNext($actor, $source->id, 1, 0);

    expect($result['outcome'])->toBe('reconciled')
        ->and($source->fresh()->status)->toBe('scanning')
        ->and($source->fresh()->scan_token)->toBe($newToken)
        ->and($source->fresh()->scan_revision)->toBe(1)
        ->and($source->fresh()->file_count)->toBe(0)
        ->and($source->fresh()->cursor['fragments'])->toBe([])
        ->and($source->fresh()->error_code)->toBeNull();
})->with(['old success' => false, 'old failure' => true]);

test('publication propagates revoked permissions rather than reporting them as a systemic source error', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $realFiles = app(CatalogAssetSourceFiles::class);
    $source = app(CatalogAssetSources::class)->startScan($actor, 'fixture', 'product.main_image', [], false);
    $files = Mockery::mock(CatalogAssetSourceFiles::class)->makePartial();
    $files->shouldReceive('scan')->once()->andReturnUsing(function (...$arguments) use ($realFiles, $actor): array {
        $result = $realFiles->scan(...$arguments);
        $actor->update(['email' => 'outsider@example.test']);

        return $result;
    });
    $sources = new CatalogAssetSources(app(CatalogAssetTargets::class), $files);

    expect(fn () => $sources->scanNext($actor, $source->id, 1, 0))->toThrow(AuthorizationException::class);
    expect($source->fresh()->file_count)->toBe(0)->and($source->fresh()->error_code)->toBeNull();
});

test('invalid uploaded candidates remain reported metadata and cannot be privately previewed', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Invalid candidate');
    $entry = (string) Str::ulid();
    $sources->reserveUpload($actor, $source->id, $entry, ['name' => 'invalid.png', 'relative_path' => 'invalid.png', 'bytes' => 3]);
    Storage::disk('tmp-for-tests')->put('livewire-tmp/invalid.png', 'bad');
    $committed = $sources->commitUpload($actor, $source->id, $entry, TemporaryUploadedFile::signPath('invalid.png'));
    $sources->sealUploads($actor, $source->id, $committed['revision']);
    $status = finishCatalogSourceScan($sources, $actor, $source);
    $sources->finalize($actor, $source->id, 1, $status['revision']);
    $manifest = $sources->manifest($actor, $source->id, 1);

    expect($manifest['entries'])->toHaveCount(1)
        ->and($manifest['entries'][0]['status'])->toBe('invalid')
        ->and($manifest['entries'][0]['sha256'])->toBeNull()
        ->and($manifest['file_count'])->toBe(1)
        ->and($manifest['byte_count'])->toBe(3);
    expect(fn () => $sources->preview($actor, $source->id, 1, $entry))->toThrow(NotFoundHttpException::class);
});

test('finalization recovers after an artifact write succeeds but its SQL publication fails', function () {
    $this->freezeTime();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $status = finishCatalogSourceScan($sources, $actor, $source);
    $originalDispatcher = CatalogAssetImportSource::getEventDispatcher();
    CatalogAssetImportSource::setEventDispatcher(clone $originalDispatcher);
    $failPublication = true;
    CatalogAssetImportSource::saving(function (CatalogAssetImportSource $saving) use (&$failPublication): void {
        if ($saving->status === 'ready' && $failPublication) {
            $failPublication = false;
            throw new RuntimeException('Simulated lost SQL publication after a completed artifact write.');
        }
    });
    try {
        $paused = $sources->finalize($actor, $source->id, 1, $status['revision']);
        expect($paused['outcome'])->toBe('paused')->and($paused['next_action'])->toBe('operator_recovery');
        expect($source->fresh()->manifest_path)->toBeNull();
        $this->travel(61)->seconds();

        $recovered = $sources->finalize($actor, $source->id, 1, $status['revision']);

        expect($recovered['stage'])->toBe('ready');
        expect($sources->manifest($actor, $source->id, 1)['entries'])->toHaveCount(1);
    } finally {
        CatalogAssetImportSource::setEventDispatcher($originalDispatcher);
    }
});

test('entry status reconciles one reservation without exposing private bindings or mutating the source', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Folder upload');
    $entry = (string) Str::ulid();
    $sources->reserveUpload($actor, $source->id, $entry, ['name' => 'one.png', 'relative_path' => 'one.png', 'bytes' => 1]);
    $before = $source->fresh()->getAttributes();

    $reserved = $sources->status($actor, $source->id, $entry);
    $missingId = (string) Str::ulid();
    $missing = $sources->status($actor, $source->id, $missingId);

    expect($reserved['current_entry'])->toBe(['entry_id' => $entry, 'status' => 'reserved', 'committed' => false])
        ->and($missing['current_entry'])->toBe(['entry_id' => $missingId, 'status' => 'missing', 'committed' => false])
        ->and($source->fresh()->getAttributes())->toBe($before);
    expect(fn () => $sources->status($actor, $source->id, 'one.png'))->toThrow(ValidationException::class);
});

test('an expired upload handler cannot settle or fail an entry now owned by another attempt', function (bool $failOldHandler) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Folder upload');
    $entry = (string) Str::ulid();
    $sources->reserveUpload($actor, $source->id, $entry, ['name' => 'one.png', 'relative_path' => 'one.png', 'bytes' => 1]);
    Storage::disk('tmp-for-tests')->put('livewire-tmp/one.png', catalogSourcePng());
    $realFiles = app(CatalogAssetSourceFiles::class);
    $newToken = (string) Str::ulid();
    $files = Mockery::mock(CatalogAssetSourceFiles::class)->makePartial();
    $files->shouldReceive('inspect')->once()->andReturnUsing(function (...$arguments) use ($realFiles, $source, $entry, $newToken, $failOldHandler): array {
        expect(DB::transactionLevel())->toBe(0);
        $result = $realFiles->inspect(...$arguments);
        $current = $source->fresh();
        $ledger = $current->upload_ledger;
        $ledger[$entry]['token'] = $newToken;
        $ledger[$entry]['expires_at'] = now()->addMinute()->timestamp;
        $current->forceFill(['upload_ledger' => $ledger, 'scan_revision' => $current->scan_revision + 1])->save();
        if ($failOldHandler) {
            throw new DomainException('source_stale');
        }

        return $result;
    });
    $sources = new CatalogAssetSources(app(CatalogAssetTargets::class), $files);

    $result = $sources->commitUpload($actor, $source->id, $entry, TemporaryUploadedFile::signPath('one.png'));

    expect($result['outcome'])->toBe('reconciled')
        ->and($source->fresh()->status)->toBe('uploading')
        ->and($source->fresh()->upload_ledger[$entry]['token'])->toBe($newToken)
        ->and($source->fresh()->upload_ledger[$entry]['status'])->toBe('uploading')
        ->and($source->fresh()->file_count)->toBe(0)
        ->and($source->fresh()->unsettled_uploads)->toBe(1)
        ->and($source->fresh()->error_code)->toBeNull();
})->with(['old success' => false, 'old failure' => true]);

test('source observability logs committed scan and finalize stages once and keeps replay and status read only', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    Log::spy();

    $scanned = $sources->scanNext($actor, $source->id, 1, 0);
    $sources->scanNext($actor, $source->id, 1, 0);
    $sources->status($actor, $source->id);
    $ready = $sources->finalize($actor, $source->id, 1, $scanned['revision']);
    $sources->finalize($actor, $source->id, 1, $scanned['revision']);

    Log::shouldHaveReceived('info')->twice();
    foreach (['scan' => $scanned, 'finalize' => $ready] as $stage => $result) {
        Log::shouldHaveReceived('info')->with('Catalog asset source request completed.', Mockery::on(fn (array $context): bool => $context['stage'] === $stage
            && $context['actor_id'] === $actor->id && $context['source_id'] === $source->id && $context['scan_version'] === 1
            && $context['revision'] === $result['revision'] && $context['correlation_id'] === $result['correlation_id']
            && Str::isUlid($context['claim_token']) && is_numeric($context['elapsed_ms']) && $context['elapsed_ms'] >= 0))->once();
    }
});

test('terminal source faults persist the same safe correlation returned and logged after commit', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    config(['catalog-assets.limits.source_bytes' => 1]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    Log::spy();

    $result = $sources->scanNext($actor, $source->id, 1, 0);

    expect($source->fresh()->error_detail)->toContain($result['correlation_id'])
        ->not->toContain(Storage::disk('catalog-source-fixtures')->path(''));
    expect(strlen($source->fresh()->error_detail))->toBeLessThanOrEqual(1000);
    Log::shouldHaveReceived('info')->with('Catalog asset source request completed.', Mockery::on(fn (array $context): bool => $context['stage'] === 'scan'
        && $context['outcome'] === 'failed' && $context['code'] === 'source_limit' && $context['correlation_id'] === $result['correlation_id']
        && $context['revision'] === $source->fresh()->scan_revision && $context['elapsed_ms'] >= 0))->once();
});

test('upload observability records its committed entry once outside SQL and not on replay', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Folder upload');
    $entry = (string) Str::ulid();
    $sources->reserveUpload($actor, $source->id, $entry, ['name' => 'one.png', 'relative_path' => 'one.png', 'bytes' => 1]);
    Storage::disk('tmp-for-tests')->put('livewire-tmp/one.png', catalogSourcePng());
    $observed = [];
    Log::shouldReceive('info')->once()->with('Catalog asset source request completed.', Mockery::type('array'))
        ->andReturnUsing(function (string $message, array $context) use (&$observed): void {
            expect(DB::transactionLevel())->toBe(0);
            $observed = $context;
        });

    $result = $sources->commitUpload($actor, $source->id, $entry, TemporaryUploadedFile::signPath('one.png'));
    $sources->commitUpload($actor, $source->id, $entry, TemporaryUploadedFile::signPath('one.png'));

    expect($observed['stage'])->toBe('upload')
        ->and($observed['entry_id'])->toBe($entry)
        ->and($observed['correlation_id'])->toBe($result['correlation_id'])
        ->and($observed['revision'])->toBe($result['revision'])
        ->and($observed['elapsed_ms'])->toBeGreaterThanOrEqual(0);
});

test('systemic source errors log one timed correlation without falsely failing the journal', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $source = app(CatalogAssetSources::class)->startScan($actor, 'fixture', 'product.main_image', [], false);
    $files = Mockery::mock(CatalogAssetSourceFiles::class)->makePartial();
    $files->shouldReceive('scan')->once()->andThrow(new RuntimeException('Private path /do/not/expose is unavailable.'));
    $sources = new CatalogAssetSources(app(CatalogAssetTargets::class), $files);
    Log::spy();

    $result = $sources->scanNext($actor, $source->id, 1, 0);

    expect($result['outcome'])->toBe('paused')
        ->and($result['next_action'])->toBe('operator_recovery')
        ->and($result['message'])->not->toContain('/do/not/expose')
        ->and($source->fresh()->error_detail)->toBeNull()
        ->and($source->fresh()->status)->toBe('scanning');
    Log::shouldNotHaveReceived('info');
    Log::shouldHaveReceived('error')->with('Catalog asset source operation paused.', Mockery::on(fn (array $context): bool => $context['stage'] === 'scan'
        && $context['correlation_id'] === $result['correlation_id'] && $context['elapsed_ms'] >= 0 && Str::isUlid($context['claim_token'])))->once();
});

test('an upload failure-journal database outage preserves committed entries and pauses for recovery', function () {
    $this->freezeTime();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startUpload($actor, 'product.main_image', [], 'Folder upload');
    $committedEntry = (string) Str::ulid();
    $pendingEntry = (string) Str::ulid();
    Storage::disk('tmp-for-tests')->put('livewire-tmp/one.png', catalogSourcePng());
    Storage::disk('tmp-for-tests')->put('livewire-tmp/two.png', catalogSourcePng());
    $sources->reserveUpload($actor, $source->id, $committedEntry, ['name' => 'one.png', 'relative_path' => 'one.png', 'bytes' => 1]);
    $sources->commitUpload($actor, $source->id, $committedEntry, TemporaryUploadedFile::signPath('one.png'));
    $sources->reserveUpload($actor, $source->id, $pendingEntry, ['name' => 'two.png', 'relative_path' => 'two.png', 'bytes' => 1]);
    $before = $source->fresh();
    $reference = TemporaryUploadedFile::signPath('two.png');
    config(['catalog-assets.limits.source_bytes' => $before->byte_count + 10]);
    failCatalogSourceJournalUpdates();
    Log::spy();

    $result = $sources->commitUpload($actor, $source->id, $pendingEntry, $reference);

    $current = $source->fresh();
    expect($result['outcome'])->toBe('paused')
        ->and($result['code'])->toBe('systemic_error')
        ->and($result['next_action'])->toBe('operator_recovery')
        ->and($result['revision'])->toBe($before->scan_revision + 1)
        ->and($result['message'])->not->toContain('database outage');
    expect($current->status)->toBe('uploading')
        ->and($current->scan_revision)->toBe($before->scan_revision + 1)
        ->and($current->cursor)->toBe($before->cursor)
        ->and($current->file_count)->toBe(1)
        ->and($current->byte_count)->toBe(strlen(catalogSourcePng()))
        ->and($current->visited_nodes)->toBe(1)
        ->and($current->unsettled_uploads)->toBe(1)
        ->and($current->upload_ledger[$committedEntry])->toBe($before->upload_ledger[$committedEntry])
        ->and($current->upload_ledger[$pendingEntry]['status'])->toBe('uploading')
        ->and($current->upload_ledger[$pendingEntry]['reference_hash'])->toBe(hash('sha256', $reference))
        ->and(Str::isUlid($current->upload_ledger[$pendingEntry]['token']))->toBeTrue()
        ->and($current->error_code)->toBeNull()
        ->and($current->error_detail)->toBeNull();
    Log::shouldNotHaveReceived('info');
    Log::shouldHaveReceived('error')->with('Catalog asset source operation paused.', Mockery::on(fn (array $context): bool => $context['stage'] === 'upload'
        && $context['entry_id'] === $pendingEntry && $context['claim_token'] === $current->upload_ledger[$pendingEntry]['token']
        && $context['correlation_id'] === $result['correlation_id'] && $context['exception'] instanceof QueryException))->once();

    config(['catalog-assets.limits.source_bytes' => 1000]);
    $this->travel(61)->seconds();
    $recovered = $sources->commitUpload($actor, $source->id, $pendingEntry, $reference);

    expect($recovered['outcome'])->toBe('committed')
        ->and($recovered['file_count'])->toBe(2)
        ->and($recovered['byte_count'])->toBe(strlen(catalogSourcePng()) * 2)
        ->and($recovered['unsettled_uploads'])->toBe(0);
});

test('a scan failure-journal database outage retains committed traversal and pauses for recovery', function () {
    $this->freezeTime();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    Storage::disk('catalog-source-fixtures')->put('two.png', catalogSourcePng());
    config(['catalog-assets.requests.scan_new_nodes' => 1]);
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $sources->scanNext($actor, $source->id, 1, 0);
    $before = $source->fresh();
    config(['catalog-assets.limits.source_bytes' => $before->byte_count]);
    failCatalogSourceJournalUpdates();
    Log::spy();

    $result = $sources->scanNext($actor, $source->id, 1, $before->scan_revision);

    $current = $source->fresh();
    expect($result['outcome'])->toBe('paused')
        ->and($result['code'])->toBe('systemic_error')
        ->and($result['next_action'])->toBe('operator_recovery')
        ->and($result['revision'])->toBe($before->scan_revision);
    expect($current->status)->toBe('scanning')
        ->and($current->scan_revision)->toBe($before->scan_revision)
        ->and($current->cursor)->toBe($before->cursor)
        ->and($current->upload_ledger)->toBe($before->upload_ledger)
        ->and($current->file_count)->toBe(1)
        ->and($current->byte_count)->toBe(strlen(catalogSourcePng()))
        ->and($current->visited_nodes)->toBe(1)
        ->and(Str::isUlid($current->scan_token))->toBeTrue()
        ->and($current->error_code)->toBeNull()
        ->and($current->error_detail)->toBeNull();
    Log::shouldNotHaveReceived('info');
    Log::shouldHaveReceived('error')->with('Catalog asset source operation paused.', Mockery::on(fn (array $context): bool => $context['stage'] === 'scan'
        && $context['claim_token'] === $current->scan_token && $context['correlation_id'] === $result['correlation_id']
        && $context['exception'] instanceof QueryException))->once();

    config(['catalog-assets.limits.source_bytes' => 1000]);
    $this->travel(61)->seconds();
    $recovered = $sources->scanNext($actor, $source->id, 1, $before->scan_revision);

    expect($recovered['outcome'])->toBe('scanned')
        ->and($recovered['file_count'])->toBe(2)
        ->and($recovered['byte_count'])->toBe(strlen(catalogSourcePng()) * 2)
        ->and($recovered['visited_nodes'])->toBe(2);
});

test('a finalization failure-journal database outage retains verified progress and pauses for recovery', function () {
    $this->freezeTime();
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    Storage::disk('catalog-source-fixtures')->put('one.png', catalogSourcePng());
    $sources = app(CatalogAssetSources::class);
    $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], false);
    $status = finishCatalogSourceScan($sources, $actor, $source);
    $before = $source->fresh();
    $fragment = $before->cursor['fragments'][0];
    $originalArtifact = Storage::disk('catalog-asset-imports')->get($fragment['path']);
    Storage::disk('catalog-asset-imports')->put($fragment['path'], '{}');
    failCatalogSourceJournalUpdates();
    Log::spy();

    $result = $sources->finalize($actor, $source->id, 1, $status['revision']);

    $current = $source->fresh();
    expect($result['outcome'])->toBe('paused')
        ->and($result['code'])->toBe('systemic_error')
        ->and($result['next_action'])->toBe('operator_recovery')
        ->and($result['revision'])->toBe($before->scan_revision);
    expect($current->status)->toBe('scanning')
        ->and($current->scan_revision)->toBe($before->scan_revision)
        ->and($current->cursor)->toBe($before->cursor)
        ->and($current->upload_ledger)->toBe($before->upload_ledger)
        ->and($current->file_count)->toBe(1)
        ->and($current->byte_count)->toBe(strlen(catalogSourcePng()))
        ->and($current->visited_nodes)->toBe(1)
        ->and($current->traversal_complete)->toBeTrue()
        ->and(Str::isUlid($current->scan_token))->toBeTrue()
        ->and($current->manifest_path)->toBeNull()
        ->and($current->error_code)->toBeNull()
        ->and($current->error_detail)->toBeNull();
    Log::shouldNotHaveReceived('info');
    Log::shouldHaveReceived('error')->with('Catalog asset source operation paused.', Mockery::on(fn (array $context): bool => $context['stage'] === 'finalize'
        && $context['claim_token'] === $current->scan_token && $context['correlation_id'] === $result['correlation_id']
        && $context['exception'] instanceof QueryException))->once();

    Storage::disk('catalog-asset-imports')->put($fragment['path'], $originalArtifact);
    $this->travel(61)->seconds();
    $recovered = $sources->finalize($actor, $source->id, 1, $status['revision']);

    expect($recovered['outcome'])->toBe('ready')
        ->and($recovered['file_count'])->toBe(1)
        ->and($sources->manifest($actor, $source->id, 1)['entries'])->toHaveCount(1);
});

test('actual configured folder filesystem confinement rejects linked files directories and root components', function (string $kind) {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $owner = Product::factory()->create()->refresh();
    $ownerBefore = $owner->getAttributes();
    $disk = Storage::disk('catalog-source-fixtures');
    $disk->put('control.png', catalogSourcePng());
    $outside = catalogImageTestDisk('outside-source');
    $outside->put('nested/escape.png', catalogSourcePng());
    $outsideBefore = hash_file('sha256', $outside->path('nested/escape.png'));
    $sources = app(CatalogAssetSources::class);
    $control = $sources->startScan($actor, 'fixture', 'product.main_image', [], true);
    $progress = finishCatalogSourceScan($sources, $actor, $control);
    $sources->finalize($actor, $control->id, 1, $progress['revision']);
    expect(array_column($sources->manifest($actor, $control->id, 1)['entries'], 'name'))->toBe(['control.png']);
    $link = $disk->path($kind === 'file' ? 'linked.png' : 'linked');
    expect(symlink($kind === 'file' ? $outside->path('nested/escape.png') : rtrim($outside->path(''), '/'), $link))->toBeTrue();
    try {
        if ($kind === 'root component') {
            config(['catalog-assets.sources.fixture.path' => $link.'/nested']);
            expect(fn () => $sources->startScan($actor, 'fixture', 'product.main_image', [], true))->toThrow(DomainException::class, 'path_invalid');
            expect(CatalogAssetImportSource::count())->toBe(1);
        } else {
            $source = $sources->startScan($actor, 'fixture', 'product.main_image', [], true);
            $result = $sources->scanNext($actor, $source->id, 1, 0);
            expect($result['stage'])->toBe('stale')->and($result['code'])->toBe('path_invalid');
            expect(fn () => $sources->manifest($actor, $source->id, 1))->toThrow(ValidationException::class);
        }
        expect(CatalogAsset::count())->toBe(0)->and(Media::count())->toBe(0)
            ->and($owner->fresh()->getAttributes())->toBe($ownerBefore)
            ->and(hash_file('sha256', $outside->path('nested/escape.png')))->toBe($outsideBefore);
    } finally {
        unlink($link);
    }
})->with(['file', 'directory', 'root component']);
