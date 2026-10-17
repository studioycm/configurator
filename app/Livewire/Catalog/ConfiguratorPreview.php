<?php

namespace App\Livewire\Catalog;

use App\Actions\SaveTestedConfiguratorChanges;
use App\DTO\ConfiguratorEvaluationInput;
use App\Filament\Resources\Configurators\Pages\EditConfigurator;
use App\Models\Configurator;
use App\Models\Product;
use App\Services\CatalogAvailability;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEditorChanges;
use App\Services\ConfiguratorEngine;
use App\Services\ConfiguratorPreviewNotes;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

class ConfiguratorPreview extends ProductConfigurator implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public int $configuratorId;

    /** @var array<string, mixed> */
    public array $formState = [];

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

    /** @var array<string, array<string, mixed>> */
    #[Locked]
    public array $sourceRuntimes = [];

    #[Locked]
    public ?string $testedFingerprint = null;

    #[Locked]
    public ?string $reviewedFingerprint = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $reviewedTest = [];

    #[Locked]
    public ?string $productResetNotice = null;

    /** @param list<array<string, mixed>> $captures */
    public function rebuildPreview(array $captures = [], string $source = 'saved'): bool
    {
        Gate::authorize('manage-catalog');
        abort_unless(in_array($source, ['saved', 'unsaved'], true), 422);
        $this->sourceRuntimes[$this->previewSource] = $this->runtime;
        $this->runtime = $this->sourceRuntimes[$source] ?? $this->runtime;
        $this->previewSource = $source;
        $this->previewCaptures = $captures;
        $this->previewData = null;
        $this->previewChanges = [];
        $this->previewErrors = [];
        $this->selectionWarnings = [];
        $this->testedFingerprint = null;
        $this->reviewedFingerprint = null;
        $this->reviewedTest = [];
        $this->definitionStale = false;
        $this->preparedResult = null;
        $this->cachedSchemas = [];
        try {
            DB::transaction(function (): void {
                $configurator = Configurator::findOrFail($this->configuratorId);
                if ($this->previewSource === 'unsaved') {
                    $prepared = app(ConfiguratorEditorChanges::class)->prepare($configurator, $this->previewCaptures);
                    $this->previewData = $prepared['data'];
                    $this->previewChanges = $prepared['changes'];
                }
                $this->evaluate(['kind' => 'Reevaluate']);
                if ($this->productId !== null) {
                    $this->testedFingerprint = app(ConfiguratorEditorChanges::class)->fingerprint($configurator, $this->productId);
                }
            });
        } catch (ValidationException $exception) {
            $this->previewErrors = array_values(array_unique(Arr::flatten($exception->errors())));
            $this->previewData = null;
            $this->preparedResult = null;
            $this->evaluate(['kind' => 'Reevaluate']);

            return false;
        }

        return true;
    }

    public function canSaveTestedChanges(): bool
    {
        return $this->previewSource === 'unsaved' && $this->previewErrors === [] && $this->previewChanges !== []
            && $this->testedFingerprint !== null && $this->productId !== null
            && app(ConfiguratorEditorChanges::class)->blockingIssues($this->result()) === [];
    }

    /** @param list<array<string, mixed>> $captures */
    public function reviewChanges(array $captures): void
    {
        Gate::authorize('manage-catalog');
        if (! $this->rebuildPreview($captures, 'unsaved') || ! $this->canSaveTestedChanges()) {
            Notification::make()->title(__('configurator.preview.test_errors'))->danger()->send();

            return;
        }
        $this->reviewedFingerprint = $this->testedFingerprint;
        $this->reviewedTest = [
            'product' => $this->products()->findOrFail($this->productId)->product_code,
            'source' => __('configurator.preview.unsaved_source'),
            'code' => $this->result()->configurationCode,
            'values' => app(ConfiguratorPreviewNotes::class)->build($this->result())['selected_values'],
            'runtime' => $this->runtime,
        ];
        $this->mountAction('saveTestedChanges');
    }

    public function saveTestedChangesAction(): Action
    {
        return Action::make('saveTestedChanges')->label(__('configurator.preview.save_tested'))->authorize('manage-catalog')
            ->requiresConfirmation()->modalHeading(__('configurator.preview.confirm_save'))
            ->modalDescription(__('configurator.preview.confirm_save_help'))
            ->modalContent(fn (): View => view('livewire.catalog.configurator-preview-changes', ['changes' => $this->previewChanges, 'tested' => $this->reviewedTest]))
            ->modalSubmitActionLabel(__('configurator.preview.confirm_save_submit'))
            ->mountUsing(function (): void {
                if ($this->reviewedFingerprint === null || ! $this->canSaveTestedChanges()) {
                    throw ValidationException::withMessages(['preview' => __('configurator.preview.test_errors')]);
                }
            })
            ->action(function (): void {
                if ($this->reviewedFingerprint === null || ! $this->canSaveTestedChanges()) {
                    throw ValidationException::withMessages(['preview' => __('configurator.preview.test_errors')]);
                }
                $newRules = app(SaveTestedConfiguratorChanges::class)->handle(auth()->user(), $this->configuratorId, $this->productId, $this->previewCaptures, $this->reviewedFingerprint, $this->reviewedTest['runtime']);
                $captures = $this->previewCaptures;
                $this->previewCaptures = [];
                $this->sourceRuntimes = [];
                $this->rebuildPreview([], 'saved');
                $this->dispatch('configurator-preview-saved', configuratorId: $this->configuratorId, captures: $captures, newRules: $newRules);
                $this->dispatch('configurator-updated')->to(EditConfigurator::class);
                Notification::make()->title(__('configurator.preview.changes_saved'))->success()->send();
            });
    }

    #[On('configurator-updated')]
    public function markDefinitionStale(): void
    {
        Gate::authorize('manage-catalog');
        $this->definitionStale = true;
    }

    public function refreshDefinition(): void
    {
        Gate::authorize('manage-catalog');
        $this->selectionWarnings = [];
        parent::refreshDefinition();
        $this->definitionStale = false;
    }

    public function toggleTrace(): void
    {
        Gate::authorize('manage-catalog');
        $this->showTrace = ! $this->showTrace;
    }

    public function showConflictDetails(): void
    {
        Gate::authorize('manage-catalog');
        $this->showTrace = true;
    }

    public function mount(int $configuratorId): void
    {
        Gate::authorize('manage-catalog');
        $this->configuratorId = Configurator::findOrFail($configuratorId)->id;
        $product = $this->dashboardProduct() ?? $this->firstProduct($this->products());
        $this->chooseProduct($product?->id);
    }

    /** @return list<array{id: string, label: string}> */
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
        $this->definitionStale = false;
        $this->resetValidation('product');
        if ($productId === null || $productId === '') {
            $this->productResetNotice = null;
            $this->reviewedFingerprint = null;
            $this->reviewedTest = [];
            $this->selectionWarnings = [];
            $this->productId = null;
            $this->runtime = [];
            $this->preparedResult = null;
            $this->cachedSchemas = [];
            $this->form->fill();

            return null;
        }
        $product = $this->products()->find($productId);
        if ($product === null) {
            throw ValidationException::withMessages(['product' => __('configurator.validation.choose_a_product_from_a_currently_assigned')]);
        }
        $this->productResetNotice = $this->productId === null ? null : __('configurator.preview.product_reset', ['code' => $product->product_code]);
        $this->productId = $product->id;
        $this->sourceRuntimes = [];
        $this->reviewedFingerprint = null;
        $this->reviewedTest = [];
        $this->selectionWarnings = [];
        $this->runtime = [];
        $this->evaluate(['kind' => 'Initialize']);

        return $this->productId;
    }

    public function selectOption(string $attributeId, string $optionId): void
    {
        Gate::authorize('manage-catalog');
        $this->productResetNotice = null;
        $this->selectionWarnings = [];
        parent::selectOption($attributeId, $optionId);
    }

    /** @param array<string, mixed> $intent */
    protected function input(array $intent): ConfiguratorEvaluationInput
    {
        Gate::authorize('manage-catalog');
        if ($this->productId === null) {
            return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'preview_product_required', 'message' => __('configurator.messages.choose_a_product_from_an_assigned_group')]]);
        }

        if ($this->previewErrors !== []) {
            return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'invalid_editor_changes', 'message' => __('configurator.preview.invalid_edits')]]);
        }
        $input = app(ConfiguratorDefinitionLoader::class)->forPreview(auth()->user(), $this->configuratorId, $this->productId, $this->runtime, $intent, $this->previewSource === 'unsaved' ? $this->previewData : null);

        return new ConfiguratorEvaluationInput($input->definition, $input->properties, $input->context, $input->selections, $input->remembered, $input->intent, $input->configuratorId, $input->diagnostics, trace: true);
    }

    protected function evaluated(): void
    {
        $this->formState = ['product' => $this->productId, 'choices' => $this->runtime['selections']];
        $this->cachedSchemas = [];
        $warnings = app(ConfiguratorPreviewNotes::class)->build($this->preparedResult)['warnings'];
        if ($warnings !== [] || $this->preparedResult?->definition === null) {
            $this->selectionWarnings = $warnings;
        }
    }

    public function form(Schema $schema): Schema
    {
        Gate::authorize('manage-catalog');
        $fields = [];
        $attributeFields = [];
        if ($this->productId !== null) {
            $result = $this->preparedResult ?? app(ConfiguratorEngine::class)->evaluate($this->input(['kind' => 'Reevaluate']));
            $attributes = $result->definition?->attributes ?? [];
            uasort($attributes, fn ($a, $b): int => [$a->displayOrder, $a->id] <=> [$b->displayOrder, $b->id]);
            foreach ($attributes as $attribute) {
                $state = $result->attributes[$attribute->id];
                if (! $state['applicable']) {
                    continue;
                }
                $options = [];
                foreach ($attribute->options as $option) {
                    if (! in_array($option->id, $state['hidden'], true)) {
                        $presentation = $state['options'][$option->id];
                        $options[$option->id] = $presentation['label'].' · '.$option->code;
                    }
                }
                $field = $attribute->inputType === 'select' ? Select::make('choices.'.$attribute->id)->selectablePlaceholder(false) : ToggleButtons::make('choices.'.$attribute->id)->inline();
                $attributeFields[] = $field->label($state['label'])->options($options)
                    ->helperText($state['legal'] === [] ? __('configurator.preview.no_available_values') : null)
                    ->disableOptionWhen(fn ($value): bool => ! in_array((string) $value, $state['legal'], true))
                    ->live()->afterStateUpdated(fn ($state) => $this->selectOption($attribute->id, (string) $state))->columnSpanFull();
            }
        }

        if ($attributeFields !== []) {
            $fields[] = Group::make($attributeFields)->columns(1)->columnSpanFull()
                ->extraAttributes(['class' => 'catalog-configurator-preview-attributes fi-fixed-positioning-context']);
        }

        return $schema->statePath('formState')->columns(1)->inlineLabel()->components($fields);
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

        $result = $this->productId === null ? null : $this->result();

        return view('livewire.catalog.configurator-preview', [
            'result' => $result,
            'blockingIssues' => $result === null ? [] : app(ConfiguratorEditorChanges::class)->blockingIssues($result),
            'notes' => app(ConfiguratorPreviewNotes::class)->build($result),
            'dashboardProduct' => $this->dashboardProduct(),
            'selectedProductCode' => $this->productId === null ? '' : ($this->products()->find($this->productId)?->product_code ?? ''),
            'publicOnlyRules' => Configurator::findOrFail($this->configuratorId)->rules()->whereHas('conditions', fn (Builder $query): Builder => $query->whereIn('source_kind', ['Territory', 'Application']))->orderByDesc('priority')->pluck('label')->all(),
        ]);
    }
}
