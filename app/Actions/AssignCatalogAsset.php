<?php

namespace App\Actions;

use App\DTO\CatalogAssetChange;
use App\Models\CatalogAssetBatchItem;
use App\Models\CatalogAssetImportSource;
use App\Models\Group;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetReviewFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class AssignCatalogAsset
{
    public function __construct(private SaveCatalogMainImage $writer, private ReviewCatalogAssets $reviews, private ApplyCatalogAssets $applies, private CatalogAssetAssignments $assignments, private CatalogAssetReviewFiles $files) {}

    public function handle(User $actor, CatalogAssetBatchItem $item, string $requestStepId, string $processingToken, CatalogAssetChange $change): bool
    {
        if (DB::transactionLevel() !== 0 || $change->operation !== 'set') {
            throw new LogicException('A prepared batch image requires its independent owner transaction.');
        }
        $batch = $this->reviews->owned($actor, $item->batch_id);
        $expected = $this->applies->expectedFingerprint($item);
        $changed = false;
        $lockedBatch = null;
        $lockedItem = null;
        $causal = [];
        $this->writer->handle($actor, $batch->target_type.'.main_image', $item->record_id, $batch->owner_context, $expected, null,
            function (Model $owner) use ($actor, $batch, $item, $requestStepId, $processingToken, $change, $expected, &$lockedBatch, &$lockedItem, &$causal, &$changed): CatalogAssetChange {
                $changed = false;
                $causal = [];
                $lockedBatch = $this->reviews->owned($actor, $batch->id, true);
                $rows = $lockedBatch->items()->where('acceptance_id', $item->acceptance_id)->when($batch->target_type !== 'group', fn ($query) => $query->whereKey($item->id))->orderBy('position')->orderBy('id')->lockForUpdate()->get();
                $lockedItem = $rows->find($item->id);
                $first = $lockedBatch->items()->where('acceptance_id', $item->acceptance_id)->whereIn('apply_status', ['pending', 'processing'])->orderBy('position')->orderBy('id')->first();
                if ($lockedItem === null || $lockedBatch->active_acceptance_id !== $item->acceptance_id || $lockedItem->request_step_id !== $requestStepId || $lockedItem->processing_token !== $processingToken || $lockedItem->apply_status !== 'processing' || $first?->id !== $item->id || $lockedItem->accepted_preview_version !== $lockedBatch->preview_version || ! hash_equals($expected, $this->applies->expectedFingerprint($lockedItem))) {
                    throw ValidationException::withMessages(['apply.step' => 'This image step was replaced. Refresh status.']);
                }
                $proof = $lockedItem->snapshot['acceptance_source'];
                $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->find($proof['choice']['source_id']);
                if ($source === null || $source->status !== 'ready' || $source->scan_version !== $proof['scan_version'] || ! hash_equals((string) $source->manifest_sha256, $proof['manifest_sha256']) || ($source->source['target_key'] ?? null) !== $batch->target_type.'.main_image' || $this->files->digest($source->source['owner_context'] ?? []) !== $this->files->digest($batch->owner_context)) {
                    throw ValidationException::withMessages(['apply.source' => 'The accepted source changed before saving.']);
                }
                $asset = $this->assignments->validatedAsset($batch->target_type.'.main_image', $change->assetId);
                $choice = $lockedItem->snapshot['chosen_entry'];
                if ($asset->created_by !== $actor->id || $asset->import_key !== ProcessNextCatalogAssetBatchItem::importKey($actor, $lockedBatch, $lockedItem, $this->files)
                    || ($asset->source['source_id'] ?? null) !== $choice['source_id'] || ($asset->source['scan_version'] ?? null) !== $choice['scan_version'] || ($asset->source['entry']['entry_id'] ?? null) !== $choice['entry_id']
                    || ! hash_equals($asset->sha256, $choice['sha256']) || $this->files->digest($asset->source['owner_context'] ?? []) !== $this->files->digest($batch->owner_context)) {
                    throw ValidationException::withMessages(['image.asset' => 'The prepared image does not belong to this accepted choice.']);
                }
                if ($owner instanceof Group) {
                    $groups = Group::query()->get()->keyBy('id');
                    $affected = array_values(array_filter([$owner->id, $owner->parent_id], fn ($id) => $id !== null));
                    do {
                        $count = count($affected);
                        foreach ($groups as $group) {
                            if (in_array($group->parent_id, $affected, true) && ! in_array($group->id, $affected, true)) {
                                $affected[] = $group->id;
                            }
                        }
                    } while ($count !== count($affected));
                    foreach ($rows as $other) {
                        $eligible = in_array($other->apply_status, ['pending', 'processing'], true) || ($other->apply_status === 'failed' && ($other->snapshot['apply_result']['retryable'] ?? false) === true);
                        $group = $groups->get($other->record_id);
                        if ($other->id !== $item->id && $eligible && $group !== null && in_array($group->id, $affected, true) && hash_equals($this->applies->expectedFingerprint($other), CatalogAssetAssignments::fingerprint($group))) {
                            $causal[] = ['item' => $other, 'attributes' => $group->getAttributes(), 'fingerprint' => CatalogAssetAssignments::fingerprint($group)];
                        }
                    }
                }

                return $change;
            },
            function (Model $owner, bool $didChange) use ($change, $requestStepId, &$lockedBatch, &$lockedItem, &$causal, &$changed): void {
                $changed = $didChange;
                if ($didChange && $causal !== []) {
                    $groups = Group::query()->whereKey(array_column(array_column($causal, 'attributes'), 'id'))->get()->keyBy('id');
                    foreach ($causal as $proof) {
                        $other = $proof['item'];
                        $after = $groups->get($other->record_id);
                        $beforeAttributes = $proof['attributes'];
                        $afterAttributes = $after?->getAttributes();
                        if ($afterAttributes === null) {
                            throw new LogicException('A locked Group disappeared.');
                        }
                        $nextRevision = $afterAttributes['catalog_revision'];
                        $afterAttributes['catalog_revision'] = $beforeAttributes['catalog_revision'];
                        if ($afterAttributes !== $beforeAttributes || (int) $nextRevision !== (int) $beforeAttributes['catalog_revision'] + 1) {
                            throw new LogicException('An image write changed more than its causal Group revision.');
                        }
                        $snapshot = $other->snapshot;
                        $causes = $snapshot['execution_causes'] ?? [];
                        if (count($causes) >= 49) {
                            throw new LogicException('The accepted Group causal bound was exceeded.');
                        }
                        $nextFingerprint = CatalogAssetAssignments::fingerprint($after);
                        $snapshot['execution_causes'] = [...$causes, ['acceptance_id' => $lockedItem->acceptance_id, 'caused_by_item_id' => $lockedItem->id, 'caused_by_step_id' => $requestStepId, 'from_fingerprint' => $proof['fingerprint'], 'to_fingerprint' => $nextFingerprint, 'recorded_at' => now()->toISOString()]];
                        $other->forceFill(['snapshot' => $snapshot, 'execution_fingerprint' => $nextFingerprint])->save();
                    }
                }
                $snapshot = $lockedItem->snapshot;
                $asset = $this->assignments->validatedAsset($lockedBatch->target_type.'.main_image', $change->assetId);
                $warnings = array_slice($asset->verified_metadata['warnings'] ?? [], 0, 10);
                $snapshot['apply_result'] = ['code' => $didChange ? 'applied' : 'unchanged', 'retryable' => false, 'correlation_id' => (string) Str::ulid(), 'warnings' => $warnings, 'recorded_at' => now()->toISOString()];
                $lockedItem->forceFill(['prepared_asset_id' => $change->assetId, 'apply_status' => $didChange ? 'applied' : 'unchanged', 'snapshot' => $snapshot, 'processing_token' => null, 'processing_expires_at' => null, 'applied_at' => now(), 'error_code' => null, 'error_detail' => null])->save();
                $this->applies->finishLocked($lockedBatch, $lockedItem->acceptance_id);
            });

        return $changed;
    }
}
