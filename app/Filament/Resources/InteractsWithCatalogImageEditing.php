<?php

namespace App\Filament\Resources;

use App\Actions\SaveCatalogMainImage;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetRecordSequence;
use App\Services\CatalogMainImageEdits;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait InteractsWithCatalogImageEditing
{
    use InteractsWithCatalogMainImage;

    public array $data = [];

    #[Locked]
    public ?string $imageEditorSession = null;

    #[Locked]
    public ?array $imagePreparedAttempt = null;

    #[Locked]
    public ?array $imagePendingTransition = null;

    protected bool $executingWorkspaceTransition = false;

    public function getRecord(): ?Model
    {
        return $this->imageRecordId === null ? null : $this->imageEligibleQuery()->find($this->imageRecordId);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(1)->components([CatalogMainImageField::make()]);
    }

    public function hasDatabaseTransactions(): bool
    {
        return false;
    }

    protected function catalogMainImageSaveMethods(): array
    {
        return [];
    }

    protected function catalogMainImageContext(): array
    {
        abort_unless($this->imageRecordId !== null && $this->getRecord() !== null, 404);

        return ['target_key' => $this->imageTarget, 'record_id' => $this->imageRecordId, 'creation_session' => null,
            'owner_context' => $this->imageOwnerContext, 'expected_fingerprint' => $this->catalogMainImageOriginal, 'expected_group_fingerprint' => null];
    }

    public function workspaceImageEditorContext(): array
    {
        return ['session' => $this->imageEditorSession, 'target' => $this->imageTarget, 'record' => $this->imageRecordId, 'baseline' => $this->catalogMainImageOriginal];
    }

    protected function initializeWorkspaceImageEditor(): void
    {
        $this->authorizeImageBrowser();
        $this->resetCatalogMainImageDraft(true);
        $this->imagePreparedAttempt = null;
        $this->imagePendingTransition = null;
        if ($this->getRecord() === null) {
            $this->data = [];
            $this->imageEditorSession = null;
            $this->catalogMainImageOriginal = null;

            return;
        }
        $this->beforeFill();
        $this->imageEditorSession = (string) Str::ulid();
        $this->form->fill(['image_intent' => $this->catalogMainImageInitialIntent(), 'image_upload' => []]);
    }

    protected function admitWorkspaceImageEditor(array $expectedEditor, bool $requireOwner = true): void
    {
        $this->authorizeImageBrowser();
        if ($this->imageEditorSession === null || $expectedEditor !== $this->workspaceImageEditorContext()) {
            $this->invalidWorkspaceImage(__('catalog-images.editor_changed'));
        }
        if ($requireOwner) {
            abort_unless($this->getRecord() !== null, 404);
        }
        $this->resetValidation();
    }

    protected function workspaceImageMeaning(): string
    {
        $checks = array_map('intval', $this->selectedTableRecords);
        sort($checks);

        return app(CatalogMainImageEdits::class)->draftHash(['target' => $this->imageTarget, 'mode' => $this->imageNavigationMode,
            'search' => $this->imageSearch, 'sort' => $this->tableSort, 'checks' => $checks, 'exact' => $this->exactCodeSet]);
    }

    protected function beforeImageBrowserTransition(array $destination): bool
    {
        if ($this->executingWorkspaceTransition || ! $this->catalogMainImageHasChanges()) {
            return true;
        }
        $this->imagePendingTransition = ['token' => (string) Str::ulid(), 'actor' => auth()->id(), 'host' => $this->getId(),
            'editor' => $this->workspaceImageEditorContext(), 'draft_hash' => app(CatalogMainImageEdits::class)->draftHash($this->data),
            'meaning' => $this->workspaceImageMeaning(), 'destination' => $destination,
            'destination_label' => $destination['kind'] === 'target'
                ? __('catalog-images.'.($destination['target'] === 'product.main_image' ? 'products' : 'groups'))
                : (string) $this->imageEligibleQuery()->whereKey($destination['record'])->value($this->imageTarget === 'product.main_image' ? 'product_code' : 'name')];

        return false;
    }

    protected function workspacePendingTransition(?string $token): ?array
    {
        if ($token === null) {
            if ($this->imagePendingTransition !== null) {
                $this->invalidWorkspaceImage(__('catalog-images.pending_choice'));
            }

            return null;
        }
        $pending = $this->imagePendingTransition;
        if ($pending === null || $pending['token'] !== $token || $pending['actor'] !== auth()->id() || $pending['host'] !== $this->getId()
            || $pending['editor'] !== $this->workspaceImageEditorContext()
            || ! hash_equals($pending['draft_hash'], app(CatalogMainImageEdits::class)->draftHash($this->data))
            || ! hash_equals($pending['meaning'], $this->workspaceImageMeaning())) {
            $this->invalidWorkspaceImage(__('catalog-images.pending_changed'));
        }

        return $pending;
    }

    public function prepareWorkspaceImageSave(array $expectedEditor, ?string $pendingToken = null): array
    {
        $this->admitWorkspaceImageEditor($expectedEditor);
        $this->workspacePendingTransition($pendingToken);
        $this->validateWorkspaceImageData();
        $this->imagePreparedAttempt = null;
        $result = $this->prepareImageSave();
        if ($result['outcome'] === 'ready' && is_string($this->catalogMainImageReceipt)) {
            $this->imagePreparedAttempt = ['editor' => $this->workspaceImageEditorContext(), 'context' => $this->catalogMainImageContext(),
                'draft_hash' => app(CatalogMainImageEdits::class)->draftHash($this->data), 'receipt_hash' => hash('sha256', $this->catalogMainImageReceipt), 'pending_token' => $pendingToken];
        }

        return $result;
    }

    protected function validateWorkspaceImageData(): void
    {
        $this->validate(['data' => ['array:image_intent,image_upload'], 'data.image_intent' => ['required', 'array:operation,source_token,draft_intent_id']]);
    }

    protected function workspacePreparedAttempt(?string $pendingToken): array
    {
        $this->validateWorkspaceImageData();
        $attempt = $this->imagePreparedAttempt;
        if ($attempt === null || $attempt['editor'] !== $this->workspaceImageEditorContext()
            || $attempt['context'] !== $this->catalogMainImageContext() || $attempt['pending_token'] !== $pendingToken
            || ! hash_equals($attempt['draft_hash'], app(CatalogMainImageEdits::class)->draftHash($this->data))
            || ! is_string($this->catalogMainImageReceipt) || ! hash_equals($attempt['receipt_hash'], hash('sha256', $this->catalogMainImageReceipt))) {
            $this->invalidWorkspaceImage(__('catalog-images.prepare_first'));
        }

        return $attempt;
    }

    public function saveWorkspaceImage(array $expectedEditor, ?string $pendingToken = null): array
    {
        $this->admitWorkspaceImageEditor($expectedEditor);
        if (DB::transactionLevel() !== 0 || $this->getRecord()->getConnection()->transactionLevel() !== 0) {
            throw new \LogicException('Workspace image saves must own their commit.');
        }
        $pending = $this->workspacePendingTransition($pendingToken);
        $attempt = $this->workspacePreparedAttempt($pendingToken);
        $intent = $this->data['image_intent'];
        $receipt = $this->catalogMainImageReceipt;
        app(SaveCatalogMainImage::class)->handle(auth()->user(), $this->imageTarget, $this->imageRecordId, $this->imageOwnerContext,
            $this->catalogMainImageOriginal, null, fn (Model $record) => app(CatalogMainImageEdits::class)->resolve(auth()->user(), $attempt['context'], $intent, $attempt['draft_hash'], $receipt));

        return $this->completeWorkspaceImage($pending, 'committed');
    }

    public function reconcileWorkspaceImageSave(array $expectedEditor, ?string $pendingToken = null): array
    {
        $this->admitWorkspaceImageEditor($expectedEditor);
        $pending = $this->workspacePendingTransition($pendingToken);
        $attempt = $this->workspacePreparedAttempt($pendingToken);
        $result = app(CatalogMainImageEdits::class)->reconcile(auth()->user(), $attempt['context'], $this->data['image_intent'], $attempt['draft_hash'], $this->catalogMainImageReceipt);
        if ($result['outcome'] !== 'goal_matches') {
            return $result;
        }
        $fresh = $this->getRecord();
        if ($fresh === null || ! hash_equals($result['expected_fingerprint'], CatalogAssetAssignments::fingerprint($fresh))) {
            return ['outcome' => 'conflict', 'message' => __('catalog-images.editor_changed')];
        }

        return $this->completeWorkspaceImage($pending, 'goal_matches');
    }

    protected function completeWorkspaceImage(?array $pending, string $outcome): array
    {
        $owner = ['target' => $this->imageTarget, 'record' => $this->imageRecordId];
        $meaning = $this->workspaceImageMeaning();
        $this->initializeWorkspaceImageEditor();
        $this->changedImageBrowser(false);
        $destinationChanged = $pending !== null && ($pending['meaning'] !== $meaning || ! $this->workspaceDestinationValid($pending['destination']));
        if ($pending !== null && ! $destinationChanged) {
            $this->executeWorkspaceImageTransition($pending['destination']);
        }
        $message = $destinationChanged ? __('catalog-images.destination_changed') : ($outcome === 'goal_matches' ? __('catalog-images.goal_matches') : __('catalog-images.saved'));
        $this->imageBrowserNotice = $message;

        return ['outcome' => $outcome, 'destination_changed' => $destinationChanged, 'owner' => $owner, 'message' => $message];
    }

    public function resolveWorkspaceImageTransition(string $choice, string $pendingToken, array $expectedEditor): array
    {
        $this->admitWorkspaceImageEditor($expectedEditor, false);
        if ($choice === 'stay' && $this->imagePendingTransition !== null && $this->imagePendingTransition['token'] === $pendingToken) {
            $this->imagePendingTransition = null;

            return ['outcome' => 'stayed'];
        }
        if ($choice !== 'discard') {
            $this->invalidWorkspaceImage(__('catalog-images.pending_choice'));
        }
        $pending = $this->workspacePendingTransition($pendingToken);
        $this->initializeWorkspaceImageEditor();
        if (! $this->workspaceDestinationValid($pending['destination'])) {
            $this->imageBrowserNotice = __('catalog-images.destination_changed');

            return ['outcome' => 'destination_changed'];
        }
        $this->executeWorkspaceImageTransition($pending['destination']);

        return ['outcome' => 'discarded'];
    }

    protected function workspaceDestinationValid(array $destination): bool
    {
        $this->authorizeImageBrowser();
        if ($destination['kind'] === 'target') {
            return in_array($destination['target'], ['product.main_image', 'group.main_image'], true);
        }
        if ($destination['target'] !== $this->imageTarget) {
            return false;
        }
        $query = ($destination['sequence'] ?? false) ? $this->imageSequenceQuery() : $this->imageEligibleQuery();

        return $query->whereKey($destination['record'])->exists();
    }

    protected function executeWorkspaceImageTransition(array $destination): void
    {
        $this->executingWorkspaceTransition = true;
        try {
            if ($destination['kind'] === 'target') {
                $this->switchImageTarget($destination['target'], $this->imageBrowserContext());
            } else {
                $this->inspectImageRecord($destination['record'], $this->imageBrowserContext(), $destination['target']);
                $position = app(CatalogAssetRecordSequence::class)->position($this->imageResultsQuery(), $destination['record'], $this->imageSort(), (int) $this->getTableRecordsPerPage());
                if ($position !== null) {
                    $this->setPage($position['page'], $this->getTablePaginationPageName());
                }
            }
        } finally {
            $this->executingWorkspaceTransition = false;
        }
    }

    public function catalogMainImageRevert(): void
    {
        $this->authorizeImageBrowser();
        $this->initializeWorkspaceImageEditor();
        $this->resetValidation();
        $this->dispatch('catalog-image-draft-discarded', hostId: $this->getId())->self();
    }

    protected function invalidWorkspaceImage(string $message): never
    {
        throw ValidationException::withMessages(['imageEditor' => $message]);
    }
}
