<?php

namespace App\Services;

use App\Actions\ReviewCatalogAssets;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetImportSource;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use RuntimeException;
use Throwable;

/** Batch transport association; Sources retains every copy, claim and scan invariant. */
class CatalogAssetIntake
{
    private const MaxManifestBytes = 16777216;

    public function __construct(private CatalogAssetSources $sources, private CatalogAssetReviewFiles $reviews, private CatalogAssetSourceFiles $files) {}

    /** @param list<array{name: string, relative_path: string, bytes: int}> $files
     * @return array{source_id: string, intake_token: string, entry_ids: list<string>}
     */
    public function issue(User $actor, string $launchToken, string $label, array $files): array
    {
        [$actor, $scope] = $this->admit($actor, $launchToken, true);
        Validator::make(compact('label', 'files'), ['label' => ['required', 'string', 'max:160'],
            'files' => ['required', 'array', 'list', 'min:1', 'max:'.config('catalog-assets.limits.source_files')]])->validate();
        $entries = [];
        foreach ($files as $metadata) {
            $this->require(is_array($metadata), 'Choose a valid image selection.');
            $id = (string) Str::ulid();
            $metadata = $this->sources->uploadMetadata($id, $metadata);
            $this->require($metadata['bytes'] <= config('catalog-assets.image.max_bytes'), 'A selected image exceeds 3 MiB.');
            $entries[] = ['entry_id' => $id, ...$metadata];
        }
        $bytes = array_sum(array_column($entries, 'bytes'));
        $this->require($bytes <= config('catalog-assets.limits.source_bytes'), 'The selected source exceeds its byte limit.');
        $batch = ['id' => (string) Str::ulid(), 'intake_id' => (string) Str::ulid(), 'file_count' => count($entries),
            'byte_count' => $bytes, 'selection_sha256' => $this->reviews->digest($entries), 'entries' => $entries];
        $claims = ['purpose' => 'catalog-intake-begin', 'actor_id' => $actor->id, 'scope' => $scope['scope_sha256'],
            'target' => $scope['target_key'], 'context' => $scope['owner_context'], 'label' => $label,
            'batch' => $batch, 'expires_at' => now()->addHours(24)->timestamp];
        $encoded = json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->require(strlen($encoded) <= self::MaxManifestBytes, 'The selected source metadata is too large.');

        return ['source_id' => $batch['id'], 'intake_token' => Crypt::encryptString($encoded), 'entry_ids' => array_column($entries, 'entry_id')];
    }

    /** @return array<string, mixed> */
    public function begin(User $actor, string $launchToken, string $intakeToken): array
    {
        $this->noTransaction();
        [$actor, $scope] = $this->admit($actor, $launchToken, true);
        $claim = $this->open($actor, $intakeToken, 'catalog-intake-begin');
        $this->require(($claim['scope'] ?? null) === $scope['scope_sha256'] && ($claim['target'] ?? null) === $scope['target_key']
            && ($claim['context'] ?? null) === $scope['owner_context'], 'This intake belongs to a different review scope.');
        $source = $this->sources->startUpload($actor, $scope['target_key'], $scope['owner_context'], $claim['label'], $claim['batch']);

        return $this->summary($actor, $source);
    }

    /** @return array{url: string, token: string, entry_id: string, expires_at: int} */
    public function entry(User $actor, string $launchToken, string $sourceId, int $index): array
    {
        [$actor, $scope] = $this->admit($actor, $launchToken, true);
        $source = $this->source($actor, $scope, $sourceId);
        $entry = $source->source['intake']['entries'][$index] ?? null;
        $this->require($index >= 0 && is_array($entry), 'Choose an entry from the current intake.');
        $expiry = now()->addMinutes(5);
        $claims = ['purpose' => 'catalog-intake-entry', 'actor_id' => $actor->id, 'source_id' => $sourceId,
            'intake_id' => $source->source['intake']['intake_id'], 'entry_id' => $entry['entry_id'], 'index' => $index,
            'version' => 1, 'scope' => $scope['scope_sha256'], 'metadata' => $this->reviews->digest($entry), 'expires_at' => $expiry->timestamp];
        $token = Crypt::encryptString(json_encode($claims, JSON_THROW_ON_ERROR));

        return ['url' => URL::temporarySignedRoute('catalog.images.batch-upload', $expiry, ['entry_token' => $token], false),
            'token' => $token, 'entry_id' => $entry['entry_id'], 'expires_at' => $expiry->timestamp];
    }

    /** @return array<string, mixed> */
    public function transfer(User $actor, string $launchToken, string $entryToken, ?UploadedFile $file): array
    {
        $this->noTransaction();
        [$actor, $scope] = $this->admit($actor, $launchToken, true);
        $claim = $this->open($actor, $entryToken, 'catalog-intake-entry');
        $this->require(($claim['scope'] ?? null) === $scope['scope_sha256'] && is_int($claim['index'] ?? null) && ($claim['version'] ?? null) === 1, 'This transfer belongs to a different review.');
        $source = $this->source($actor, $scope, $claim['source_id']);
        $entry = $source->source['intake']['entries'][$claim['index']] ?? null;
        $this->require(is_array($entry) && $entry['entry_id'] === ($claim['entry_id'] ?? null)
            && $source->source['intake']['intake_id'] === ($claim['intake_id'] ?? null)
            && hash_equals($this->reviews->digest($entry), $claim['metadata'] ?? ''), 'The transfer entry changed.');
        $directory = $this->entryDirectory($source, $entry['entry_id'], true);
        $lock = $this->lock($directory, true);
        if ($lock === null) {
            return $this->busy($actor, $source, $entry['entry_id']);
        }
        try {
            $source->refresh();
            $state = $source->upload_ledger[$entry['entry_id']] ?? null;
            $receipt = $this->receipt($directory);
            if (($state['status'] ?? null) === 'committed') {
                if ($receipt !== null && $this->validReceipt($actor, $source, $entry, $receipt, $state['reference_hash'] ?? null)) {
                    $this->removeTemporary($receipt);
                }

                return $this->summary($actor, $source, $entry['entry_id']);
            }
            $bound = $state['reference_hash'] ?? null;
            if ($receipt !== null && ! $this->validReceipt($actor, $source, $entry, $receipt, $bound)) {
                return $this->unresolved($actor, $source, $entry['entry_id']);
            }
            if ($bound !== null && $receipt === null) {
                return $this->unresolved($actor, $source, $entry['entry_id']);
            }
            if (($state['token'] ?? null) !== null && ($state['expires_at'] ?? 0) > now()->timestamp) {
                return $this->busy($actor, $source, $entry['entry_id']);
            }
            $this->require($source->status === 'uploading' && ! $source->uploads_sealed, 'This intake cannot receive another file.');
            $metadata = array_diff_key($entry, ['entry_id' => true]);
            if ($receipt === null || $file !== null) {
                $this->validateFile($file, $metadata);
                if ($receipt !== null) {
                    $this->require(hash_equals($receipt['sha256'], hash_file('sha256', $file->getRealPath())), 'Continue requires the same image bytes.');
                }
            }
            $this->sources->reserveUpload($actor, $source->id, $entry['entry_id'], $metadata);
            if ($receipt === null) {
                $mime = $file->getMimeType();
                $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
                $basename = 'intake-'.$source->id.'-'.$entry['entry_id'].'.'.$extension;
                $receipt = ['schema' => 1, 'actor_id' => $actor->id, 'source_id' => $source->id, 'entry_id' => $entry['entry_id'], 'version' => 1,
                    'intake_id' => $source->source['intake']['intake_id'], 'target' => $source->source['target_key'], 'context' => $this->reviews->digest($source->source['owner_context']),
                    'metadata' => $this->reviews->digest($entry), 'basename' => $basename, 'reference' => TemporaryUploadedFile::signPath($basename),
                    'disk' => FileUploadConfiguration::disk(), 'directory' => FileUploadConfiguration::directory(),
                    'bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'mime_type' => $mime];
                $this->publishReceipt($directory, $receipt);
            }
            $temporary = $this->temporary($receipt);
            if (! is_file($temporary) || ! hash_equals($receipt['sha256'], hash_file('sha256', $temporary))) {
                $source->refresh();
                if (($source->upload_ledger[$entry['entry_id']]['reference_hash'] ?? null) !== null) {
                    return $this->unresolved($actor, $source, $entry['entry_id']);
                }
                $this->validateFile($file, $metadata);
                $this->require(hash_equals($receipt['sha256'], hash_file('sha256', $file->getRealPath())), 'Continue requires the same image bytes.');
                $disk = FileUploadConfiguration::storage();
                $this->ensureDirectory($disk, $receipt['directory']);
                $sidecar = $receipt['directory'].'/'.$receipt['basename'].'.json';
                $disk->put($sidecar, json_encode(['name' => $metadata['name'], 'type' => $receipt['mime_type'], 'size' => $receipt['bytes'], 'hash' => $file->hashName()], JSON_THROW_ON_ERROR));
                $stored = $file->storeAs($receipt['directory'], $receipt['basename'], ['disk' => $receipt['disk']]);
                if (! is_string($stored) || ! is_file($temporary) || ! hash_equals($receipt['sha256'], hash_file('sha256', $temporary))) {
                    throw new RuntimeException('The owned temporary image was not copied completely.');
                }
            }
            $result = $this->sources->commitUpload($actor, $source->id, $entry['entry_id'], $receipt['reference']);
            $source->refresh();
            $progress = $this->summary($actor, $source, $entry['entry_id']);
            if ($progress['current_entry']['committed']) {
                $this->removeTemporary($receipt);
            }

            return [...$progress, 'outcome' => $result['outcome'], 'code' => $result['code'], 'message' => $result['message']];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed> */
    public function status(User $actor, string $launchToken, string $sourceId, ?string $entryId = null): array
    {
        [$actor, $scope] = $this->admit($actor, $launchToken, false);
        $source = $this->source($actor, $scope, $sourceId);
        $result = $this->summary($actor, $source, $entryId);
        if ($entryId === null) {
            return $result;
        }
        $entry = collect($source->source['intake']['entries'])->firstWhere('entry_id', $entryId);
        $this->require($entry !== null, 'Choose an entry from the intake.');
        $directory = $this->entryDirectory($source, $entryId, false);
        if (! is_dir($directory)) {
            return [...$result, 'needs_file' => true, 'recovery' => 'continue'];
        }
        $lock = $this->lock($directory, false);
        if ($lock === null && is_file($directory.'/transfer.lock')) {
            return $this->busy($actor, $source, $entryId);
        }
        try {
            $source->refresh();
            $result = $this->summary($actor, $source, $entryId);
            $receipt = $this->receipt($directory);
            $bound = $source->upload_ledger[$entryId]['reference_hash'] ?? null;
            if (($bound !== null && $receipt === null) || ($receipt !== null && ! $this->validReceipt($actor, $source, $entry, $receipt, $bound))) {
                return $this->unresolved($actor, $source, $entryId);
            }
            if ($result['current_entry']['committed']) {
                return [...$result, 'needs_file' => false, 'recovery' => 'committed'];
            }
            $temporary = $receipt === null ? null : $this->temporary($receipt);
            $complete = $temporary !== null && is_file($temporary) && hash_equals($receipt['sha256'], hash_file('sha256', $temporary));
            if ($bound !== null && ! $complete) {
                return $this->unresolved($actor, $source, $entryId);
            }

            return [...$result, 'needs_file' => ! $complete, 'recovery' => 'continue'];
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** @return array<string, mixed> */
    public function finish(User $actor, string $launchToken, string $sourceId, int $revision): array
    {
        $this->noTransaction();
        [$actor, $scope] = $this->admit($actor, $launchToken, true);
        $source = $this->source($actor, $scope, $sourceId);
        $this->sources->sealUploads($actor, $sourceId, $revision);

        return $this->summary($actor, $source->fresh());
    }

    /** @return array{User, array<string, mixed>} */
    private function admit(User $actor, string $token, bool $mutating): array
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        $actor = User::query()->findOrFail($actor->getKey());
        app(ReviewCatalogAssets::class)->authorize($actor);
        $scope = $this->reviews->openLaunch($actor, $token);
        app(CatalogAssetTargets::class)->eligibleQuery($actor, $scope['target_key'], $scope['owner_context']);
        if ($mutating) {
            $accepted = CatalogAssetBatch::query()->where('actor_id', $actor->id)->whereKey($scope['review_id'])->first();
            $this->require($accepted === null || ! $accepted->items()->whereNotNull('acceptance_id')->exists(), 'Accepted review choices are frozen. Start a remaining review.');
        }

        return [$actor, $scope];
    }

    /** @param array<string, mixed> $scope */
    private function source(User $actor, array $scope, string $id): CatalogAssetImportSource
    {
        $this->require(Str::isUlid($id), 'Choose a current intake source.');
        $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->findOrFail($id);
        $this->require($source->source_kind === 'upload' && $source->scan_version === 1 && is_array($source->source['intake'] ?? null)
            && $source->source['target_key'] === $scope['target_key'] && $this->reviews->digest($source->source['owner_context']) === $this->reviews->digest($scope['owner_context']), 'This source belongs to a different image context.');

        return $source;
    }

    /** @return array<string, mixed> */
    private function open(User $actor, string $token, string $purpose): array
    {
        try {
            if (strlen($token) > self::MaxManifestBytes * 2) {
                throw new RuntimeException;
            }
            $claim = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($claim) || ($claim['purpose'] ?? null) !== $purpose || ($claim['actor_id'] ?? null) !== $actor->id || ! is_int($claim['expires_at'] ?? null) || $claim['expires_at'] < now()->timestamp) {
                throw new RuntimeException;
            }

            return $claim;
        } catch (Throwable) {
            throw ValidationException::withMessages(['image.upload' => 'This upload capability is invalid or expired. Refresh status and explicitly continue.']);
        }
    }

    /** @param array{name: string, relative_path: string, bytes: int} $metadata */
    private function validateFile(?UploadedFile $file, array $metadata): void
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:3072']])->validate();
        $this->require($file->getClientOriginalName() === $metadata['name'] && $file->getSize() === $metadata['bytes'], 'The image does not match its reserved name and bytes.');
        $size = @getimagesize($file->getRealPath());
        $this->require($size !== false && $size[0] > 0 && $size[1] > 0 && $size[1] <= intdiv(config('catalog-assets.image.max_pixels'), $size[0]), 'The image exceeds its pixel limit or cannot be decoded.');
    }

    /** @return array<string, mixed> */
    private function summary(User $actor, CatalogAssetImportSource $source, ?string $entry = null): array
    {
        return [...$this->sources->status($actor, $source->id, $entry), 'declared_files' => $source->source['intake']['file_count'], 'declared_bytes' => $source->source['intake']['byte_count']];
    }

    /** @return array<string, mixed> */
    private function busy(User $actor, CatalogAssetImportSource $source, string $entry): array
    {
        return [...$this->summary($actor, $source, $entry), 'outcome' => 'busy', 'recovery' => 'busy', 'message' => 'This transfer is still in progress. Refresh status before continuing.'];
    }

    /** @return array<string, mixed> */
    private function unresolved(User $actor, CatalogAssetImportSource $source, string $entry): array
    {
        return [...$this->summary($actor, $source, $entry), 'outcome' => 'paused', 'recovery' => 'unresolved', 'code' => 'binding_unavailable',
            'message' => 'The saved transfer binding is unavailable. Preserve this source and start a fresh selection; no file will be guessed or replaced.'];
    }

    private function entryDirectory(CatalogAssetImportSource $source, string $entry, bool $create): string
    {
        $disk = Storage::disk('catalog-asset-imports');
        $this->require(Str::isUlid($entry), 'The intake entry is invalid.');
        $relative = 'intakes/'.$source->id.'/v1/'.$entry;
        if ($create) {
            return $this->ensureDirectory($disk, $relative);
        }
        $root = $this->localRoot($disk);
        $path = $root.'/'.$relative;
        $this->require(! is_link($path), 'The private intake path is invalid.');
        if (is_dir($path)) {
            $this->files->safePath($root, $relative, true);
        }

        return $path;
    }

    private function localRoot(FilesystemAdapter $disk): string
    {
        if (! $disk->getAdapter() instanceof LocalFilesystemAdapter) {
            throw new LogicException('Catalog intake requires local source and temporary disks.');
        }

        return $this->files->root($disk->path(''));
    }

    private function ensureDirectory(FilesystemAdapter $disk, string $relative): string
    {
        $root = $this->localRoot($disk);
        $path = $root;
        foreach (explode('/', $relative) as $part) {
            $this->require($part !== '' && $part !== '.' && $part !== '..', 'The private intake directory is invalid.');
            $path .= '/'.$part;
            clearstatcache(true, $path);
            $this->require(! is_link($path), 'The private intake path is invalid.');
            if (! is_dir($path) && ! @mkdir($path, 0700) && ! is_dir($path)) {
                throw new RuntimeException('Private intake storage is unavailable.');
            }
        }

        return $this->files->safePath($root, $relative, true);
    }

    /** @return resource|null */
    private function lock(string $directory, bool $create): mixed
    {
        $path = $directory.'/transfer.lock';
        $this->require(! is_link($path), 'The private intake lock is invalid.');
        if (! $create && ! is_file($path)) {
            return null;
        }
        $handle = @fopen($path, $create ? 'c+b' : 'rb');
        if ($handle === false) {
            throw new RuntimeException('Private intake storage is unavailable.');
        }
        if (! flock($handle, ($create ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /** @return array<string, mixed>|null */
    private function receipt(string $directory): ?array
    {
        $path = $directory.'/binding.json';
        if (! file_exists($path) && ! is_link($path)) {
            return null;
        }
        if (is_link($path) || ! is_file($path) || filesize($path) > 16384) {
            return [];
        }
        $body = file_get_contents($path);
        try {
            $result = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

            return is_array($result) ? $result : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $entry
     * @param  array<string, mixed>  $receipt
     */
    private function validReceipt(User $actor, CatalogAssetImportSource $source, array $entry, array $receipt, ?string $bound): bool
    {
        return ($receipt['schema'] ?? null) === 1 && ($receipt['actor_id'] ?? null) === $actor->id && ($receipt['source_id'] ?? null) === $source->id
            && ($receipt['entry_id'] ?? null) === $entry['entry_id'] && ($receipt['version'] ?? null) === 1
            && ($receipt['intake_id'] ?? null) === $source->source['intake']['intake_id'] && ($receipt['target'] ?? null) === $source->source['target_key']
            && ($receipt['context'] ?? null) === $this->reviews->digest($source->source['owner_context']) && ($receipt['metadata'] ?? null) === $this->reviews->digest($entry)
            && is_string($receipt['basename'] ?? null) && preg_match('/\Aintake-'.preg_quote($source->id, '/').'-'.preg_quote($entry['entry_id'], '/').'\.(jpg|png|webp)\z/', $receipt['basename']) === 1
            && is_string($receipt['reference'] ?? null) && TemporaryUploadedFile::extractPathFromSignedPath($receipt['reference']) === $receipt['basename']
            && ($bound === null || hash_equals($bound, hash('sha256', $receipt['reference'])))
            && ($receipt['disk'] ?? null) === FileUploadConfiguration::disk() && ($receipt['directory'] ?? null) === FileUploadConfiguration::directory()
            && in_array($receipt['mime_type'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)
            && ($receipt['bytes'] ?? null) === $entry['bytes'] && is_string($receipt['sha256'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $receipt['sha256']) === 1;
    }

    /** @param array<string, mixed> $receipt */
    private function publishReceipt(string $directory, array $receipt): void
    {
        $destination = $directory.'/binding.json';
        $this->require(! file_exists($destination) && ! is_link($destination), 'The intake receipt already exists.');
        $pending = $directory.'/binding-'.Str::ulid().'.pending';
        $handle = @fopen($pending, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('The intake receipt could not be written.');
        }
        try {
            chmod($pending, 0600);
            $body = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $offset = 0;
            while ($offset < strlen($body)) {
                $n = fwrite($handle, substr($body, $offset));
                if ($n === false || $n === 0) {
                    throw new RuntimeException('The intake receipt could not be written.');
                }
                $offset += $n;
            }
            if (! fflush($handle)) {
                throw new RuntimeException('The intake receipt could not be flushed.');
            }
        } finally {
            fclose($handle);
        }
        if (! rename($pending, $destination)) {
            throw new RuntimeException('The intake receipt could not be published.');
        }
    }

    /** @param array<string, mixed> $receipt */
    private function temporary(array $receipt): string
    {
        $disk = FileUploadConfiguration::storage();
        $root = $this->localRoot($disk);
        $directory = $root.'/'.$receipt['directory'];
        if (file_exists($directory) || is_link($directory)) {
            $this->files->safePath($root, $receipt['directory'], true);
        }
        $relative = $receipt['directory'].'/'.$receipt['basename'];
        $path = $root.'/'.$relative;
        $this->require(! is_link($path) && ! is_link($path.'.json'), 'The owned temporary image path is invalid.');
        if (is_file($path)) {
            $this->files->safePath($root, $relative);
        }

        return $path;
    }

    /** @param array<string, mixed> $receipt */
    private function removeTemporary(array $receipt): void
    {
        $path = $this->temporary($receipt);
        foreach ([$path, $path.'.json'] as $owned) {
            if (is_file($owned) && ! unlink($owned)) {
                throw new RuntimeException('The committed transfer temporary file could not be removed.');
            }
        }
    }

    private function noTransaction(): void
    {
        if ((new CatalogAssetImportSource)->getConnection()->transactionLevel() !== 0) {
            throw new LogicException('Catalog intake writes require independently committed requests.');
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['image.upload' => $message]);
        }
    }
}
