<?php

namespace App\Actions;

use App\DTO\CatalogAssetChange;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetBatchItem;
use App\Models\CatalogAssetImportSource;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetReviewQuery;
use App\Services\CatalogAssetStorage;
use App\Services\CatalogAssetTargets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class ProcessNextCatalogAssetBatchItem
{
    public function __construct(private ReviewCatalogAssets $reviews, private ApplyCatalogAssets $applies, private CatalogAssetReviewQuery $query, private CatalogAssetStorage $storage, private CatalogAssetTargets $targets, private CatalogAssetReviewFiles $files, private AssignCatalogAsset $assign) {}

    public static function importKey(User $actor, CatalogAssetBatch $batch, CatalogAssetBatchItem $item, CatalogAssetReviewFiles $files): string
    {
        return $files->digest(['schema' => 'catalog-image-batch-v1', 'actor_id' => $actor->id, 'batch_id' => $batch->id, 'accepted_preview_version' => $item->accepted_preview_version, 'target_key' => $batch->target_type.'.main_image', 'owner_context' => $batch->owner_context, 'choice' => $item->snapshot['chosen_entry']]);
    }

    public function handle(User $actor, string $batchId, string $acceptanceId, string $requestStepId): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('An image step must own its commits.');
        }
        $actor = User::query()->findOrFail($actor->id);
        $claim = DB::transaction(function () use ($actor, $batchId, $acceptanceId, $requestStepId): array {
            $batch = $this->reviews->owned($actor, $batchId, true);
            $row = $batch->items()->where('acceptance_id', $acceptanceId)->where('request_step_id', $requestStepId)->lockForUpdate()->first();
            if ($row === null) {
                return ['outcome' => 'stale_step'];
            }
            if (in_array($row->apply_status, ['applied', 'unchanged', 'failed', 'stale', 'skipped'], true)) {
                return ['outcome' => $row->apply_status, 'item_id' => $row->id, 'replay' => true];
            }
            $first = $batch->items()->where('acceptance_id', $acceptanceId)->whereIn('apply_status', ['pending', 'processing'])->orderBy('position')->orderBy('id')->first();
            if ($batch->active_acceptance_id !== $acceptanceId || $first?->id !== $row->id || $row->accepted_preview_version !== $batch->preview_version) {
                return ['outcome' => 'out_of_order'];
            }
            if ($row->apply_status === 'processing') {
                return ['outcome' => $row->processing_expires_at?->isFuture() ? 'busy' : 'recovery_required'];
            }
            $row->forceFill(['apply_status' => 'processing', 'processing_token' => (string) Str::ulid(), 'processing_expires_at' => now()->addSeconds(60), 'attempts' => $row->attempts + 1])->save();

            return ['batch' => $batch, 'item' => $row];
        }, 3);
        if (! isset($claim['item'])) {
            return [...$claim, 'changed_record_ids' => [], 'progress' => $this->query->applyProgress($actor, $batchId, $acceptanceId)];
        }
        $batch = $claim['batch'];
        $item = $claim['item'];
        try {
            $owner = $this->targets->eligibleQuery($actor, $batch->target_type.'.main_image', $batch->owner_context)->find($item->record_id);
            if ($owner === null || ! hash_equals($this->applies->expectedFingerprint($item), CatalogAssetAssignments::fingerprint($owner))) {
                return $this->settle($actor, $batch, $item, 'stale', 'owner_changed');
            }
            $proof = $item->snapshot['acceptance_source'];
            $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->find($proof['choice']['source_id']);
            if ($source === null || $source->status !== 'ready' || $source->scan_version !== $proof['scan_version'] || ! hash_equals((string) $source->manifest_sha256, $proof['manifest_sha256']) || ($source->source['target_key'] ?? null) !== $batch->target_type.'.main_image' || $this->files->digest($source->source['owner_context'] ?? []) !== $this->files->digest($batch->owner_context)) {
                return $this->settle($actor, $batch, $item, 'stale', 'source_changed');
            }
            $prepared = $this->storage->prepare($actor, $proof['choice'], $batch->target_type.'.main_image', self::importKey($actor, $batch, $item, $this->files));
            if ($prepared['outcome'] === 'busy') {
                return $this->settle($actor, $batch, $item, 'pending', 'preparation_busy', restoreAttempt: true);
            }
            if ($prepared['outcome'] === 'failed') {
                return $this->settle($actor, $batch, $item, $prepared['code'] === 'source_stale' ? 'stale' : 'failed', $prepared['code'], $prepared['warnings']);
            }
            if ($prepared['outcome'] !== 'ready') {
                return $this->response($actor, $batch, 'paused');
            }
            $changed = $this->assign->handle($actor, $item, $requestStepId, $item->processing_token, new CatalogAssetChange('set', $prepared['asset']->id));

            return $this->response($actor, $batch, $changed ? 'applied' : 'unchanged', $changed ? [$item->record_id] : []);
        } catch (ValidationException $exception) {
            if (array_intersect(array_keys($exception->errors()), ['record', 'image.context', 'apply.source']) !== []) {
                return $this->settle($actor, $batch, $item, 'stale', 'owner_changed');
            }
            if (array_key_exists('apply.step', $exception->errors())) {
                return $this->response($actor, $batch, 'stale_step');
            }

            return $this->response($actor, $batch, 'paused');
        } catch (Throwable $exception) {
            $correlation = (string) Str::ulid();
            Log::warning('Catalog image assignment paused.', ['batch_id' => $batch->id, 'item_id' => $item->id, 'correlation_id' => $correlation, 'exception' => $exception]);

            return $this->response($actor, $batch, 'paused');
        }
    }

    private function settle(User $actor, CatalogAssetBatch $batch, CatalogAssetBatchItem $claim, string $status, string $code, array $warnings = [], bool $restoreAttempt = false): array
    {
        $settled = DB::transaction(function () use ($actor, $batch, $claim, $status, $code, $warnings, $restoreAttempt): bool {
            $current = $this->reviews->owned($actor, $batch->id, true);
            $row = $current->items()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($current->active_acceptance_id !== $claim->acceptance_id || $row->request_step_id !== $claim->request_step_id || $row->processing_token !== $claim->processing_token || $row->apply_status !== 'processing') {
                return false;
            }
            $snapshot = $row->snapshot;
            if ($status !== 'pending') {
                $snapshot['apply_result'] = ['code' => $code, 'retryable' => false, 'correlation_id' => (string) Str::ulid(), 'warnings' => array_slice($warnings, 0, 10), 'recorded_at' => now()->toISOString()];
            }
            $row->forceFill(['snapshot' => $snapshot, 'apply_status' => $status, 'processing_token' => null, 'processing_expires_at' => null, 'attempts' => $restoreAttempt ? max(0, $row->attempts - 1) : $row->attempts, 'error_code' => $status === 'pending' ? null : $code, 'error_detail' => null])->save();
            $this->applies->finishLocked($current, $claim->acceptance_id);

            return true;
        }, 3);

        return $this->response($actor, $batch, $settled ? ($status === 'pending' ? 'busy' : $status) : 'stale_step');
    }

    private function response(User $actor, CatalogAssetBatch $batch, string $outcome, array $changed = []): array
    {
        return ['outcome' => $outcome, 'changed_record_ids' => $changed, 'model' => $this->targets->profile($batch->target_type.'.main_image')['model'], 'progress' => $this->query->applyProgress($actor, $batch->id)];
    }
}
