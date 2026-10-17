<?php

namespace App\Livewire\Catalog;

use App\DTO\ConfiguratorEvaluationInput;
use App\Models\Configurator;
use App\Models\Product;
use App\Services\CatalogAvailability;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use App\Services\ConfiguratorPreviewNotes;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;

class ConfiguratorPreview extends ProductConfigurator implements HasSchemas
{
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
        $this->productId = $product->id;
        $this->selectionWarnings = [];
        $this->runtime = [];
        $this->evaluate(['kind' => 'Initialize']);

        return $this->productId;
    }

    public function selectOption(string $attributeId, string $optionId): void
    {
        Gate::authorize('manage-catalog');
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

        $input = app(ConfiguratorDefinitionLoader::class)->forPreview(auth()->user(), $this->configuratorId, $this->productId, $this->runtime, $intent);

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
            'notes' => app(ConfiguratorPreviewNotes::class)->build($result),
            'dashboardProduct' => $this->dashboardProduct(),
            'selectedProductCode' => $this->productId === null ? '' : ($this->products()->find($this->productId)?->product_code ?? ''),
            'publicOnlyRules' => Configurator::findOrFail($this->configuratorId)->rules()->whereHas('conditions', fn (Builder $query): Builder => $query->whereIn('source_kind', ['Territory', 'Application']))->orderByDesc('priority')->pluck('label')->all(),
        ]);
    }
}
