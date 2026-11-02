<?php

namespace App\Livewire\Catalog;

use App\Actions\ReviewCatalogAssets;
use App\Filament\Resources\CatalogAssetActions;
use App\Filament\Resources\CatalogAssetMatchForm;
use App\Filament\Resources\CatalogAssetReviewTable;
use App\Filament\Resources\InteractsWithCatalogAssetApplying;
use App\Models\CatalogAssetBatch;
use App\Models\CatalogAssetBatchItem;
use App\Models\User;
use App\Services\CatalogAssetDisplay;
use App\Services\CatalogAssetIntake;
use App\Services\CatalogAssetRecipe;
use App\Services\CatalogAssetReviewFiles;
use App\Services\CatalogAssetReviewQuery;
use App\Services\CatalogAssetSources;
use App\Services\CatalogAssetTargets;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AssetAssignment extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithCatalogAssetApplying;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public string $launchToken;

    #[Locked]
    public string $launchSession;

    #[Locked]
    public string $operation;

    #[Locked]
    public string $parentHost;

    #[Locked]
    public string $targetKey;

    #[Locked]
    public int $recordCount;

    #[Locked]
    public ?string $sourceId = null;

    #[Locked]
    public int $sourceVersion = 0;

    #[Locked]
    public ?string $batchId = null;

    #[Locked]
    public int $previewVersion = 0;

    #[Locked]
    public ?int $reviewItemId = null;

    #[Locked]
    public ?array $oneImageChoice = null;

    #[Locked]
    public array $sourceProgress = [];

    #[Locked]
    public array $intakeProgress = [];

    #[Locked]
    public array $reviewProgress = [];

    #[Locked]
    public ?string $candidateSourceId = null;

    #[Locked]
    public int $candidateSourceVersion = 0;

    public array $recipeData = [];

    public array $candidateData = ['source_choice' => ''];

    public string $reviewSearch = '';

    public string $candidateSearch = '';

    public int $candidatePage = 1;

    public bool $allSourceFiles = false;

    public string $reviewNotice = '';

    protected ?array $savedImages = null;

    protected ?array $selectionOrigins = null;

    protected ?array $currentImages = null;

    public function mount(string $launchToken, string $launchSession, string $operation, string $parentHost): void
    {
        $this->launchToken = $launchToken;
        $this->launchSession = $launchSession;
        $this->operation = $operation;
        $this->parentHost = $parentHost;
        $scope = $this->scope();
        $this->targetKey = $scope['target_key'];
        $this->recordCount = count($scope['record_ids']);
        $savedSize = session()->get('catalog-image-review-size.'.auth()->id().'.'.$this->targetKey, 10);
        $this->tableRecordsPerPage = is_int($savedSize) && $savedSize >= 1 && $savedSize <= 500 ? $savedSize : 10;
        abort_unless($scope['review_id'] === $launchSession && in_array($operation, ['one_image', 'folder_matches'], true) && ($operation !== 'folder_matches' || $this->targetKey === 'product.main_image'), 404);
        $this->recipeData = ['source_choice' => '', 'mode' => 'fill_missing', 'parameters' => $operation === 'folder_matches' ? app(CatalogAssetRecipe::class)->defaults() : [], 'positions' => '', 'recursive_scan' => false];
        if (CatalogAssetBatch::query()->where('actor_id', auth()->id())->whereKey($launchSession)->exists()) {
            $this->restoreReview();
        }
    }

    public function hydrate(): void
    {
        $this->actor();
        $this->scope();
    }

    protected function actor(): User
    {
        abort_unless(auth()->user() instanceof User, 403);
        app(ReviewCatalogAssets::class)->authorize(auth()->user());

        return auth()->user();
    }

    protected function scope(): array
    {
        return app(CatalogAssetReviewFiles::class)->openLaunch($this->actor(), $this->launchToken);
    }

    public function canReview(): bool
    {
        $this->actor();

        return true;
    }

    public function reviewContext(): array
    {
        return ['session' => $this->launchSession, 'batch' => $this->batchId, 'version' => $this->previewVersion, 'source' => $this->sourceId, 'source_version' => $this->sourceVersion];
    }

    protected function admit(array $expected): void
    {
        $this->actor();
        $this->scope();
        if ($expected !== $this->reviewContext()) {
            throw ValidationException::withMessages(['review' => 'This review changed. Refresh its current status.']);
        }
        $this->resetValidation();
        if ($this->batchId !== null) {
            $batch = app(ReviewCatalogAssets::class)->owned($this->actor(), $this->batchId);
            if ($batch->preview_version !== $this->previewVersion) {
                throw ValidationException::withMessages(['review' => 'This review changed. Refresh its current status.']);
            }
        }
    }

    public function restoreRecipePreferences(array $preferences, array $expected): void
    {
        $this->admit($expected);
        if ($this->batchId !== null) {
            return;
        }
        Validator::make(['preferences' => $preferences], ['preferences' => ['array:mode,parameters'], 'preferences.mode' => ['required', Rule::in(['fill_missing', 'replace_existing'])], 'preferences.parameters' => ['present', 'array']])->validate();
        $parameters = app(CatalogAssetRecipe::class)->validate($preferences['parameters'], $this->operation, $this->targetKey === 'product.main_image' ? 'product' : 'group');
        $this->recipeData['parameters'] = $parameters;
        $this->recipeData['positions'] = implode(', ', $parameters['positions'] ?? []);
        $this->recipeData['mode'] = $preferences['mode'];
    }

    public function previewAction(): Action
    {
        return CatalogAssetActions::command($this, 'preview', 'Preview review', 'preview()', 'running || unknown || !$wire.sourceId');
    }

    public function continueAction(): Action
    {
        return CatalogAssetActions::command($this, 'continue', 'Continue review', 'build()', 'running || unknown || !$wire.batchId');
    }

    public function refreshAction(): Action
    {
        return CatalogAssetActions::command($this, 'refresh', 'Refresh status', 'refresh()', 'running');
    }

    public function openSourceAction(): Action
    {
        return CatalogAssetActions::command($this, 'openSource', 'Open source', "command('chooseReviewSource')");
    }

    public function scanAction(): Action
    {
        return CatalogAssetActions::command($this, 'scan', 'Start / continue scan', 'scan()', 'running || unknown || !$wire.sourceId');
    }

    public function backAction(): Action
    {
        return CatalogAssetActions::command($this, 'back', 'Back to records', "command('backToRecords')", 'running');
    }

    public function matchForm(Schema $schema): Schema
    {
        return CatalogAssetMatchForm::configure($schema, $this);
    }

    public function candidateForm(Schema $schema): Schema
    {
        return CatalogAssetMatchForm::candidate($schema, $this);
    }

    public function table(Table $table): Table
    {
        return CatalogAssetReviewTable::configure($table, $this);
    }

    public function sourceOptions(string $search = ''): array
    {
        return app(CatalogAssetReviewQuery::class)->sourceOptions($this->actor(), $this->launchToken, $search);
    }

    public function fieldOptions(string $search = ''): array
    {
        return app(CatalogAssetReviewQuery::class)->fieldOptions($this->actor(), $this->launchToken, $search);
    }

    public function sourceLabel(string $value): ?string
    {
        if (str_starts_with($value, 'source:')) {
            $source = app(CatalogAssetReviewQuery::class)->scopedSources($this->actor(), $this->scope())->find(substr($value, 7));

            return $source === null ? null : (($source->source['label'] ?? 'Image source').' · '.$source->status.' · '.substr($source->id, -6));
        }

        return $this->sourceOptions()[$value] ?? null;
    }

    public function chooseReviewSource(array $expected): array
    {
        $this->admit($expected);
        $this->requireUnacceptedReview();
        $choice = $this->recipeData['source_choice'] ?? '';
        if (! is_string($choice) || strlen($choice) > 120) {
            throw ValidationException::withMessages(['review' => 'Choose an image source.']);
        }
        if (str_starts_with($choice, 'alias:')) {
            $scope = $this->scope();
            $source = app(CatalogAssetSources::class)->startScan($this->actor(), substr($choice, 6), $this->targetKey, $scope['owner_context'], (bool) ($this->recipeData['recursive_scan'] ?? false));
        } elseif (str_starts_with($choice, 'source:')) {
            $source = app(CatalogAssetReviewQuery::class)->scopedSources($this->actor(), $this->scope())->findOrFail(substr($choice, 7));
        } else {
            throw ValidationException::withMessages(['review' => 'Choose an authorized folder or source.']);
        }
        $this->sourceId = $source->id;
        $this->sourceVersion = $source->scan_version;
        $this->recipeData['source_choice'] = 'source:'.$source->id;
        $this->oneImageChoice = null;
        $this->candidateSourceId = null;
        $this->candidatePage = 1;
        $this->intakeProgress = $source->source_kind === 'upload' && isset($source->source['intake'])
            ? app(CatalogAssetIntake::class)->status($this->actor(), $this->launchToken, $source->id) : [];

        return $this->sourceProgress = app(CatalogAssetSources::class)->status($this->actor(), $this->sourceId);
    }

    /** @param list<array{name: string, relative_path: string, bytes: int}> $files */
    public function issueReviewUpload(array $files, string $label, array $expected): array
    {
        $this->admit($expected);
        $this->requireUnacceptedReview();

        return app(CatalogAssetIntake::class)->issue($this->actor(), $this->launchToken, $label, $files);
    }

    public function beginReviewUpload(string $token, array $expected): array
    {
        $this->admit($expected);
        $this->requireUnacceptedReview();
        $progress = app(CatalogAssetIntake::class)->begin($this->actor(), $this->launchToken, $token);
        $this->sourceId = $progress['source_id'];
        $this->sourceVersion = $progress['scan_version'];
        $this->recipeData['source_choice'] = 'source:'.$this->sourceId;
        $this->oneImageChoice = null;
        $this->candidateSourceId = null;
        $this->candidatePage = 1;

        return $this->sourceProgress = $this->intakeProgress = $progress;
    }

    public function issueReviewUploadEntry(int $index, array $expected): array
    {
        $this->admit($expected);
        $this->requireUnacceptedReview();
        $this->requireSource();

        return app(CatalogAssetIntake::class)->entry($this->actor(), $this->launchToken, $this->sourceId, $index);
    }

    public function refreshReviewUpload(?string $entryId, array $expected): array
    {
        $this->actor();
        $this->scope();
        if (($expected['session'] ?? null) !== $this->launchSession || ($expected['source'] ?? null) !== $this->sourceId || ($expected['source_version'] ?? null) !== $this->sourceVersion) {
            abort(404);
        }
        $this->requireSource();

        return $this->sourceProgress = $this->intakeProgress = app(CatalogAssetIntake::class)->status($this->actor(), $this->launchToken, $this->sourceId, $entryId);
    }

    public function finishReviewUpload(int $revision, array $expected): array
    {
        $this->admit($expected);
        $this->requireUnacceptedReview();
        $this->requireSource();

        return $this->sourceProgress = $this->intakeProgress = app(CatalogAssetIntake::class)->finish($this->actor(), $this->launchToken, $this->sourceId, $revision);
    }

    public function advanceReviewSource(array $expected, int $revision): array
    {
        $this->admit($expected);
        $this->requireSource();
        $sources = app(CatalogAssetSources::class);
        $p = $sources->status($this->actor(), $this->sourceId);
        if ($p['stage'] === 'ready' || $p['outcome'] === 'busy') {
            return $this->sourceProgress = $p;
        }
        if ($p['stage'] === 'uploading') {
            return $this->sourceProgress = [...$p, 'message' => 'Copy all selected files and choose Finish upload before scanning.'];
        }

        return $this->sourceProgress = $p['traversal_complete'] ? $sources->finalize($this->actor(), $this->sourceId, $this->sourceVersion, $revision) : $sources->scanNext($this->actor(), $this->sourceId, $this->sourceVersion, $revision);
    }

    public function chooseOneImage(array $choice, array $expected): array
    {
        $this->admit($expected);
        $this->requireUnacceptedReview();
        $this->requireSource();
        if ($this->operation !== 'one_image' || ($choice['source_id'] ?? '') !== $this->sourceId || ($choice['scan_version'] ?? 0) !== $this->sourceVersion) {
            throw ValidationException::withMessages(['review' => 'Choose one image from the current source.']);
        }
        $selected = app(CatalogAssetSources::class)->selectedEntry($this->actor(), $choice, $this->targetKey);
        if (app(CatalogAssetReviewFiles::class)->digest($selected['owner_context']) !== app(CatalogAssetReviewFiles::class)->digest($this->scope()['owner_context'])) {
            abort(404);
        }
        $this->oneImageChoice = $choice;

        return ['message' => 'Image chosen. Preview the checked records to review it.'];
    }

    protected function parameters(): array
    {
        if ($this->operation === 'one_image') {
            return [];
        }
        $parameters = $this->recipeData['parameters'] ?? [];
        foreach (['separator', 'prefix', 'suffix', 'include_patterns', 'exclude_patterns'] as $key) {
            if (array_key_exists($key, $parameters) && $parameters[$key] === null) {
                $parameters[$key] = '';
            }
        }
        $text = $this->recipeData['positions'] ?? '';
        if (! is_string($text) || strlen($text) > 60 || ($text !== '' && ! preg_match('/^\s*\d{1,2}(?:\s*,\s*\d{1,2})*\s*$/D', $text))) {
            throw ValidationException::withMessages(['review' => 'Use comma-separated code positions from1 to16.']);
        }
        $parameters['positions'] = ($parameters['source_field'] ?? '') !== 'product_code' || $text === '' ? [] : array_map('intval', explode(',', $text));

        return app(CatalogAssetRecipe::class)->validate($parameters, $this->operation, $this->targetKey === 'product.main_image' ? 'product' : 'group');
    }

    public function previewReview(array $expected): array
    {
        $this->admit($expected);
        $this->requireSource();
        $scope = $this->scope();
        $intent = ['source_id' => $this->sourceId, 'source_version' => $this->sourceVersion, 'mode' => $this->recipeData['mode'] ?? '', 'recipe' => $this->parameters()];
        if ($this->operation === 'one_image') {
            $intent['entry_choice'] = $this->oneImageChoice;
        }
        $a = app(ReviewCatalogAssets::class);
        if ($this->batchId === null) {
            $batch = $a->handle($this->actor(), $this->targetKey === 'product.main_image' ? 'product' : 'group', $scope['record_ids'], [...$intent, 'operation' => $this->operation, 'review_id' => $this->launchSession, 'scope_token' => $this->launchToken]);
            $this->batchId = $batch->id;
        } else {
            $a->revise($this->actor(), $this->batchId, $this->previewVersion, $intent);
        }
        $this->restoreReview();

        return $this->reviewProgress;
    }

    public function buildReview(array $expected, int $revision): array
    {
        $this->admit($expected);
        $this->requireBatch();

        $this->reviewProgress = app(ReviewCatalogAssets::class)->buildNext($this->actor(), $this->batchId, $this->previewVersion, $revision);
        if ($this->reviewProgress['stage'] !== 'building') {
            $this->syncImageApply();
        }

        return $this->reviewProgress;
    }

    public function refreshReviewStatus(array $expected): array
    {
        // Read-only recovery deliberately permits an obsolete version from this same launch.
        $this->actor();
        $this->scope();
        if (($expected['session'] ?? null) !== $this->launchSession) {
            abort(404);
        }
        if (CatalogAssetBatch::query()->where('actor_id', auth()->id())->whereKey($this->launchSession)->exists()) {
            $this->restoreReview();
        }
        if ($this->sourceId !== null) {
            $confirmedChoice = 'source:'.$this->sourceId;
            if (($this->recipeData['source_choice'] ?? null) !== $confirmedChoice) {
                $this->recipeData['source_choice'] = $confirmedChoice;
                $this->reviewNotice = 'Source selection restored to the confirmed opened source. To use a different source, select it and press Open source.';
            }
            $this->sourceProgress = app(CatalogAssetSources::class)->status($this->actor(), $this->sourceId);
        }

        return ['review' => $this->reviewProgress, 'source' => $this->sourceProgress, 'apply' => $this->applyProgress];
    }

    protected function restoreReview(): void
    {
        $batch = app(ReviewCatalogAssets::class)->owned($this->actor(), $this->launchSession);
        if ($batch->target_type.'.main_image' !== $this->targetKey || $batch->operation !== $this->operation) {
            abort(404);
        }
        $changed = $this->batchId === null || $this->previewVersion !== $batch->preview_version;
        $this->batchId = $batch->id;
        $this->previewVersion = $batch->preview_version;
        if ($changed) {
            $this->sourceId = $batch->source_id;
            $this->sourceVersion = $batch->source_version;
            $this->recipeData = ['source_choice' => 'source:'.$batch->source_id, 'mode' => $batch->recipe['mode'], 'parameters' => $batch->recipe['parameters'], 'positions' => implode(', ', $batch->recipe['parameters']['positions'] ?? []), 'recursive_scan' => false];
            try {
                $this->oneImageChoice = app(ReviewCatalogAssets::class)->reviewEvidence($batch)['one_image']['choice'] ?? null;
            } catch (\RuntimeException|\DomainException) {
                $this->oneImageChoice = null;
                $this->reviewNotice = 'Review evidence is unavailable. Return to records and create a fresh review from a valid source.';
            }
            $this->reviewItemId = null;
            $this->candidatePage = 1;
            $this->resetTable();
        }
        $this->reviewProgress = app(ReviewCatalogAssets::class)->status($this->actor(), $batch->id);
        $this->syncImageApply();
    }

    public function reviewRows(): Builder
    {
        $this->actor();

        return $this->batchId === null ? CatalogAssetBatchItem::query()->whereRaw('1=0') : app(CatalogAssetReviewQuery::class)->items($this->actor(), $this->batchId, $this->previewVersion, ['query' => $this->reviewSearch]);
    }

    protected function reviewFilters(): array
    {
        $filters = ['query' => $this->reviewSearch];
        if (($v = $this->tableFilters['apply_status']['value'] ?? '') !== '') {
            $filters['apply_status'] = $v;
        }
        if (($v = $this->tableFilters['match_status']['value'] ?? '') !== '') {
            $filters['status'] = $v;
        }
        $v = $this->tableFilters['included']['value'] ?? null;
        if ($v !== null && $v !== '') {
            $filters['included'] = (bool) $v;
        }

        return $filters;
    }

    public function inspectReviewItem(int $id, array $expected): void
    {
        $this->admit($expected);
        $this->requireBatch();
        app(CatalogAssetReviewQuery::class)->items($this->actor(), $this->batchId, $this->previewVersion, [])->findOrFail($id);
        $this->reviewItemId = $id;
        $this->candidatePage = 1;
        $this->allSourceFiles = false;
    }

    public function navigateReviewItem(string $direction, array $expected): void
    {
        $this->admit($expected);
        $this->requireBatch();
        $next = app(CatalogAssetReviewQuery::class)->neighbor($this->actor(), $this->batchId, $this->previewVersion, $this->reviewItemId, $direction, $this->reviewFilters(), (int) $this->getTableRecordsPerPage());
        if ($next === null) {
            $this->reviewNotice = 'End of this review sequence.';

            return;
        }
        $this->reviewItemId = $next['item_id'];
        $this->candidatePage = 1;
        $this->setPage($next['page'], $this->getTablePaginationPageName());
        $this->reviewNotice = '';
    }

    public function setReviewIncluded(int $id, bool $included, array $expected): array
    {
        $this->admit($expected);
        $this->requireBatch();

        return $this->reviewProgress = app(ReviewCatalogAssets::class)->setIncluded($this->actor(), $this->batchId, $this->previewVersion, [$id], $included);
    }

    public function resolveReviewCandidate(array $choice, array $expected): array
    {
        $this->admit($expected);
        $this->requireBatch();
        $row = $this->reviewRows()->findOrFail($this->reviewItemId);
        app(ReviewCatalogAssets::class)->revise($this->actor(), $this->batchId, $this->previewVersion, ['override' => ['record_id' => $row->record_id, 'choice' => $choice]]);
        $this->restoreReview();

        return $this->reviewProgress;
    }

    public function clearReviewOverride(array $expected): array
    {
        $this->admit($expected);
        $this->requireBatch();
        $row = $this->reviewRows()->findOrFail($this->reviewItemId);
        app(ReviewCatalogAssets::class)->revise($this->actor(), $this->batchId, $this->previewVersion, ['override' => ['record_id' => $row->record_id, 'choice' => null]]);
        $this->restoreReview();

        return $this->reviewProgress;
    }

    public function useCandidateSource(array $expected): void
    {
        $this->admit($expected);
        $value = $this->candidateData['source_choice'] ?? '';
        if (! is_string($value) || ! str_starts_with($value, 'source:')) {
            throw ValidationException::withMessages(['review' => 'Choose an existing ready source. Open server folders using the primary source control first.']);
        }
        $source = app(CatalogAssetReviewQuery::class)->scopedSources($this->actor(), $this->scope())->where('status', 'ready')->findOrFail(substr($value, 7));
        $this->candidateSourceId = $source->id;
        $this->candidateSourceVersion = $source->scan_version;
        $this->allSourceFiles = true;
        $this->candidatePage = 1;
    }

    public function candidateResults(): array
    {
        $this->actor();
        try {

            if ($this->sourceId === null || (($this->sourceProgress['stage'] ?? '') !== 'ready' && $this->batchId === null)) {
                return ['entries' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
            }
            $query = app(CatalogAssetReviewQuery::class);
            if ($this->batchId !== null && $this->reviewItemId !== null && ! $this->allSourceFiles) {
                return $query->candidates($this->actor(), $this->batchId, $this->previewVersion, $this->reviewItemId, $this->candidatePage, $this->candidateSearch);
            }

            return $query->sourceCandidates($this->actor(), $this->launchToken, $this->candidateSourceId ?? $this->sourceId, $this->candidateSourceId === null ? $this->sourceVersion : $this->candidateSourceVersion, $this->candidatePage, $this->candidateSearch);
        } catch (\RuntimeException|\DomainException) {
            return ['entries' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'error' => 'Source evidence is unavailable. Refresh status or open a valid source; existing files have not been recreated.'];
        }
    }

    public function inspector(): ?array
    {
        if ($this->batchId === null || $this->reviewItemId === null) {
            return null;
        }
        $row = app(CatalogAssetReviewQuery::class)->items($this->actor(), $this->batchId, $this->previewVersion, [])->find($this->reviewItemId);
        if ($row === null) {
            return null;
        }
        $position = app(CatalogAssetReviewQuery::class)->position($this->actor(), $this->batchId, $this->previewVersion, $row->id, $this->reviewFilters(), (int) $this->getTableRecordsPerPage());
        $saved = $row->before_asset_id === null ? null : (app(CatalogAssetDisplay::class)->forIds([$row->before_asset_id])[$row->before_asset_id] ?? null);

        return ['id' => $row->id, 'title' => $row->snapshot['title'], 'raw' => $row->snapshot['raw_display'], 'truncated' => $row->snapshot['raw_truncated'], 'identifier' => $row->snapshot['identifier'], 'filename' => $row->snapshot['filename'], 'reason' => $row->snapshot['reason'], 'status' => $row->match_status, 'manual' => $row->snapshot['manual'], 'included' => $row->included, 'accepted' => $row->acceptance_id !== null, 'apply_status' => $row->apply_status, 'apply_code' => $row->error_code ?? ($row->snapshot['apply_result']['code'] ?? null), 'warnings' => array_slice($row->snapshot['apply_result']['warnings'] ?? [], 0, 10), 'current_url' => $this->currentReviewImages()[$row->record_id]['thumbnail_url'] ?? null, 'saved_url' => $saved['thumbnail_url'] ?? null, 'proposed_url' => $this->proposedUrl($row), 'position' => $position];
    }

    protected function proposedUrl(CatalogAssetBatchItem $row): ?string
    {
        return CatalogAssetReviewTable::proposedUrl($row);
    }

    public function savedReviewImages(): array
    {
        $this->actor();

        return $this->savedImages ??= app(CatalogAssetDisplay::class)->forIds($this->getTableRecords()->pluck('before_asset_id')->filter()->unique()->all());
    }

    public function readyPageApplyCount(): int
    {
        return $this->getTableRecords()->filter(fn (CatalogAssetBatchItem $row): bool => $row->included && $row->match_status === 'ready' && $row->acceptance_id === null)->count();
    }

    public function currentReviewImages(): array
    {
        if ($this->currentImages !== null) {
            return $this->currentImages;
        }
        $batch = app(ReviewCatalogAssets::class)->owned($this->actor(), $this->batchId);
        $recordIds = $this->getTableRecords()->pluck('record_id')->all();
        if ($this->reviewItemId !== null) {
            $inspectedId = $batch->items()->whereKey($this->reviewItemId)->value('record_id');
            if ($inspectedId !== null) {
                $recordIds[] = $inspectedId;
            }
        }
        $owners = app(CatalogAssetTargets::class)->eligibleQuery($this->actor(), $this->targetKey, $batch->owner_context)->whereKey($recordIds)->pluck('main_image_id', 'id');
        $images = app(CatalogAssetDisplay::class)->forIds($owners->filter()->unique()->values()->all());
        $this->currentImages = [];
        foreach ($owners as $id => $asset) {
            $this->currentImages[$id] = $images[$asset] ?? null;
        }

        return $this->currentImages;
    }

    protected function requireUnacceptedReview(): void
    {
        if ($this->batchId !== null && app(ReviewCatalogAssets::class)->owned($this->actor(), $this->batchId)->items()->whereNotNull('acceptance_id')->exists()) {
            throw ValidationException::withMessages(['review' => 'Accepted review choices are frozen. Start a remaining review to change them.']);
        }
    }

    public function reviewSelectionLabel(CatalogAssetBatchItem $row): string
    {
        $this->actor();
        if ($this->batchId === null || $row->batch_id !== $this->batchId) {
            abort(404);
        }
        $this->selectionOrigins ??= $this->batchId === null ? [] : app(ReviewCatalogAssets::class)->owned($this->actor(), $this->batchId)->recipe['review_selection'];
        $choice = $this->selectionOrigins[$row->record_id] ?? null;

        return $choice === null ? 'Not classified' : ($choice['included'] ? 'Included' : ($choice['origin'] === 'explicit' ? 'Explicitly excluded' : 'Deferred'));
    }

    public function recipeExample(): ?array
    {
        if ($this->operation !== 'folder_matches') {
            return null;
        }
        try {
            $parameters = $this->parameters();
            $scope = $this->scope();
            $record = app(CatalogAssetTargets::class)->eligibleQuery($this->actor(), $this->targetKey, $scope['owner_context'])->whereKey(array_slice($scope['record_ids'], 0, 1))->first();
            if ($record === null) {
                return null;
            }
            $example = app(CatalogAssetRecipe::class)->identifier(array_replace($record->getAttributes(), ['profile_fields' => $record->profile_fields]), $parameters);

            return ['raw' => $example['raw_display'], 'truncated' => $example['raw_truncated'], 'identifier' => $example['identifier'], 'reason' => $example['reason']];
        } catch (ValidationException) {
            return ['reason' => 'Complete a valid recipe to see the example.'];
        }
    }

    public function setReviewPageSize(string $raw, array $expected): void
    {
        $this->admit($expected);
        if (! preg_match('/^[1-9][0-9]{0,2}$/D', $raw) || (int) $raw > 500) {
            throw ValidationException::withMessages(['review' => 'Use a whole number from1 to500.']);
        }
        $name = $this->getTablePaginationPageName();
        $old = (int) $this->getTableRecordsPerPage();
        $ordinal = (max(1, $this->getPage($name)) - 1) * $old;
        $this->tableRecordsPerPage = (int) $raw;
        $total = $this->getFilteredTableQuery()->count();
        $this->setPage(min(intdiv($ordinal, (int) $raw) + 1, max(1, (int) ceil($total / (int) $raw))), $name);
        session()->put('catalog-image-review-size.'.auth()->id().'.'.$this->targetKey, (int) $raw);
    }

    public function getTableRecordsPerPage(): int
    {
        $size = $this->tableRecordsPerPage ?? session()->get('catalog-image-review-size.'.auth()->id().'.'.($this->targetKey ?? 'product.main_image'), 10);

        return is_numeric($size) && (int) $size >= 1 && (int) $size <= 500 ? (int) $size : 10;
    }

    public function reviewPageOptions(): array
    {
        $sizes = [5, 10, 15, 25, 50, 100, 200, $this->getTableRecordsPerPage()];
        sort($sizes);

        return array_values(array_unique($sizes));
    }

    public function updatedReviewSearch(): void
    {
        $this->actor();
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function backToRecords(array $expected): void
    {
        $this->admit($expected);
        $this->dispatch('catalog-image-review-closed', session: $this->launchSession, host: $this->parentHost);
    }

    protected function requireSource(): void
    {
        if ($this->sourceId === null) {
            throw ValidationException::withMessages(['review' => 'Open a source first.']);
        }
        if (($this->recipeData['source_choice'] ?? null) !== 'source:'.$this->sourceId) {
            throw ValidationException::withMessages(['review' => 'The selected source has not been opened. Press Open source to confirm it before previewing or continuing.']);
        }
    }

    protected function requireBatch(): void
    {
        if ($this->batchId === null) {
            throw ValidationException::withMessages(['review' => 'Preview the checked records first.']);
        }
    }

    public function previousCandidatePage(array $expected): void
    {
        $this->admit($expected);
        $this->candidatePage = max(1, $this->candidatePage - 1);
    }

    public function nextCandidatePage(array $expected): void
    {
        $this->admit($expected);
        $this->candidatePage = min(84, $this->candidatePage + 1);
    }

    public function updatedCandidateSearch(): void
    {
        $this->actor();
        $this->candidatePage = 1;
    }

    public function render(): View
    {
        return view('livewire.catalog.asset-assignment');
    }
}
