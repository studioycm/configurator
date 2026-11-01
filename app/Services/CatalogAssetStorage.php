<?php

namespace App\Services;

use App\Models\CatalogAsset;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * @phpstan-type PreparationResult array{stage: string, outcome: string, code: string|null, message: string, correlation_id: string, asset: CatalogAsset|null, warnings: list<string>, next_action: string}
 */
class CatalogAssetStorage
{
    public function __construct(private CatalogAssetSources $sources, private CatalogAssetFiles $files) {}

    /**
     * This internal boundary accepts reviewed source identity, never a client path or asset ID.
     *
     * @param  array{source_id: string, scan_version: int, entry_id: string, sha256: string}  $sourceChoice
     * @return PreparationResult
     */
    public function prepare(User $actor, array $sourceChoice, string $targetKey, string $importKey): array
    {
        $this->assertIndependentConnection();
        Validator::make(['import_key' => $importKey], ['import_key' => ['required', 'regex:/^[a-f0-9]{64}$/D']])->validate();
        $started = hrtime(true);
        $correlation = (string) Str::ulid();
        $asset = null;
        $token = null;
        try {
            $selection = $this->selection($actor, $sourceChoice, $targetKey);
            $provenance = $this->provenance($selection);
            $asset = $this->identity($actor, $selection, $provenance, $importKey);
            $claim = $this->transaction(function () use ($actor, $asset, $provenance): array {
                $current = CatalogAsset::query()->lockForUpdate()->findOrFail($asset->id);
                $this->assertProvenance($current, $actor, $provenance);
                if ($current->preparation_status === 'ready') {
                    return ['asset' => $current, 'outcome' => 'ready', 'token' => null];
                }
                if ($current->preparation_token !== null && $current->preparation_expires_at?->isFuture()) {
                    return ['asset' => $current, 'outcome' => 'busy', 'token' => null];
                }
                $token = (string) Str::ulid();
                $current->forceFill(['preparation_status' => 'preparing', 'preparation_token' => $token,
                    'preparation_expires_at' => now()->addSeconds(config('catalog-assets.requests.claim_seconds')),
                    'error_code' => null, 'error_detail' => null])->save();

                return ['asset' => $current, 'outcome' => 'claimed', 'token' => $token];
            });
            $asset = $claim['asset'];
            if ($claim['outcome'] === 'busy') {
                return $this->result('busy', $correlation);
            }
            if ($claim['outcome'] === 'ready') {
                try {
                    $this->verifyWinner($asset, $selection);
                } catch (DomainException) {
                    return $this->result('paused', $correlation, 'ready_asset_invalid');
                }

                return $this->result('ready', $correlation, null, $asset);
            }
            $token = $claim['token'];
            $work = $this->files->workingCopy($selection, $selection['source_id'].'/v'.$selection['scan_version'].'/preparation/'.$asset->id.'/'.$token.'/source', config('catalog-assets.image'));
            if (! $this->renew($actor, $sourceChoice, $targetKey, $asset, $provenance, $token)) {
                return $this->result('superseded', $correlation);
            }
            $fileAdderError = null;
            try {
                $asset->addMedia($work['path'])->preservingOriginal()->usingName(pathinfo($selection['entry']['name'], PATHINFO_FILENAME))
                    ->usingFileName($this->safeFilename($selection['entry']['name'], $selection['entry']['mime_type']))
                    ->withCustomProperties($this->attemptProperties($asset, $selection, $token))
                    ->toMediaCollection('preparation-attempts', 'catalog-assets');
            } catch (Throwable $exception) {
                $fileAdderError = $exception;
            }
            $attempts = $asset->media()->where('collection_name', 'preparation-attempts')
                ->where('custom_properties->preparation_token', $token)->limit(2)->get();
            if ($attempts->count() !== 1) {
                if ($attempts->isEmpty() && $fileAdderError !== null) {
                    throw $fileAdderError;
                }
                throw new DomainException($attempts->isEmpty() ? 'attempt_missing' : 'attempt_ambiguous');
            }
            $media = $attempts->first();
            try {
                $metadata = $this->verifyMedia($asset, $media, $selection, $token, 'preparation-attempts');
            } catch (DomainException $verificationError) {
                if ($fileAdderError !== null && ! $fileAdderError instanceof DomainException) {
                    throw $fileAdderError;
                }
                throw $verificationError;
            }
            $warnings = $fileAdderError !== null ? ['conversion_incomplete'] : [];
            $conversions = $this->conversionOutcomes($media);
            if (in_array(false, $conversions, true)) {
                $warnings = ['conversion_incomplete'];
            }
            $this->selection($actor, $sourceChoice, $targetKey);
            $published = $this->transaction(function () use ($actor, $asset, $provenance, $selection, $token, $media, $metadata, $conversions, $warnings): ?CatalogAsset {
                $current = CatalogAsset::query()->lockForUpdate()->findOrFail($asset->id);
                $this->assertProvenance($current, $actor, $provenance);
                if (! $this->owns($current, $token)) {
                    return null;
                }
                $ownedMedia = $current->media()->lockForUpdate()->findOrFail($media->id);
                $this->assertMedia($current, $ownedMedia, $selection, $token, 'preparation-attempts');
                $ownedMedia->collection_name = 'original';
                $ownedMedia->save();
                $current->forceFill(['original_media_id' => $ownedMedia->id, 'preparation_status' => 'ready', 'preparation_token' => null,
                    'preparation_expires_at' => null, 'verified_metadata' => [...$metadata, 'source_id' => $selection['source_id'],
                        'scan_version' => $selection['scan_version'], 'entry_id' => $selection['entry']['entry_id'],
                        'conversions' => $conversions, 'warnings' => $warnings], 'prepared_at' => now(), 'error_code' => null, 'error_detail' => null])->save();

                return $current;
            });
            if ($published === null) {
                return $this->result('superseded', $correlation);
            }
            $result = $this->result('ready', $correlation, null, $published, $warnings);
            Log::info('Catalog asset preparation completed.', $this->logContext($actor, $sourceChoice, $asset, $token, $correlation, $started)
                + ['media_id' => $media->id, 'warnings' => $warnings]);

            return $result;
        } catch (AuthorizationException|ModelNotFoundException|ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (DomainException $exception) {
            $code = $this->safeCode($exception->getMessage());
            if ($asset !== null && $token !== null) {
                try {
                    $failed = $this->fail($actor, $asset, $provenance, $token, $code, $correlation);
                } catch (QueryException $journalError) {
                    return $this->paused($journalError, $actor, $sourceChoice, $asset, $token, $correlation, $started);
                }
                if (! $failed) {
                    return $this->result('superseded', $correlation);
                }
            }
            Log::info('Catalog asset preparation stopped.', $this->logContext($actor, $sourceChoice, $asset, $token, $correlation, $started) + ['code' => $code]);

            return $this->result('failed', $correlation, $code);
        } catch (Throwable $exception) {
            return $this->paused($exception, $actor, $sourceChoice, $asset, $token, $correlation, $started);
        }
    }

    /** @param array<string, mixed> $sourceChoice
     * @return PreparationResult
     */
    private function paused(Throwable $exception, User $actor, array $sourceChoice, ?CatalogAsset $asset, ?string $token, string $correlation, int $started): array
    {
        $context = $this->logContext($actor, $sourceChoice, $asset, $token, $correlation, $started) + ['exception' => $exception];
        if ($asset !== null && $token !== null) {
            try {
                $current = CatalogAsset::query()->find($asset->id);
                if ($current !== null && ! $this->owns($current, $token)) {
                    return $this->result('superseded', $correlation);
                }
            } catch (Throwable $reconciliationError) {
                $context['reconciliation_exception'] = $reconciliationError;
            }
        }
        Log::error('Catalog asset preparation paused.', $context);

        return $this->result('paused', $correlation, 'systemic_error');
    }

    private function assertIndependentConnection(): void
    {
        $asset = new CatalogAsset;
        $mediaClass = config('media-library.media_model');
        $media = new $mediaClass;
        if ($media->getConnectionName() === null) {
            $media->setConnection($asset->getConnectionName());
        }
        if ($asset->getConnection() !== $media->getConnection() || $asset->getConnection()->transactionLevel() !== 0 || $media->getConnection()->transactionLevel() !== 0) {
            throw new LogicException('Catalog asset preparation requires one independently committed Asset/Media connection.');
        }
        if (config('filesystems.disks.catalog-assets.driver') !== 'local') {
            throw new LogicException('Catalog managed image storage must be local.');
        }
    }

    /** @param array<string, mixed> $choice
     * @return array<string, mixed>
     */
    private function selection(User $actor, array $choice, string $targetKey): array
    {
        abort_unless($actor->exists && $actor->getKey() !== null, 403);
        $currentActor = User::query()->findOrFail($actor->getKey());

        return $this->sources->selectedEntry($currentActor, $choice, $targetKey);
    }

    /** @param array<string, mixed> $selection
     * @return array<string, mixed>
     */
    private function provenance(array $selection): array
    {
        return array_intersect_key($selection, array_flip(['source_id', 'source_kind', 'scan_version', 'target_key', 'owner_context', 'entry']));
    }

    /** @param array<string, mixed> $selection
     * @param  array<string, mixed>  $provenance
     */
    private function identity(User $actor, array $selection, array $provenance, string $importKey): CatalogAsset
    {
        $asset = CatalogAsset::query()->where('import_key', $importKey)->first();
        if ($asset !== null) {
            $this->assertProvenance($asset, $actor, $provenance);

            return $asset;
        }
        try {
            $asset = new CatalogAsset;
            $asset->forceFill(['created_by' => $actor->getKey(), 'source_kind' => $selection['source_kind'], 'source' => $provenance,
                'sha256' => $selection['entry']['sha256'], 'import_key' => $importKey])->save();
        } catch (UniqueConstraintViolationException) {
            $asset = CatalogAsset::query()->where('import_key', $importKey)->firstOrFail();
        }
        $this->assertProvenance($asset, $actor, $provenance);

        return $asset;
    }

    /** @param array<string, mixed> $provenance */
    private function assertProvenance(CatalogAsset $asset, User $actor, array $provenance): void
    {
        if ($asset->created_by !== (int) $actor->getKey() || $this->canonicalJson($asset->source) !== $this->canonicalJson($provenance) || $asset->sha256 !== $provenance['entry']['sha256']
            || $asset->source_kind !== $provenance['source_kind']) {
            throw ValidationException::withMessages(['image.import' => 'This preparation identity belongs to different source evidence.']);
        }
    }

    /** @param array<string, mixed> $choice
     * @param  array<string, mixed>  $provenance
     */
    private function renew(User $actor, array $choice, string $targetKey, CatalogAsset $asset, array $provenance, string $token): bool
    {
        $this->selection($actor, $choice, $targetKey);

        return $this->transaction(function () use ($actor, $asset, $provenance, $token): bool {
            $current = CatalogAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $this->assertProvenance($current, $actor, $provenance);
            if (! $this->owns($current, $token)) {
                return false;
            }
            $current->forceFill(['preparation_expires_at' => now()->addSeconds(config('catalog-assets.requests.claim_seconds'))])->save();

            return true;
        });
    }

    private function owns(CatalogAsset $asset, string $token): bool
    {
        return $asset->preparation_status === 'preparing' && $asset->preparation_token === $token;
    }

    /** @param array<string, mixed> $selection */
    private function verifyWinner(CatalogAsset $asset, array $selection): void
    {
        if ($asset->original_media_id === null || ! is_array($asset->verified_metadata) || $asset->prepared_at === null) {
            throw new DomainException('ready_asset_invalid');
        }
        $media = $asset->media()->whereKey($asset->original_media_id)->first();
        if ($media === null) {
            throw new DomainException('ready_asset_invalid');
        }
        $token = $media->getCustomProperty('preparation_token');
        if (! is_string($token) || ! Str::isUlid($token)) {
            throw new DomainException('ready_asset_invalid');
        }
        $metadata = $this->verifyMedia($asset, $media, $selection, $token, 'original');
        foreach ($metadata as $key => $value) {
            if (($asset->verified_metadata[$key] ?? null) !== $value) {
                throw new DomainException('ready_asset_invalid');
            }
        }
    }

    /** @param array<string, mixed> $selection
     * @return array<string, mixed>
     */
    private function verifyMedia(CatalogAsset $asset, Media $media, array $selection, string $token, string $collection): array
    {
        $this->assertMedia($asset, $media, $selection, $token, $collection);

        return $this->files->verify(Storage::disk('catalog-assets')->path(''), $media->getPathRelativeToRoot(), $selection['entry'], config('catalog-assets.image'), $media->file_name);
    }

    /** @param array<string, mixed> $selection */
    private function assertMedia(CatalogAsset $asset, Media $media, array $selection, string $token, string $collection): void
    {
        if ($media->model_type !== $asset->getMorphClass() || (int) $media->model_id !== $asset->id || $media->collection_name !== $collection
            || $media->disk !== 'catalog-assets' || $media->conversions_disk !== 'catalog-assets'
            || $this->canonicalJson($media->custom_properties) !== $this->canonicalJson($this->attemptProperties($asset, $selection, $token))
            || $media->mime_type !== $selection['entry']['mime_type'] || (int) $media->size !== $selection['entry']['bytes']) {
            throw new DomainException('attempt_invalid');
        }
    }

    /** @param array<string, mixed> $selection
     * @return array<string, int|string>
     */
    private function attemptProperties(CatalogAsset $asset, array $selection, string $token): array
    {
        return ['asset_id' => $asset->id, 'preparation_token' => $token, 'source_id' => $selection['source_id'],
            'scan_version' => $selection['scan_version'], 'entry_id' => $selection['entry']['entry_id'], 'sha256' => $selection['entry']['sha256']];
    }

    /** @return array<string, bool> */
    private function conversionOutcomes(Media $media): array
    {
        $outcomes = [];
        $media->refresh();
        foreach (config('catalog-assets.image.conversion_sizes') as $size) {
            $name = 'image-'.$size;
            $outcomes[$name] = $media->hasGeneratedConversion($name) && Storage::disk('catalog-assets')->exists($media->getPathRelativeToRoot($name));
        }

        return $outcomes;
    }

    private function safeFilename(string $name, string $mime): string
    {
        $stem = Str::ascii(pathinfo($name, PATHINFO_FILENAME));
        $stem = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $stem), '.-_');
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'
        };

        return substr($stem === '' ? 'image' : $stem, 0, 120).'.'.$extension;
    }

    /** @param array<string, mixed> $provenance */
    private function fail(User $actor, CatalogAsset $asset, array $provenance, string $token, string $code, string $correlation): bool
    {
        return $this->transaction(function () use ($actor, $asset, $provenance, $token, $code, $correlation): bool {
            $current = CatalogAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $this->assertProvenance($current, $actor, $provenance);
            if (! $this->owns($current, $token)) {
                return false;
            }
            $current->forceFill(['preparation_status' => 'failed', 'preparation_token' => null, 'preparation_expires_at' => null,
                'error_code' => $code, 'error_detail' => 'Image preparation stopped. Reference: '.$correlation.'. Review the source before trying again.'])->save();

            return true;
        });
    }

    private function transaction(Closure $operation): mixed
    {
        return (new CatalogAsset)->getConnection()->transaction($operation, 3);
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

        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function safeCode(string $code): string
    {
        return in_array($code, ['source_stale', 'path_invalid', 'image_too_large', 'image_invalid', 'image_decode_failed', 'attempt_missing', 'attempt_ambiguous', 'attempt_invalid'], true)
            ? $code : 'image_invalid';
    }

    /** @param list<string> $warnings
     * @return PreparationResult
     */
    private function result(string $outcome, string $correlation, ?string $code = null, ?CatalogAsset $asset = null, array $warnings = []): array
    {
        return ['stage' => 'preparation', 'outcome' => $outcome, 'code' => $code,
            'message' => match ($outcome) {
                'ready' => 'The managed image is ready for a separate save.',
                'busy' => 'Another preparation is running. Reconcile before continuing.',
                'superseded' => 'A newer preparation owns this image. Reconcile before continuing.',
                'failed' => 'The image could not be prepared. Review the source before trying again.',
                default => 'Preparation paused. Request operator review before continuing.',
            }, 'correlation_id' => $correlation, 'asset' => $asset, 'warnings' => $warnings,
            'next_action' => match ($outcome) {
                'ready' => 'none', 'busy', 'superseded' => 'continue', 'failed' => 'review_again', default => 'operator_recovery'
            }];
    }

    /** @param array<string, mixed> $choice
     * @return array<string, mixed>
     */
    private function logContext(User $actor, array $choice, ?CatalogAsset $asset, ?string $token, string $correlation, int $started): array
    {
        return ['stage' => 'preparation', 'actor_id' => $actor->getKey(), 'source_id' => $choice['source_id'] ?? null,
            'scan_version' => $choice['scan_version'] ?? null, 'entry_id' => $choice['entry_id'] ?? null, 'asset_id' => $asset?->id,
            'preparation_token' => $token, 'correlation_id' => $correlation, 'elapsed_ms' => round((hrtime(true) - $started) / 1000000, 3)];
    }
}
