<?php

namespace App\Filament\Resources;

use App\Actions\ReviewCatalogAssets;
use App\Services\CatalogAssetReviewFiles;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait InteractsWithCatalogImageReview
{
    #[Locked]
    public ?array $imageReviewLaunch = null;

    public function openImageReview(string $operation, array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        if ($this->imageReviewLaunch !== null || ! in_array($operation, ['one_image', 'folder_matches'], true) || ($operation === 'folder_matches' && $this->imageEntity() !== 'product')) {
            throw ValidationException::withMessages(['review' => 'Choose an available image operation.']);
        }
        $ids = array_map('intval', $this->selectedTableRecords);
        $frozen = app(ReviewCatalogAssets::class)->freeze(auth()->user(), $this->imageTarget, $this->imageOwnerContext, $ids, $this->imageSort());
        $launch = ['session' => $frozen['review_id'], 'token' => $frozen['token'], 'target' => $this->imageTarget, 'operation' => $operation, 'count' => $frozen['count']];
        $destination = ['kind' => 'review', 'target' => $this->imageTarget, 'launch' => $launch];
        if ($this->beforeImageBrowserTransition($destination)) {
            $this->imageReviewLaunch = $launch;
        }
    }

    public function returnToImageReview(string $reviewId, array $expectedContext): void
    {
        $this->admitImageContext($expectedContext);
        if ($this->imageReviewLaunch !== null) {
            return;
        }
        $batch = app(ReviewCatalogAssets::class)->owned(auth()->user(), $reviewId);
        if ($batch->target_type.'.main_image' !== $this->imageTarget || $batch->owner_context !== $this->imageOwnerContext) {
            throw ValidationException::withMessages(['review' => 'Switch to the matching record type before returning to this review.']);
        }
        $token = app(CatalogAssetReviewFiles::class)->launchToken(auth()->user(), $batch->id, ['path' => $batch->scope_path, 'sha256' => $batch->scope_sha256]);
        app(CatalogAssetReviewFiles::class)->openLaunch(auth()->user(), $token);
        $launch = ['session' => $batch->id, 'token' => $token, 'target' => $this->imageTarget, 'operation' => $batch->operation, 'count' => $batch->scope_count];
        if ($this->beforeImageBrowserTransition(['kind' => 'review', 'target' => $this->imageTarget, 'launch' => $launch, 'return' => true])) {
            $this->imageReviewLaunch = $launch;
        }
    }

    protected function imageReviewDestinationValid(array $destination): bool
    {
        $scope = app(CatalogAssetReviewFiles::class)->openLaunch(auth()->user(), $destination['launch']['token']);
        if ($destination['return'] ?? false) {
            $batch = app(ReviewCatalogAssets::class)->owned(auth()->user(), $destination['launch']['session']);

            return $batch->target_type.'.main_image' === $this->imageTarget && $batch->owner_context === $this->imageOwnerContext;
        }
        $checks = array_map('intval', $this->selectedTableRecords);
        sort($checks);
        $frozen = $scope['record_ids'];
        sort($frozen);

        return $destination['target'] === $this->imageTarget && $scope['owner_context'] === $this->imageOwnerContext && $checks === $frozen;
    }

    public function closeImageReview(string $session, string $host): void
    {
        $this->authorizeImageBrowser();
        if ($host !== $this->getId() || $session !== ($this->imageReviewLaunch['session'] ?? null)) {
            throw ValidationException::withMessages(['review' => 'This image review is no longer open.']);
        }
        $this->imageReviewLaunch = null;
    }
}
