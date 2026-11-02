<?php

namespace App\Actions;

use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetImportSource;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetMatcher;
use App\Services\CatalogAssetRecipe;
use App\Services\CatalogAssetRecordSequence;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetTargets;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class ReviewCatalogAssets
{
    public function __construct(private CatalogAssetTargets $targets, private CatalogAssetSources $sources, private CatalogAssetRecipe $recipes, private CatalogAssetMatcher $matcher, private CatalogAssetReviewFiles $files, private CatalogAssetRecordSequence $sequence) {}

    public function authorize(User $actor): void
    {
        abort_unless(config('catalog-assets.enabled'), 404);
        Gate::forUser($actor)->authorize('manage-catalog');
    }

    public function owned(User $actor, string $id, bool $lock = false): CatalogAssetBatch
    {
        $actor = User::query()->find($actor->getKey());
        abort_unless($actor !== null, 403);
        $this->authorize($actor);
        $batch = CatalogAssetBatch::query()->where('actor_id', $actor->id)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        $this->targets->eligibleQuery($actor, $batch->target_type.'.main_image', $batch->owner_context);

        return $batch;
    }

    public function freeze(User $actor, string $targetKey, array $context, array $ids, array $sort): array
    {
        $this->authorize($actor);
        $this->noTransaction();
        Validator::make(['ids' => $ids], ['ids' => ['required', 'array', 'min:1', 'max:5000'], 'ids.*' => ['integer', 'min:1', 'distinct']])->validate();
        $ids = array_map('intval', $ids);
        $query = $this->targets->eligibleQuery($actor, $targetKey, $context);
        $model = $query->getModel();
        $existing = $model->newQuery()->whereKey($ids)->count();
        $eligible = (clone $query)->whereKey($ids);
        if ($eligible->count() !== $existing || count(array_unique($ids)) !== count($ids)) {
            $this->invalid('The checked records no longer belong to this image scope.');
        }
        foreach ($sort as $term) {
            if (! str_starts_with($term['column'] ?? '', $model->getTable().'.')) {
                $this->invalid('Choose the current record sort.');
            }
        }
        if ($sort === []) {
            $this->invalid('Choose the current record sort.');
        }
        $ordered = $this->sequence->ordered($eligible, $sort)->pluck($model->getTable().'.id')->map(fn ($id) => (int) $id)->all();
        $missing = array_values(array_diff($ids, $ordered));
        sort($missing);
        $reviewId = (string) Str::ulid();
        $scope = ['actor_id' => $actor->id, 'review_id' => $reviewId, 'target_key' => $targetKey, 'owner_context' => $context, 'record_ids' => [...$ordered, ...$missing], 'sort' => $sort];
        $artifact = $this->files->write($reviewId, 1, 'scope', $scope);

        return ['review_id' => $reviewId, 'token' => $this->files->launchToken($actor, $reviewId, $artifact), 'count' => count($ids)];
    }

    public function handle(User $actor, string $entity, array $explicitIds, array $intent, ?int $ownerGroupId = null): CatalogAssetBatch
    {
        $this->authorize($actor);
        $this->noTransaction();
        Validator::make(['intent' => $intent], ['intent' => ['required', 'array:review_id,scope_token,source_id,source_version,operation,mode,recipe,entry_choice'], 'intent.review_id' => ['required', 'ulid'], 'intent.scope_token' => ['required', 'string', 'max:32768'], 'intent.source_id' => ['required', 'ulid'], 'intent.source_version' => ['required', 'integer', 'min:1'], 'intent.operation' => ['required', Rule::in(['one_image', 'folder_matches'])], 'intent.mode' => ['required', Rule::in(['fill_missing', 'replace_existing'])], 'intent.recipe' => ['present', 'array']])->validate();
        $scope = $this->files->openLaunch($actor, $intent['scope_token']);
        if ($scope['review_id'] !== $intent['review_id'] || $scope['target_key'] !== $entity.'.main_image' || $scope['record_ids'] !== $explicitIds || ! is_int($intent['source_version'])) {
            $this->invalid('This preview must use its exact frozen record scope.');
        }
        $this->targets->eligibleQuery($actor, $scope['target_key'], $scope['owner_context']);
        if ($ownerGroupId !== null && $scope['owner_context'] !== ['list_key' => 'group-products', 'parent_id' => $ownerGroupId]) {
            $this->invalid('The mandatory Group scope changed.');
        }
        $parameters = $this->recipes->validate($intent['recipe'], $intent['operation'], $entity);
        $creationHash = $this->files->digest(['scope' => $scope['scope_sha256'], 'source' => $intent['source_id'], 'version' => $intent['source_version'], 'operation' => $intent['operation'], 'mode' => $intent['mode'], 'parameters' => $parameters, 'entry_choice' => $intent['entry_choice'] ?? null]);
        if (CatalogAssetBatch::query()->whereKey($intent['review_id'])->exists()) {
            $existing = $this->owned($actor, $intent['review_id']);
            if (! hash_equals($existing->creation_hash, $creationHash)) {
                $this->invalid('This review was created with a different choice. Use its current version.');
            }

            return $existing;
        }
        $evidence = $this->evidence($actor, $scope, $intent['source_id'], $intent['source_version'], $parameters, $intent['operation'], $intent['entry_choice'] ?? null, []);
        $artifact = $this->files->write($scope['review_id'], 1, 'manifest', $evidence);
        $attributes = ['id' => $scope['review_id'], 'actor_id' => $actor->id, 'target_type' => $entity, 'role' => 'main_image', 'operation' => $intent['operation'], 'owner_context' => $scope['owner_context'], 'recipe' => ['parameters' => $parameters, 'mode' => $intent['mode'], 'review_selection' => $scope['review_selection'] ?? []], 'creation_hash' => $creationHash, 'source_id' => $intent['source_id'], 'source_version' => $intent['source_version'], 'scope_path' => $scope['scope_path'], 'scope_sha256' => $scope['scope_sha256'], 'scope_count' => count($explicitIds), 'manifest_path' => $artifact['path'], 'manifest_sha256' => $artifact['sha256']];
        try {
            return DB::transaction(fn () => tap((new CatalogAssetBatch)->forceFill($attributes), fn ($batch) => $batch->save()), 3);
        } catch (QueryException $exception) {
            $existing = CatalogAssetBatch::query()->where('actor_id', $actor->id)->find($scope['review_id']);
            if ($existing === null || ! hash_equals($existing->creation_hash, $creationHash)) {
                throw $exception;
            }

            return $existing;
        }
    }

    public function buildNext(User $actor, string $batchId, int $expectedPreviewVersion, int $expectedBuildRevision): array
    {
        $this->authorize($actor);
        $this->noTransaction();
        $claim = DB::transaction(function () use ($actor, $batchId, $expectedPreviewVersion, $expectedBuildRevision): ?CatalogAssetBatch {
            $batch = $this->owned($actor, $batchId, true);
            $this->version($batch, $expectedPreviewVersion);
            if ($expectedBuildRevision > $batch->build_revision) {
                $this->invalid('The build cursor changed. Refresh status before continuing.');
            }
            if ($expectedBuildRevision < $batch->build_revision || $batch->status !== 'building') {
                return null;
            }
            if ($expectedBuildRevision !== $batch->build_revision) {
                $this->invalid('The build cursor changed. Refresh status before continuing.');
            }
            if ($batch->build_token !== null && $batch->build_expires_at?->isFuture()) {
                return null;
            }
            $batch->forceFill(['build_token' => (string) Str::ulid(), 'build_expires_at' => now()->addSeconds(config('catalog-assets.requests.claim_seconds'))])->save();

            return $batch;
        }, 3);
        if ($claim === null) {
            return $this->status($actor, $batchId);
        }
        try {
            $scope = $this->scope($claim);
            $evidence = $this->reviewEvidence($claim);
            $this->sourceManifest($actor, $scope, $claim->source_id, $claim->source_version);
            if (! hash_equals($evidence['source_hash'], (string) CatalogAssetImportSource::findOrFail($claim->source_id)->manifest_sha256)) {
                $this->invalid('The reviewed source manifest changed.');
            }
            $ids = array_slice($scope['record_ids'], $claim->build_position, 100);
            $records = $this->targets->eligibleQuery($actor, $scope['target_key'], $scope['owner_context'])->whereKey($ids)->get()->keyBy('id');
            $descriptors = [];
            foreach ($ids as $offset => $id) {
                $record = $records->get($id);
                $descriptors[] = ['id' => $id, 'position' => $claim->build_position + $offset, 'entity' => $claim->target_type, 'fingerprint' => $record === null ? null : CatalogAssetAssignments::fingerprint($record), 'main_image_id' => $record?->main_image_id, 'title' => $record === null ? 'Unavailable record' : ($claim->target_type === 'product' ? $record->product_code : $record->name), 'product_code' => $claim->target_type === 'product' ? $record?->product_code : null, 'profile_fields' => $claim->target_type === 'product' ? ($record?->profile_fields ?? []) : []];
            }
            $overrides = $evidence['overrides'];
            if ($claim->operation === 'one_image') {
                foreach ($ids as $id) {
                    $overrides[$id] ??= $evidence['one_image'];
                }
            }
            $rows = $this->matcher->reviewChunk($descriptors, $evidence['index'], [...$claim->recipe['parameters'], 'mode' => $claim->recipe['mode']], $overrides);
            foreach ($rows as &$row) {
                if (is_string($row['snapshot']['chosen_entry'])) {
                    $row['snapshot']['chosen_entry'] = $this->choice($claim->source_id, $claim->source_version, $row['snapshot']['chosen_entry'], $evidence['entries']);
                }
            }
            unset($row);
            DB::transaction(function () use ($actor, $claim, $rows, $ids, $evidence): void {
                $current = $this->owned($actor, $claim->id, true);
                if ($current->preview_version !== $claim->preview_version || $current->build_revision !== $claim->build_revision || $current->build_token !== $claim->build_token || $current->status !== 'building') {
                    $this->invalid('This build response belongs to an older review.');
                }
                $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->findOrFail($claim->source_id);
                if ($source->status !== 'ready' || $source->scan_version !== $claim->source_version || ! hash_equals($evidence['source_hash'], (string) $source->manifest_sha256)) {
                    $this->invalid('The reviewed source changed before publication.');
                }
                $state = $current->recipe;
                foreach ($rows as $row) {
                    $choice = $state['review_selection'][$row['record_id']] ?? ['included' => $row['match_status'] === 'ready' && $row['record_fingerprint'] !== null, 'origin' => 'initial'];
                    $state['review_selection'][$row['record_id']] = $choice;
                    $current->items()->make()->forceFill([...$row, 'included' => $choice['included']])->save();
                }
                $position = $current->build_position + count($ids);
                $finished = $position === $current->scope_count;
                $current->forceFill(['recipe' => $state, 'build_position' => $position, 'build_revision' => $current->build_revision + 1, 'build_token' => null, 'build_expires_at' => null, 'status' => $finished ? 'review' : 'building', 'expires_at' => $finished ? now()->addHours(24) : null, 'error_code' => null, 'error_detail' => null])->save();
            }, 3);
        } catch (Throwable $exception) {
            $correlation = (string) Str::ulid();
            try {
                DB::transaction(function () use ($actor, $claim, $correlation): void {
                    $current = $this->owned($actor, $claim->id, true);
                    if ($current->build_token === $claim->build_token && $current->preview_version === $claim->preview_version && $current->build_revision === $claim->build_revision) {
                        $current->forceFill(['status' => 'failed', 'build_token' => null, 'build_expires_at' => null, 'error_code' => 'review_unavailable', 'error_detail' => 'Review construction stopped. Reference '.$correlation])->save();
                    }
                }, 3);
            } catch (Throwable) {
                Log::warning('catalog_review_failure_not_journaled', ['batch_id' => $batchId, 'correlation_id' => $correlation]);
            }
            throw $exception;
        }

        return $this->status($actor, $batchId);
    }

    public function status(User $actor, string $batchId): array
    {
        $batch = $this->owned($actor, $batchId);
        $counts = $batch->items()->select('match_status')->selectRaw('COUNT(*) AS total')->groupBy('match_status')->pluck('total', 'match_status')->all();
        $accepted = $batch->items()->whereNotNull('acceptance_id')->exists();
        $complete = $batch->build_position === $batch->scope_count;
        $stage = $complete ? ($batch->expires_at?->isPast() ? 'expired' : 'review') : $batch->status;
        $busy = $stage === 'building' && $batch->build_token !== null && $batch->build_expires_at?->isFuture();
        $selection = $batch->recipe['review_selection'] ?? [];
        $explicitlyExcluded = count(array_filter($selection, fn (array $choice): bool => ! $choice['included'] && $choice['origin'] === 'explicit'));
        $deferred = count(array_filter($selection, fn (array $choice): bool => ! $choice['included'] && $choice['origin'] === 'initial'));

        return ['explicitly_excluded' => $explicitlyExcluded, 'deferred' => $deferred, 'stage' => $stage, 'outcome' => $busy ? 'busy' : 'status', 'batch_id' => $batch->id, 'preview_version' => $batch->preview_version, 'revision' => $batch->build_revision, 'classified' => $batch->build_position, 'total' => $batch->scope_count, 'counts' => $counts, 'excluded' => $batch->items()->where('included', false)->count(), 'next_action' => $stage === 'building' ? ($busy ? 'continue' : 'next_step') : ($stage === 'review' ? 'none' : 'review_again'), 'has_acceptance' => $accepted, 'apply_status' => $accepted ? $batch->status : null, 'message' => $stage === 'review' ? ($accepted ? 'Review retained. See This Apply for saved results.' : 'Review ready. No images have been assigned.') : ($stage === 'failed' ? 'Review evidence is unavailable. Choose a valid source and review again.' : 'Review '.$stage.'.')];
    }

    public function revise(User $actor, string $batchId, int $expectedVersion, array $intent): array
    {
        $this->authorize($actor);
        $this->noTransaction();
        Validator::make(['intent' => $intent], ['intent' => ['required', 'array:source_id,source_version,mode,recipe,entry_choice,override'], 'intent.mode' => ['sometimes', Rule::in(['fill_missing', 'replace_existing'])], 'intent.recipe' => ['sometimes', 'array'], 'intent.source_id' => ['sometimes', 'ulid'], 'intent.source_version' => ['sometimes', 'integer', 'min:1']])->validate();
        $batch = $this->owned($actor, $batchId);
        $this->version($batch, $expectedVersion);
        $this->requireRevisable($batch);
        $scope = $this->scope($batch);
        $old = $this->reviewEvidence($batch);
        $parameters = $this->recipes->validate(array_replace($batch->recipe['parameters'], $intent['recipe'] ?? []), $batch->operation, $batch->target_type);
        $overrides = $old['overrides'];
        if (isset($intent['override'])) {
            if ($batch->status !== 'review' || ! $batch->expires_at?->isFuture()) {
                $this->invalid('Complete a current review before choosing a row override.');
            }
            Validator::make($intent['override'], ['record_id' => ['required', 'integer', 'min:1'], 'choice' => ['present']])->validate();
            $recordId = (int) $intent['override']['record_id'];
            if (! in_array($recordId, $scope['record_ids'], true)) {
                $this->invalid('Choose a record from this review.');
            }
            if ($intent['override']['choice'] === null) {
                unset($overrides[$recordId]);
            } else {
                $overrides[$recordId] = $this->override($actor, $scope, $intent['override']['choice']);
            }
        }
        $sourceId = $intent['source_id'] ?? $batch->source_id;
        $sourceVersion = (int) ($intent['source_version'] ?? $batch->source_version);
        $one = $intent['entry_choice'] ?? ($old['one_image']['choice'] ?? null);
        $evidence = $this->evidence($actor, $scope, $sourceId, $sourceVersion, $parameters, $batch->operation, $one, $overrides);
        $artifact = $this->files->write($batch->id, $expectedVersion + 1, 'manifest', $evidence);
        DB::transaction(function () use ($actor, $batchId, $expectedVersion, $intent, $parameters, $artifact, $sourceId, $sourceVersion): void {
            $current = $this->owned($actor, $batchId, true);
            $this->version($current, $expectedVersion);
            $this->requireRevisable($current);
            $state = $current->recipe;
            $state['parameters'] = $parameters;
            $state['mode'] = $intent['mode'] ?? $state['mode'];
            $current->items()->delete();
            $current->forceFill(['recipe' => $state, 'source_id' => $sourceId, 'source_version' => $sourceVersion, 'manifest_path' => $artifact['path'], 'manifest_sha256' => $artifact['sha256'], 'preview_version' => $expectedVersion + 1, 'build_revision' => $current->build_revision + 1, 'build_position' => 0, 'build_token' => null, 'build_expires_at' => null, 'expires_at' => null, 'status' => 'building', 'error_code' => null, 'error_detail' => null])->save();
        }, 3);

        return $this->status($actor, $batchId);
    }

    public function setIncluded(User $actor, string $batchId, int $expectedVersion, array $itemIds, bool $included): array
    {
        $this->authorize($actor);
        $this->noTransaction();
        Validator::make(['ids' => $itemIds], ['ids' => ['required', 'array', 'min:1', 'max:5000'], 'ids.*' => ['integer', 'min:1', 'distinct']])->validate();
        DB::transaction(function () use ($actor, $batchId, $expectedVersion, $itemIds, $included): void {
            $batch = $this->owned($actor, $batchId, true);
            $this->version($batch, $expectedVersion);
            if ($batch->build_position !== $batch->scope_count || $batch->active_acceptance_id !== null || ! $batch->expires_at?->isFuture()) {
                $this->invalid('Complete a current review and finish its active Apply before changing inclusion.');
            }
            $rows = $batch->items()->whereKey($itemIds)->get();
            if ($rows->count() !== count($itemIds)) {
                $this->invalid('Choose current rows from this review.');
            }
            $eligible = $included ? $this->targets->eligibleQuery($actor, $batch->target_type.'.main_image', $batch->owner_context)->whereKey($rows->pluck('record_id'))->get()->keyBy('id') : collect();
            $state = $batch->recipe;
            foreach ($rows as $row) {
                if ($row->acceptance_id !== null) {
                    $this->invalid('Accepted rows cannot change inclusion.');
                }
                if ($included && ($row->match_status !== 'ready' || ! $eligible->has($row->record_id) || ! hash_equals($row->record_fingerprint ?? '', CatalogAssetAssignments::fingerprint($eligible->get($row->record_id))))) {
                    $this->invalid('Only current ready proposals can be included. Review the record again.');
                }
                $state['review_selection'][$row->record_id] = ['included' => $included, 'origin' => 'explicit'];
                $row->forceFill(['included' => $included])->save();
            }
            $batch->forceFill(['recipe' => $state])->save();
        }, 3);

        return $this->status($actor, $batchId);
    }

    private function requireRevisable(CatalogAssetBatch $batch): void
    {
        if ($batch->items()->whereNotNull('acceptance_id')->exists() || $batch->pending_apply_intent !== null) {
            $this->invalid('Accepted reviews are immutable. Finish Apply and start a new review for remaining records; discard an unaccepted prepared Apply first.');
        }
    }

    public function freezeRemaining(User $actor, string $batchId, int $expectedVersion): array
    {
        $this->noTransaction();
        $batch = $this->owned($actor, $batchId);
        $this->version($batch, $expectedVersion);
        if ($batch->active_acceptance_id !== null || $batch->pending_apply_intent !== null || $batch->build_position !== $batch->scope_count) {
            $this->invalid('Finish the active Apply and discard any prepared Apply before starting a remaining review.');
        }
        $original = $this->scope($batch);
        $finished = $batch->items()->whereIn('apply_status', ['applied', 'unchanged', 'skipped'])->pluck('record_id')->all();
        $ids = array_values(array_diff($original['record_ids'], $finished));
        if ($ids === []) {
            $this->invalid('There are no remaining records to review.');
        }
        $id = (string) Str::ulid();
        $scope = ['actor_id' => $actor->id, 'review_id' => $id, 'target_key' => $batch->target_type.'.main_image', 'owner_context' => $batch->owner_context, 'record_ids' => $ids, 'sort' => $original['sort'],
            'parent_review_id' => $batch->id, 'parent_preview_version' => $expectedVersion, 'review_selection' => array_intersect_key($batch->recipe['review_selection'], array_flip($ids))];
        $artifact = $this->files->write($id, 1, 'scope', $scope);
        DB::transaction(function () use ($actor, $batchId, $expectedVersion, $batch, $finished): void {
            $current = $this->owned($actor, $batchId, true);
            $this->version($current, $expectedVersion);
            $nowFinished = $current->items()->whereIn('apply_status', ['applied', 'unchanged', 'skipped'])->pluck('record_id')->all();
            if ($current->active_acceptance_id !== null || $current->pending_apply_intent !== null || $current->recipe !== $batch->recipe || $nowFinished !== $finished) {
                $this->invalid('The parent review changed. Refresh before starting a remaining review.');
            }
        }, 3);

        return ['review_id' => $id, 'token' => $this->files->launchToken($actor, $id, $artifact), 'count' => count($ids)];
    }

    public function scope(CatalogAssetBatch $batch): array
    {
        return $this->files->read($batch->id, 1, 'scope', $batch->scope_path, $batch->scope_sha256);
    }

    public function reviewEvidence(CatalogAssetBatch $batch): array
    {
        return $this->files->read($batch->id, $batch->preview_version, 'manifest', $batch->manifest_path, $batch->manifest_sha256);
    }

    private function sourceManifest(User $actor, array $scope, string $sourceId, int $version): array
    {
        $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->findOrFail($sourceId);
        if (($source->source['target_key'] ?? null) !== $scope['target_key'] || $this->files->digest($source->source['owner_context'] ?? []) !== $this->files->digest($scope['owner_context'])) {
            $this->invalid('Choose an image source from this exact record scope.');
        }

        return $this->sources->manifest($actor, $sourceId, $version);
    }

    private function evidence(User $actor, array $scope, string $sourceId, int $version, array $parameters, string $operation, ?array $one, array $overrides): array
    {
        $manifest = $this->sourceManifest($actor, $scope, $sourceId, $version);
        foreach ($overrides as $id => $override) {
            $overrides[$id] = $this->override($actor, $scope, $override['choice']);
        }
        $single = $operation === 'one_image' ? $this->override($actor, $scope, $one ?? []) : null;
        if ($single !== null && ($single['choice']['source_id'] !== $sourceId || $single['choice']['scan_version'] !== $version)) {
            $this->invalid('Choose one image from the selected source.');
        }
        $recipe = $operation === 'one_image' ? $this->recipes->defaults() : $parameters;

        return ['source_id' => $sourceId, 'source_version' => $version, 'source_hash' => CatalogAssetImportSource::findOrFail($sourceId)->manifest_sha256, 'entries' => array_column($manifest['entries'], null, 'entry_id'), 'index' => $this->matcher->index($manifest, $recipe), 'overrides' => $overrides, 'one_image' => $single];
    }

    private function override(User $actor, array $scope, array $choice): array
    {
        $selected = $this->sources->selectedEntry($actor, $choice, $scope['target_key']);
        if ($this->files->digest($selected['owner_context']) !== $this->files->digest($scope['owner_context'])) {
            $this->invalid('This candidate belongs to another mandatory scope.');
        }

        return ['choice' => $choice, 'filename' => $selected['entry']['name'], 'width' => $selected['entry']['width'], 'height' => $selected['entry']['height']];
    }

    private function choice(string $sourceId, int $version, string $id, array $entries): array
    {
        if (! isset($entries[$id]) || $entries[$id]['status'] !== 'valid') {
            $this->invalid('The reviewed candidate is unavailable.');
        }

        return ['source_id' => $sourceId, 'scan_version' => $version, 'entry_id' => $id, 'sha256' => $entries[$id]['sha256']];
    }

    private function version(CatalogAssetBatch $batch, int $expected): void
    {
        if ($batch->preview_version !== $expected) {
            $this->invalid('This review changed. Refresh its current version.');
        }
    }

    private function noTransaction(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Image review operations must own their commits.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['review' => $message]);
    }
}
