<?php

namespace App\Services;

use App\DTO\CatalogAssetChange;
use App\Models\CatalogAsset;
use App\Models\CatalogAssetImportSource;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

/**
 * @phpstan-type ImageContext array{target_key: string, record_id: int|null, creation_session: string|null, owner_context: array{list_key?: string, parent_id?: int}, expected_fingerprint: string|null, expected_group_fingerprint: string|null}
 * @phpstan-type ImageIntent array{operation: string, source_token: string|null, draft_intent_id: string}
 */
class CatalogMainImageEdits
{
    private const MAX_TOKEN_BYTES = 32768;

    public function __construct(private CatalogAssetTargets $targets, private CatalogAssetSources $sources, private CatalogAssetStorage $storage, private CatalogAssetAssignments $assignments) {}

    /** @param array<string, mixed> $rawDraft */
    public function draftHash(array $rawDraft): string
    {
        unset($rawDraft['image_upload']);

        return hash('sha256', $this->canonicalJson($rawDraft));
    }

    /**
     * @param  ImageContext  $context
     * @param  array{source_id: string, scan_version: int, entry_id: string, sha256: string}  $sourceChoice
     * @return ImageIntent
     */
    public function stageSource(User $actor, array $context, array $sourceChoice): array
    {
        $context = $this->authorizeContext($actor, $context);
        $selection = $this->sources->selectedEntry($actor, $sourceChoice, $context['target_key']);
        $this->require($this->canonicalJson($selection['owner_context']) === $this->canonicalJson($context['owner_context']), 'The selected source belongs to a different owner context.');
        $source = $this->sourceJournal($actor, $context, $sourceChoice);
        $intentId = (string) Str::ulid();
        $token = $this->encrypt([
            'schema' => 1, 'kind' => 'catalog-image-source', 'actor_id' => (int) $actor->id, 'context' => $context,
            'draft_intent_id' => $intentId, 'source' => [...$sourceChoice, 'manifest_sha256' => $source->manifest_sha256], 'expires_at' => now()->addDay()->timestamp,
        ]);

        return ['operation' => 'set', 'source_token' => $token, 'draft_intent_id' => $intentId];
    }

    /**
     * @param  ImageContext  $context
     * @param  ImageIntent  $intent
     * @return array{stage: string, outcome: string, code: string|null, message: string, correlation_id: string, receipt: string|null, warnings: list<string>, next_action: string}
     */
    public function prepare(User $actor, array $context, array $intent, string $draftHash): array
    {
        $this->assertNoTransaction();
        $context = $this->authorizeContext($actor, $context);
        $intent = $this->validateIntent($intent);
        $this->validateHash($draftHash);
        $source = $intent['operation'] === 'set' ? $this->sourceEvidence($actor, $context, $intent) : null;
        $asset = null;
        $progress = ['stage' => 'preparation', 'outcome' => 'ready', 'code' => 'no_preparation_needed', 'message' => 'The image change is ready for a separate save.',
            'correlation_id' => (string) Str::ulid(), 'warnings' => [], 'next_action' => 'none'];
        if ($source !== null) {
            $choice = array_intersect_key($source, array_flip(['source_id', 'scan_version', 'entry_id', 'sha256']));
            $importKey = hash('sha256', $this->canonicalJson(['actor_id' => (int) $actor->id, 'context' => $context, 'draft_intent_id' => $intent['draft_intent_id'], 'source' => $choice]));
            $result = $this->storage->prepare($actor, $choice, $context['target_key'], $importKey);
            $progress = array_intersect_key($result, array_flip(['stage', 'outcome', 'code', 'message', 'correlation_id', 'warnings', 'next_action']));
            if ($result['outcome'] !== 'ready' || ! $result['asset'] instanceof CatalogAsset) {
                return [...$progress, 'receipt' => null];
            }
            $asset = $this->assignments->validatedAsset($context['target_key'], $result['asset']->id);
            $this->assertAssetEvidence($asset, $actor, $context, $source);
            $this->sourceJournal($actor, $context, $source);
        }
        $receipt = $this->encrypt([
            'schema' => 1, 'kind' => 'catalog-image-save', 'actor_id' => (int) $actor->id, 'context' => $context,
            'operation' => $intent['operation'], 'draft_intent_id' => $intent['draft_intent_id'],
            'source_token_hash' => $intent['source_token'] === null ? null : hash('sha256', $intent['source_token']), 'source' => $source,
            'asset_id' => $asset?->id, 'draft_hash' => $draftHash, 'expires_at' => now()->addDay()->timestamp,
        ]);

        return [...$progress, 'receipt' => $receipt];
    }

    /**
     * Resolves only SQL metadata; it is safe inside the native final Save transaction.
     *
     * @param  ImageContext  $context
     * @param  ImageIntent  $intent
     */
    public function resolve(User $actor, array $context, array $intent, string $draftHash, string $receipt): CatalogAssetChange
    {
        $context = $this->authorizeContext($actor, $context);
        $intent = $this->validateIntent($intent);
        $this->validateHash($draftHash);

        return $this->receiptChange($actor, $context, $intent, $draftHash, $receipt);
    }

    /**
     * Confirms current desired state after an unknown response; never replays a write.
     *
     * @param  ImageContext  $context
     * @param  ImageIntent  $intent
     * @return array{outcome: string, message: string, expected_fingerprint?: string}
     */
    public function reconcile(User $actor, array $context, array $intent, string $draftHash, string $receipt): array
    {
        $context = $this->authorizeContext($actor, $context, checkFingerprint: false);
        $this->require($context['record_id'] !== null, 'An unknown Group creation requires review before another create attempt.');
        $intent = $this->validateIntent($intent);
        $this->validateHash($draftHash);
        $change = $this->receiptChange($actor, $context, $intent, $draftHash, $receipt);
        $record = $this->targets->eligibleQuery($actor, $context['target_key'], $context['owner_context'])->find($context['record_id']);
        $this->require($record !== null, 'This image owner is no longer available in this context.');
        $fingerprint = CatalogAssetAssignments::fingerprint($record);
        $unchanged = hash_equals($context['expected_fingerprint'], $fingerprint);
        $desired = $change->operation === 'keep' ? $unchanged : ($record->main_image_id === null ? null : (int) $record->main_image_id) === $change->assetId;
        if ($desired) {
            return ['outcome' => 'goal_matches', 'expected_fingerprint' => $fingerprint, 'message' => 'Image matches your choice.'];
        }
        if ($unchanged) {
            return ['outcome' => 'baseline_matches', 'message' => 'The saved owner still matches the starting state. Review before explicitly retrying.'];
        }

        return ['outcome' => 'conflict', 'message' => 'This owner changed and has a different image state. Review it before saving.'];
    }

    /** @param ImageContext $context
     * @param  ImageIntent  $intent
     */
    private function receiptChange(User $actor, array $context, array $intent, string $draftHash, string $receipt): CatalogAssetChange
    {
        $payload = $this->decrypt($receipt, 'catalog-image-save');
        $this->require(($payload['actor_id'] ?? null) === (int) $actor->id && ($payload['context'] ?? null) === $context
            && ($payload['operation'] ?? null) === $intent['operation'] && ($payload['draft_intent_id'] ?? null) === $intent['draft_intent_id']
            && ($payload['draft_hash'] ?? null) === $draftHash
            && ($payload['source_token_hash'] ?? null) === ($intent['source_token'] === null ? null : hash('sha256', $intent['source_token'])), 'The image receipt no longer matches this draft.');
        if ($intent['operation'] !== 'set') {
            $this->require(array_key_exists('source', $payload) && $payload['source'] === null && array_key_exists('asset_id', $payload) && $payload['asset_id'] === null, 'The image receipt contains unexpected source evidence.');

            return new CatalogAssetChange($intent['operation']);
        }
        $source = $this->sourceEvidence($actor, $context, $intent);
        $this->require(($payload['source'] ?? null) === $source && is_int($payload['asset_id'] ?? null) && $payload['asset_id'] > 0, 'The image receipt contains different source evidence.');
        $asset = $this->assignments->validatedAsset($context['target_key'], $payload['asset_id']);
        $this->assertAssetEvidence($asset, $actor, $context, $source);

        return new CatalogAssetChange('set', $asset->id);
    }

    /** @param ImageContext $context
     * @return ImageContext
     */
    private function authorizeContext(User $actor, array $context, bool $checkFingerprint = true): array
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        $currentActor = User::query()->find($actor->getKey());
        abort_unless($currentActor !== null, 403);
        Gate::forUser($currentActor)->authorize('manage-catalog');
        Validator::make(['context' => $context], [
            'context' => ['required', 'array:target_key,record_id,creation_session,owner_context,expected_fingerprint,expected_group_fingerprint'],
            'context.target_key' => ['required', 'string'], 'context.record_id' => ['present', 'nullable', 'integer', 'min:1'],
            'context.creation_session' => ['present', 'nullable', 'ulid'], 'context.owner_context' => ['present', 'array'],
            'context.expected_fingerprint' => ['present', 'nullable', 'regex:/^[a-f0-9]{64}$/D'],
            'context.expected_group_fingerprint' => ['present', 'nullable', 'regex:/^[a-f0-9]{64}$/D'],
        ])->validate();
        $this->targets->profile($context['target_key']);
        $query = $this->targets->eligibleQuery($currentActor, $context['target_key'], $context['owner_context']);
        if ($context['record_id'] === null) {
            $this->require($context['target_key'] === 'group.main_image' && is_string($context['creation_session']) && Str::isUlid($context['creation_session'])
                && $context['expected_fingerprint'] === null && $context['expected_group_fingerprint'] === null && $context['owner_context'] === [], 'This creation image requires its own Group creation session.');
        } else {
            $this->require(is_int($context['record_id']) && $context['creation_session'] === null && is_string($context['expected_fingerprint']), 'The image owner context is invalid.');
            $record = $query->find($context['record_id']);
            $this->require($record !== null, 'This image owner is no longer available in this context.');
            if ($checkFingerprint) {
                $this->require(hash_equals(CatalogAssetAssignments::fingerprint($record), $context['expected_fingerprint']), 'This record changed after the image editor was opened. Review it before saving.');
            }
            if ($context['expected_group_fingerprint'] !== null) {
                $this->require($context['target_key'] === 'product.main_image' && hash_equals(ProductEditorData::groupFingerprint($record), $context['expected_group_fingerprint']), 'The Product editor fields changed after the editor was opened.');
            }
        }

        return ['target_key' => $context['target_key'], 'record_id' => $context['record_id'], 'creation_session' => $context['creation_session'],
            'owner_context' => $this->canonical($context['owner_context']), 'expected_fingerprint' => $context['expected_fingerprint'], 'expected_group_fingerprint' => $context['expected_group_fingerprint']];
    }

    /** @param ImageIntent $intent
     * @return ImageIntent
     */
    private function validateIntent(array $intent): array
    {
        Validator::make(['image' => $intent], [
            'image' => ['required', 'array:operation,source_token,draft_intent_id'], 'image.operation' => ['required', 'in:keep,set,remove'],
            'image.source_token' => ['present', 'nullable', 'string', 'max:'.self::MAX_TOKEN_BYTES], 'image.draft_intent_id' => ['required', 'ulid'],
        ])->validate();
        $this->require($intent['operation'] === 'set' ? is_string($intent['source_token']) && $intent['source_token'] !== '' : $intent['source_token'] === null, 'Only a selected image may carry a source token.');

        return ['operation' => $intent['operation'], 'source_token' => $intent['source_token'], 'draft_intent_id' => $intent['draft_intent_id']];
    }

    /** @param ImageContext $context
     * @param  ImageIntent  $intent
     * @return array<string, mixed>
     */
    private function sourceEvidence(User $actor, array $context, array $intent): array
    {
        $token = $this->decrypt($intent['source_token'], 'catalog-image-source');
        $this->require(($token['actor_id'] ?? null) === (int) $actor->id && ($token['context'] ?? null) === $context
            && ($token['draft_intent_id'] ?? null) === $intent['draft_intent_id'] && is_array($token['source'] ?? null), 'The selected source token belongs to a different image draft.');
        $this->sourceJournal($actor, $context, $token['source']);

        return $token['source'];
    }

    /** @param ImageContext $context
     * @param  array<string, mixed>  $evidence
     */
    private function sourceJournal(User $actor, array $context, array $evidence): CatalogAssetImportSource
    {
        Validator::make(['source' => $evidence], [
            'source.source_id' => ['required', 'ulid'], 'source.scan_version' => ['required', 'integer', 'min:1'],
            'source.entry_id' => ['required', 'ulid'], 'source.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
        ])->validate();
        $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->find($evidence['source_id']);
        $this->require($source !== null && $source->status === 'ready' && is_int($evidence['scan_version']) && $source->scan_version === $evidence['scan_version']
            && is_string($source->manifest_sha256) && ($source->source['target_key'] ?? null) === $context['target_key']
            && $this->canonicalJson($source->source['owner_context'] ?? null) === $this->canonicalJson($context['owner_context'])
            && (! array_key_exists('manifest_sha256', $evidence) || $source->manifest_sha256 === $evidence['manifest_sha256']), 'The selected source is no longer ready in this owner context.');

        return $source;
    }

    /** @param ImageContext $context
     * @param  array<string, mixed>  $source
     */
    private function assertAssetEvidence(CatalogAsset $asset, User $actor, array $context, array $source): void
    {
        $provenance = $asset->source;
        $this->require($asset->created_by === (int) $actor->id && ($provenance['target_key'] ?? null) === $context['target_key']
            && ($provenance['source_id'] ?? null) === $source['source_id'] && ($provenance['scan_version'] ?? null) === $source['scan_version']
            && ($provenance['entry']['entry_id'] ?? null) === $source['entry_id'] && $asset->sha256 === $source['sha256']
            && $this->canonicalJson($provenance['owner_context'] ?? null) === $this->canonicalJson($context['owner_context']), 'The prepared image belongs to different source evidence.');
    }

    /** @param array<string, mixed> $payload */
    private function encrypt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->require(strlen($json) <= self::MAX_TOKEN_BYTES, 'The image receipt is too large.');
        $encrypted = Crypt::encryptString($json);
        $this->require(strlen($encrypted) <= self::MAX_TOKEN_BYTES, 'The image receipt is too large.');

        return $encrypted;
    }

    /** @return array<string, mixed> */
    private function decrypt(string $encrypted, string $kind): array
    {
        $this->require($encrypted !== '' && strlen($encrypted) <= self::MAX_TOKEN_BYTES, 'The image receipt is invalid.');
        try {
            $payload = json_decode(Crypt::decryptString($encrypted), true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['image.receipt' => 'The image receipt is invalid.']);
        }
        $this->require(is_array($payload) && ($payload['schema'] ?? null) === 1 && ($payload['kind'] ?? null) === $kind
            && is_int($payload['expires_at'] ?? null) && $payload['expires_at'] > now()->timestamp, 'The image receipt expired or is invalid.');

        return $payload;
    }

    private function validateHash(string $hash): void
    {
        $this->require(preg_match('/^[a-f0-9]{64}$/D', $hash) === 1, 'The image draft fingerprint is invalid.');
    }

    private function assertNoTransaction(): void
    {
        $asset = new CatalogAsset;
        $mediaClass = config('media-library.media_model');
        $media = new $mediaClass;
        if ($media->getConnectionName() === null) {
            $media->setConnection($asset->getConnectionName());
        }
        if ($asset->getConnection() !== $media->getConnection() || $asset->getConnection()->transactionLevel() !== 0 || $media->getConnection()->transactionLevel() !== 0) {
            throw new LogicException('Image preparation requires an independently committed Asset/Media connection.');
        }
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->canonical($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['image.receipt' => $message]);
        }
    }
}
