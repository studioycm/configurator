<?php

namespace App\Actions;

use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetBatchItem;
use App\Models\CatalogAssetImportSource;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetReviewQuery;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetTargets;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class ApplyCatalogAssets
{
    public function __construct(private ReviewCatalogAssets $reviews, private CatalogAssetTargets $targets, private CatalogAssetSources $sources, private CatalogAssetReviewFiles $files, private CatalogAssetReviewQuery $query) {}

    public function prepareIntent(User $actor, string $batchId, int $expectedVersion, array $itemIds): string
    {
        $this->independent();
        Validator::make(['ids' => $itemIds], ['ids' => ['required', 'array', 'min:1', 'max:50'], 'ids.*' => ['integer', 'min:1', 'distinct']])->validate();
        $ids = array_map('intval', $itemIds);
        if (count(array_unique($ids)) !== count($ids)) {
            $this->invalid('Choose distinct current review rows.');
        }
        $batch = $this->reviews->owned($actor, $batchId);
        $this->currentReview($batch, $expectedVersion);
        $rows = $batch->items()->whereKey($ids)->orderBy('position')->orderBy('id')->get();
        $this->validateRows($actor, $batch, $rows, $ids);
        $proofs = $this->sourceProofs($actor, $batch, $rows);

        return DB::transaction(function () use ($actor, $batchId, $expectedVersion, $ids, $proofs): string {
            $batch = $this->reviews->owned($actor, $batchId, true);
            $this->currentReview($batch, $expectedVersion);
            $this->noActive($batch);
            $rows = $batch->items()->whereKey($ids)->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $this->validateRows($actor, $batch, $rows, $ids);
            $this->checkSourceProofs($actor, $batch, $proofs);
            $ordered = $rows->modelKeys();
            $meaning = $this->files->digest(['version' => $expectedVersion, 'ids' => $ordered, 'source_evidence' => $proofs]);
            if (($batch->pending_apply_intent['meaning_sha256'] ?? null) === $meaning) {
                return $batch->pending_apply_intent['id'];
            }
            $id = (string) Str::ulid();
            $batch->forceFill(['pending_apply_intent' => ['id' => $id, 'preview_version' => $expectedVersion, 'item_ids' => $ordered, 'issued_at' => now()->toISOString(), 'meaning_sha256' => $meaning, 'source_evidence' => $proofs]])->save();

            return $id;
        }, 3);
    }

    public function handle(User $actor, string $batchId, string $intentId): CatalogAssetBatch
    {
        $this->independent();

        return DB::transaction(function () use ($actor, $batchId, $intentId): CatalogAssetBatch {
            $batch = $this->reviews->owned($actor, $batchId, true);
            if ($batch->items()->where('acceptance_id', $intentId)->exists()) {
                return $batch;
            }
            $intent = $batch->pending_apply_intent;
            if (! is_array($intent) || ($intent['id'] ?? null) !== $intentId) {
                $this->invalid('This prepared Apply is no longer current. Refresh status.');
            }
            $this->currentReview($batch, $intent['preview_version']);
            $this->noActive($batch);
            $rows = $batch->items()->whereKey($intent['item_ids'])->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $this->validateRows($actor, $batch, $rows, $intent['item_ids']);
            $this->checkSourceProofs($actor, $batch, $intent['source_evidence']);
            foreach ($rows as $row) {
                $choice = $row->snapshot['chosen_entry'];
                $snapshot = $row->snapshot;
                $snapshot['acceptance_source'] = ['choice' => $choice, ...$intent['source_evidence'][$choice['source_id']]];
                $row->forceFill(['snapshot' => $snapshot, 'acceptance_id' => $intentId, 'accepted_preview_version' => $batch->preview_version, 'accepted_at' => now(), 'request_step_id' => (string) Str::ulid(), 'apply_status' => 'pending', 'processing_token' => null, 'processing_expires_at' => null, 'execution_fingerprint' => null])->save();
            }
            $recipe = $batch->recipe;
            $recipe['last_acceptance_id'] = $intentId;
            $batch->forceFill(['recipe' => $recipe, 'pending_apply_intent' => null, 'active_acceptance_id' => $intentId, 'status' => 'accepted'])->save();

            return $batch;
        }, 3);
    }

    public function discardPendingIntent(User $actor, string $batchId, string $expectedIntentId): array
    {
        $this->independent();
        DB::transaction(function () use ($actor, $batchId, $expectedIntentId): void {
            $batch = $this->reviews->owned($actor, $batchId, true);
            if (($batch->pending_apply_intent['id'] ?? null) !== $expectedIntentId) {
                $this->invalid('The pending Apply changed. Refresh status.');
            }
            $batch->forceFill(['pending_apply_intent' => null])->save();
        }, 3);

        return $this->query->applyProgress($actor, $batchId);
    }

    public function continueAcceptance(User $actor, string $batchId, string $acceptanceId): array
    {
        $this->independent();
        DB::transaction(function () use ($actor, $batchId, $acceptanceId): void {
            $batch = $this->reviews->owned($actor, $batchId, true);
            if ($batch->active_acceptance_id !== $acceptanceId) {
                return;
            }
            $row = $batch->items()->where('acceptance_id', $acceptanceId)->whereIn('apply_status', ['pending', 'processing'])->orderBy('position')->orderBy('id')->lockForUpdate()->first();
            if ($row === null || $row->apply_status !== 'processing' || $row->processing_expires_at?->isFuture()) {
                return;
            }
            $row->forceFill(['request_step_id' => (string) Str::ulid(), 'processing_token' => null, 'processing_expires_at' => null, 'apply_status' => 'pending'])->save();
        }, 3);

        return $this->query->applyProgress($actor, $batchId, $acceptanceId);
    }

    public function retryFailed(User $actor, string $batchId, string $acceptanceId): array
    {
        $this->independent();
        $batch = $this->reviews->owned($actor, $batchId);
        $rows = $batch->items()->where('acceptance_id', $acceptanceId)->where('apply_status', 'failed')->orderBy('position')->get()->filter(fn ($row): bool => ($row->snapshot['apply_result']['retryable'] ?? false) === true);
        if ($rows->isEmpty()) {
            $this->invalid('There are no recoverable failures in this Apply. Stale proposals need a new review.');
        }
        $proofs = $this->sourceProofs($actor, $batch, $rows);
        DB::transaction(function () use ($actor, $batchId, $acceptanceId, $rows, $proofs): void {
            $batch = $this->reviews->owned($actor, $batchId, true);
            $this->noActive($batch);
            if ($batch->pending_apply_intent !== null) {
                $this->invalid('Discard the prepared Apply before retrying another acceptance.');
            }
            $current = $batch->items()->whereKey($rows->modelKeys())->orderBy('position')->orderBy('id')->lockForUpdate()->get();
            $this->checkOwners($actor, $batch, $current);
            $this->checkSourceProofs($actor, $batch, $proofs);
            foreach ($current as $row) {
                if ($row->acceptance_id !== $acceptanceId || $row->apply_status !== 'failed' || ($row->snapshot['apply_result']['retryable'] ?? false) !== true) {
                    $this->invalid('These failures changed. Refresh status.');
                }
                $snapshot = $row->snapshot;
                unset($snapshot['apply_result']);
                $row->forceFill(['snapshot' => $snapshot, 'apply_status' => 'pending', 'request_step_id' => (string) Str::ulid(), 'processing_token' => null, 'processing_expires_at' => null, 'error_code' => null, 'error_detail' => null])->save();
            }
            $recipe = $batch->recipe;
            $recipe['last_acceptance_id'] = $acceptanceId;
            $batch->forceFill(['recipe' => $recipe, 'active_acceptance_id' => $acceptanceId, 'status' => 'accepted'])->save();
        }, 3);

        return $this->query->applyProgress($actor, $batchId, $acceptanceId);
    }

    public function expectedFingerprint(CatalogAssetBatchItem $row): string
    {
        $expected = $row->record_fingerprint;
        $causes = $row->snapshot['execution_causes'] ?? [];
        if (! is_string($expected) || ! is_array($causes) || count($causes) > 49) {
            $this->invalid('The execution expectation is unavailable. Review this record again.');
        }
        foreach ($causes as $cause) {
            if (! is_array($cause) || ($cause['acceptance_id'] ?? null) !== $row->acceptance_id || ($cause['from_fingerprint'] ?? null) !== $expected || ! is_string($cause['to_fingerprint'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $cause['to_fingerprint']) !== 1) {
                $this->invalid('The execution expectation changed. Review this record again.');
            }
            $expected = $cause['to_fingerprint'];
        }
        if (($row->execution_fingerprint ?? $row->record_fingerprint) !== $expected) {
            $this->invalid('The execution expectation changed. Review this record again.');
        }

        return $expected;
    }

    public function finishLocked(CatalogAssetBatch $batch, string $acceptanceId): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Apply completion belongs to the journal owner transaction.');
        }
        if ($batch->active_acceptance_id !== $acceptanceId) {
            return;
        }
        $counts = $batch->items()->where('acceptance_id', $acceptanceId)->select('apply_status')->selectRaw('COUNT(*) AS total')->groupBy('apply_status')->pluck('total', 'apply_status')->all();
        if (($counts['pending'] ?? 0) + ($counts['processing'] ?? 0) > 0) {
            return;
        }
        $success = ($counts['applied'] ?? 0) + ($counts['unchanged'] ?? 0);
        $failure = ($counts['failed'] ?? 0) + ($counts['stale'] ?? 0);
        $batch->forceFill(['active_acceptance_id' => null, 'status' => $failure === 0 ? 'completed' : ($success === 0 ? 'failed' : 'partial')])->save();
    }

    private function validateRows(User $actor, CatalogAssetBatch $batch, Collection $rows, array $ids): void
    {
        if ($rows->count() !== count($ids)) {
            $this->invalid('Choose current rows from this review.');
        }
        foreach ($rows as $row) {
            if (! $row->included || $row->match_status !== 'ready' || $row->record_fingerprint === null || $row->acceptance_id !== null || ! is_array($row->snapshot['chosen_entry'] ?? null)) {
                $this->invalid('Only included, ready, unaccepted proposals can enter This Apply.');
            }
        }
        $this->checkOwners($actor, $batch, $rows);
    }

    private function checkOwners(User $actor, CatalogAssetBatch $batch, Collection $rows): void
    {
        $owners = $this->targets->eligibleQuery($actor, $batch->target_type.'.main_image', $batch->owner_context)->whereKey($rows->pluck('record_id'))->get()->keyBy('id');
        foreach ($rows as $row) {
            $owner = $owners->get($row->record_id);
            if ($owner === null || ! hash_equals($this->expectedFingerprint($row), CatalogAssetAssignments::fingerprint($owner)) || ($batch->recipe['mode'] === 'fill_missing' && $owner->main_image_id !== null)) {
                $this->invalid('Selected records changed. Start a new review for remaining records.');
            }
        }
    }

    private function sourceProofs(User $actor, CatalogAssetBatch $batch, Collection $rows): array
    {
        $evidence = $this->reviews->reviewEvidence($batch);
        $proofs = [];
        $entries = [];
        foreach ($rows as $row) {
            $choice = $row->snapshot['chosen_entry'];
            $id = $choice['source_id'];
            if (! isset($proofs[$id])) {
                $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->findOrFail($id);
                if (($source->source['target_key'] ?? null) !== $batch->target_type.'.main_image' || $this->files->digest($source->source['owner_context'] ?? []) !== $this->files->digest($batch->owner_context)) {
                    $this->invalid('This source belongs to another record scope.');
                }
                $manifest = $this->sources->manifest($actor, $id, $choice['scan_version']);
                $entries[$id] = array_column($manifest['entries'], null, 'entry_id');
                if ($id === $batch->source_id && ! hash_equals($evidence['source_hash'], (string) $source->manifest_sha256)) {
                    $this->invalid('The reviewed source changed. Review again.');
                }
                $proofs[$id] = ['scan_version' => $source->scan_version, 'manifest_sha256' => $source->manifest_sha256];
            }
            $entry = $entries[$id][$choice['entry_id']] ?? null;
            if (($proofs[$id]['scan_version'] ?? null) !== $choice['scan_version'] || $entry === null || $entry['status'] !== 'valid' || ! hash_equals($entry['sha256'], $choice['sha256'])) {
                $this->invalid('The reviewed file changed. Review again.');
            }
        }
        ksort($proofs);

        return $proofs;
    }

    private function checkSourceProofs(User $actor, CatalogAssetBatch $batch, array $proofs): void
    {
        foreach ($proofs as $id => $proof) {
            $source = CatalogAssetImportSource::query()->where('actor_id', $actor->id)->findOrFail($id);
            if ($source->status !== 'ready' || $source->scan_version !== $proof['scan_version'] || ! hash_equals((string) $source->manifest_sha256, $proof['manifest_sha256']) || ($source->source['target_key'] ?? null) !== $batch->target_type.'.main_image' || $this->files->digest($source->source['owner_context'] ?? []) !== $this->files->digest($batch->owner_context)) {
                $this->invalid('The accepted source evidence changed. Review again.');
            }
        }
    }

    private function currentReview(CatalogAssetBatch $batch, int $version): void
    {
        if ($batch->preview_version !== $version || $batch->build_position !== $batch->scope_count || ! $batch->expires_at?->isFuture() || $batch->items()->count() !== $batch->scope_count) {
            $this->invalid('Complete a current, unexpired review before accepting an Apply.');
        }
    }

    private function noActive(CatalogAssetBatch $batch): void
    {
        if ($batch->active_acceptance_id !== null) {
            $this->invalid('Finish the active Apply before accepting or retrying another subset.');
        }
    }

    private function independent(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Image Apply operations must own their commits.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['apply' => $message]);
    }
}
