<?php

namespace App\Livewire\Catalog;

use App\Actions\SaveTestedConfiguratorChanges;
use App\DTO\ConfiguratorDefinition;
use App\DTO\ConfiguratorEvaluationInput;
use App\DTO\ConfiguratorEvaluationResult;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Models\Configurator;
use App\Models\Product;
use App\Services\CatalogAvailability;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorPreviewInspection;
use App\Services\ConfiguratorPreviewNotes;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Throwable;

class ConfiguratorPreview extends ProductConfigurator implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public int $configuratorId;

    #[Locked]
    public bool $definitionStale = false;

    #[Locked]
    public bool $showTrace = false;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $selectionWarnings = [];

    #[Locked]
    public string $previewSource = 'saved';

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $previewCaptures = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $previewData = null;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $previewChanges = [];

    /** @var list<string> */
    #[Locked]
    public array $previewErrors = [];

    /** @var list<string> */
    #[Locked]
    public array $inventoryErrors = [];

    #[Locked]
    public ?string $inventoryHash = null;

    #[Locked]
    public bool $editorPreviewStale = false;

    /** @var array<string, mixed> */
    #[Locked]
    public array $previewView = [];

    /** @var array<string, array<string, mixed>> */
    #[Locked]
    public array $sourceRuntimes = [];

    #[Locked]
    public ?string $testedFingerprint = null;

    #[Locked]
    public ?string $productResetNotice = null;

    /** @var array{attribute_id:string,option_id:?string}|null */
    #[Locked]
    public ?array $inspectionTarget = null;

    /** @var array<string,mixed>|null */
    #[Locked]
    public ?array $saveReceipt = null;

    protected ?ConfiguratorDefinition $evaluationDefinition = null;

    /** @var list<array<string,mixed>> */
    protected array $evaluationExcludedRules = [];

    protected ?string $evaluationFingerprint = null;

    protected ?string $evaluationInventoryHash = null;

    public function mount(int $configuratorId): void
    {
        Gate::authorize('manage-catalog');
        $this->configuratorId = Configurator::findOrFail($configuratorId)->id;
        $product = $this->dashboardProduct() ?? $this->firstProduct($this->products());
        $this->chooseProduct($product?->id);
    }

    /** @param list<array<string,mixed>> $captures */
    public function refreshEditorInventory(array $captures): void
    {
        Gate::authorize('manage-catalog');
        $changes = app(ConfiguratorEditorChanges::class);
        $configurator = Configurator::findOrFail($this->configuratorId);
        $inventory = $changes->inspect($configurator, $captures);
        $hash = $changes->captureHash($this->configuratorId, $captures);
        $this->previewCaptures = $captures;
        $this->previewChanges = $inventory['changes'];
        $this->inventoryErrors = $inventory['errors'];
        $this->inventoryHash = $hash;
        $this->editorPreviewStale = $this->previewSource === 'unsaved' && ($this->previewView['inventory_hash'] ?? null) !== $hash;
        if ($this->editorPreviewStale) {
            $this->testedFingerprint = null;
        }
        if ($this->productId !== null && ($this->previewView['evaluation_fingerprint'] ?? null) !== null) {
            try {
                $fingerprint = $this->products()->whereKey($this->productId)->exists() ? $changes->fingerprint($configurator, $this->productId) : null;
                if ($fingerprint === null || ! hash_equals($this->previewView['evaluation_fingerprint'], $fingerprint)) {
                    $this->definitionStale = true;
                    $this->testedFingerprint = null;
                }
            } catch (ModelNotFoundException) {
                $this->definitionStale = true;
                $this->testedFingerprint = null;
            }
        }
    }

    /** @param list<array<string,mixed>> $captures */
    public function rebuildPreview(array $captures = [], string $source = 'saved'): bool
    {
        Gate::authorize('manage-catalog');
        abort_unless(in_array($source, ['saved', 'unsaved'], true), 422);
        if ($this->saveReceipt !== null && $this->saveReceipt['status'] === 'reconcile_pending') {
            return false;
        }
        $this->sourceRuntimes[$this->previewSource] = $this->runtime;
        $this->runtime = $this->sourceRuntimes[$source] ?? $this->runtime;
        $this->previewSource = $source;
        $this->previewData = null;
        $this->previewErrors = [];
        $this->selectionWarnings = [];
        $this->testedFingerprint = null;
        $this->preparedResult = null;
        try {
            $this->refreshEditorInventory($captures);
            DB::transaction(function (): void {
                if ($this->previewSource === 'unsaved') {
                    $prepared = app(ConfiguratorEditorChanges::class)->prepare(Configurator::findOrFail($this->configuratorId), $this->previewCaptures);
                    $this->previewData = $prepared['data'];
                    $this->evaluationInventoryHash = $this->inventoryHash;
                }
                $this->definitionStale = false;
                $this->editorPreviewStale = false;
                $this->evaluate(['kind' => 'Reevaluate']);
            });
        } catch (ValidationException $exception) {
            $this->previewErrors = array_values(array_unique(Arr::flatten($exception->errors())));
            $this->previewData = null;
            $this->testedFingerprint = null;
            foreach ($this->previewChanges as &$change) {
                $errors = array_values(array_filter($this->previewErrors, fn (string $error): bool => str_contains($error, $change['label'])));
                if ($errors !== []) {
                    $change['invalid'] = true;
                    $change['errors'] = array_values(array_unique([...($change['errors'] ?? []), ...$errors]));
                }
            }
            unset($change);
            $this->evaluationInventoryHash = $source === 'unsaved' ? $this->inventoryHash : null;
            $this->editorPreviewStale = false;
            $this->evaluate(['kind' => 'Reevaluate']);

            return false;
        }

        return true;
    }

    public function canAttemptSave(): bool
    {
        Gate::authorize('manage-catalog');
        if ($this->previewChanges === [] || $this->inventoryErrors !== [] || $this->productId === null
            || ! $this->products()->whereKey($this->productId)->exists()
            || ($this->saveReceipt['status'] ?? null) === 'reconcile_pending') {
            return false;
        }
        if ($this->previewSource === 'saved' || $this->editorPreviewStale || $this->definitionStale) {
            return true;
        }

        return $this->previewErrors === [] && ($this->previewView['blocking_issues'] ?? []) === [];
    }

    public function canSaveTestedChanges(): bool
    {
        return $this->previewSource === 'unsaved' && $this->canAttemptSave() && $this->testedFingerprint !== null;
    }

    /** @param list<array<string,mixed>> $captures */
    public function reviewChanges(array $captures): void
    {
        Gate::authorize('manage-catalog');
        $this->refreshEditorInventory($captures);
        $this->mountAction('reviewChanges');
    }

    public function reviewChangesAction(): Action
    {
        $detail = Js::from(['componentId' => $this->getId(), 'configuratorId' => $this->configuratorId])->toHtml();

        return Action::make('reviewChanges')->label(__('configurator.preview.review_changes'))->authorize('manage-catalog')
            ->slideOver()->modalHeading(__('configurator.preview.review_changes'))->modalSubmitAction(false)
            ->modalContent(fn (): View => view('livewire.catalog.configurator-preview-changes', ['changes' => $this->previewChanges, 'tested' => $this->previewView, 'inventoryErrors' => $this->inventoryErrors, 'previewErrors' => $this->previewErrors, 'inventoryHash' => $this->inventoryHash, 'definitionStale' => $this->definitionStale, 'editorPreviewStale' => $this->editorPreviewStale, 'productCode' => $this->productId === null ? null : $this->products()->find($this->productId)?->product_code]))
            ->extraModalFooterActions([Action::make('savePreviewChanges')->label(__('configurator.preview.save_changes'))->disabled(fn (): bool => ! $this->canAttemptSave())
                ->extraAttributes(['x-bind:disabled' => 'busy || '.($this->canAttemptSave() ? 'false' : 'true')])
                ->alpineClickHandler("window.dispatchEvent(new CustomEvent('configurator-preview-save-request', { detail: {$detail} }))")])
            ->modalCancelActionLabel(__('ui.actions.close'))->stickyModalHeader()->stickyModalFooter();
    }

    public function previewNoticesAction(): Action
    {
        return Action::make('previewNotices')->label(__('configurator.preview.all_notices'))->authorize('manage-catalog')
            ->slideOver()->modalSubmitAction(false)->modalCancelActionLabel(__('ui.actions.close'))
            ->modalContent(fn (): View => view('livewire.catalog.configurator-preview-notices', ['notices' => $this->notices(), 'notes' => $this->previewView['notes'] ?? []]))
            ->stickyModalHeader()->stickyModalFooter();
    }

    /** @param list<array<string,mixed>> $captures @param list<string> $editorIds @return array<string,mixed> */
    public function saveChanges(array $captures, array $editorIds = []): array
    {
        Gate::authorize('manage-catalog');
        if (($this->saveReceipt['status'] ?? null) === 'reconcile_pending') {
            return ['status' => 'committed', 'receipt' => $this->saveReceipt];
        }
        abort_if(count($editorIds) > 3 || array_filter($editorIds, fn ($id): bool => ! is_string($id) || strlen($id) > 100) !== [], 422);
        if (! $this->rebuildPreview($captures, 'unsaved') || ! $this->canSaveEvaluatedChanges()) {
            return ['status' => 'rejected', 'errors' => [...$this->inventoryErrors, ...$this->previewErrors, ...($this->previewView['blocking_issues'] ?? [])]];
        }
        try {
            $newRules = app(SaveTestedConfiguratorChanges::class)->handle(auth()->user(), $this->configuratorId, $this->productId, $this->previewCaptures, $this->testedFingerprint, $this->runtime);
        } catch (ValidationException $exception) {
            $this->previewErrors = array_values(array_unique(Arr::flatten($exception->errors())));

            return ['status' => 'rejected', 'errors' => $this->previewErrors];
        }
        $this->saveReceipt = ['operation_id' => (string) Str::uuid(), 'status' => 'reconcile_pending', 'captures' => $this->previewCaptures,
            'newRules' => $newRules, 'editor_ids' => array_values(array_unique($editorIds)), 'refresh_error' => null];
        $this->dispatch('configurator-preview-saved', configuratorId: $this->configuratorId, captures: $this->previewCaptures, newRules: $newRules, operationId: $this->saveReceipt['operation_id']);
        $this->dispatch('configurator-updated')->to(EditConfigurator::class);
        try {
            $this->refreshSavedPreview();
        } catch (Throwable $exception) {
            report($exception);
            $this->saveReceipt['refresh_error'] = __('configurator.preview.saved_refresh_failed');
        }
        if ($this->getMountedAction()?->getName() === 'reviewChanges') {
            $this->unmountAction();
        }
        Notification::make()->title(__('configurator.preview.changes_saved'))->success()->send();

        return ['status' => 'committed', 'receipt' => $this->saveReceipt];
    }

    private function canSaveEvaluatedChanges(): bool
    {
        return $this->previewSource === 'unsaved' && $this->previewChanges !== [] && $this->inventoryErrors === [] && $this->previewErrors === []
            && $this->testedFingerprint !== null && $this->productId !== null && $this->preparedResult !== null
            && app(ConfiguratorEditorChanges::class)->blockingIssues($this->preparedResult) === [];
    }

    public function finishPreviewSave(string $operationId): void
    {
        Gate::authorize('manage-catalog');
        abort_unless($this->saveReceipt !== null && hash_equals($this->saveReceipt['operation_id'], $operationId), 422);
        $this->saveReceipt['status'] = 'reconciled';
        $this->previewCaptures = [];
        $this->previewChanges = [];
        $this->inventoryErrors = [];
        $this->inventoryHash = app(ConfiguratorEditorChanges::class)->captureHash($this->configuratorId, []);
    }

    public function refreshSavedPreview(): void
    {
        Gate::authorize('manage-catalog');
        $previous = [];
        foreach (['sourceRuntimes', 'previewSource', 'previewData', 'previewErrors', 'definitionStale', 'editorPreviewStale', 'evaluationInventoryHash', 'runtime', 'preparedResult', 'evaluationDefinition', 'evaluationExcludedRules', 'evaluationFingerprint', 'previewView', 'testedFingerprint', 'selectionWarnings', 'inspectionTarget'] as $property) {
            $previous[$property] = $this->{$property};
        }
        try {
            $this->sourceRuntimes = [];
            $this->previewSource = 'saved';
            $this->previewData = null;
            $this->previewErrors = [];
            $this->definitionStale = false;
            $this->editorPreviewStale = false;
            $this->evaluationInventoryHash = null;
            $this->evaluate(['kind' => 'Reevaluate']);
        } catch (Throwable $exception) {
            foreach ($previous as $property => $value) {
                $this->{$property} = $value;
            }
            throw $exception;
        }
        if ($this->saveReceipt !== null) {
            $this->saveReceipt['refresh_error'] = null;
        }
    }

    #[On('configurator-updated')]
    public function markDefinitionStale(): void
    {
        Gate::authorize('manage-catalog');
        $this->definitionStale = true;
        $this->testedFingerprint = null;
    }

    public function refreshDefinition(): void
    {
        Gate::authorize('manage-catalog');
        $this->rebuildPreview($this->previewCaptures, $this->previewSource);
    }

    public function toggleTrace(): void
    {
        Gate::authorize('manage-catalog');
        $this->showTrace = ! $this->showTrace;
        $this->dispatch('configurator-preview-trace-toggle', componentId: $this->getId(), configuratorId: $this->configuratorId);
    }

    #[Renderless]
    public function notifyNavigationFailure(): void
    {
        Gate::authorize('manage-catalog');
        Notification::make()->title(__('configurator.preview.source_unavailable'))->warning()->send();
    }

    public function showConflictDetails(): void
    {
        Gate::authorize('manage-catalog');
        $inspection = $this->previewView['inspection'];
        $diagnostic = collect($inspection['diagnostics'])->first(fn (array $row): bool => isset($row['attribute_id']));
        $id = $inspection['conflicts'][0]['attribute_id'] ?? ($diagnostic['attribute_id'] ?? array_key_first($inspection['attributes']));
        if ($id !== null) {
            $this->inspectState((string) $id);
        }
    }

    public function inspectState(string $attributeId, ?string $optionId = null): void
    {
        Gate::authorize('manage-catalog');
        $attribute = $this->previewView['inspection']['attributes'][$attributeId] ?? null;
        if ($attribute === null || ($optionId !== null && ! isset($attribute['options'][$optionId]))) {
            $this->notifyNavigationFailure();

            return;
        }
        $this->inspectionTarget = ['attribute_id' => $attributeId, 'option_id' => $optionId];
        $this->showTrace = true;
        $this->dispatch('configurator-preview-inspection-reveal', componentId: $this->getId(), configuratorId: $this->configuratorId, attributeId: $attributeId, optionId: $optionId);
    }

    public function clearInspection(): void
    {
        Gate::authorize('manage-catalog');
        $this->inspectionTarget = null;
    }

    /** @return list<array{id:string,label:string}> */
    #[Renderless]
    public function searchProducts(string $search): array
    {
        return $this->products()->whereRaw('LOWER(product_code) LIKE ?', ['%'.Str::lower($search).'%'])
            ->orderBy('product_code')->orderBy('id')->limit(50)->get(['id', 'product_code'])
            ->map(fn (Product $product): array => ['id' => (string) $product->id, 'label' => $product->product_code])->all();
    }

    public function chooseProduct(int|string|null $productId): ?int
    {
        Gate::authorize('manage-catalog');
        if (($this->saveReceipt['status'] ?? null) === 'reconcile_pending') {
            return $this->productId;
        }
        $this->resetValidation('product');
        $product = $productId === null || $productId === '' ? null : $this->products()->find($productId);
        if ($product === null && $productId !== null && $productId !== '') {
            throw ValidationException::withMessages(['product' => __('configurator.validation.choose_a_product_from_a_currently_assigned')]);
        }
        $this->productResetNotice = $product !== null && $this->productId !== null ? __('configurator.preview.product_reset', ['code' => $product->product_code]) : null;
        $this->productId = $product?->id;
        $this->sourceRuntimes = [];
        $this->runtime = [];
        $this->selectionWarnings = [];
        $this->inspectionTarget = null;
        $this->preparedResult = null;
        $this->definitionStale = false;
        $this->evaluate(['kind' => 'Initialize']);

        return $this->productId;
    }

    public function selectOption(string $attributeId, string $optionId): void
    {
        Gate::authorize('manage-catalog');
        if (($this->saveReceipt['status'] ?? null) === 'reconcile_pending') {
            return;
        }
        $this->productResetNotice = null;
        $this->selectionWarnings = [];
        parent::selectOption($attributeId, $optionId);
    }

    /** @param array<string,mixed> $intent */
    protected function evaluate(array $intent): void
    {
        if ($this->previewSource === 'unsaved' && $this->evaluationInventoryHash === null) {
            $this->evaluationInventoryHash = $this->previewView['inventory_hash'] ?? $this->inventoryHash;
        }
        DB::transaction(fn () => parent::evaluate($intent));
    }

    /** @param array<string,mixed> $intent */
    protected function input(array $intent): ConfiguratorEvaluationInput
    {
        Gate::authorize('manage-catalog');
        $this->evaluationDefinition = null;
        $this->evaluationExcludedRules = [];
        $this->evaluationFingerprint = null;
        if ($this->productId === null) {
            return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'preview_product_required', 'message' => __('configurator.messages.choose_a_product_from_an_assigned_group')]]);
        }
        if ($this->previewSource === 'unsaved' && $this->previewErrors !== []) {
            return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'invalid_editor_changes', 'message' => __('configurator.preview.invalid_edits')]]);
        }
        if (! $this->products()->whereKey($this->productId)->exists()) {
            return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'preview_product_unavailable', 'message' => __('configurator.messages.this_product_is_no_longer_assigned_to')]]);
        }
        $configurator = Configurator::findOrFail($this->configuratorId);
        $loader = app(ConfiguratorDefinitionLoader::class);
        $data = $this->previewSource === 'unsaved' ? $this->previewData : $loader->draft($configurator);
        if ($this->previewSource === 'unsaved' && $data === null) {
            return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'invalid_editor_changes', 'message' => __('configurator.preview.invalid_edits')]]);
        }
        $this->evaluationExcludedRules = array_values(array_filter($data['rules'] ?? [], fn (array $rule): bool => $loader->hasPublicContextCondition($rule['conditions'])));
        $input = $loader->forPreview(auth()->user(), $this->configuratorId, $this->productId, $this->runtime, $intent, $data);
        $this->evaluationDefinition = $input->definition;
        if ($input->definition !== null) {
            $this->evaluationFingerprint = app(ConfiguratorEditorChanges::class)->fingerprint($configurator, $this->productId);
        }

        return new ConfiguratorEvaluationInput($input->definition, $input->properties, $input->context, $input->selections, $input->remembered, $input->intent, $input->configuratorId, $input->diagnostics, trace: true);
    }

    protected function evaluated(): void
    {
        $this->previewView = $this->assemblePreviewView($this->preparedResult, $this->evaluationDefinition, $this->evaluationExcludedRules);
        $this->testedFingerprint = $this->previewView['evaluation_fingerprint'];
        $this->sourceRuntimes[$this->previewSource] = $this->runtime;
        $warnings = $this->previewView['notes']['warnings'];
        if ($warnings !== [] || $this->preparedResult->definition === null) {
            $this->selectionWarnings = $warnings;
        }
        if ($this->inspectionTarget !== null) {
            $attribute = $this->previewView['inspection']['attributes'][$this->inspectionTarget['attribute_id']] ?? null;
            if ($attribute === null || ($this->inspectionTarget['option_id'] !== null && ! isset($attribute['options'][$this->inspectionTarget['option_id']]))) {
                $this->inspectionTarget = null;
            }
        }
    }

    /** @param list<array<string,mixed>> $excludedRules @return array<string,mixed> */
    private function assemblePreviewView(ConfiguratorEvaluationResult $result, ?ConfiguratorDefinition $compiledDefinition, array $excludedRules): array
    {
        $inspection = app(ConfiguratorPreviewInspection::class)->build($result, $compiledDefinition, $excludedRules);

        return ['version' => 1, 'product_id' => $this->productId, 'source' => $this->previewSource,
            'inventory_hash' => $this->previewSource === 'unsaved' ? $this->evaluationInventoryHash : null,
            'evaluation_fingerprint' => $this->evaluationFingerprint, 'status' => $result->definition === null ? 'unavailable' : 'ready',
            'is_complete' => $result->isComplete, 'configuration_code' => $result->isComplete ? $result->configurationCode : null,
            'inspection' => $inspection, 'notes' => app(ConfiguratorPreviewNotes::class)->build($result, $inspection),
            'blocking_issues' => app(ConfiguratorEditorChanges::class)->blockingIssues($result), 'excluded_rules' => $excludedRules];
    }

    /** @return list<array<string,mixed>> */
    public function notices(): array
    {
        Gate::authorize('manage-catalog');
        $notices = [];
        foreach (array_values(array_unique([...$this->inventoryErrors, ...$this->previewErrors, ...($this->previewView['blocking_issues'] ?? [])])) as $message) {
            $notices[] = ['type' => 'error', 'title' => __('configurator.preview.test_errors'), 'message' => $message, 'action' => 'inspect'];
        }
        if ($this->definitionStale || $this->editorPreviewStale) {
            $notices[] = ['type' => 'warning', 'title' => __('configurator.preview.preview_stale'), 'message' => __('configurator.copy.saved_changes_are_available_refresh_to_retest_your_choices'), 'action' => 'rebuild'];
        }
        if ($this->productResetNotice !== null) {
            $notices[] = ['type' => 'info', 'title' => __('configurator.preview.product_label'), 'message' => $this->productResetNotice];
        }
        foreach ($this->selectionWarnings as $warning) {
            $notices[] = ['type' => 'info', 'title' => __('configurator.preview.rules_and_state_notes'), 'message' => $warning['message'], 'action' => 'inspect'];
        }
        if (($this->saveReceipt['refresh_error'] ?? null) !== null) {
            $notices[] = ['type' => 'warning', 'title' => __('configurator.preview.changes_saved'), 'message' => $this->saveReceipt['refresh_error'], 'action' => 'refresh'];
        } elseif ($this->previewChanges !== []) {
            $notices[] = ['type' => 'info', 'title' => __('configurator.preview.unsaved_source'), 'message' => trans_choice('configurator.preview.pending_count', count($this->previewChanges), ['count' => count($this->previewChanges)]), 'action' => 'review'];
        } elseif (($this->saveReceipt['status'] ?? null) === 'reconciled') {
            $notices[] = ['type' => 'success', 'title' => __('configurator.preview.changes_saved'), 'message' => __('configurator.preview.saved_receipt_complete')];
        }

        return $notices;
    }

    private function products(): Builder
    {
        Gate::authorize('manage-catalog');

        return Product::whereHas('group', fn (Builder $query) => $query->where('configurator_id', $this->configuratorId)->doesntHave('children'));
    }

    private function dashboardProduct(): ?Product
    {
        $products = $this->products()->where('products.is_active', true)->whereIn('products.group_id', app(CatalogAvailability::class)->visibleGroupIds());
        if ($this->productId !== null) {
            return $products->find($this->productId);
        }

        return $this->firstProduct($products);
    }

    private function firstProduct(Builder $products): ?Product
    {
        return $products->join('groups', 'groups.id', '=', 'products.group_id')
            ->orderBy('groups.sort_order')->orderBy('groups.id')->orderBy('products.product_code')->orderBy('products.id')
            ->first(['products.id', 'products.product_code']);
    }

    public function render(): View
    {
        Gate::authorize('manage-catalog');

        return view('livewire.catalog.configurator-preview', [
            'previewView' => $this->previewView, 'blockingIssues' => $this->previewView['blocking_issues'] ?? [],
            'notes' => $this->previewView['notes'] ?? [], 'notices' => $this->notices(), 'dashboardProduct' => $this->dashboardProduct(),
            'selectedProductCode' => $this->productId === null ? '' : ($this->products()->find($this->productId)?->product_code ?? ''),
            'publicOnlyRules' => array_column($this->previewView['excluded_rules'] ?? [], 'label'),
        ]);
    }
}
