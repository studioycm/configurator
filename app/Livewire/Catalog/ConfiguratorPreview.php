<?php

namespace App\Livewire\Catalog;

use App\DTO\ConfiguratorEvaluationInput;
use App\Models\Configurator;
use App\Models\Product;
use App\Services\CatalogAvailability;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
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

    #[On('configurator-updated')]
    public function markDefinitionStale(): void
    {
        Gate::authorize('manage-catalog');
        $this->definitionStale = true;
    }

    public function refreshDefinition(): void
    {
        Gate::authorize('manage-catalog');
        parent::refreshDefinition();
        $this->definitionStale = false;
    }

    public function toggleTrace(): void
    {
        Gate::authorize('manage-catalog');
        $this->showTrace = ! $this->showTrace;
        $this->refreshDefinition();
    }

    public function mount(int $configuratorId): void
    {
        Gate::authorize('manage-catalog');
        $this->configuratorId = Configurator::findOrFail($configuratorId)->id;
        $this->form->fill();
    }

    public function chooseProduct(int|string|null $productId): void
    {
        Gate::authorize('manage-catalog');
        $this->definitionStale = false;
        if ($productId === null || $productId === '') {
            $this->productId = null;
            $this->runtime = [];
            $this->preparedResult = null;
            $this->cachedSchemas = [];
            $this->form->fill();

            return;
        }
        $product = $this->products()->find($productId);
        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'Choose a Product from a currently assigned leaf Group.']);
        }
        $this->productId = $product->id;
        $this->runtime = [];
        $this->evaluate(['kind' => 'Initialize']);
    }

    /** @param array<string, mixed> $intent */
    protected function input(array $intent): ConfiguratorEvaluationInput
    {
        Gate::authorize('manage-catalog');
        if ($this->productId === null) {
            return new ConfiguratorEvaluationInput(null, diagnostics: [['code' => 'preview_product_required', 'message' => 'Choose a Product from an assigned Group to preview.']]);
        }

        $input = app(ConfiguratorDefinitionLoader::class)->forPreview(auth()->user(), $this->configuratorId, $this->productId, $this->runtime, $intent);

        return new ConfiguratorEvaluationInput($input->definition, $input->properties, $input->context, $input->selections, $input->remembered, $input->intent, $input->configuratorId, $input->diagnostics, trace: $this->showTrace);
    }

    protected function evaluated(): void
    {
        $this->formState = ['product' => $this->productId, 'choices' => $this->runtime['selections']];
        $this->cachedSchemas = [];
    }

    public function form(Schema $schema): Schema
    {
        Gate::authorize('manage-catalog');
        $fields = [Select::make('product')->label('Product Code')->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $this->products()->whereRaw('LOWER(product_code) LIKE ?', ['%'.Str::lower($search).'%'])->orderBy('product_code')->limit(50)->pluck('product_code', 'id')->all())
            ->getOptionLabelUsing(fn ($value): ?string => $this->products()->find($value)?->product_code)
            ->live()->afterStateUpdated(fn ($state) => $this->chooseProduct($state))->columnSpanFull()];
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

        return $products->join('groups', 'groups.id', '=', 'products.group_id')
            ->orderBy('groups.sort_order')->orderBy('groups.id')->orderBy('products.product_code')->orderBy('products.id')
            ->first(['products.id', 'products.product_code']);
    }

    public function render(): View
    {
        Gate::authorize('manage-catalog');

        return view('livewire.catalog.configurator-preview', [
            'result' => $this->productId === null ? null : $this->result(),
            'dashboardProduct' => $this->dashboardProduct(),
            'publicOnlyRules' => Configurator::findOrFail($this->configuratorId)->rules()->whereHas('conditions', fn (Builder $query): Builder => $query->whereIn('source_kind', ['Territory', 'Application']))->orderByDesc('priority')->pluck('label')->all(),
        ]);
    }
}
