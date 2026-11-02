<?php

namespace App\Filament\Resources;

use App\Actions\ApplyCatalogAssets;
use App\Actions\ProcessNextCatalogAssetBatchItem;
use App\Actions\ReviewCatalogAssets;
use App\Models\CatalogAssetBatch;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetReviewQuery;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait InteractsWithCatalogAssetApplying
{
    #[Locked]
    public array $applyProgress = [];

    public function applyContext(): array
    {
        return [...$this->reviewContext(), 'pending' => $this->applyProgress['pending_intent']['id'] ?? null, 'acceptance' => $this->applyProgress['acceptance_id'] ?? null];
    }

    protected function admitApply(array $expected): void
    {
        $review = array_diff_key($expected, ['pending' => true, 'acceptance' => true]);
        $this->admit($review);
        $this->requireBatch();
        if ($expected !== $this->applyContext()) {
            throw ValidationException::withMessages(['apply' => 'This Apply changed. Refresh status before continuing.']);
        }
    }

    protected function syncImageApply(?string $acceptanceId = null): array
    {
        $this->savedImages = null;
        $this->currentImages = null;

        $this->applyProgress = app(CatalogAssetReviewQuery::class)->applyProgress($this->actor(), $this->batchId, $acceptanceId);
        if ($this->applyProgress['accepted']) {
            $this->reviewProgress = app(ReviewCatalogAssets::class)->status($this->actor(), $this->batchId);
        }

        return $this->applyProgress;
    }

    protected function frozenImageMeaning(): CatalogAssetBatch
    {
        $this->requireSource();
        $batch = app(ReviewCatalogAssets::class)->owned($this->actor(), $this->batchId);
        $files = app(CatalogAssetReviewFiles::class);
        $evidence = app(ReviewCatalogAssets::class)->reviewEvidence($batch);
        if ($this->sourceId !== $batch->source_id || $this->sourceVersion !== $batch->source_version || ($this->recipeData['mode'] ?? null) !== $batch->recipe['mode'] || $files->digest($this->parameters()) !== $files->digest($batch->recipe['parameters']) || ($this->operation === 'one_image' && $files->digest($this->oneImageChoice ?? []) !== $files->digest($evidence['one_image']['choice'] ?? []))) {
            throw ValidationException::withMessages(['apply' => 'Preview these changes before applying.']);
        }

        return $batch;
    }

    public function prepareImageApply(array $itemIds, array $expected): array
    {
        $this->admitApply($expected);
        $this->frozenImageMeaning();
        app(ApplyCatalogAssets::class)->prepareIntent($this->actor(), $this->batchId, $this->previewVersion, $itemIds);

        return $this->syncImageApply();
    }

    public function applyImages(string $intentId, array $expected): array
    {
        $this->admitApply($expected);
        $this->frozenImageMeaning();
        app(ApplyCatalogAssets::class)->handle($this->actor(), $this->batchId, $intentId);
        $this->selectedTableRecords = [];

        return $this->syncImageApply($intentId);
    }

    public function processNextImageAssignment(string $requestStepId, array $expected): array
    {
        $this->admitApply($expected);
        $acceptance = $this->applyProgress['acceptance_id'] ?? null;
        abort_unless(is_string($acceptance), 404);
        $result = app(ProcessNextCatalogAssetBatchItem::class)->handle($this->actor(), $this->batchId, $acceptance, $requestStepId);
        $this->syncImageApply($acceptance);
        if ($result['changed_record_ids'] !== []) {
            $this->dispatch('catalog-batch-applied', model: $result['model'], ids: $result['changed_record_ids']);
        }

        return $result;
    }

    public function continueImageApply(array $expected): array
    {
        $this->admitApply($expected);
        app(ApplyCatalogAssets::class)->continueAcceptance($this->actor(), $this->batchId, $this->applyProgress['acceptance_id'] ?? '');

        return $this->syncImageApply();
    }

    public function retryFailedImages(array $expected): array
    {
        $this->admitApply($expected);
        app(ApplyCatalogAssets::class)->retryFailed($this->actor(), $this->batchId, $this->applyProgress['acceptance_id'] ?? '');

        return $this->syncImageApply();
    }

    public function discardImageApply(array $expected): array
    {
        $this->admitApply($expected);
        app(ApplyCatalogAssets::class)->discardPendingIntent($this->actor(), $this->batchId, $this->applyProgress['pending_intent']['id'] ?? '');

        return $this->syncImageApply();
    }

    public function selectReadyImageApply(string $mode, array $expected): void
    {
        $this->admitApply($expected);
        if (($this->applyProgress['active_acceptance_id'] ?? null) !== null || ($this->applyProgress['pending_intent'] ?? null) !== null || ! in_array($mode, ['page', 'next', 'clear'], true)) {
            throw ValidationException::withMessages(['apply' => 'Choose checks before preparing or continuing Apply.']);
        }
        if ($mode === 'clear') {
            $this->selectedTableRecords = [];

            return;
        }
        $query = app(CatalogAssetReviewQuery::class)->eligibleApplyItems($this->actor(), $this->batchId, $this->previewVersion, $this->reviewFilters());
        if ($mode === 'page') {
            $query->whereKey($this->getTableRecords()->modelKeys());
            if ((clone $query)->count() > 50) {
                throw ValidationException::withMessages(['apply' => 'This page has more than 50 ready rows. Use next 50 ready or check rows individually.']);
            }
        }
        $this->selectedTableRecords = $query->limit(50)->pluck('id')->map(fn ($id): string => (string) $id)->all();
    }

    public function startRemainingImageReview(array $expected): array
    {
        $this->admitApply($expected);
        $scope = app(ReviewCatalogAssets::class)->freezeRemaining($this->actor(), $this->batchId, $this->previewVersion);
        $this->dispatch('catalog-image-review-replaced', session: $this->launchSession, host: $this->parentHost, token: $scope['token']);

        return ['message' => 'Opening a fresh review of remaining records. Open its source and preview before applying.'];
    }

    public function discardImageApplyAction(): Action
    {
        return CatalogAssetActions::command($this, 'discardImageApply', __('catalog-images.discard_apply'), "command('discardImageApply')");
    }

    public function remainingImageReviewAction(): Action
    {
        return CatalogAssetActions::command($this, 'remainingImageReview', __('catalog-images.remaining_review'), "command('startRemainingImageReview')", 'running || unknown || !!$wire.applyProgress.active_acceptance_id || !!$wire.applyProgress.pending_intent');
    }

    public function imageApplyAction(): Action
    {
        return CatalogAssetActions::command($this, 'imageApply', __('catalog-images.apply_checked'), 'apply()', 'running || unknown || !$wire.selectedTableRecords.length || !!$wire.applyProgress.active_acceptance_id || !!$wire.applyProgress.pending_intent || $wire.applyProgress.review_expired');
    }

    public function confirmImageApplyAction(): Action
    {
        return CatalogAssetActions::command($this, 'confirmImageApply', __('catalog-images.confirm_apply'), 'confirmApply()', 'running || unknown');
    }

    public function continueImageApplyAction(): Action
    {
        return CatalogAssetActions::command($this, 'continueImageApply', __('catalog-images.continue_apply'), 'continueApply()', 'running || unknown');
    }

    public function retryImageApplyAction(): Action
    {
        return CatalogAssetActions::command($this, 'retryImageApply', __('catalog-images.retry_failed'), 'retryApply()', 'running || unknown');
    }
}
