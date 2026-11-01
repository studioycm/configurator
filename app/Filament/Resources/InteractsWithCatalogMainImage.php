<?php

namespace App\Filament\Resources;

use App\DTO\CatalogAssetChange;
use App\Models\CatalogAssetImportSource;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogAssetAssignments;
use App\Services\CatalogAssetDisplay;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetTargets;
use App\Services\CatalogMainImageEdits;
use App\Services\ProductEditorData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Adapts native and image-only forms to the same staged main-image contract.
 * Hosts supply context/current projection/save methods; data.image_upload alone
 * is temporary transport. Source and preparation services retain all file rules.
 */
trait InteractsWithCatalogMainImage
{
    public ?string $catalogMainImageReceipt = null;

    #[Locked]
    public ?string $catalogMainImageCreationSession = null;

    #[Locked]
    public ?string $catalogMainImageOriginal = null;

    /** @var array{operation: string, source_token: null, draft_intent_id: string}|null */
    #[Locked]
    public ?array $catalogMainImageBaselineIntent = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $catalogMainImageUploadBinding = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $catalogMainImageSourceProgress = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $catalogMainImageProposed = null;

    public bool $catalogMainImageFinderOpen = false;

    public string $catalogMainImageFilename = '';

    public string $catalogMainImageRecoverySourceId = '';

    public int $catalogMainImageCandidatePage = 1;

    public bool $catalogMainImageCaseSensitive = false;

    public bool $catalogMainImagePartialMatch = false;

    protected ?CatalogAssetChange $catalogMainImageResolvedChange = null;

    protected bool $catalogMainImageWriting = false;

    public function hasDatabaseTransactions(): bool
    {
        return $this->catalogMainImageWriting || parent::hasDatabaseTransactions();
    }

    protected function beginCatalogMainImageWrite(): void
    {
        if ($this->catalogMainImageEnabled() && $this->catalogMainImageHasChanges()) {
            if ($this->getRecord()?->getConnection()->transactionLevel() > 0 || DB::transactionLevel() > 0) {
                throw new \LogicException('Native image saves must own their database commit.');
            }
            $this->catalogMainImageWriting = true;
        }
    }

    protected function catalogMainImageCompletion(callable $complete): void
    {
        if (! $this->catalogMainImageWriting) {
            $complete();

            return;
        }
        $recordId = $this->getRecord()->getKey();
        $session = $this->catalogMainImageCreationSession;
        $hash = app(CatalogMainImageEdits::class)->draftHash($this->data ?? []);
        $this->getRecord()->getConnection()->afterCommit(function () use ($complete, $recordId, $session, $hash): void {
            if ($this->getRecord()?->getKey() === $recordId && $this->catalogMainImageCreationSession === $session
                && hash_equals($hash, app(CatalogMainImageEdits::class)->draftHash($this->data ?? []))) {
                $complete();
            }
        });
    }

    public function catalogMainImageEnabled(): bool
    {
        return (bool) config('catalog-assets.enabled');
    }

    protected function beforeFill(): void
    {
        if (! $this->catalogMainImageEnabled()) {
            return;
        }
        $this->catalogMainImageBaselineIntent = null;
        $record = $this->getRecord();
        if ($record === null) {
            $this->catalogMainImageCreationSession = (string) Str::ulid();
        } else {
            $this->catalogMainImageOriginal = app(CatalogAssetAssignments::class)->fingerprint($record);
        }
        $this->catalogMainImageReceipt = null;
        $this->catalogMainImageResolvedChange = null;
        $this->catalogMainImageUploadBinding = null;
        $this->catalogMainImageProposed = null;
        $this->catalogMainImageSourceProgress = null;
    }

    /** @return array{operation: string, source_token: null, draft_intent_id: string} */
    public function catalogMainImageInitialIntent(): array
    {
        return $this->catalogMainImageBaselineIntent ??= ['operation' => 'keep', 'source_token' => null, 'draft_intent_id' => (string) Str::ulid()];
    }

    public function resetCatalogMainImageDraft(bool $newContext = false): void
    {
        if ($newContext) {
            $this->catalogMainImageBaselineIntent = null;
        }
        $this->data ??= [];
        $this->data['image_intent'] = $this->catalogMainImageInitialIntent();
        $this->data['image_upload'] = [];
        $this->catalogMainImageReceipt = null;
        $this->catalogMainImageResolvedChange = null;
        $this->catalogMainImageUploadBinding = null;
        $this->catalogMainImageProposed = null;
        $this->catalogMainImageSourceProgress = null;
        $this->catalogMainImageFinderOpen = false;
        $this->catalogMainImageFilename = '';
        $this->catalogMainImageRecoverySourceId = '';
        $this->catalogMainImageCandidatePage = 1;
    }

    public function catalogMainImageHasChanges(): bool
    {
        return ($this->data['image_intent']['operation'] ?? 'keep') !== 'keep' || filled($this->data['image_upload'] ?? []) || $this->catalogMainImageUploadBinding !== null;
    }

    /** @return array<string, mixed> */
    protected function catalogMainImageContext(): array
    {
        $record = $this->getRecord();
        $product = $record instanceof Product;
        $owner = isset($this->listKey, $this->parentId) ? ['list_key' => $this->listKey, 'parent_id' => $this->parentId] : [];

        return ['target_key' => $product ? 'product.main_image' : 'group.main_image',
            'record_id' => $record === null ? null : (int) $record->getKey(),
            'creation_session' => $record === null ? $this->catalogMainImageCreationSession : null,
            'owner_context' => $owner,
            'expected_fingerprint' => $record === null ? null : ($product ? $this->batchEditorRecordOriginal : $this->catalogMainImageOriginal),
            'expected_group_fingerprint' => $product ? $this->productGroupOriginal : null];
    }

    /** @return array<string, mixed>|null */
    protected function catalogMainImageCurrent(): ?array
    {
        $id = $this->getRecord()?->main_image_id;

        return $id === null ? null : (app(CatalogAssetDisplay::class)->forIds([(int) $id])[(int) $id] ?? null);
    }

    /** @return list<string> */
    protected function catalogMainImageSaveMethods(): array
    {
        return [$this->getRecord() === null ? 'create' : 'save'];
    }

    /** @return array{methods: list<string>} */
    public function catalogMainImageEditorConfig(): array
    {
        return ['methods' => $this->catalogMainImageSaveMethods()];
    }

    protected function authorizeCatalogMainImage(): User
    {
        abort_unless($this->catalogMainImageEnabled() && auth()->user() instanceof User, 403);
        $actor = auth()->user();
        Gate::forUser($actor)->authorize('manage-catalog');
        $context = $this->catalogMainImageContext();
        $query = app(CatalogAssetTargets::class)->eligibleQuery($actor, $context['target_key'], $context['owner_context']);
        if ($context['record_id'] !== null) {
            abort_unless($query->whereKey($context['record_id'])->exists(), 404);
        } else {
            abort_unless($context['target_key'] === 'group.main_image' && is_string($context['creation_session']) && Str::isUlid($context['creation_session']), 403);
        }

        return $actor;
    }

    /** @return array<string, mixed> */
    public function prepareImageSave(): array
    {
        $actor = $this->authorizeCatalogMainImage();
        $context = $this->catalogMainImageContext();
        if ($this->catalogMainImageUploadBinding !== null && $this->catalogMainImageUploadBinding['temporary_reference'] === null) {
            throw ValidationException::withMessages(['data.image_intent' => 'Wait for the image upload to finish or revert the image change before saving.']);
        }
        if (filled($this->data['image_upload'] ?? [])) {
            throw ValidationException::withMessages(['data.image_intent' => 'Wait for the uploaded image scan to finish before saving.']);
        }
        $this->form->validate();
        $this->catalogMainImageReceipt = null;
        try {
            $result = app(CatalogMainImageEdits::class)->prepare($actor, $context, $this->data['image_intent'] ?? [], app(CatalogMainImageEdits::class)->draftHash($this->data ?? []));
        } catch (ValidationException $exception) {
            $this->markCatalogMainImageStale();
            throw ValidationException::withMessages(['data.image_intent' => $exception->validator->errors()->first()]);
        }
        $this->catalogMainImageReceipt = $result['receipt'] ?? null;

        return $result;
    }

    protected function beforeValidate(): void
    {
        if ($this->catalogMainImageEnabled() && $this->catalogMainImageHasChanges()) {
            if (! $this->catalogMainImageWriting || ! is_string($this->catalogMainImageReceipt) || $this->catalogMainImageReceipt === '') {
                throw ValidationException::withMessages(['data.image_intent' => 'Prepare this image draft through its native Save action.']);
            }
        }
    }

    protected function resolveCatalogMainImageChange(): CatalogAssetChange
    {
        $actor = $this->authorizeCatalogMainImage();
        if (! is_string($this->catalogMainImageReceipt) || $this->catalogMainImageReceipt === '') {
            throw ValidationException::withMessages(['image_intent' => 'Prepare this image draft before saving.']);
        }
        try {
            return app(CatalogMainImageEdits::class)->resolve($actor, $this->catalogMainImageContext(), $this->data['image_intent'] ?? [], app(CatalogMainImageEdits::class)->draftHash($this->data ?? []), $this->catalogMainImageReceipt ?? '');
        } catch (ValidationException $exception) {
            $this->markCatalogMainImageStale();
            throw ValidationException::withMessages(['image_intent' => $exception->validator->errors()->first()]);
        }
    }

    protected function markCatalogMainImageStale(): void
    {
        if (! property_exists($this, 'batchEditorStale') || ! method_exists($this, 'getRecord') || $this->getRecord() === null) {
            return;
        }
        $context = $this->catalogMainImageContext();
        $fresh = $this->getRecord()->fresh();
        if ($fresh === null || CatalogAssetAssignments::fingerprint($fresh) !== $context['expected_fingerprint']
            || ($fresh instanceof Product && $context['expected_group_fingerprint'] !== null && ProductEditorData::groupFingerprint($fresh) !== $context['expected_group_fingerprint'])) {
            $this->batchEditorStale = true;
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function stripCatalogMainImageTransport(array $data): array
    {
        unset($data['image_intent'], $data['image_upload']);

        return $data;
    }

    /** @return array{outcome: string, message: string} */
    public function reconcileImageSave(): array
    {
        $this->authorizeCatalogMainImage();

        return ['outcome' => 'review_required', 'message' => 'The Save response is unknown. Check the saved record before retrying. Group creation will not be replayed.'];
    }

    public function catalogMainImageRemove(): void
    {
        $this->authorizeCatalogMainImage();
        $this->data['image_intent'] = ['operation' => 'remove', 'source_token' => null, 'draft_intent_id' => (string) Str::ulid()];
        $this->data['image_upload'] = [];
        $this->catalogMainImageReceipt = null;
        $this->catalogMainImageUploadBinding = null;
        $this->catalogMainImageProposed = null;
    }

    public function catalogMainImageRevert(): void
    {
        $this->authorizeCatalogMainImage();
        $this->resetCatalogMainImageDraft();
        $this->resetValidation('data.image_intent');
        $this->dispatch('catalog-image-draft-discarded', hostId: $this->getId())->self();
    }

    #[Renderless]
    public function _startUpload(mixed $name, mixed $fileInfo, mixed $isMultiple): void
    {
        if ($this->catalogMainImageEnabled() && is_string($name) && preg_match('/^data\.image_upload\.[a-zA-Z0-9-]+$/D', $name)) {
            $actor = $this->authorizeCatalogMainImage();
            abort_unless(! $isMultiple && is_array($fileInfo) && count($fileInfo) === 1, 422);
            $nonce = (string) Str::ulid();
            $expires = now()->addMinutes(FileUploadConfiguration::maxUploadTime());
            $pending = session()->get('catalog_main_image_uploads', []);
            $pending = is_array($pending) ? array_filter($pending, fn (mixed $ticket): bool => is_array($ticket) && ($ticket['expires_at'] ?? 0) >= now()->timestamp) : [];
            $pending = array_slice($pending, -31, null, true);
            $ticket = ['actor_id' => $actor->id, 'context_hash' => $this->catalogMainImageContextHash(), 'context' => $this->catalogMainImageContext(),
                'host_id' => $this->getId(), 'property' => $name, 'entry_id' => (string) Str::ulid(), 'expires_at' => $expires->timestamp, 'reference_hash' => null];
            $pending[$nonce] = $ticket;
            session()->put('catalog_main_image_uploads', $pending);
            $this->catalogMainImageUploadBinding = ['nonce' => $nonce, ...Arr::only($ticket, ['actor_id', 'context_hash', 'host_id', 'property', 'entry_id']), 'temporary_reference' => null, 'source_id' => null];
            $this->data['image_intent']['draft_intent_id'] = (string) Str::ulid();
            $this->catalogMainImageReceipt = null;
            $relative = URL::temporarySignedRoute('catalog.images.native-upload', $expires, ['nonce' => $nonce], false);
            $this->dispatch('upload:generatedSignedUrl', name: $name, url: URL::to($relative))->self();

            return;
        }
        parent::_startUpload($name, $fileInfo, $isMultiple);
    }

    public function _finishUpload(mixed $name, mixed $tmpPath, mixed $isMultiple, mixed $append = true): void
    {
        $image = $this->catalogMainImageEnabled() && is_string($name) && preg_match('/^data\.image_upload\.[a-zA-Z0-9-]+$/D', $name);
        if ($image) {
            $this->assertCatalogMainImageUploadBinding();
            abort_unless($this->catalogMainImageUploadBinding['property'] === $name, 403, 'The upload callback belongs to a different property.');
            abort_unless(! $isMultiple && is_array($tmpPath) && count($tmpPath) === 1 && is_string($tmpPath[0]), 422);
            $path = TemporaryUploadedFile::extractPathFromSignedPath($tmpPath[0]);
            abort_unless(is_string($path) && $path !== '' && basename($path) === $path, 403, 'The temporary upload signature is invalid.');
            $ticket = session()->get('catalog_main_image_uploads.'.$this->catalogMainImageUploadBinding['nonce']);
            abort_unless(is_array($ticket) && $ticket['actor_id'] === auth()->id() && $ticket['host_id'] === $this->getId()
                && $ticket['property'] === $name && $ticket['entry_id'] === $this->catalogMainImageUploadBinding['entry_id']
                && $ticket['context_hash'] === $this->catalogMainImageContextHash() && $ticket['expires_at'] >= now()->timestamp
                && is_string($ticket['reference_hash']) && hash_equals($ticket['reference_hash'], hash('sha256', $tmpPath[0])), 403, 'The upload response does not match its issued editor ticket.');
        }
        parent::_finishUpload($name, $tmpPath, $isMultiple, $append);
        if ($image) {
            $file = $this->catalogMainImageTemporaryFile();
            abort_unless($file !== null && hash_equals($path, $file->getFilename()), 403, 'The temporary upload is not present on its expected field.');
            $this->catalogMainImageUploadBinding['temporary_reference'] = $tmpPath[0];
            $this->catalogMainImageReceipt = null;
            $this->catalogMainImageStageUpload();
            $this->dispatch('catalog-image-source-started', hostId: $this->getId())->self();
        }
    }

    protected function catalogMainImageContextHash(): string
    {
        return app(CatalogMainImageEdits::class)->draftHash($this->catalogMainImageContext());
    }

    protected function assertCatalogMainImageUploadBinding(): void
    {
        $actor = $this->authorizeCatalogMainImage();
        abort_unless($this->catalogMainImageUploadBinding !== null && $this->catalogMainImageUploadBinding['actor_id'] === $actor->id
            && hash_equals($this->catalogMainImageUploadBinding['context_hash'], $this->catalogMainImageContextHash()), 403, 'This image upload belongs to a different actor or editor context.');
    }

    protected function catalogMainImageTemporaryFile(): ?TemporaryUploadedFile
    {
        $files = Arr::wrap($this->data['image_upload'] ?? []);
        $files = array_values(array_filter($files, fn (mixed $file): bool => $file instanceof TemporaryUploadedFile));

        return count($files) === 1 ? $files[0] : null;
    }

    /** @return array<string, mixed> */
    public function catalogMainImageStageUpload(): array
    {
        $this->assertCatalogMainImageUploadBinding();
        $actor = $this->authorizeCatalogMainImage();
        $file = $this->catalogMainImageTemporaryFile();
        $reference = $this->catalogMainImageUploadBinding['temporary_reference'];
        abort_unless($file !== null && is_string($reference) && TemporaryUploadedFile::extractPathFromSignedPath($reference) === $file->getFilename(), 403);
        $this->validateOnly('data.image_upload');
        $context = $this->catalogMainImageContext();
        $sources = app(CatalogAssetSources::class);
        $this->catalogMainImageFinderOpen = true;
        if ($this->catalogMainImageUploadBinding['source_id'] === null) {
            $source = $sources->startUpload($actor, $context['target_key'], $context['owner_context'], 'Individual image upload');
            $this->catalogMainImageUploadBinding['source_id'] = $source->id;
        }
        $sourceId = $this->catalogMainImageUploadBinding['source_id'];
        $source = $this->catalogMainImageScopedSource($actor, $sourceId, $context);
        if (($this->catalogMainImageSourceProgress['source_id'] ?? null) === $sourceId) {
            abort_unless($source->scan_version === $this->catalogMainImageSourceProgress['scan_version'], 409);
        }
        $entryId = $this->catalogMainImageUploadBinding['entry_id'];
        $progress = $sources->reserveUpload($actor, $sourceId, $entryId, ['name' => $file->getClientOriginalName(), 'relative_path' => $file->getClientOriginalName(), 'bytes' => $file->getSize()]);
        $progress = $sources->commitUpload($actor, $sourceId, $entryId, $reference);
        $this->catalogMainImageSourceProgress = $progress;
        $this->catalogMainImageRecoverySourceId = $progress['source_id'];
        $this->proposeCompletedCatalogMainImageUpload();
        if (in_array($progress['outcome'], ['busy', 'paused'], true) || $progress['unsettled_uploads'] !== 0 || $progress['stage'] !== 'uploading') {
            return $progress;
        }
        $progress = $sources->sealUploads($actor, $sourceId, $progress['revision']);
        $source = $sources->startScan($actor, $sourceId, $context['target_key'], $context['owner_context'], false);
        $this->catalogMainImageSourceProgress = $sources->status($actor, $source->id);
        $this->catalogMainImageFinderOpen = true;
        $this->resetValidation('data.image_intent');

        return $this->catalogMainImageSourceProgress;
    }

    public function catalogMainImageFind(): void
    {
        $this->authorizeCatalogMainImage();
        $this->catalogMainImageFinderOpen = ! $this->catalogMainImageFinderOpen;
    }

    public function catalogMainImageUseReference(): void
    {
        $this->authorizeCatalogMainImage();
        $reference = $this->catalogMainImageViewState()['reference'];
        if (is_string($reference)) {
            $this->catalogMainImageFilename = mb_substr(pathinfo(str_replace('\\', '/', $reference), PATHINFO_FILENAME), 0, 250);
        }
        $this->catalogMainImageFinderOpen = true;
        $this->catalogMainImageCandidatePage = 1;
    }

    public function catalogMainImageCandidatePage(int $step): void
    {
        $this->authorizeCatalogMainImage();
        abort_unless(in_array($step, [-1, 1], true), 422);
        $this->catalogMainImageCandidatePage = max(1, min(1000, $this->catalogMainImageCandidatePage + $step));
    }

    /** @return array<string, string> */
    public function catalogMainImageSourceOptions(): array
    {
        $actor = $this->authorizeCatalogMainImage();
        $context = $this->catalogMainImageContext();
        $options = [];
        foreach (config('catalog-assets.sources', []) as $alias => $source) {
            if (is_string($alias) && is_array($source) && is_string($source['label'] ?? null)) {
                $options['alias:'.$alias] = $source['label'].' (scan source)';
            }
        }
        $ownerContext = $context['owner_context'];
        ksort($ownerContext);
        $sources = $this->catalogMainImageSourcesQuery($actor, $context)->latest()->orderByDesc('id')->limit(50)->get(['id', 'source', 'status', 'scan_version']);
        foreach ($sources as $source) {
            $sourceContext = $source->source['owner_context'] ?? null;
            if (is_array($sourceContext)) {
                ksort($sourceContext);
            }
            if (($source->source['target_key'] ?? null) === $context['target_key'] && $sourceContext === $ownerContext) {
                $options['source:'.$source->id] = $source->source['label'].' · '.$source->status.' · v'.$source->scan_version.' · '.substr($source->id, -6);
            }
        }

        return $options;
    }

    /** @param array<string, mixed> $context
     * @return Builder<CatalogAssetImportSource>
     */
    protected function catalogMainImageSourcesQuery(User $actor, array $context): Builder
    {
        $ownerContext = $context['owner_context'];
        ksort($ownerContext);
        $query = CatalogAssetImportSource::query()->where('actor_id', $actor->id)
            ->where('source->target_key', $context['target_key'])->whereNotNull('source->owner_context');
        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $query->whereRaw('(SELECT COUNT(*) FROM json_each(source, ?)) = ?', ['$.owner_context', count($ownerContext)]);
        } else {
            $query->whereJsonLength('source->owner_context', count($ownerContext));
        }
        if ($ownerContext !== []) {
            $query->where('source->owner_context->list_key', $ownerContext['list_key'])
                ->where('source->owner_context->parent_id', $ownerContext['parent_id']);
        }

        return $query;
    }

    /** @param array<string, mixed> $context */
    protected function catalogMainImageScopedSource(User $actor, string $sourceId, array $context): CatalogAssetImportSource
    {
        abort_unless(Str::isUlid($sourceId), 404);
        $source = $this->catalogMainImageSourcesQuery($actor, $context)->findOrFail($sourceId);
        $expected = $context['owner_context'];
        $actual = $source->source['owner_context'] ?? null;
        ksort($expected);
        if (is_array($actual)) {
            ksort($actual);
        }
        abort_unless(($source->source['target_key'] ?? null) === $context['target_key'] && $actual === $expected, 404);

        return $source;
    }

    /** @return array<string, mixed> */
    public function catalogMainImageChooseSource(string $choice): array
    {
        $actor = $this->authorizeCatalogMainImage();
        $context = $this->catalogMainImageContext();
        $sources = app(CatalogAssetSources::class);
        if (str_starts_with($choice, 'alias:')) {
            abort_unless(array_key_exists($choice, $this->catalogMainImageSourceOptions()), 404);
            $source = $sources->startScan($actor, substr($choice, 6), $context['target_key'], $context['owner_context'], false);
            $progress = $sources->status($actor, $source->id);
        } else {
            abort_unless(str_starts_with($choice, 'source:'), 404);
            $source = $this->catalogMainImageScopedSource($actor, substr($choice, 7), $context);
            $progress = $sources->status($actor, $source->id);
        }
        $this->catalogMainImageRecoverySourceId = $source->id;
        $this->catalogMainImageFinderOpen = true;
        $this->catalogMainImageUploadBinding = null;
        $this->catalogMainImageReceipt = null;
        $this->catalogMainImageSourceProgress = $progress;
        $this->catalogMainImageCandidatePage = 1;

        return $progress;
    }

    /** @return array<string, mixed> */
    public function catalogMainImageSourceStatus(): array
    {
        $actor = $this->authorizeCatalogMainImage();
        abort_unless($this->catalogMainImageSourceProgress !== null, 404);
        $selected = $this->catalogMainImageSourceProgress;
        $source = $this->catalogMainImageScopedSource($actor, $selected['source_id'], $this->catalogMainImageContext());
        abort_unless($source->scan_version === $selected['scan_version'], 409, 'This source version changed. Review its stable identity before selecting it again.');
        $progress = app(CatalogAssetSources::class)->status($actor, $source->id);
        abort_unless($progress['scan_version'] === $selected['scan_version'], 409);
        $this->catalogMainImageSourceProgress = $progress;
        $this->proposeCompletedCatalogMainImageUpload();

        return $this->catalogMainImageSourceProgress;
    }

    /** @return array<string, mixed> */
    public function catalogMainImageAdvanceSource(): array
    {
        $actor = $this->authorizeCatalogMainImage();
        $progress = $this->catalogMainImageSourceStatus();
        $sources = app(CatalogAssetSources::class);
        if ($progress['stage'] === 'uploading' && $progress['file_count'] > 0 && $progress['unsettled_uploads'] === 0) {
            $this->catalogMainImageSourceProgress = $sources->sealUploads($actor, $progress['source_id'], $progress['revision']);

            return $this->catalogMainImageSourceProgress;
        }
        if ($progress['stage'] !== 'scanning' || $progress['outcome'] === 'paused') {
            return $progress;
        }
        $this->catalogMainImageSourceProgress = $progress['traversal_complete']
            ? $sources->finalize($actor, $progress['source_id'], $progress['scan_version'], $progress['revision'])
            : $sources->scanNext($actor, $progress['source_id'], $progress['scan_version'], $progress['revision']);
        $this->proposeCompletedCatalogMainImageUpload();

        return $this->catalogMainImageSourceProgress;
    }

    protected function proposeCompletedCatalogMainImageUpload(): void
    {
        $sourceId = $this->catalogMainImageUploadBinding['source_id'] ?? null;
        if ($sourceId === null || ($this->catalogMainImageSourceProgress['stage'] ?? null) !== 'ready'
            || $sourceId !== $this->catalogMainImageSourceProgress['source_id']) {
            return;
        }
        $entries = $this->catalogMainImageManifestEntries();
        if (count($entries) === 1 && $entries[0]['status'] === 'valid') {
            $this->catalogMainImageSelectCandidate($entries[0]['entry_id']);
        }
    }

    /** @return list<array<string, mixed>> */
    protected function catalogMainImageManifestEntries(): array
    {
        $actor = $this->authorizeCatalogMainImage();
        $progress = $this->catalogMainImageSourceProgress;
        if ($progress === null || $progress['stage'] !== 'ready') {
            return [];
        }
        $manifest = app(CatalogAssetSources::class)->manifest($actor, $progress['source_id'], $progress['scan_version']);
        $context = $this->catalogMainImageContext();
        abort_unless($manifest['target_key'] === $context['target_key'] && $manifest['owner_context'] == $context['owner_context'], 404);

        return $manifest['entries'];
    }

    /** @return array{entries: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function updatedCatalogMainImageFilename(): void
    {
        $this->catalogMainImageCandidatePage = 1;
    }

    public function updatedCatalogMainImageCaseSensitive(): void
    {
        $this->catalogMainImageCandidatePage = 1;
    }

    public function updatedCatalogMainImagePartialMatch(): void
    {
        $this->catalogMainImageCandidatePage = 1;
    }

    public function catalogMainImageCandidates(): array
    {
        $this->validate(['catalogMainImageFilename' => ['string', 'max:250'], 'catalogMainImageCandidatePage' => ['integer', 'min:1', 'max:1000']]);
        $needle = $this->catalogMainImageCaseSensitive ? $this->catalogMainImageFilename : mb_strtolower($this->catalogMainImageFilename);
        $entries = array_values(array_filter($this->catalogMainImageManifestEntries(), function (array $entry) use ($needle): bool {
            $stem = pathinfo($entry['name'], PATHINFO_FILENAME);
            $stem = $this->catalogMainImageCaseSensitive ? $stem : mb_strtolower($stem);

            return $entry['status'] === 'valid' && ($needle === '' || ($this->catalogMainImagePartialMatch ? str_contains($stem, $needle) : $stem === $needle));
        }));
        $page = min(max(1, (int) ceil(count($entries) / 12)), $this->catalogMainImageCandidatePage);

        return ['entries' => array_map(fn (array $entry): array => $this->catalogMainImageCandidate($entry), array_slice($entries, ($page - 1) * 12, 12)), 'total' => count($entries), 'page' => $page, 'pages' => max(1, (int) ceil(count($entries) / 12))];
    }

    /** @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    protected function catalogMainImageCandidate(array $entry): array
    {
        $progress = $this->catalogMainImageSourceProgress;
        $url = URL::temporarySignedRoute('catalog.images.source-preview', now()->addMinutes(10), ['source' => $progress['source_id'], 'version' => $progress['scan_version'], 'entry' => $entry['entry_id']], false);

        return ['entry_id' => $entry['entry_id'], 'filename' => $entry['name'], 'url' => $url, 'thumbnail_url' => $url,
            ...Arr::only($entry, ['width', 'height', 'mime_type', 'bytes']), 'warnings' => []];
    }

    public function catalogMainImageSelectCandidate(string $entryId): void
    {
        $actor = $this->authorizeCatalogMainImage();
        $entry = collect($this->catalogMainImageManifestEntries())->first(fn (array $entry): bool => $entry['entry_id'] === $entryId && $entry['status'] === 'valid');
        abort_unless(is_array($entry), 404);
        $progress = $this->catalogMainImageSourceProgress;
        $this->data['image_intent'] = app(CatalogMainImageEdits::class)->stageSource($actor, $this->catalogMainImageContext(), ['source_id' => $progress['source_id'], 'scan_version' => $progress['scan_version'], 'entry_id' => $entry['entry_id'], 'sha256' => $entry['sha256']]);
        $this->data['image_upload'] = [];
        $this->catalogMainImageReceipt = null;
        $this->catalogMainImageUploadBinding = null;
        $this->catalogMainImageProposed = $this->catalogMainImageCandidate($entry);
        $this->catalogMainImageFinderOpen = false;
        $this->resetValidation('data.image_intent');
    }

    protected function catalogMainImageReference(): ?string
    {
        $reference = method_exists($this, 'getRecord') && $this->getRecord() instanceof Product ? ($this->getRecord()->profile_fields['product_pic'] ?? null) : null;

        return is_string($reference) ? $reference : null;
    }

    /** @return array{current: ?array, proposed: ?array, operation: string, reference: ?string} */
    public function catalogMainImageViewState(): array
    {
        $this->authorizeCatalogMainImage();
        $reference = $this->catalogMainImageReference();

        return ['current' => $this->catalogMainImageCurrent(), 'proposed' => $this->catalogMainImageProposed,
            'operation' => $this->data['image_intent']['operation'] ?? 'keep', 'reference' => is_string($reference) ? $reference : null];
    }
}
