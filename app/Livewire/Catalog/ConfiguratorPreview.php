<?php

namespace App\Livewire\Catalog;

use App\DTO\ConfiguratorEvaluationInput;
use App\Models\Configurator;
use App\Models\Product;
use App\Services\ConfiguratorDefinitionLoader;
use App\Services\ConfiguratorEngine;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ConfiguratorPreview extends ProductConfigurator implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public int $configuratorId;

    /** @var array<string, mixed> */
    public array $formState = [];

    public function mount(int $configuratorId): void
    {
        Gate::authorize('manage-catalog');
        $this->configuratorId = Configurator::findOrFail($configuratorId)->id;
        $this->form->fill();
    }

    public function chooseProduct(int|string|null $productId): void
    {
        Gate::authorize('manage-catalog');
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

        return app(ConfiguratorDefinitionLoader::class)->forPreview(auth()->user(), $this->configuratorId, $this->productId, $this->runtime, $intent);
    }

    protected function evaluated(): void
    {
        $this->formState = ['product' => $this->productId, 'context' => $this->runtime['context'], 'choices' => $this->runtime['selections']];
        $this->cachedSchemas = [];
    }

    public function form(Schema $schema): Schema
    {
        Gate::authorize('manage-catalog');
        $fields = [Select::make('product')->label('Product Code')->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $this->products()->where('product_code', 'like', '%'.$search.'%')->orderBy('product_code')->limit(50)->pluck('product_code', 'id')->all())
            ->getOptionLabelUsing(fn ($value): ?string => $this->products()->find($value)?->product_code)
            ->live()->afterStateUpdated(fn ($state) => $this->chooseProduct($state))->columnSpanFull()];
        if ($this->productId !== null) {
            $result = $this->preparedResult ?? app(ConfiguratorEngine::class)->evaluate($this->input(['kind' => 'Reevaluate']));
            foreach ($result->definition === null ? [] : ['territory', 'application'] as $dimension) {
                $fields[] = Select::make('context.'.$dimension)->label(ucfirst($dimension))->options(['All' => 'All', ...array_column($result->definition->contextSchema[$dimension], 'label', 'value')])->selectablePlaceholder(false)->live()
                    ->afterStateUpdated(fn ($state) => $this->changeContext($dimension, (string) $state));
            }
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
                $fields[] = $field->label($state['label'])->helperText(view('livewire.catalog.configurator-preview-notes', ['state' => $state, 'attribute' => $attribute, 'selectedId' => $result->selections[$attribute->id] ?? null]))->options($options)
                    ->disableOptionWhen(fn ($value): bool => ! in_array((string) $value, $state['legal'], true))
                    ->live()->afterStateUpdated(fn ($state) => $this->selectOption($attribute->id, (string) $state))->columnSpanFull();
            }
        }

        return $schema->statePath('formState')->columns(2)->components($fields);
    }

    private function products(): Builder
    {
        Gate::authorize('manage-catalog');

        return Product::whereHas('group', fn (Builder $query) => $query->where('configurator_id', $this->configuratorId)->doesntHave('children'));
    }

    public function render(): View
    {
        Gate::authorize('manage-catalog');

        return view('livewire.catalog.configurator-preview', ['result' => $this->productId === null ? null : $this->result()]);
    }
}
