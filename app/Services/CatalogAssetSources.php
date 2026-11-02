<?php

namespace App\Services;

use App\Models\CatalogAssetImportSource;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * @phpstan-type SourceProgress array{stage: string, outcome: string, code: string|null, message: string, correlation_id: string, source_id: string, scan_version: int, revision: int, file_count: int, byte_count: int, visited_nodes: int, uploads_sealed: bool, unsettled_uploads: int, traversal_complete: bool, next_action: string, current_entry?: array{entry_id: string, status: string, committed: bool}}
 */
class CatalogAssetSources
{
    private const MaxArtifactBytes = 16777216;

    public function __construct(private CatalogAssetTargets $targets, private CatalogAssetSourceFiles $files) {}

    /** @param array<string, mixed> $ownerContext
     * @param  array<string, mixed>|null  $batch
     */
    public function startUpload(User $actor, string $targetKey, array $ownerContext, string $label, ?array $batch = null): CatalogAssetImportSource
    {
        $this->assertNoTransaction();
        $this->authorizeTarget($actor, $targetKey, $ownerContext);
        Validator::make(['label' => $label], ['label' => ['required', 'string', 'max:160']])->validate();

        $definition = ['target_key' => $targetKey, 'owner_context' => $ownerContext, 'label' => $label];
        if ($batch === null) {
            return $this->createSource($actor, 'upload', $definition,
                ['status' => 'uploading', 'cursor' => ['upload_position' => 0, 'fragments' => []]]);
        }
        $batch = $this->batchDescriptor($batch);
        $definition['intake'] = $batch;
        $existing = CatalogAssetImportSource::query()->find($batch['id']);
        if ($existing === null) {
            try {
                return $this->createSource($actor, 'upload', $definition,
                    ['status' => 'uploading', 'cursor' => ['upload_position' => 0, 'fragments' => []]], $batch['id']);
            } catch (UniqueConstraintViolationException) {
                $existing = CatalogAssetImportSource::query()->findOrFail($batch['id']);
            }
        }
        $meaning = app(CatalogAssetReviewFiles::class);
        $this->require($existing->actor_id === $actor->id && $existing->source_kind === 'upload'
            && hash_equals($meaning->digest($definition), $meaning->digest($existing->source)), 'This upload identity belongs to a different intake.');

        return $existing;
    }

    /** @param array<string, mixed> $metadata
     * @return SourceProgress
     */
    public function reserveUpload(User $actor, string $sourceId, string $entryId, array $metadata): array
    {
        $this->assertNoTransaction();
        $metadata = $this->uploadMetadata($entryId, $metadata);

        return $this->transaction(function () use ($actor, $sourceId, $entryId, $metadata): array {
            $source = $this->owned($actor, $sourceId, true);
            $ledger = $source->upload_ledger;
            if (isset($ledger[$entryId])) {
                $this->require($ledger[$entryId]['metadata'] == $metadata, 'The upload entry has different metadata.');

                return $this->progress($source, 'reconciled');
            }
            $this->require($source->source_kind === 'upload' && $source->status === 'uploading' && ! $source->uploads_sealed, 'This upload source is sealed or cannot accept files.');
            $limits = $this->sourceLimits($source);
            $this->require(count($ledger) < $limits['source_files'], 'The source file limit has been reached.');
            $reserved = $this->reservedBytes($ledger);
            $this->require($source->byte_count + $reserved + $metadata['bytes'] <= $limits['source_bytes'], 'The source byte limit would be exceeded.');
            $ledger[$entryId] = ['metadata' => $metadata, 'status' => 'reserved', 'reference_hash' => null, 'token' => null, 'expires_at' => null];
            $source->forceFill(['upload_ledger' => $ledger, 'unsettled_uploads' => $source->unsettled_uploads + 1, 'scan_revision' => $source->scan_revision + 1])->save();

            return $this->progress($source, 'reserved');
        });
    }

    /**
     * The signed reference is a bearer capability. The UI adapter must retain its
     * temporary-property association with this actor/source/entry, not infer it from a filename.
     *
     * @return SourceProgress
     */
    public function commitUpload(User $actor, string $sourceId, string $entryId, string $temporaryUploadId): array
    {
        $this->assertNoTransaction();
        $started = hrtime(true);
        $path = TemporaryUploadedFile::extractPathFromSignedPath($temporaryUploadId);
        $this->require(is_string($path) && $path !== '' && basename($path) === $path && ! preg_match('/[\\\\\x00-\x1f\x7f]/', $path), 'The temporary upload reference is invalid.');
        $referenceHash = hash('sha256', $temporaryUploadId);
        $claim = $this->transaction(function () use ($actor, $sourceId, $entryId, $referenceHash): array {
            $source = $this->owned($actor, $sourceId, true);
            $ledger = $source->upload_ledger;
            $this->require(isset($ledger[$entryId]), 'Reserve this upload entry first.');
            $entry = $ledger[$entryId];
            $this->require($entry['reference_hash'] === null || hash_equals($entry['reference_hash'], $referenceHash), 'This upload entry is already bound to a different temporary file.');
            if ($entry['status'] === 'committed') {
                return ['progress' => $this->progress($source, 'reconciled')];
            }
            $this->require($source->source_kind === 'upload' && $source->status === 'uploading' && ! $source->uploads_sealed, 'This source cannot commit another upload.');
            if ($entry['token'] !== null && $entry['expires_at'] > now()->timestamp) {
                return ['progress' => $this->progress($source, 'busy', 'busy')];
            }
            $token = (string) Str::ulid();
            $ledger[$entryId] = [...$entry, 'status' => 'uploading', 'reference_hash' => $referenceHash, 'token' => $token, 'expires_at' => now()->addSeconds(config('catalog-assets.requests.claim_seconds'))->timestamp];
            $source->forceFill(['upload_ledger' => $ledger, 'scan_revision' => $source->scan_revision + 1])->save();

            return ['source' => $source, 'entry' => $ledger[$entryId], 'token' => $token];
        });
        if (isset($claim['progress'])) {
            return $claim['progress'];
        }
        $source = $claim['source'];
        $token = $claim['token'];
        try {
            $temporaryDisk = FileUploadConfiguration::storage();
            if (! $temporaryDisk->getAdapter() instanceof LocalFilesystemAdapter) {
                throw new LogicException('Catalog asset intake requires a local temporary upload disk.');
            }
            $root = $this->files->root($temporaryDisk->path(FileUploadConfiguration::directory()));
            $metadata = $claim['entry']['metadata'];
            $entry = $this->files->inspect($root, $path, $this->policy(), true, $metadata['name']);
            $privatePath = null;
            if ($entry['status'] === 'valid') {
                $privatePath = $source->id.'/v'.$source->scan_version.'/entries/'.$entryId.'/'.$token.'/source';
                $this->writeExclusive($privatePath, $entry['contents']);
            }
            unset($entry['contents'], $entry['read_bytes']);
            $entry = [...$entry, 'entry_id' => $entryId, 'name' => $metadata['name'], 'relative_path' => $metadata['relative_path'], 'private_path' => $privatePath];

            $result = $this->transaction(function () use ($actor, $source, $entryId, $token, $referenceHash, $entry): array {
                $current = $this->owned($actor, $source->id, true);
                $ledger = $current->upload_ledger;
                if (! isset($ledger[$entryId]) || $ledger[$entryId]['token'] !== $token || $ledger[$entryId]['reference_hash'] !== $referenceHash || $current->status !== 'uploading') {
                    return $this->progress($current, 'reconciled');
                }
                $reservedOtherBytes = $this->reservedBytes($ledger) - $ledger[$entryId]['metadata']['bytes'];
                $limits = $this->sourceLimits($current);
                if ($current->file_count + 1 > $limits['source_files']
                    || $current->byte_count + $entry['bytes'] + $reservedOtherBytes > $limits['source_bytes']
                    || $current->visited_nodes + 1 > config('catalog-assets.requests.source_nodes')) {
                    throw new DomainException('source_limit');
                }
                $ledger[$entryId] = [...$ledger[$entryId], 'status' => 'committed', 'entry' => $entry, 'token' => null, 'expires_at' => null];
                $current->forceFill(['upload_ledger' => $ledger, 'unsettled_uploads' => $current->unsettled_uploads - 1, 'file_count' => $current->file_count + 1,
                    'byte_count' => $current->byte_count + $entry['bytes'], 'visited_nodes' => $current->visited_nodes + 1, 'scan_revision' => $current->scan_revision + 1])->save();

                return $this->progress($current, 'committed');
            });

            return $this->completed($source, 'upload', $result, $started, $entryId, $token);
        } catch (DomainException $exception) {
            try {
                $result = $this->failUpload($actor, $source, $entryId, $token, $exception->getMessage());
            } catch (QueryException $journalError) {
                return $this->systemic($source, 'upload', $journalError, $started, $entryId, $token);
            }

            return $this->completed($source, 'upload', $result, $started, $entryId, $token);
        } catch (AuthorizationException|ModelNotFoundException|ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->systemic($source, 'upload', $exception, $started, $entryId, $token);
        }
    }

    /** @return SourceProgress */
    public function sealUploads(User $actor, string $sourceId, int $expectedRevision): array
    {
        $this->assertNoTransaction();

        return $this->transaction(function () use ($actor, $sourceId, $expectedRevision): array {
            $source = $this->owned($actor, $sourceId, true);
            $this->require($expectedRevision >= 0 && $expectedRevision <= $source->scan_revision, 'The source revision is invalid.');
            if ($expectedRevision < $source->scan_revision || $source->uploads_sealed) {
                return $this->progress($source, 'reconciled');
            }
            $this->require($source->source_kind === 'upload' && $source->status === 'uploading' && $source->unsettled_uploads === 0, 'Settle all reserved uploads before sealing the source.');
            if (isset($source->source['intake'])) {
                $intake = $source->source['intake'];
                $this->require($source->file_count === $intake['file_count'] && $source->byte_count === $intake['byte_count'], 'Copy the complete selected intake before finishing.');
                foreach ($intake['entries'] as $entry) {
                    $this->require(($source->upload_ledger[$entry['entry_id']]['status'] ?? null) === 'committed', 'Copy the complete selected intake before finishing.');
                }
            }
            $source->forceFill(['uploads_sealed' => true, 'status' => 'scanning', 'scan_revision' => $source->scan_revision + 1])->save();

            return $this->progress($source, 'sealed');
        });
    }

    /** @param array<string, mixed> $ownerContext */
    public function startScan(User $actor, string $sourceAliasOrOwnedId, string $targetKey, array $ownerContext, bool $recursive): CatalogAssetImportSource
    {
        $this->assertNoTransaction();
        $this->authorizeTarget($actor, $targetKey, $ownerContext);
        $version = 1;
        if (Str::isUlid($sourceAliasOrOwnedId)) {
            $previous = $this->owned($actor, $sourceAliasOrOwnedId);
            $this->require($previous->source['target_key'] === $targetKey && $previous->source['owner_context'] == $ownerContext, 'This source belongs to a different image target or context.');
            if ($previous->source_kind === 'upload') {
                $this->require($previous->uploads_sealed && $previous->unsettled_uploads === 0
                    && count(array_filter($previous->upload_ledger, fn (array $entry): bool => $entry['status'] !== 'committed')) === 0, 'Seal and settle this upload source before scanning.');
                if ($previous->status === 'scanning') {
                    return $previous;
                }

                return $this->createSource($actor, 'upload', $previous->source, ['status' => 'scanning', 'scan_version' => $previous->scan_version + 1,
                    'uploads_sealed' => true, 'upload_ledger' => $previous->upload_ledger, 'file_count' => $previous->file_count, 'byte_count' => $previous->byte_count,
                    'visited_nodes' => $previous->visited_nodes, 'cursor' => ['upload_position' => 0, 'fragments' => []]]);
            }
            $sourceAliasOrOwnedId = $previous->source['alias'];
            $version = $previous->scan_version + 1;
        }
        $configured = config('catalog-assets.sources.'.$sourceAliasOrOwnedId);
        $this->require(is_array($configured) && isset($configured['path'], $configured['label']), 'Choose a configured image source.');
        $root = $this->files->root($configured['path']);
        $cursor = $this->files->initialCursor($root);
        $cursor['recursive'] = $recursive;
        $stat = stat($root);

        return $this->createSource($actor, 'folder', ['target_key' => $targetKey, 'owner_context' => $ownerContext, 'label' => $configured['label'], 'alias' => $sourceAliasOrOwnedId,
            'root_identity' => ['path' => $root, 'dev' => $stat['dev'], 'ino' => $stat['ino']]],
            ['status' => 'scanning', 'scan_version' => $version, 'uploads_sealed' => true, 'cursor' => ['traversal' => $cursor, 'fragments' => []]]);
    }

    /** @return SourceProgress */
    public function scanNext(User $actor, string $sourceId, int $expectedVersion, int $expectedRevision): array
    {
        $this->assertNoTransaction();
        $started = hrtime(true);
        $claim = $this->claim($actor, $sourceId, $expectedVersion, $expectedRevision, false);
        if (isset($claim['progress'])) {
            return $claim['progress'];
        }
        $source = $claim['source'];
        try {
            $cursor = $source->cursor;
            if ($source->source_kind === 'folder') {
                $step = $this->files->scan($this->folderRoot($source), $cursor['traversal'], $this->policy(), config('catalog-assets.requests'),
                    ['file_count' => $source->file_count, 'byte_count' => $source->byte_count, 'visited_nodes' => $source->visited_nodes]);
                $cursor['traversal'] = $step['cursor'];
            } else {
                $this->require($source->uploads_sealed && $source->unsettled_uploads === 0, 'Uploads are not settled and sealed.');
                $entries = array_values($source->upload_ledger);
                $this->require(config('catalog-assets.requests.scan_new_nodes') > 0, 'The scan request budget is invalid.');
                $part = array_slice($entries, $cursor['upload_position'], config('catalog-assets.requests.scan_new_nodes'));
                $cursor['upload_position'] += count($part);
                $step = ['entries' => array_column($part, 'entry'), 'complete' => $cursor['upload_position'] >= count($entries),
                    'file_count' => $source->file_count, 'byte_count' => $source->byte_count, 'visited_nodes' => $source->visited_nodes];
            }
            $fragment = ['source_id' => $source->id, 'scan_version' => $source->scan_version, 'revision' => $expectedRevision, 'entries' => $step['entries']];
            $body = $this->canonicalJson($fragment);
            $path = $source->id.'/v'.$source->scan_version.'/scan/'.$expectedRevision.'/'.$claim['token'].'.json';
            $this->writeExclusive($path, $body);
            $cursor['fragments'][] = ['path' => $path, 'sha256' => hash('sha256', $body)];

            $result = $this->transaction(function () use ($actor, $source, $claim, $cursor, $step): array {
                $current = $this->owned($actor, $source->id, true);
                if (! $this->ownsClaim($current, $source, $claim['token'])) {
                    return $this->progress($current, 'reconciled');
                }
                $current->forceFill(['cursor' => $cursor, 'file_count' => $step['file_count'], 'byte_count' => $step['byte_count'], 'visited_nodes' => $step['visited_nodes'],
                    'traversal_complete' => $step['complete'], 'scan_revision' => $current->scan_revision + 1, 'scan_token' => null, 'scan_expires_at' => null])->save();

                return $this->progress($current, 'scanned');
            });

            return $this->completed($source, 'scan', $result, $started, null, $claim['token']);
        } catch (DomainException $exception) {
            try {
                $result = $this->failClaim($actor, $source, $claim['token'], $exception->getMessage());
            } catch (QueryException $journalError) {
                return $this->systemic($source, 'scan', $journalError, $started, null, $claim['token']);
            }

            return $this->completed($source, 'scan', $result, $started, null, $claim['token']);
        } catch (AuthorizationException|ModelNotFoundException|ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->systemic($source, 'scan', $exception, $started, null, $claim['token']);
        }
    }

    /** @return SourceProgress */
    public function finalize(User $actor, string $sourceId, int $expectedVersion, int $expectedRevision): array
    {
        $this->assertNoTransaction();
        $started = hrtime(true);
        $claim = $this->claim($actor, $sourceId, $expectedVersion, $expectedRevision, true);
        if (isset($claim['progress'])) {
            return $claim['progress'];
        }
        $source = $claim['source'];
        try {
            if ($source->source_kind === 'folder') {
                $this->files->verifyDirectories($this->folderRoot($source), $source->cursor['traversal']);
            }
            $entries = [];
            $seen = [];
            foreach ($source->cursor['fragments'] as $fragment) {
                $decoded = $this->readArtifact($source, $fragment['path'], $fragment['sha256']);
                if (($decoded['source_id'] ?? null) !== $source->id || ($decoded['scan_version'] ?? null) !== $source->scan_version || ! is_array($decoded['entries'] ?? null)) {
                    throw new DomainException('artifact_invalid');
                }
                foreach ($decoded['entries'] as $entry) {
                    if (! is_array($entry) || ! Str::isUlid($entry['entry_id'] ?? '') || isset($seen[$entry['entry_id']]) || ! is_int($entry['bytes'] ?? null) || $entry['bytes'] < 0) {
                        throw new DomainException('artifact_invalid');
                    }
                    $seen[$entry['entry_id']] = true;
                    $entries[] = $entry;
                    if (count($entries) > config('catalog-assets.limits.source_files')) {
                        throw new DomainException('source_limit');
                    }
                }
            }
            if (count($entries) !== $source->file_count || array_sum(array_column($entries, 'bytes')) !== $source->byte_count) {
                throw new DomainException('artifact_invalid');
            }
            $this->verifyCaps($source);
            $manifest = ['source_id' => $source->id, 'scan_version' => $source->scan_version, 'target_key' => $source->source['target_key'],
                'owner_context' => $source->source['owner_context'], 'entries' => $entries, 'file_count' => $source->file_count, 'byte_count' => $source->byte_count];
            $body = $this->canonicalJson($manifest);
            $hash = hash('sha256', $body);
            $path = $source->id.'/v'.$source->scan_version.'/manifest/'.$claim['token'].'/'.$hash.'.json';
            $this->writeExclusive($path, $body);

            $result = $this->transaction(function () use ($actor, $source, $claim, $path, $hash): array {
                $current = $this->owned($actor, $source->id, true);
                if (! $this->ownsClaim($current, $source, $claim['token'])) {
                    return $this->progress($current, 'reconciled');
                }
                $current->forceFill(['status' => 'ready', 'manifest_path' => $path, 'manifest_sha256' => $hash, 'scan_revision' => $current->scan_revision + 1,
                    'scan_token' => null, 'scan_expires_at' => null])->save();

                return $this->progress($current, 'ready');
            });

            return $this->completed($source, 'finalize', $result, $started, null, $claim['token']);
        } catch (DomainException $exception) {
            try {
                $result = $this->failClaim($actor, $source, $claim['token'], $exception->getMessage());
            } catch (QueryException $journalError) {
                return $this->systemic($source, 'finalize', $journalError, $started, null, $claim['token']);
            }

            return $this->completed($source, 'finalize', $result, $started, null, $claim['token']);
        } catch (AuthorizationException|ModelNotFoundException|ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->systemic($source, 'finalize', $exception, $started, null, $claim['token']);
        }
    }

    /** @return SourceProgress */
    public function status(User $actor, string $sourceId, ?string $entryId = null): array
    {
        $source = $this->owned($actor, $sourceId);
        $progress = $this->progress($source, 'status');
        if ($entryId !== null) {
            $this->require(Str::isUlid($entryId), 'The upload entry identifier is invalid.');
            $status = $source->upload_ledger[$entryId]['status'] ?? 'missing';
            $progress['current_entry'] = ['entry_id' => $entryId, 'status' => $status, 'committed' => $status === 'committed'];
        }

        return $progress;
    }

    /** @return array{source_id: string, scan_version: int, target_key: string, owner_context: array<string, mixed>, entries: list<array<string, mixed>>, file_count: int, byte_count: int} */
    public function manifest(User $actor, string $sourceId, int $version): array
    {
        $source = $this->owned($actor, $sourceId);
        $this->require($source->status === 'ready' && $source->scan_version === $version && $source->manifest_path !== null && $source->manifest_sha256 !== null, 'This image source is not ready for review.');
        $manifest = $this->readArtifact($source, $source->manifest_path, $source->manifest_sha256);
        if (($manifest['source_id'] ?? null) !== $sourceId || ($manifest['scan_version'] ?? null) !== $version || ($manifest['target_key'] ?? null) !== $source->source['target_key']
            || ($manifest['owner_context'] ?? null) != $source->source['owner_context']) {
            throw new DomainException('artifact_invalid');
        }

        return $manifest;
    }

    /**
     * Resolves private preparation evidence; this result must never enter browser state.
     *
     * @param  array{source_id: string, scan_version: int, entry_id: string, sha256: string}  $sourceChoice
     * @return array{source_id: string, source_kind: string, scan_version: int, target_key: string, owner_context: array<string, mixed>, entry: array<string, mixed>, root: string, relative_path: string}
     */
    public function selectedEntry(User $actor, array $sourceChoice, string $targetKey): array
    {
        Validator::make(['choice' => $sourceChoice], [
            'choice' => ['required', 'array:source_id,scan_version,entry_id,sha256'],
            'choice.source_id' => ['required', 'ulid'], 'choice.scan_version' => ['required', 'integer', 'min:1'],
            'choice.entry_id' => ['required', 'ulid'], 'choice.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
        ])->validate();
        $this->require(is_int($sourceChoice['scan_version']), 'The source version must be an integer.');
        $source = $this->owned($actor, $sourceChoice['source_id']);
        $this->require($source->source['target_key'] === $targetKey, 'This source belongs to a different image target.');
        $manifest = $this->manifest($actor, $source->id, $sourceChoice['scan_version']);
        $matches = array_values(array_filter($manifest['entries'], fn (array $entry): bool => $entry['entry_id'] === $sourceChoice['entry_id']));
        $this->require(count($matches) === 1 && $matches[0]['status'] === 'valid' && is_string($matches[0]['sha256'])
            && hash_equals($matches[0]['sha256'], $sourceChoice['sha256']), 'The reviewed source entry is unavailable or has changed.');
        $entry = $matches[0];
        $root = $source->source_kind === 'folder' ? $this->folderRoot($source) : $this->files->root($this->imports()->path(''));
        $relative = $source->source_kind === 'folder' ? $entry['relative_path'] : $entry['private_path'];
        $actual = $this->files->inspect($root, $relative, $this->policy(), false, $entry['name']);
        if ($actual['status'] !== 'valid' || $actual['sha256'] !== $entry['sha256'] || $actual['bytes'] !== $entry['bytes']
            || $actual['mime_type'] !== $entry['mime_type'] || $actual['width'] !== $entry['width'] || $actual['height'] !== $entry['height']) {
            throw new DomainException('source_stale');
        }

        return ['source_id' => $source->id, 'source_kind' => $source->source_kind, 'scan_version' => $source->scan_version,
            'target_key' => $targetKey, 'owner_context' => $source->source['owner_context'], 'entry' => $entry,
            'root' => $root, 'relative_path' => $relative];
    }

    /** @return array{stream: resource, mime_type: string, bytes: int} */
    public function preview(User $actor, string $sourceId, int $version, string $entryId): array
    {
        $source = $this->owned($actor, $sourceId);
        abort_unless($source->status === 'ready' && $source->scan_version === $version && Str::isUlid($entryId), 404);
        try {
            $manifest = $this->manifest($actor, $sourceId, $version);
            $entry = null;
            foreach ($manifest['entries'] as $candidate) {
                if ($candidate['entry_id'] === $entryId) {
                    $entry = $candidate;
                    break;
                }
            }
            abort_unless($entry !== null && $entry['status'] === 'valid' && is_string($entry['sha256']), 404);
            $root = $source->source_kind === 'folder' ? $this->folderRoot($source) : $this->files->root($this->imports()->path(''));
            $relative = $source->source_kind === 'folder' ? $entry['relative_path'] : $entry['private_path'];
            $actual = $this->files->inspect($root, $relative, $this->policy(), true, $entry['name']);
            abort_unless($actual['status'] === 'valid' && hash_equals($entry['sha256'], $actual['sha256'])
                && $actual['bytes'] === $entry['bytes'] && $actual['mime_type'] === $entry['mime_type'] && $actual['width'] === $entry['width'] && $actual['height'] === $entry['height'], 404);
            $stream = fopen('php://temp', 'w+b');
            if ($stream === false || fwrite($stream, $actual['contents']) !== strlen($actual['contents']) || ! rewind($stream)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                throw new RuntimeException('Unable to prepare a private preview stream.');
            }

            return ['stream' => $stream, 'mime_type' => $actual['mime_type'], 'bytes' => $actual['bytes']];
        } catch (DomainException) {
            abort(404);
        }
    }

    /** @param array<string, mixed> $source
     * @param  array<string, mixed>  $attributes
     */
    private function createSource(User $actor, string $kind, array $source, array $attributes, ?string $id = null): CatalogAssetImportSource
    {
        $model = new CatalogAssetImportSource;
        $model->forceFill(['actor_id' => $actor->id, 'source_kind' => $kind, 'source' => $source, ...$attributes, ...($id === null ? [] : ['id' => $id])])->save();

        return $model;
    }

    private function authorizeTarget(User $actor, string $targetKey, array $ownerContext): void
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        abort_unless($actor->exists && $actor->getKey() !== null, 403);
        $this->targets->eligibleQuery($actor, $targetKey, $ownerContext);
    }

    private function owned(User $actor, string $sourceId, bool $lock = false): CatalogAssetImportSource
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        Gate::forUser($actor)->authorize('manage-catalog');
        abort_unless($actor->exists && $actor->getKey() !== null && Str::isUlid($sourceId), 404);
        $query = CatalogAssetImportSource::query()->where('actor_id', $actor->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $source = $query->findOrFail($sourceId);
        abort_unless(is_string($source->source['target_key'] ?? null) && is_array($source->source['owner_context'] ?? null), 404);
        $this->authorizeTarget($actor, $source->source['target_key'], $source->source['owner_context']);

        return $source;
    }

    /** @return array<string, mixed> */
    private function claim(User $actor, string $sourceId, int $expectedVersion, int $expectedRevision, bool $finalize): array
    {
        return $this->transaction(function () use ($actor, $sourceId, $expectedVersion, $expectedRevision, $finalize): array {
            $source = $this->owned($actor, $sourceId, true);
            $this->require($expectedVersion === $source->scan_version && $expectedRevision >= 0 && $expectedRevision <= $source->scan_revision, 'The source version or revision is invalid.');
            if ($expectedRevision < $source->scan_revision || $source->status === 'ready' || in_array($source->status, ['stale', 'failed'], true)) {
                return ['progress' => $this->progress($source, 'reconciled')];
            }
            if ($source->scan_token !== null && $source->scan_expires_at?->isFuture()) {
                return ['progress' => $this->progress($source, 'busy', 'busy')];
            }
            $this->require($source->status === 'scanning', 'This source is not available for scanning.');
            if ($finalize) {
                $this->require($source->traversal_complete && $source->uploads_sealed && $source->unsettled_uploads === 0, 'Finish traversal and settle all uploads before finalizing.');
            } elseif ($source->traversal_complete) {
                return ['progress' => $this->progress($source, 'scanned')];
            }
            $token = (string) Str::ulid();
            $source->forceFill(['scan_token' => $token, 'scan_expires_at' => now()->addSeconds(config('catalog-assets.requests.claim_seconds'))])->save();

            return ['source' => $source, 'token' => $token];
        });
    }

    private function ownsClaim(CatalogAssetImportSource $current, CatalogAssetImportSource $claimed, string $token): bool
    {
        return $current->status === 'scanning' && $current->scan_version === $claimed->scan_version && $current->scan_revision === $claimed->scan_revision
            && $current->scan_token === $token && $this->canonicalJson($current->cursor) === $this->canonicalJson($claimed->cursor);
    }

    /** @return SourceProgress */
    private function failClaim(User $actor, CatalogAssetImportSource $source, string $token, string $code): array
    {
        $correlation = (string) Str::ulid();

        return $this->transaction(function () use ($actor, $source, $token, $code, $correlation): array {
            $current = $this->owned($actor, $source->id, true);
            if (! $this->ownsClaim($current, $source, $token)) {
                return $this->progress($current, 'reconciled');
            }
            $code = $this->safeCode($code);
            $current->forceFill(['status' => in_array($code, ['source_stale', 'path_invalid'], true) ? 'stale' : 'failed', 'error_code' => $code,
                'error_detail' => 'Image source stopped. Reference: '.$correlation.'. Start a new source or request operator review.', 'scan_token' => null, 'scan_expires_at' => null,
                'scan_revision' => $current->scan_revision + 1])->save();

            return $this->progress($current, 'failed', $code, $correlation);
        });
    }

    /** @return SourceProgress */
    private function failUpload(User $actor, CatalogAssetImportSource $source, string $entryId, string $token, string $code): array
    {
        $correlation = (string) Str::ulid();

        return $this->transaction(function () use ($actor, $source, $entryId, $token, $code, $correlation): array {
            $current = $this->owned($actor, $source->id, true);
            $ledger = $current->upload_ledger;
            if (! isset($ledger[$entryId]) || $ledger[$entryId]['token'] !== $token || $current->status !== 'uploading') {
                return $this->progress($current, 'reconciled');
            }
            $code = $this->safeCode($code);
            $ledger[$entryId] = [...$ledger[$entryId], 'status' => 'failed', 'token' => null, 'expires_at' => null];
            $current->forceFill(['upload_ledger' => $ledger, 'unsettled_uploads' => $current->unsettled_uploads - 1,
                'status' => in_array($code, ['source_stale', 'path_invalid'], true) ? 'stale' : 'failed', 'error_code' => $code, 'error_detail' => 'Image source stopped. Reference: '.$correlation.'. Start a new source or request operator review.',
                'scan_revision' => $current->scan_revision + 1])->save();

            return $this->progress($current, 'failed', $code, $correlation);
        });
    }

    private function folderRoot(CatalogAssetImportSource $source): string
    {
        $configured = config('catalog-assets.sources.'.$source->source['alias']);
        if (! is_array($configured) || ! is_string($configured['path'] ?? null)) {
            throw new DomainException('source_stale');
        }
        $root = $this->files->root($configured['path']);
        $stat = stat($root);
        $identity = $source->source['root_identity'];
        if ($root !== $identity['path'] || $stat['dev'] !== $identity['dev'] || $stat['ino'] !== $identity['ino']) {
            throw new DomainException('source_stale');
        }

        return $root;
    }

    /** @return array<string, mixed> */
    private function readArtifact(CatalogAssetImportSource $source, string $path, string $expectedHash): array
    {
        if (! str_starts_with($path, $source->id.'/v'.$source->scan_version.'/')) {
            throw new DomainException('artifact_invalid');
        }
        $absolute = $this->files->safePath($this->files->root($this->imports()->path('')), $path);
        $handle = @fopen($absolute, 'rb');
        if ($handle === false) {
            throw new RuntimeException('A private source artifact is unavailable.');
        }
        try {
            $body = stream_get_contents($handle, self::MaxArtifactBytes + 1);
        } finally {
            fclose($handle);
        }
        if ($body === false || strlen($body) > self::MaxArtifactBytes || ! hash_equals($expectedHash, hash('sha256', $body))) {
            throw new DomainException('artifact_invalid');
        }
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new DomainException('artifact_invalid');
        }

        return $decoded;
    }

    private function writeExclusive(string $path, string $contents): void
    {
        $disk = $this->imports();
        if (! $disk->makeDirectory(dirname($path))) {
            throw new RuntimeException('Unable to create private source storage.');
        }
        $absolute = $disk->path($path);
        $this->files->safePath($this->files->root($disk->path('')), dirname($path), true);
        $handle = @fopen($absolute, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to create an immutable source artifact.');
        }
        try {
            if (! chmod($absolute, 0600)) {
                throw new RuntimeException('Unable to protect private source storage.');
            }
            $offset = 0;
            $length = strlen($contents);
            while ($offset < $length) {
                $written = fwrite($handle, substr($contents, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write private source storage.');
                }
                $offset += $written;
            }
            if (! fflush($handle)) {
                throw new RuntimeException('Unable to flush private source storage.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function imports(): FilesystemAdapter
    {
        if (config('filesystems.disks.catalog-asset-imports.driver') !== 'local') {
            throw new LogicException('Catalog asset source storage must be local.');
        }

        return Storage::disk('catalog-asset-imports');
    }

    /** @return array<string, mixed> */
    private function policy(): array
    {
        return [...config('catalog-assets.image'), 'source_files' => config('catalog-assets.limits.source_files'), 'source_bytes' => config('catalog-assets.limits.source_bytes')];
    }

    private function verifyCaps(CatalogAssetImportSource $source): void
    {
        $limits = $this->sourceLimits($source);
        if ($source->file_count > $limits['source_files'] || $source->byte_count > $limits['source_bytes']
            || $source->visited_nodes > config('catalog-assets.requests.source_nodes')) {
            throw new DomainException('source_limit');
        }
    }

    /** @param array<string, mixed> $batch
     * @return array<string, mixed>
     */
    private function batchDescriptor(array $batch): array
    {
        Validator::make(['batch' => $batch], [
            'batch' => ['array:id,intake_id,file_count,byte_count,selection_sha256,entries'],
            'batch.id' => ['required', 'ulid'], 'batch.intake_id' => ['required', 'ulid'],
            'batch.file_count' => ['required', 'integer', 'min:1', 'max:'.config('catalog-assets.limits.source_files')],
            'batch.byte_count' => ['required', 'integer', 'min:0', 'max:'.config('catalog-assets.limits.source_bytes')],
            'batch.selection_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'batch.entries' => ['required', 'array', 'list', 'min:1', 'max:'.config('catalog-assets.limits.source_files')],
            'batch.entries.*' => ['required', 'array:entry_id,name,relative_path,bytes'],
            'batch.entries.*.entry_id' => ['required', 'ulid', 'distinct:strict'],
        ])->validate();
        $this->require(is_int($batch['file_count']) && is_int($batch['byte_count']), 'The intake counts must be integers.');
        $entries = [];
        foreach ($batch['entries'] as $entry) {
            $metadata = $this->uploadMetadata($entry['entry_id'], array_diff_key($entry, ['entry_id' => true]));
            $this->require($metadata['bytes'] <= config('catalog-assets.image.max_bytes'), 'The selected image exceeds its byte limit.');
            $entries[] = ['entry_id' => $entry['entry_id'], ...$metadata];
        }
        $this->require(count($entries) === $batch['file_count'] && array_sum(array_column($entries, 'bytes')) === $batch['byte_count']
            && hash_equals($batch['selection_sha256'], app(CatalogAssetReviewFiles::class)->digest($entries)), 'The intake selection does not match its declared counts or digest.');

        return ['id' => $batch['id'], 'intake_id' => $batch['intake_id'], 'file_count' => $batch['file_count'],
            'byte_count' => $batch['byte_count'], 'selection_sha256' => $batch['selection_sha256'], 'entries' => $entries];
    }

    /** @return array{source_files: int, source_bytes: int} */
    private function sourceLimits(CatalogAssetImportSource $source): array
    {
        $files = config('catalog-assets.limits.source_files');
        $bytes = config('catalog-assets.limits.source_bytes');
        if (array_key_exists('intake', $source->source)) {
            $intake = $source->source['intake'];
            $this->require(is_array($intake) && is_int($intake['file_count'] ?? null) && $intake['file_count'] > 0
                && is_int($intake['byte_count'] ?? null) && $intake['byte_count'] >= 0, 'The stored intake limits are invalid.');
            $files = min($files, $intake['file_count']);
            $bytes = min($bytes, $intake['byte_count']);
        }

        return ['source_files' => $files, 'source_bytes' => $bytes];
    }

    /** @param array<string, array<string, mixed>> $ledger */
    private function reservedBytes(array $ledger): int
    {
        $bytes = 0;
        foreach ($ledger as $entry) {
            if (in_array($entry['status'], ['reserved', 'uploading'], true)) {
                $bytes += $entry['metadata']['bytes'];
            }
        }

        return $bytes;
    }

    /** @param array<string, mixed> $metadata
     * @return array{name: string, relative_path: string, bytes: int}
     */
    public function uploadMetadata(string $entryId, array $metadata): array
    {
        $this->require(Str::isUlid($entryId), 'The upload entry identifier is invalid.');
        Validator::make(['metadata' => $metadata], ['metadata' => ['required', 'array:name,relative_path,bytes'],
            'metadata.name' => ['required', 'string', 'max:255'], 'metadata.relative_path' => ['required', 'string', 'max:1024'], 'metadata.bytes' => ['required', 'integer', 'min:0']])->validate();
        $this->require(is_int($metadata['bytes']), 'The reserved byte count must be an integer.');
        $relative = str_replace('\\', '/', $metadata['relative_path']);
        $parts = explode('/', $relative);
        $this->require(! str_starts_with($relative, '/') && ! preg_match('/^[A-Za-z]:|[\x00-\x1f\x7f]/', $relative)
            && ! array_intersect($parts, ['', '.', '..']) && basename($relative) === $metadata['name'] && ! str_contains($metadata['name'], '\\'), 'The upload filename or relative path is invalid.');

        return ['name' => $metadata['name'], 'relative_path' => $relative, 'bytes' => $metadata['bytes']];
    }

    private function assertNoTransaction(): void
    {
        if ((new CatalogAssetImportSource)->getConnection()->transactionLevel() !== 0) {
            throw new LogicException('Catalog asset source commands require an independently committed request.');
        }
    }

    private function transaction(Closure $operation): mixed
    {
        return (new CatalogAssetImportSource)->getConnection()->transaction($operation, 3);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['image.source' => $message]);
        }
    }

    private function safeCode(string $code): string
    {
        return in_array($code, ['source_stale', 'path_invalid', 'source_limit', 'unsupported_budget', 'artifact_invalid'], true) ? $code : 'source_invalid';
    }

    /** @param SourceProgress $result
     * @return SourceProgress
     */
    private function completed(CatalogAssetImportSource $source, string $stage, array $result, int $started, ?string $entryId, string $token): array
    {
        if (in_array($result['outcome'], ['committed', 'scanned', 'ready', 'failed'], true)) {
            Log::info('Catalog asset source request completed.', ['stage' => $stage, 'actor_id' => $source->actor_id, 'source_id' => $source->id,
                'scan_version' => $result['scan_version'], 'revision' => $result['revision'], 'entry_id' => $entryId, 'claim_token' => $token,
                'correlation_id' => $result['correlation_id'], 'outcome' => $result['outcome'], 'code' => $result['code'],
                'elapsed_ms' => round((hrtime(true) - $started) / 1000000, 3)]);
        }

        return $result;
    }

    /** @return SourceProgress */
    private function systemic(CatalogAssetImportSource $source, string $stage, Throwable $exception, int $started, ?string $entryId, string $token): array
    {
        $correlation = (string) Str::ulid();
        Log::error('Catalog asset source operation paused.', ['stage' => $stage, 'actor_id' => $source->actor_id, 'source_id' => $source->id,
            'scan_version' => $source->scan_version, 'revision' => $source->scan_revision, 'entry_id' => $entryId, 'claim_token' => $token,
            'correlation_id' => $correlation, 'elapsed_ms' => round((hrtime(true) - $started) / 1000000, 3), 'exception' => $exception]);

        return $this->progress($source, 'paused', 'systemic_error', $correlation);
    }

    /** @return SourceProgress */
    private function progress(CatalogAssetImportSource $source, string $outcome, ?string $code = null, ?string $correlation = null): array
    {
        $failed = in_array($source->status, ['failed', 'stale'], true);
        $code ??= $source->error_code;
        $next = match (true) {
            $outcome === 'paused' => 'operator_recovery',
            $failed => 'review_again',
            $outcome === 'busy' => 'continue',
            $source->status === 'ready' => 'none',
            $source->status === 'uploading' => 'continue',
            default => 'next_step',
        };

        return ['stage' => $source->status, 'outcome' => $outcome, 'code' => $code, 'message' => match (true) {
            $outcome === 'paused' => 'Source processing paused. Reconcile its status before continuing.',
            $failed => 'The source cannot continue. Review the source and start a new scan.',
            $outcome === 'busy' => 'Another source request is still running. Check its status before continuing.',
            $source->status === 'ready' => 'The source is ready for review.',
            default => 'Source progress has been saved.',
        }, 'correlation_id' => $correlation ?? (string) Str::ulid(), 'source_id' => $source->id, 'scan_version' => $source->scan_version, 'revision' => $source->scan_revision,
            'file_count' => $source->file_count, 'byte_count' => $source->byte_count, 'visited_nodes' => $source->visited_nodes, 'uploads_sealed' => $source->uploads_sealed,
            'unsettled_uploads' => $source->unsettled_uploads, 'traversal_complete' => $source->traversal_complete, 'next_action' => $next];
    }

    private function canonicalJson(mixed $value): string
    {
        $normalize = function (mixed $input) use (&$normalize): mixed {
            if (! is_array($input)) {
                return $input;
            }
            if (! array_is_list($input)) {
                ksort($input);
            }

            return array_map($normalize, $input);
        };

        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
